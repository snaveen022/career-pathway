<?php
// admin/approve_club_roles.php
require_once __DIR__ . '/../config/config.php';
require_admin($pdo);

$errors = [];
$success = null;

// helper: approve single pending club role
function approve_single_club_role(PDO $pdo, int $pendingId): void {
    // lock the pending row
    $stmt = $pdo->prepare("SELECT * FROM club_roles_pending WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $pendingId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new Exception("Pending role #{$pendingId} not found.");
    }

    $clubId   = (int)$row['club_id'];
    $userRoll = $row['user_roll'];
    $role     = $row['role'];
    $canPost  = (int)$row['can_post_questions'];

    // upsert into club_roles
    $upsert = $pdo->prepare("
        INSERT INTO club_roles (club_id, user_roll, role, can_post_questions)
        VALUES (:cid, :r, :role, :cp)
        ON DUPLICATE KEY UPDATE
            role = VALUES(role),
            can_post_questions = VALUES(can_post_questions)
    ");
    $upsert->execute([
        ':cid'  => $clubId,
        ':r'    => $userRoll,
        ':role' => $role,
        ':cp'   => $canPost
    ]);

    // sync global role in users table (you can tweak this behaviour if needed)
    $updUser = $pdo->prepare("UPDATE users SET role = :role WHERE roll_no = :r");
    $updUser->execute([
        ':role' => $role,
        ':r'    => $userRoll
    ]);

    // mark as approved in pending table
    $del = $pdo->prepare("UPDATE club_roles_pending SET is_approve = 'approved' WHERE id = :id");
    $del->execute([':id' => $pendingId]);
}

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid request.";
    } else {
        // Approve single pending role
        if (isset($_POST['approve'])) {
            $pid = (int)($_POST['pending_id'] ?? 0);
            if ($pid <= 0) {
                $errors[] = "Invalid pending id.";
            } else {
                try {
                    $pdo->beginTransaction();
                    approve_single_club_role($pdo, $pid);
                    $pdo->commit();
                    $success = "Member role approved and applied.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = "Approve failed: " . $e->getMessage();
                }
            }
        }

        // Reject single pending role
        if (isset($_POST['reject'])) {
            $pid = (int)($_POST['pending_id'] ?? 0);
            if ($pid <= 0) {
                $errors[] = "Invalid pending id.";
            } else {
                try {
                    $pdo->beginTransaction();
                    $del = $pdo->prepare("UPDATE club_roles_pending SET is_approve = 'rejected' WHERE id = :id");
                    $del->execute([':id' => $pid]);
                    $pdo->commit();
                    $success = "Pending role request rejected.";
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = "Reject failed: " . $e->getMessage();
                }
            }
        }
    }
}

// fetch all pending club roles with club + user info + existing roles
$sql = "
    SELECT
        p.id,
        p.club_id,
        c.name AS club_name,
        p.user_roll,
        u.full_name,
        u.class,
        p.role              AS requested_role,
        p.can_post_questions AS requested_can_post,

        cr.role             AS existing_role,
        cr.can_post_questions AS existing_can_post
    FROM club_roles_pending p
    JOIN clubs c ON c.id = p.club_id
    LEFT JOIN users u ON u.roll_no = p.user_roll
    LEFT JOIN club_roles cr
           ON cr.club_id = p.club_id
          AND cr.user_roll = p.user_roll
    WHERE p.is_approve = 'waiting'
    ORDER BY c.name ASC, p.user_roll ASC
";
$pendingStmt = $pdo->query($sql);
$pendingRows = $pendingStmt->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Approve Club Member Roles</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap {
            max-width: 1000px;
            margin: 20px auto;
        }
        h2 { margin-top:0; }
        table {
            width:100%;
            border-collapse:collapse;
            margin-top:12px;
            background:#fff;
            border-radius:10px;
            overflow:hidden;
            border:1px solid #e5e7eb;
        }
        th, td {
            padding:10px 8px;
            border-bottom:1px solid #eef2ff;
            font-size:0.95rem;
            text-align:left;
        }
        th {
            background:#f9fafb;
            font-weight:600;
            color:#4b5563;
        }
        .btn {
            padding:6px 10px;
            border-radius:8px;
            border:0;
            cursor:pointer;
            font-weight:600;
            font-size:0.85rem;
        }
        .btn-approve {
            background:linear-gradient(90deg,#10b981,#059669);
            color:#fff;
        }
        .btn-reject {
            background:#ef4444;
            color:#fff;
        }
        .badge {
            display:inline-block;
            padding:3px 7px;
            border-radius:999px;
            font-size:0.75rem;
            font-weight:600;
        }
        .badge-yes { background:#dcfce7; color:#166534; }
        .badge-no { background:#fee2e2; color:#b91c1c; }
        .badge-existing { background:#e0f2fe; color:#0369a1; }
        .badge-none { background:#f3f4f6; color:#4b5563; }
        .errors ul {
            margin:0;
            padding-left:18px;
            color:#b91c1c;
            font-weight:500;
        }
        .success {
            color:#065f46;
            font-weight:600;
            margin-bottom:10px;
        }
        .note {
            color:#6b7280;
            font-size:0.85rem;
            margin-bottom:8px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <div class="wrap">
        <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
            <h2>Approve Club Member Roles</h2>
            <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= e(BASE_URL) ?>/admin/dashboard.php">← Back to Admin</a>
        </div>

        <!-- <p class="note">
            For each request you can see the <strong>requested role</strong> and the user's <strong>existing role in this club</strong> (if any),
            before approving or rejecting.
        </p> -->

        <?php if ($errors): ?>
            <div class="errors">
                <ul>
                    <?php foreach ($errors as $e) echo '<li>'.e($e).'</li>'; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= e($success) ?></div>
        <?php endif; ?>

        <?php if (empty($pendingRows)): ?>
            <p style="color:#6b7280;">No pending role requests found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Club</th>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Requested Role</th>
                        <th>Existing Role in Club</th>
                        <th>Requested Can Post?</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($pendingRows as $idx => $row): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td><?= e($row['club_name']) ?></td>
                        <td><?= e($row['user_roll']) ?></td>
                        <td><?= e($row['full_name'] ?? '') ?></td>
                        <td><?= e($row['class'] ?? '') ?></td>

                        <!-- Requested role -->
                        <td><?= e($row['requested_role']) ?></td>

                        <!-- Existing role in this club -->
                        <td>
                            <?php if ($row['existing_role']): ?>
                                <span class="badge badge-existing">
                                    <?= e($row['existing_role']) ?>
                                    <?php if ((int)$row['existing_can_post'] === 1): ?>
                                        · can post
                                    <?php else: ?>
                                        · no posting
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge badge-none">Not a member yet</span>
                            <?php endif; ?>
                        </td>

                        <!-- Requested posting -->
                        <td>
                            <?php if ((int)$row['requested_can_post'] === 1): ?>
                                <span class="badge badge-yes">Yes</span>
                            <?php else: ?>
                                <span class="badge badge-no">No</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <form method="post" style="display:inline-block;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="pending_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" name="approve"
                                        class="btn btn-approve"
                                        onclick="return confirm('Approve this member role?');">
                                    Approve
                                </button>
                            </form>
                            <form method="post" style="display:inline-block;margin-left:4px;">
                                <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                                <input type="hidden" name="pending_id" value="<?= (int)$row['id'] ?>">
                                <button type="submit" name="reject"
                                        class="btn btn-reject"
                                        onclick="return confirm('Reject and mark this request as rejected?');">
                                    Reject
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
