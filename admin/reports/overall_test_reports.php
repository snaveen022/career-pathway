<?php
// admin/overall_test_reports.php
require_once __DIR__ . '/../../config/config.php';
require_admin($pdo);

function esc($v) {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Overall Test Reports</title>

    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap {
            max-width: 900px;
            margin: 30px auto;
        }
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
        }
    </style>
</head>

<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="container">

    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <h2>OVERALL TEST REPORT</h2>

        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= esc(BASE_URL) ?>/../reports.php">← Back to Reports</a>
    </div>

    <!-- Cards Section -->
    <div class="cards">

        <div class="card">
            <h3>Daily Test Report</h3>
            <p>View and analyze the performance of all daily tests.</p>
            <a class="report-btn" href="<?= esc(BASE_URL) ?>/overall_test_report/daily_report.php">Open</a>
        </div>

        <div class="card">
            <h3>Weekly Test Report</h3>
            <p>Access detailed results for all weekly tests.</p>
            <a class="report-btn" href="<?= esc(BASE_URL) ?>/overall_test_report/weekly_report.php">Open</a>
        </div>

        <div class="card">
            <h3>Monthly Test Report</h3>
            <p>Review monthly test performance for assigned users.</p>
            <a class="report-btn" href="<?= esc(BASE_URL) ?>/overall_test_report/monthly_report.php">Open</a>
        </div>

    </div>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
