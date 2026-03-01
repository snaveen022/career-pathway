<?php
// club/create_test.php

// 1. Enable Error Reporting (Debugging)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_login();

$user_roll = $_SESSION['user_roll'];
$club_id = isset($_GET['club_id']) ? intval($_GET['club_id']) : intval($_POST['club_id'] ?? 0);

if ($club_id <= 0) {
    die('Error: Club ID is missing. Please go back and try again.');
}

// 2. Fetch Club Name (Moved up to ensure it exists for the Title)
$clubStmt = $pdo->prepare('SELECT id, name FROM clubs WHERE id = :id LIMIT 1');
$clubStmt->execute([':id' => $club_id]);
$clubRow = $clubStmt->fetch();

if (!$clubRow) {
    die('Error: Club not found.');
}

// 3. Permissions Check
$role = get_club_role($pdo, $user_roll, $club_id);
if (!$role) {
    die('You are not a member of this club.');
}
if (!$role['can_post_questions'] && !is_admin($pdo)) {
    die('You do not have permission to create tests.');
}

$errors = [];
$success = null;
$createdId = 0;

// 4. Fetch Existing Monthly Tests (for display)
$existingMonthlyTests = [];
try {
    $stmt = $pdo->query("SELECT title, test_date FROM tests WHERE test_type = 'monthly' ORDER BY test_date DESC LIMIT 5");
    $existingMonthlyTests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist yet
}

// 4b. Fetch monthly_test_schedule rows where is_active = 1 (only active dates)
$monthlySchedule = [];
try {
    $msStmt = $pdo->prepare("SELECT id, test_date, description FROM monthly_test_schedule WHERE is_active = 1 ORDER BY test_date ASC");
    $msStmt->execute();
    $monthlySchedule = $msStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // table may not exist; keep empty
    $monthlySchedule = [];
}

