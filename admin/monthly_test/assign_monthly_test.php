<?php
// admin/assign_monthly_test.php
require_once __DIR__ . '/../../config/config.php';
require_admin($pdo);

$errors = [];
$success = null;

// helper esc
function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// Load monthly tests for select
try {
    $testsStmt = $pdo->prepare("SELECT id, title, test_date, club_id FROM tests WHERE test_type = 'monthly' ORDER BY test_date DESC, id DESC");
    $testsStmt->execute();
    $monthlyTests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $monthlyTests = [];
    $errors[] = "Failed to load monthly tests: " . $e->getMessage();
}

// Max per test
$MAX_PER_TEST = 10;

// handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request token.";
    } else {
        $test_id = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
        $raw_rolls = trim($_POST['rolls'] ?? '');

        if ($test_id <= 0) {
            $errors[] = "Please select a monthly test.";
        }

        if ($raw_rolls === '') {
            $errors[] = "Please enter one or more roll numbers (comma or newline separated).";
        }

        // normalize roll list
        $rolls = [];
        if ($raw_rolls !== '') {
            // split by comma or newline
            $parts = preg_split('/[\r\n,]+/', $raw_rolls);
            foreach ($parts as $p) {
                $r = trim($p);
                if ($r !== '') $rolls[] = $r;
            }
            // unique, preserve order
            $rolls = array_values(array_unique($rolls));
        }

        if (empty($errors)) {
            try {
                // ensure selected test exists and is monthly
                $checkTest = $pdo->prepare("SELECT id, title, test_date FROM tests WHERE id = :id AND test_type = 'monthly' LIMIT 1");
                $checkTest->execute([':id' => $test_id]);
                $testRow = $checkTest->fetch(PDO::FETCH_ASSOC);
                if (!$testRow) {
                    $errors[] = "Selected test not found or not a monthly test.";
                } else {
                    // Count current assigned users
                    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_test_users WHERE test_id = :tid");
                    $countStmt->execute([':tid' => $test_id]);
                    $currentCount = (int)$countStmt->fetchColumn();

                    // If no rolls after normalization, nothing to do
                    if (empty($rolls)) {
                        $errors[] = "No valid roll numbers to add.";
                    } else {
                        // Check which rolls already assigned for this test
                        $inPlaceholders = implode(',', array_fill(0, count($rolls), '?'));
                        $alreadyStmt = $pdo->prepare("SELECT user_roll FROM monthly_test_users WHERE test_id = ? AND user_roll IN ($inPlaceholders)");
                        $params = array_merge([$test_id], $rolls);
                        $alreadyStmt->execute($params);
                        $alreadyRows = $alreadyStmt->fetchAll(PDO::FETCH_COLUMN);
                        $alreadySet = array_map('strval', $alreadyRows);

                        // Determine candidate rolls (those not already assigned)
                        $toCheck = array_values(array_diff($rolls, $alreadySet));
                        $skippedExisting = array_values(array_intersect($rolls, $alreadySet));

                        // Validate existence in users table for each candidate
                        $valid = [];
                        $invalid = [];

                        if (!empty($toCheck)) {
                            // Use IN clause - chunk if very large (but expected small)
                            $place = implode(',', array_fill(0, count($toCheck), '?'));
                            $uStmt = $pdo->prepare("SELECT roll_no FROM users WHERE roll_no IN ($place)");
                            $uStmt->execute($toCheck);
                            $found = $uStmt->fetchAll(PDO::FETCH_COLUMN);
                            $foundMap = array_flip($found); // quick lookup

                            foreach ($toCheck as $r) {
                                if (isset($foundMap[$r])) $valid[] = $r;
                                else $invalid[] = $r;
                            }
                        }

                        if (!empty($invalid)) {
                            $errors[] = "These roll numbers do not exist in users table and were skipped: " . esc(implode(', ', $invalid));
                        }

                        // Now apply the 10-user limit
                        $remainingSlots = max(0, $MAX_PER_TEST - $currentCount);

                        if ($remainingSlots <= 0) {
                            $errors[] = "This test already has the maximum {$MAX_PER_TEST} assigned users. No new users were added.";
                            $toInsert = [];
                        } else {
                            // Trim valid list to remaining slots
                            if (count($valid) > $remainingSlots) {
                                $toInsert = array_slice($valid, 0, $remainingSlots);
                                $errors[] = "Only {$remainingSlots} of the provided roll numbers could be assigned (limit {$MAX_PER_TEST}). Extra rolls were skipped.";
                            } else {
                                $toInsert = $valid;
                            }
                        }

                        // Perform inserts (use transaction)
                        if (!empty($toInsert)) {
                            $pdo->beginTransaction();
                            try {
                                $insertStmt = $pdo->prepare("INSERT INTO monthly_test_users (test_id, user_roll, created_by, created_at) VALUES (:tid, :r, :creator, NOW())");
                                $creator = $_SESSION['user_roll'] ?? null;
                                foreach ($toInsert as $r) {
                                    // double-check uniqueness to avoid race condition violation
                                    $chk = $pdo->prepare("SELECT 1 FROM monthly_test_users WHERE test_id = :tid AND user_roll = :r LIMIT 1");
                                    $chk->execute([':tid' => $test_id, ':r' => $r]);
                                    if ($chk->fetchColumn()) {
                                        // already assigned by concurrent action - skip
                                        continue;
                                    }
                                    $insertStmt->execute([':tid' => $test_id, ':r' => $r, ':creator' => $creator]);
                                }
                                $pdo->commit();
                                $success = "Assigned " . count($toInsert) . " user(s) to the test '" . esc($testRow['title']) . "'.";
                                if (!empty($skippedExisting)) {
                                    $success .= " (Skipped already-assigned: " . esc(implode(', ', $skippedExisting)) . ".)";
                                }
                            } catch (Exception $e) {
                                $pdo->rollBack();
                                $errors[] = "Failed to insert assignments: " . $e->getMessage();
                            }
                        } else {
                            if (empty($errors)) {
                                $errors[] = "No users to add after validation.";
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error while processing: " . $e->getMessage();
            }
        }
    }
}

// If a test is selected (via GET or POST) show current assignments
$selectedTestId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : (isset($test_id) ? (int)$test_id : 0);
$currentAssigned = [];
$selectedTestRow = null;
if ($selectedTestId > 0) {
    try {
        $tstmt = $pdo->prepare("SELECT id, title, test_date FROM tests WHERE id = :id LIMIT 1");
        $tstmt->execute([':id' => $selectedTestId]);
        $selectedTestRow = $tstmt->fetch(PDO::FETCH_ASSOC);

        $alist = $pdo->prepare("SELECT m.id, m.user_roll, m.created_by, m.created_at, u.full_name FROM monthly_test_users m LEFT JOIN users u ON u.roll_no = m.user_roll WHERE m.test_id = :tid ORDER BY m.created_at ASC, m.user_roll ASC");
        $alist->execute([':tid' => $selectedTestId]);
        $currentAssigned = $alist->fetchAll(PDO::FETCH_ASSOC);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM monthly_test_users WHERE test_id = :tid");
        $countStmt->execute([':tid' => $selectedTestId]);
        $currentCount = (int)$countStmt->fetchColumn();
    } catch (Exception $e) {
        $currentAssigned = [];
    }
} else {
    $currentCount = 0;
}

// csrf token
$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Assign Users — Monthly Test</title>
    <link rel="stylesheet" href="<?= esc(BASE_URL) ?>/../../public/css/main.css">
    <link rel="stylesheet" href="<?= esc(BASE_URL) ?>/../../public/css/header.css">
    <style>
        .wrap { max-width:1100px; margin:18px auto; padding:0 12px; }
        .card { background:#fff; border:1px solid #eef2ff; border-radius:10px; padding:14px; margin-bottom:12px; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        textarea { width:100%; min-height:120px; padding:8px; border-radius:8px; border:1px solid #e6eefb; box-sizing:border-box; }
        select, input[type="text"] { width:100%; padding:8px; border-radius:8px; border:1px solid #e6eefb; box-sizing:border-box; }
        .muted { color:#64748b; font-size:0.95rem; }
        .btn { padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; background:#2563eb; color:#fff; }
        .btn-ghost { background:transparent; border:1px solid #cbd5f5; color:#2563eb; border-radius:8px; padding:8px 12px; font-weight:700; }
        .list { margin-top:8px; }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        th, td { padding:8px 6px; border-bottom:1px solid #f3f4f6; text-align:left; }
        .error { background:#fee2e2;color:#b91c1c;padding:10px;border-radius:8px;margin-bottom:12px; }
        .success { background:#dcfce7;color:#065f46;padding:10px;border-radius:8px;margin-bottom:12px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Assign Attendee's to Monthly Test</h2>
        <a href="<?= esc(BASE_URL) ?>/../monthly_test.php" class="btn-ghost">← Back to Assign Monthly Test</a>
    </div>

    <br><br>
    
    <?php if ($errors): ?>
        <div class="error">
            <?php foreach ($errors as $er) echo '<div>' . esc($er) . '</div>'; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= esc($success) ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

            <label for="test_id">Monthly Test</label>
            <select id="test_id" name="test_id" onchange="if(this.value) window.location = '?test_id=' + encodeURIComponent(this.value);">
                <option value="">-- select monthly test --</option>
                <?php foreach ($monthlyTests as $mt): 
                    $sel = ($selectedTestId > 0 && (int)$mt['id'] === (int)$selectedTestId) ? 'selected' : '';
                    $label = esc($mt['test_date'] . ' — ' . ($mt['title'] ?: 'Test #' . $mt['id']));
                ?>
                    <option value="<?= (int)$mt['id'] ?>" <?= $sel ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($selectedTestRow): ?>
                <div class="muted" style="margin-top:8px;">
                    Selected: <strong><?= esc($selectedTestRow['title'] ?: 'Test #' . $selectedTestRow['id']) ?></strong>
                    (Date: <?= esc($selectedTestRow['test_date']) ?>)
                    — Assigned: <?= (int)$currentCount ?> / <?= $MAX_PER_TEST ?>
                </div>
            <?php endif; ?>

            <label style="margin-top:12px;">Roll numbers to assign</label>
            <div class="muted">Enter roll numbers separated by comma or newline. Only valid users will be assigned. Max total per test: <?= $MAX_PER_TEST ?>.</div>
            <textarea name="rolls" placeholder="e.g. 2504022, 2504023 or one per line"><?= esc($_POST['rolls'] ?? '') ?></textarea>

            <div style="margin-top:12px;">
                <button type="submit" class="btn">Assign Users</button>
            </div>
        </form>
    </div>

    <?php if ($selectedTestRow): ?>
        <div class="card">
            <h3>Current assignments for: <?= esc($selectedTestRow['title'] ?: 'Test #' . $selectedTestRow['id']) ?></h3>
            <?php if (empty($currentAssigned)): ?>
                <div class="muted">No users assigned yet.</div>
            <?php else: ?>
                <table>
                    <thead><tr><th>#</th><th>Roll</th><th>Name</th><th>Assigned by</th><th>When</th></tr></thead>
                    <tbody>
                        <?php $i=1; foreach ($currentAssigned as $row): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= esc($row['user_roll']) ?></td>
                                <td><?= esc($row['full_name'] ?? '-') ?></td>
                                <td><?= esc($row['created_by'] ?? '-') ?></td>
                                <td><?= esc($row['created_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
