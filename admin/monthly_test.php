<?php
// admin/monthly_test.php
require_once __DIR__ . '/../config/config.php';
require_login();

// Ensure only admins access this
if (!is_admin($pdo)) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly Test Management</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .container { max-width: 900px; margin: 40px auto; padding: 0 15px; }
        
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-top: 30px;
        }

        .action-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.2s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            height: 100%;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: #2563eb;
        }

        .icon {
            font-size: 3rem;
            margin-bottom: 15px;
            background: #eff6ff;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #2563eb;
        }

        .action-card h3 {
            margin: 0 0 10px 0;
            color: #111827;
            font-size: 1.25rem;
        }

        .action-card p {
            color: #6b7280;
            margin: 0 0 24px 0;
            line-height: 1.5;
        }

        .btn-action {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            width: 100%;
            box-sizing: border-box;
        }

        .btn-action:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <div style="text-align: center; margin-bottom: 40px;">
        <h2>Monthly Test Management</h2>
        <p style="color: #6b7280;">Manage schedules and assign students to monthly tests from here.</p>
    </div>

    <div class="action-grid">
        <div class="action-card">
            <div class="icon">📅</div>
            <h3>Schedule Test</h3>
            <p>Create a new monthly test date in the system calendar (e.g., set up the October Test).</p>
            <a href="<?= BASE_URL ?>/admin/monthly_test/schedule_monthly_test.php" class="btn-action">
                Go to Schedule
            </a>
        </div>

        <div class="action-card">
            <div class="icon">👥</div>
            <h3>Assign Students</h3>
            <p>Select a scheduled date and assign students (via Excel upload or manual entry) to that test.</p>
            <a href="<?= BASE_URL ?>/admin/monthly_test/assign_monthly_test.php" class="btn-action">
                Go to Assignment
            </a>
        </div>
    </div>
    
    <div style="margin-top: 40px; text-align: center;">
        <a href="<?= BASE_URL ?>/admin/dashboard.php" style="color: #6b7280; text-decoration: none;">&larr; Back to Admin Dashboard</a>
    </div>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>