// 5. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid security token (CSRF). Refresh page.';
    }

    $title = trim($_POST['title'] ?? '');
    $test_type = $_POST['test_type'] ?? '';
    $test_date = $_POST['test_date'] ?? '';
    $monthly_date_id = isset($_POST['monthly_date_id']) ? intval($_POST['monthly_date_id']) : 0;

    if ($title === '') $errors[] = 'Title required.';
    if (!in_array($test_type, ['daily','weekly','monthly'], true)) $errors[] = 'Invalid test type.';

    // Resolve test_date for monthly: use schedule table if monthly_date_id provided
    if ($test_type === 'monthly') {
        if ($monthly_date_id > 0) {
            try {
                $mstmt = $pdo->prepare("SELECT test_date FROM monthly_test_schedule WHERE id = :id AND is_active = 1 LIMIT 1");
                $mstmt->execute([':id' => $monthly_date_id]);
                $row = $mstmt->fetch(PDO::FETCH_ASSOC);
                if ($row && !empty($row['test_date'])) {
                    $test_date = $row['test_date'];
                } else {
                    $errors[] = 'Selected monthly schedule not found or not active.';
                }
            } catch (Exception $e) {
                $errors[] = 'Failed to read monthly schedule: ' . $e->getMessage();
            }
        } else {
            $errors[] = 'Please select a scheduled monthly date.';
        }
    } else {
        // for daily/weekly ensure a valid date string (Y-m-d)
        if (!$test_date || !DateTime::createFromFormat('Y-m-d', $test_date)) {
            $errors[] = 'Invalid or missing date.';
        }
    }

    if (empty($errors) && $test_type === 'weekly') {
        $dt = new DateTime($test_date);
        // ISO weekday: 6 = Saturday
        if ((int)$dt->format('N') !== 6) $errors[] = 'Weekly test date must be a Saturday.';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO tests (club_id, title, test_type, test_date, created_by_roll, active) VALUES (:cid, :title, :tt, :td, :creator, 1)");
            $stmt->execute([
                ':cid' => $club_id,
                ':title' => $title,
                ':tt' => $test_type,
                ':td' => $test_date,
                ':creator' => $user_roll
            ]);
            $createdId = $pdo->lastInsertId();
            $success = 'Test created successfully.';
        } catch (Exception $e) {
            if (strpos($e->getMessage(), '23000') !== false || $e->getCode() == 23000) {
                $errors[] = 'A test already exists for this date.';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

$csrf = csrf_token();

// helper escape
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Create Test — <?= h($clubRow['name']) ?></title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <link rel="stylesheet" href="/public/css/create_club.css">
    <style>
        .info-box { background: #f3f4f6; border: 1px solid #d1d5db; padding: 10px; border-radius: 6px; margin-top: 5px; font-size: 0.9rem; color: #374151; }
        .info-box ul { margin: 5px 0 0 20px; padding: 0; }
        .hidden { display:none; }
        .form-row { margin-bottom:12px; }
    </style>
    <script>
    function handleTestTypeChange() {
        const type = document.querySelector('select[name="test_type"]').value;
        const dateRow = document.getElementById('date_row');
        const monthlySelectRow = document.getElementById('monthly_select_row');
        const monthlyInfo = document.getElementById('monthly-dates-info');

        if (type === 'monthly') {
            // show monthly selector, hide manual date entry
            if (monthlySelectRow) monthlySelectRow.classList.remove('hidden');
            if (monthlyInfo) monthlyInfo.style.display = 'block';
            if (dateRow) dateRow.classList.add('hidden');
        } else {
            // hide monthly selector
            if (monthlySelectRow) monthlySelectRow.classList.add('hidden');
            if (monthlyInfo) monthlyInfo.style.display = 'none';
            if (dateRow) dateRow.classList.remove('hidden');
        }
    }

    document.addEventListener('DOMContentLoaded', function(){
        handleTestTypeChange();

        // when monthly schedule changes, update the hidden date input for server (not necessary but useful for debugging)
        const monthlySelect = document.getElementById('monthly_date_id');
        if (monthlySelect) {
            monthlySelect.addEventListener('change', function(){
                const dateInput = document.querySelector('input[name="test_date"]');
                const opt = this.options[this.selectedIndex];
                if (opt && opt.dataset.date) {
                    dateInput.value = opt.dataset.date;
                } else {
                    dateInput.value = '';
                }
            });
        }

        document.querySelector('select[name="test_type"]').addEventListener('change', handleTestTypeChange);
    });
    </script>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<?php
// Handle Redirect Logic here inside HTML body to avoid header errors
if ($success && $createdId) {
    $redirectUrl = BASE_URL . '/club/add_questions.php?club_id=' . (int)$club_id . '&test_id=' . (int)$createdId;
    echo "<script>
        alert('Test created successfully!');
        window.location.href = '" . h($redirectUrl) . "';
    </script>";
    exit; // Stop rendering the rest of the form
}

if (!empty($errors)) {
    $msg = implode("\\n", array_map('addslashes', $errors));
    echo "<script>alert('". h($msg) ."');</script>";
}
?>

<main class="container">
    <div >
        <h2 style="text-align: center;">Create Test</h2>
       <!-- <a href="<?= h(BASE_URL) ?>/club/dashboard.php?club_id=<?= (int)$club_id ?>" class="btn secondary">Back</a> -->
    </div>

    <form method="post" class="form" style="max-width:720px;">
        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="club_id" value="<?= (int)$club_id ?>">

        <div class="form-row">
            <label>Test Title</label>
            <input type="text" name="title" required value="<?= h($_POST['title'] ?? '') ?>" placeholder="e.g. Topic of the test">
        </div>

        <div class="form-row">
            <label>Type</label>
            <select name="test_type" required>
                <option value="daily" <?= (($_POST['test_type'] ?? '') === 'daily') ? 'selected' : '' ?>>Daily</option>
                <option value="weekly" <?= (($_POST['test_type'] ?? '') === 'weekly') ? 'selected' : '' ?>>Weekly (Saturday)</option>
                <option value="monthly" <?= (($_POST['test_type'] ?? '') === 'monthly') ? 'selected' : '' ?>>Monthly (choose scheduled date)</option>
            </select>
        </div>

        <div id="date_row" class="form-row">
            <label>Date</label>
            <!-- keep a date input (visible for daily/weekly only) -->
            <input type="date" name="test_date" required value="<?= h($_POST['test_date'] ?? '') ?>">
            <div id="monthly-dates-info" class="info-box" style="display:none;">
                <strong>Pick a monthly schedule date</strong>
            </div>
        </div>

        <div id="monthly_select_row" class="form-row <?= empty($monthlySchedule) ? 'hidden' : '' ?>">
            <label id="monthly_label" for="monthly_date_id">Monthly schedule (active dates)</label>
            <select id="monthly_date_id" name="monthly_date_id">
                <option value="">-- choose schedule date (if monthly) --</option>
                <?php if (!empty($monthlySchedule)): ?>
                    <?php foreach ($monthlySchedule as $ms): ?>
                        <?php
                            $sel = (isset($_POST['monthly_date_id']) && (int)$_POST['monthly_date_id'] === (int)$ms['id']) ? 'selected' : '';
                            $date = $ms['test_date'];
                            $label = isset($ms['description']) && trim($ms['description']) !== '' ? h($ms['description']) . " • " . h($date) : h($date);
                        ?>
                        <option value="<?= (int)$ms['id'] ?>" data-date="<?= h($date) ?>" <?= $sel ?>><?= $label ?></option>
                    <?php endforeach; ?>
                <?php else: ?>
                    <option value="">No active monthly schedules found</option>
                <?php endif; ?>
            </select>
        </div>

     <!--   <div class="info-box" style="margin-top:6px;">
            <strong>Scheduled Monthly Tests (Reference)</strong>
            <?php if (empty($existingMonthlyTests)): ?>
                <div><em>No monthly tests scheduled yet.</em></div>
            <?php else: ?>
                <ul>
                    <?php foreach ($existingMonthlyTests as $et): ?>
                        <li><?= h($et['test_date']) ?>: <?= h($et['title']) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div> -->

        <div style="margin-top:12px;">
            <button type="submit" class="btn">Create Test</button>
        </div>
    </form>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
