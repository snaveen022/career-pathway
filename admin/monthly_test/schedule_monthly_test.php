<?php
// admin/monthly_test/schedule_monthly_test.php
require_once __DIR__ . '/../../config/config.php';
require_login();

// Ensure only admins access this
if (!is_admin($pdo)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$success = null;
$error   = null;

// -----------------------------------------
// 1. Handle Form Submissions (Create & Toggle)
// -----------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CASE A: Toggle Status (Deactivate/Activate)
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
        $schedule_id = (int)($_POST['schedule_id'] ?? 0);
        if ($schedule_id > 0) {
            try {
                // Flip the is_active status (NOT is_active)
                $stmt = $pdo->prepare("UPDATE monthly_test_schedule SET is_active = NOT is_active WHERE id = :id");
                $stmt->execute([':id' => $schedule_id]);
                $success = "Schedule status updated successfully.";
            } catch (PDOException $e) {
                $error = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // CASE B: Create New Schedule
    elseif (isset($_POST['action']) && $_POST['action'] === 'create_schedule') {
        $test_date   = $_POST['test_date'] ?? '';
        $description = trim($_POST['description'] ?? '');
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (empty($test_date)) {
            $error = "Please select a test date.";
        } else {
            try {
                // Check for duplicate date (since test_date is UNIQUE)
                $stmt = $pdo->prepare("INSERT INTO monthly_test_schedule (test_date, description, is_active) VALUES (:td, :desc, :act)");
                $stmt->execute([
                    ':td'   => $test_date,
                    ':desc' => $description,
                    ':act'  => $is_active
                ]);
                $success = "Monthly test scheduled successfully for " . htmlspecialchars($test_date);
            } catch (PDOException $e) {
                // Error 23000 is usually a Unique constraint violation
                if ($e->getCode() == 23000) {
                    $error = "A test is already scheduled for this date.";
                } else {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }
}

// -----------------------------------------
// 2. Fetch Existing Schedules (for display)
// -----------------------------------------
$schedules = $pdo->query("SELECT * FROM monthly_test_schedule ORDER BY test_date DESC LIMIT 10")->fetchAll();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Schedule Monthly Test</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .container { max-width: 800px; margin: 30px auto; padding: 0 15px; }
        
        .card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .form-group { margin-bottom: 1.25rem; }
        
        label { 
            display: block; 
            font-weight: 600; 
            margin-bottom: 0.5rem; 
            color: #374151; 
        }

        input[type="date"],
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box; 
        }

        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            vertical-align: middle;
            margin-right: 8px;
        }

        .btn-submit {
            background-color: #2563eb;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-submit:hover { background-color: #1d4ed8; }

        .btn-mini {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            border: 1px solid transparent;
            font-weight: 500;
        }
        .btn-danger { background-color: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .btn-danger:hover { background-color: #fecaca; }
        
        .btn-success { background-color: #dcfce7; color: #15803d; border-color: #bbf7d0; }
        .btn-success:hover { background-color: #bbf7d0; }

        .alert { padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        th { background-color: #f9fafb; font-weight: 600; color: #4b5563; }
        
        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;
        }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-inactive { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2>Schedule Monthly Test</h2>
        <a href="<?= BASE_URL ?>/../monthly_test.php" class="btn-ghost" style="color:#2563eb; text-decoration:none;">&larr; Back to Assign Monthly Test</a>
    </div>

    <div class="card">
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="create_schedule">
            
            <div class="form-group">
                <label for="test_date">Test Date *</label>
                <input type="date" id="test_date" name="test_date" required min="<?= date('Y-m-d') ?>">
                <small style="color:#6b7280;">When is this monthly test happening?</small>
            </div>

            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <input type="text" id="description" name="description" placeholder="e.g. October Monthly Assessment" maxlength="100">
            </div>

            <div class="form-group">
                <label style="font-weight:400; cursor:pointer;">
                    <input type="checkbox" name="is_active" checked>
                    Make this schedule active immediately
                </label>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn-submit">Add to Schedule</button>
            </div>
        </form>
    </div>

    <h3>Upcoming Schedules</h3>
    <div class="card" style="padding:0; overflow:hidden;">
        <?php if (empty($schedules)): ?>
            <div style="padding:20px; color:#6b7280;">No tests scheduled yet.</div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Actions</th> </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($s['test_date']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($s['description'] ?? '-') ?></td>
                            <td>
                                <?php if ($s['is_active']): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="schedule_id" value="<?= $s['id'] ?>">
                                    
                                    <?php if ($s['is_active']): ?>
                                        <button type="submit" class="btn-mini btn-danger" onclick="return confirm('Are you sure you want to deactivate this test date?');">
                                            Deactivate
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" class="btn-mini btn-success">
                                            Activate
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>