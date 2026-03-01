<?php
// admin/approve_questions.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/phpmailer_config.php';

require_admin($pdo);

$errors = [];
$success = null;

// helper: approve single pending question (transaction caller handles commit/rollBack)
// helper: approve single pending question (Returns the new Question ID)
function approve_single_pending(PDO $pdo, int $pendingId): int {
    $q = $pdo->prepare("SELECT * FROM questions_pending WHERE id = :id FOR UPDATE");
    $q->execute([':id' => $pendingId]);
    $row = $q->fetch();
    if (!$row) throw new Exception("Pending question #{$pendingId} not found.");

    // Check linked test... (omitted for brevity, keep your existing logic here)
    if (!empty($row['test_id'])) {
        $tst = $pdo->prepare("SELECT id, active FROM tests WHERE id = :id LIMIT 1");
        $tst->execute([':id' => $row['test_id']]);
        $trow = $tst->fetch();
        if (!$trow) throw new Exception("Linked test not found for pending #{$pendingId}.");
        if ((int)$trow['active'] !== 1) {
            throw new Exception("Cannot approve pending #{$pendingId}: linked test is inactive.");
        }
    }

    $insQ = $pdo->prepare("
        INSERT INTO questions (club_id, test_id, question_text)
        VALUES (:cid, :tid, :qt)
    ");
    $insQ->execute([
        ':cid' => $row['club_id'],
        ':tid' => $row['test_id'] ?: null,
        ':qt'  => $row['question_text']
    ]);
    
    // CAPTURE THE ID HERE
    $newQid = (int)$pdo->lastInsertId();

    $o = $pdo->prepare("SELECT * FROM questions_pending_options WHERE pending_question_id = :id");
    $o->execute([':id' => $pendingId]);
    $opt = $o->fetch();
    if (!$opt) throw new Exception("Options for pending #{$pendingId} not found.");

    $insOpt = $pdo->prepare("
        INSERT INTO options_four (question_id, option_a, option_b, option_c, option_d, correct_option)
        VALUES (:qid, :a, :b, :c, :d, :co)
    ");
    $insOpt->execute([
        ':qid' => $newQid,
        ':a'   => $opt['option_a'],
        ':b'   => $opt['option_b'],
        ':c'   => $opt['option_c'],
        ':d'   => $opt['option_d'],
        ':co'  => $opt['correct_option']
    ]);

    // Clean up pending
    $pdo->prepare("DELETE FROM questions_pending WHERE id = :id")->execute([':id' => $pendingId]);
    $pdo->prepare("DELETE FROM questions_pending_options WHERE pending_question_id = :id")->execute([':id' => $pendingId]);

    // RETURN THE ID
    return $newQid;
}



function send_testtype_approval_mail(PDO $pdo, string $testType): void
{
    // 🔑 Use DB date (timezone-safe)
    $today = $pdo->query("SELECT CURRENT_DATE")->fetchColumn();

    // Normalize test type (avoid DAILY vs daily duplicates)
    $testType = strtoupper(trim($testType));

    // 1️⃣ Check mail log
    $chk = $pdo->prepare("
        SELECT 1 FROM testtype_mail_log
        WHERE test_type = :tt AND mail_date = :md
        LIMIT 1
    ");
    $chk->execute([
        ':tt' => $testType,
        ':md' => $today
    ]);

    if ($chk->fetch()) {
        return; // already sent today
    }

    // 2️⃣ Fetch all user emails
    $emails = $pdo->query("
        SELECT email FROM users WHERE email IS NOT NULL
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (!$emails) return;

    // 3️⃣ Mail content
    $subject = "New {$testType} Test Questions Approved";

    $message  = "<h3>{$testType} Test Update</h3>";
    $message .= "<p>New questions for the <strong>{$testType}</strong> test have been approved today.</p>";
    $message .= "<p>Please login to the portal and attend the test.</p>";
    $message .= "<p><small>— Career Pathway Admin</small></p>";

    // 4️⃣ Send mail
    foreach ($emails as $email) {
        sendMail($email, $subject, $message);
    }

    // 5️⃣ Log mail (DB date!)
    $log = $pdo->prepare("
        INSERT INTO testtype_mail_log (test_type, mail_date)
        VALUES (:tt, :md)
    ");
    $log->execute([
        ':tt' => $testType,
        ':md' => $today
    ]);
}






// POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request.";
    } else {
        // Approve single question
        if (isset($_POST['approve'])) {
            $qid = intval($_POST['pending_id']);
            try {
                $pdo->beginTransaction();
                approve_single_pending($pdo, $qid);
                $pdo->commit();

// 🔔 Fetch test_type and send mail
$stmt = $pdo->prepare("
    SELECT t.test_type
    FROM questions q
    JOIN tests t ON t.id = q.test_id
    WHERE q.id = LAST_INSERT_ID()
    LIMIT 1
");
$stmt->execute();
$testType = $stmt->fetchColumn();

if ($testType) {
    send_testtype_approval_mail($pdo, $testType);
}

$success = "Question approved.";

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }

        // Reject single question
        if (isset($_POST['reject'])) {
            $qid = intval($_POST['pending_id']);
            try {
        $pdo->beginTransaction();
        
        // 1. Capture the returned ID
        $newQuestionId = approve_single_pending($pdo, $qid);
        
        $pdo->commit();

        // 🔔 Fetch test_type using the SPECIFIC ID
        $stmt = $pdo->prepare("
            SELECT t.test_type
            FROM questions q
            JOIN tests t ON t.id = q.test_id
            WHERE q.id = :qid  
            LIMIT 1
        ");
        // Pass the captured ID here
        $stmt->execute([':qid' => $newQuestionId]); 
        $testType = $stmt->fetchColumn();

        if ($testType) {
            send_testtype_approval_mail($pdo, $testType);
        }

        $success = "Question approved.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $errors[] = $e->getMessage();
    }
        }

        // Approve group (grouped by test_type + test_date OR general)
        if (isset($_POST['approve_group'])) {
            $group_type = $_POST['group_test_type'] ?? '';
            $group_date = $_POST['group_test_date'] ?? '';
            try {
                $pdo->beginTransaction();

                if ($group_type === 'GENERAL') {
                    // select general pending (test_id IS NULL)
                    $rows = $pdo->query("SELECT id FROM questions_pending WHERE test_id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
                } else {
                    
                    // select by test_type + test_date via join (only active tests, only active pending rows)
                    $stmt = $pdo->prepare("
                        SELECT q.id
                        FROM questions_pending q
                        JOIN tests t ON t.id = q.test_id
                        WHERE t.test_type = :tt
                          AND t.test_date = :td
                          AND t.active = 1
                          AND q.active = 2
                    ");
                    $stmt->execute([':tt' => $group_type, ':td' => $group_date]);
                    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

                }

                if (empty($rows)) throw new Exception("No pending questions found in this group.");

                foreach ($rows as $pid) {
                    approve_single_pending($pdo, (int)$pid);
                }

                $pdo->commit();

// 🔔 SEND MAIL (once per test_type per day)
if ($group_type !== 'GENERAL') {
    send_testtype_approval_mail($pdo, $group_type);
}

$success = "Group approved (" . count($rows) . " question(s)).";

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Approve group failed: " . $e->getMessage();
            }
        }

        // Reject group
        if (isset($_POST['reject_group'])) {
            $group_type = $_POST['group_test_type'] ?? '';
            $group_date = $_POST['group_test_date'] ?? '';
            try {
                $pdo->beginTransaction();
                if ($group_type === 'GENERAL') {
                    // delete all general pending entries
                    $pdo->prepare("DELETE qopt FROM questions_pending_options qopt JOIN questions_pending qp ON qopt.pending_question_id = qp.id WHERE qp.test_id IS NULL")->execute();
                    $pdo->prepare("DELETE FROM questions_pending WHERE test_id IS NULL")->execute();
                    $success = "General pending questions deleted.";
                } else {
                    // deactivate all tests with that type+date
                    $upd = $pdo->prepare("UPDATE questions_pending SET active = 0 WHERE test_type = :tt AND test_date = :td");
                    $upd->execute([':tt' => $group_type, ':td' => $group_date]);
                    $success = "All tests on {$group_type} • {$group_date} have been deactivated.";
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Reject group failed: " . $e->getMessage();
            }
        }
    }
}

// VIEW controls
$view_type = isset($_GET['view_type']) ? $_GET['view_type'] : null; // 'GENERAL' or test_type value
$view_date = isset($_GET['view_date']) ? $_GET['view_date'] : null; // date string when viewing a typed group

// Build grouped summary by test_type + test_date (general group included).
// We group across clubs: all pending rows that map to the same test_type+test_date are one group.
$groupsSql = "
    SELECT
        COALESCE(t.test_type, 'GENERAL')       AS group_type,
        COALESCE(t.test_date, '0000-00-00')    AS group_date,
        COUNT(q.id)                            AS pending_count,
        GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', ') AS clubs
    FROM questions_pending q
    JOIN clubs c ON c.id = q.club_id
    LEFT JOIN tests t ON t.id = q.test_id
    WHERE (q.test_id IS NULL)
       OR (t.active = 1 AND q.active = 2)
    GROUP BY group_type, group_date
    ORDER BY group_date DESC, clubs ASC
";


$groupsStmt = $pdo->query($groupsSql);
$groups = $groupsStmt->fetchAll();

// If viewing a specific group, fetch its pending questions.
$groupPending = [];
if ($view_type !== null) {
    if ($view_type === 'GENERAL') {
        $gStmt = $pdo->prepare("
            SELECT q.id, q.question_text, c.name AS club_name, q.test_id
            FROM questions_pending q
            JOIN clubs c ON c.id = q.club_id
            WHERE q.test_id IS NULL AND q.active = 2
            ORDER BY q.id ASC
        ");
        $gStmt->execute();
    } else {
        // view by concrete test_type + test_date pair
        $gStmt = $pdo->prepare("
            SELECT q.id, q.question_text, c.name AS club_name, q.test_id, t.id AS t_id, t.test_type, t.test_date
            FROM questions_pending q
            JOIN clubs c ON c.id = q.club_id
            JOIN tests t ON t.id = q.test_id
            WHERE t.test_type = :tt AND t.test_date = :td AND t.active = 1 AND q.active = 2
            ORDER BY q.id ASC
        ");
        $gStmt->execute([':tt' => $view_type, ':td' => $view_date]);
    }
    $groupPending = $gStmt->fetchAll();
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Approve Questions (grouped by type+date)</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    
    <style>
        .groups { display:grid; gap:12px; }
        .group-card { background:#fff;border:1px solid #e6eefb;padding:12px;border-radius:10px; display:flex; justify-content:space-between; align-items:center; gap:12px; }
        .g-left { display:flex; gap:12px; align-items:center; }
        .g-meta { font-weight:700; }
        .g-sub { color:#64748b; font-size:0.9rem; }
        .actions { display:flex; gap:8px; }
        .btn { padding:8px 10px; border-radius:8px; text-decoration:none; font-weight:700; cursor:pointer; border:0; }
        .btn-approve { background:linear-gradient(90deg,#10b981,#059669); color:#fff; }
        .btn-reject { background:#ef4444;color:#fff; }
        .btn-view { background:#2563eb;color:#fff; }
        table { width:100%; border-collapse: collapse; margin-top:12px; }
        th,td { padding:15px 8px; border-bottom:1px solid #eef2ff; font-size: medium; font-weight: 500;}
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
        <h2>Pending Questions - Grouped by Test Type & Test Date</h2>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= e(BASE_URL) ?>/admin/dashboard.php">← Back to Admin</a>
    </div>
    <br>

    <?php if ($errors): ?>
        <script>alert(<?= json_encode(implode("\\n", $errors)) ?>);</script>
    <?php endif; ?>

    <?php if ($success): ?>
        <script>alert(<?= json_encode($success) ?>); window.location = "approve_questions.php";</script>
    <?php endif; ?>

    <?php if ($view_type !== null): ?>
        
        <h3>Viewing group:
            <?php if ($view_type === 'GENERAL'): ?>
                <em>General (not attached)</em>
            <?php else: ?>
                <?= e($view_type) ?> • <?= e($view_date) ?>
            <?php endif; ?>
        </h3><br>

        <?php if (empty($groupPending)): ?>
            <div class="empty">No pending questions in this group.</div>
        <?php else: ?>
            <table style="border-spacing: 10px;">
                <thead><tr><th>S. No.</th><th>Club Name</th><th>Question</th><th>Question Id</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($groupPending as $idx => $p): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><?= e($p['club_name']) ?></td>
                            <td><?= e($p['question_text']) ?></td>
                            <td><?= e($p['id']) ?></td>
                            <td>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="pending_id" value="<?= (int)$p['id'] ?>">
                                    <button name="approve" class="btn btn-approve" onclick="return confirm('Approve this question?')">Approve</button>
                                </form>

                                <form method="post" style="display:inline-block;margin-left:8px;">
                                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                    <input type="hidden" name="pending_id" value="<?= (int)$p['id'] ?>">
                                    <button name="reject" class="btn btn-reject" onclick="return confirm('Reject this question? This may deactivate the test if attached.')">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php else: ?>

        <?php if (empty($groups)): ?>
            <div class="empty">No pending questions found.</div>
        <?php else: ?>
            <div class="groups">
                <?php foreach ($groups as $g):
                    $groupType = $g['group_type'];
                    $groupDate = $g['group_date'];
                    $displayLabel = ($groupType === 'GENERAL') ? 'General (not attached)' : (e($groupType) . ' • ' . e($groupDate));
                ?>
                    <div class="group-card">
                        <div class="g-left">
                            <div>
                                <div class="g-meta"><?= $displayLabel ?></div>
                                <div class="g-sub"><?= (int)$g['pending_count'] ?> pending question(s) — Clubs: <?= e($g['clubs']) ?></div>
                            </div>
                        </div>

                        <div class="actions">
                            <form method="get" style="display:inline-block;">
                                <input type="hidden" name="view_type" value="<?= $groupType ?>">
                                <input type="hidden" name="view_date" value="<?= $groupDate ?>">
                                <button type="submit" class="btn btn-view">View</button>
                            </form>

                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <?php if ($groupType === 'GENERAL'): ?>
                                    <input type="hidden" name="group_test_type" value="GENERAL">
                                    <input type="hidden" name="group_test_date" value="">
                                <?php else: ?>
                                    <input type="hidden" name="group_test_type" value="<?= e($groupType) ?>">
                                    <input type="hidden" name="group_test_date" value="<?= e($groupDate) ?>">
                                <?php endif; ?>
                                <button name="approve_group" class="btn btn-approve" onclick="return confirm('Approve all questions in this group?')">Approve All</button>
                            </form>

                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <?php if ($groupType === 'GENERAL'): ?>
                                    <input type="hidden" name="group_test_type" value="GENERAL">
                                    <input type="hidden" name="group_test_date" value="">
                                <?php else: ?>
                                    <input type="hidden" name="group_test_type" value="<?= e($groupType) ?>">
                                    <input type="hidden" name="group_test_date" value="<?= e($groupDate) ?>">
                                <?php endif; ?>
                                <button name="reject_group" class="btn btn-reject" onclick="return confirm('Reject this group? (if attached to tests they will be deactivated)')">Reject Group</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</main>
</body>
</html>
