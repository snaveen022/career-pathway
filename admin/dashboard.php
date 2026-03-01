<?php
require_once __DIR__ . '/../config/config.php';
require_admin($pdo);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <h2>Admin Dashboard</h2>

    <div class="cards">

        <div class="card">
            <h3>Manage Clubs</h3>
            <p>Create or Edit club names/descriptions and delete clubs.</p>
            <a href="<?= e(BASE_URL) ?>/admin/manage_clubs.php">Open</a>
        </div>

        <div class="card">
            <h3>Manage Roles</h3>
            <p>Assign secretary / joint secretary / members.</p>
            <a href="<?= BASE_URL ?>/admin/manage_roles.php">Open</a>
        </div>

        <div class="card">
            <h3>Approve Questions</h3>
            <p>Review pending MCQ submissions.</p>
            <a href="<?= BASE_URL ?>/admin/approve_questions.php">Open</a>
        </div>

        <div class="card">
            <h3>Approve Club Roles</h3>
            <p>Review pending club members.</p>
            <a href="<?= BASE_URL ?>/admin/approve_club_roles.php">Open</a>
        </div>

        <div class="card">
            <h3>Manage Club Officers</h3>
            <p>View/Delete the club officers.</p>
            <a href="<?= BASE_URL ?>/admin/club_officers_list.php">Open</a>
        </div>

        <div class="card">
            <h3>User Activation</h3>
            <p>Activate or deactivate users.</p>
            <a href="<?= BASE_URL ?>/admin/user_activation.php">Open</a>
        </div>

        <div class="card">
            <h3>Reports</h3>
            <p>View overall reports and summaries.</p>
            <a href="<?= BASE_URL ?>/admin/reports.php">Open</a>
        </div>
        
        <div class="card">
    		<h3>Message</h3>
    		<p>Send message.</p>
    		<a href="<?= BASE_URL ?>/admin/admin_messages.php">Open</a>
		</div>

		<div class="card">
    		<h3 >Assign Users for Monthly Test</h3>
    		<p >
        		Add students to the monthly test using roll numbers.
    		</p>
    		<a href="<?= BASE_URL ?>/admin/monthly_test.php">Open</a>
		</div>


    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
