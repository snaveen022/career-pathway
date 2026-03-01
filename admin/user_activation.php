<?php
// admin/user_activation.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/phpmailer_config.php';
require_admin($pdo);

$errors = [];
$success = null;
$info = null;

// which roll number currently waiting for OTP input (to show OTP form)
$otpRoll = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        // Common input
        $roll = trim($_POST['roll_no'] ?? '');

        // 1) Send OTP for activation
        if (isset($_POST['send_otp'])) {
            if ($roll === '') {
                $errors[] = 'No roll number provided.';
            } else {
                // Check user exists and is inactive
                $stmt = $pdo->prepare('SELECT roll_no, email, full_name, is_active FROM users WHERE roll_no = :roll LIMIT 1');
                $stmt->execute([':roll' => $roll]);
                $user = $stmt->fetch();

                if (!$user) {
                    $errors[] = "User not found.";
                } elseif ((int)$user['is_active'] === 1) {
                    $errors[] = "User is already active.";
                } else {
                    try {
                        $pdo->beginTransaction();

                        // Generate 6-digit OTP
                        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $otpHash = hash('sha256', $otp);
                        $expiresAt = (new DateTime('+30 minutes'))->format('Y-m-d H:i:s');

                        // Delete any previous OTPs for this roll
                        $del = $pdo->prepare('DELETE FROM email_verifications WHERE roll_no = :roll');
                        $del->execute([':roll' => $roll]);

                        // Insert new OTP
                        $ins = $pdo->prepare('INSERT INTO email_verifications (roll_no, token_hash, expires_at) VALUES (:roll, :th, :exp)');
                        $ins->execute([
                            ':roll' => $roll,
                            ':th'   => $otpHash,
                            ':exp'  => $expiresAt
                        ]);

                        $pdo->commit();

                        // Send email to this user's email
                        $to = $user['email'];
                        $name = $user['full_name'] ?: $user['roll_no'];

                        $subject = 'Career Pathway — Account Activation Code';
                        $html  = "<p>Hello " . e($name) . ",</p>";
                        $html .= "<p>Your account activation code is:</p>";
                        $html .= "<p><strong style='font-size:18px; letter-spacing:2px;'>" . e($otp) . "</strong></p>";
                        $html .= "<p>This code is valid for 30 minutes.</p>";
                        $html .= "<p>Please share this code with the admin to complete activation.</p>";

                        $ok = sendMail($to, $subject, $html);

                        if ($ok) {
                            $success = "OTP sent to {$to}. Enter the OTP below to activate the account.";
                            $otpRoll = $roll;
                        } else {
                            $errors[] = "Failed to send OTP email. Please check mail configuration.";
                        }

                    } catch (Exception $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        $errors[] = "Database error while sending OTP: " . $e->getMessage();
                    }
                }
            }
        }

        // 2) Verify OTP & activate user
        if (isset($_POST['verify_otp'])) {
            $otp = trim($_POST['otp'] ?? '');

            if ($roll === '' || $otp === '') {
                $errors[] = 'Roll number or OTP missing.';
                $otpRoll = $roll;
            } else {
                $stmt = $pdo->prepare('SELECT token_hash, expires_at FROM email_verifications WHERE roll_no = :roll LIMIT 1');
                $stmt->execute([':roll' => $roll]);
                $row = $stmt->fetch();

                if (!$row) {
                    $errors[] = 'No active OTP found for this roll. Please send OTP again.';
                    $otpRoll = $roll;
                } else {
                    $now = new DateTime();
                    $exp = new DateTime($row['expires_at']);

                    if ($now > $exp) {
                        $errors[] = 'OTP has expired. Please send a new OTP.';
                        $otpRoll = $roll;
                    } else {
                        $otpHash = hash('sha256', $otp);
                        if (!hash_equals($row['token_hash'], $otpHash)) {
                            $errors[] = 'Incorrect OTP.';
                            $otpRoll = $roll;
                        } else {
                            try {
                                $pdo->beginTransaction();

                                // Activate user
                                $upd = $pdo->prepare('UPDATE users SET is_active = 1 WHERE roll_no = :roll');
                                $upd->execute([':roll' => $roll]);

                                // Remove OTP row
                                $del = $pdo->prepare('DELETE FROM email_verifications WHERE roll_no = :roll');
                                $del->execute([':roll' => $roll]);

                                $pdo->commit();
                                $success = "User {$roll} has been activated.";
                                $otpRoll = null;
                            } catch (Exception $e) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                $errors[] = 'Error activating user: ' . $e->getMessage();
                                $otpRoll = $roll;
                            }
                        }
                    }
                }
            }
        }

        // 3) Delete inactive user
        if (isset($_POST['delete_inactive'])) {
            if ($roll === '') {
                $errors[] = 'No roll number provided.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Remove any OTP rows
                    $delOtp = $pdo->prepare('DELETE FROM email_verifications WHERE roll_no = :roll');
                    $delOtp->execute([':roll' => $roll]);

                    // Delete user
                    $delUser = $pdo->prepare('DELETE FROM users WHERE roll_no = :roll');
                    $delUser->execute([':roll' => $roll]);

                    $pdo->commit();
                    $success = "Inactive user {$roll} deleted.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Error deleting inactive user: ' . $e->getMessage();
                }
            }
        }

        // 4) Deactivate active user
        if (isset($_POST['deactivate'])) {
            if ($roll === '') {
                $errors[] = 'No roll number provided.';
            } else {
                try {
                    $stmt = $pdo->prepare('UPDATE users SET is_active = 0 WHERE roll_no = :roll');
                    $stmt->execute([':roll' => $roll]);
                    $success = "User {$roll} has been deactivated.";
                } catch (Exception $e) {
                    $errors[] = 'Error deactivating user: ' . $e->getMessage();
                }
            }
        }

        // 5) Delete active user
        if (isset($_POST['delete_active'])) {
            if ($roll === '') {
                $errors[] = 'No roll number provided.';
            } else {
                try {
                    $pdo->beginTransaction();

                    // Optional: also clean up OTPs
                    $delOtp = $pdo->prepare('DELETE FROM email_verifications WHERE roll_no = :roll');
                    $delOtp->execute([':roll' => $roll]);

                    $delUser = $pdo->prepare('DELETE FROM users WHERE roll_no = :roll');
                    $delUser->execute([':roll' => $roll]);

                    $pdo->commit();
                    $success = "Active user {$roll} deleted.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = 'Error deleting active user: ' . $e->getMessage();
                }
            }
        }
    }
}

// Fetch inactive users (is_active = 0)
$inactiveStmt = $pdo->query("
    SELECT roll_no, full_name, email, class, batch
    FROM users
    WHERE is_active = 0
    ORDER BY created_at DESC, roll_no ASC
");
$inactiveUsers = $inactiveStmt->fetchAll();

// Fetch active users (is_active = 1)
$activeStmt = $pdo->query("
    SELECT roll_no, full_name, email, class, batch
    FROM users
    WHERE is_active = 1
    ORDER BY roll_no ASC
");
$activeUsers = $activeStmt->fetchAll();

$token = csrf_token();

function esc($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Activation — Admin</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap { max-width: 1100px; margin: 20px auto; }
        h2 { margin-top: 0; }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        th, td {
            padding: 10px 8px;
            border-bottom: 1px solid #eef2ff;
            font-size: 0.95rem;
            text-align: left;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
        }
        .section-title {
            margin-top: 28px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .badge {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-inactive { background: #fee2e2; color: #b91c1c; }
        .badge-active { background: #dcfce7; color: #166534; }
        .btn {
            padding: 6px 10px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.85rem;
        }
        .btn-otp { background: #2563eb; color: #fff; }
        .btn-activate { background: #16a34a; color: #fff; }
        .btn-deactivate { background: #f59e0b; color: #fff; }
        .btn-delete { background: #ef4444; color: #fff; }
        .errors ul {
            margin: 0;
            padding-left: 18px;
            color: #b91c1c;
            font-size: 0.9rem;
        }
        .errors { margin-bottom: 10px; }
        .success {
            color: #065f46;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        .otp-box {
            margin: 16px 0;
            padding: 12px;
            border-radius: 8px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }
        .otp-box label {
            font-weight: 600;
            font-size: 0.9rem;
            display: block;
            margin-bottom: 6px;
        }
        .otp-box input {
            padding: 7px 9px;
            border-radius: 6px;
            border: 1px solid #cbd5f5;
            width: 140px;
            margin-right: 8px;
        }
        .topbar-back a {
            text-decoration: none;
            font-size: 0.9rem;
            color: #2563eb;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <div class="wrap">
        <div class="topbar-back" style="margin-bottom:10px;display: flex;justify-content: space-between;">
            <div></div>
            <a href="<?= esc(BASE_URL) ?>/admin/dashboard.php">&larr; Back to Admin Dashboard</a>
        </div>

        <h2>User Activation</h2>

        <?php if ($errors): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= esc($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= esc($success) ?></div>
        <?php endif; ?>

        <!-- OTP entry box (if any user is currently in OTP phase) -->
        <?php if ($otpRoll): ?>
            <div class="otp-box">
                <form method="post" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                    <input type="hidden" name="roll_no" value="<?= esc($otpRoll) ?>">
                    <label for="otp" style="margin:0;">Enter OTP for Roll: <strong><?= esc($otpRoll) ?></strong></label>
                    <input type="number" id="otp" name="otp" placeholder="6-digit OTP" required>
                    <button type="submit" name="verify_otp" class="btn btn-activate">Verify & Activate</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Inactive users -->
        <div class="section-title">
            <h3>Inactive Users <span class="badge badge-inactive"><?= count($inactiveUsers) ?></span></h3>
        </div>

        <?php if (empty($inactiveUsers)): ?>
            <p style="color:#6b7280;">No inactive users found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($inactiveUsers as $idx => $u): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= esc($u['roll_no']) ?></td>
                        <td><?= esc($u['full_name']) ?></td>
                        <td><?= esc($u['email']) ?></td>
                        <td><?= esc($u['class']) ?></td>
                        <td><?= esc($u['batch']) ?></td>
                        <td>
                            <!-- Send OTP -->
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                <input type="hidden" name="roll_no" value="<?= esc($u['roll_no']) ?>">
                                <button type="submit"
                                        name="send_otp"
                                        class="btn btn-otp"
                                        onclick="return confirm('Send activation OTP to <?= esc($u['email']) ?>?');">
                                    Send OTP
                                </button>
                            </form>

                            <!-- Delete inactive -->
                            <form method="post" style="display:inline-block; margin-left:4px;">
                                <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                <input type="hidden" name="roll_no" value="<?= esc($u['roll_no']) ?>">
                                <button type="submit"
                                        name="delete_inactive"
                                        class="btn btn-delete"
                                        onclick="return confirm('Delete inactive user <?= esc($u['roll_no']) ?>? This cannot be undone.');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <!-- Active users -->
        <div class="section-title">
            <h3>Active Users <span class="badge badge-active"><?= count($activeUsers) ?></span></h3>
        </div>

        <?php if (empty($activeUsers)): ?>
            <p style="color:#6b7280;">No active users found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activeUsers as $idx => $u): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= esc($u['roll_no']) ?></td>
                        <td><?= esc($u['full_name']) ?></td>
                        <td><?= esc($u['email']) ?></td>
                        <td><?= esc($u['class']) ?></td>
                        <td><?= esc($u['batch']) ?></td>
                        <td>
                            <!-- Deactivate -->
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                <input type="hidden" name="roll_no" value="<?= esc($u['roll_no']) ?>">
                                <button type="submit"
                                        name="deactivate"
                                        class="btn btn-deactivate"
                                        onclick="return confirm('Deactivate user <?= esc($u['roll_no']) ?>?');">
                                    Deactivate
                                </button>
                            </form>

                            <!-- Delete active -->
                            <form method="post" style="display:inline-block; margin-left:4px;">
                                <input type="hidden" name="csrf_token" value="<?= esc($token) ?>">
                                <input type="hidden" name="roll_no" value="<?= esc($u['roll_no']) ?>">
                                <button type="submit"
                                        name="delete_active"
                                        class="btn btn-delete"
                                        onclick="return confirm('Delete active user <?= esc($u['roll_no']) ?>? This cannot be undone.');">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
