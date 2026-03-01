<?php
// admin/club_officers.php
require_once __DIR__ . '/../config/config.php';
require_admin($pdo); // only admin can view

$errors = [];
$success = null;

// Handle delete action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $club_id   = isset($_POST['club_id']) ? (int)$_POST['club_id'] : 0;
        $user_roll = trim($_POST['user_roll'] ?? '');

        if ($club_id <= 0 || $user_roll === '') {
            $errors[] = 'Missing club or user information.';
        } else {
            try {
                $pdo->beginTransaction();

                // Delete from club_roles
                $del = $pdo->prepare("
                    DELETE FROM club_roles 
                    WHERE club_id = :cid AND user_roll = :r
                ");
                $del->execute([
                    ':cid' => $club_id,
                    ':r'   => $user_roll
                ]);

                // Set user global role back to attendee
                $upd = $pdo->prepare("
                    UPDATE users 
                    SET role = 'attendee' 
                    WHERE roll_no = :r
                ");
                $upd->execute([':r' => $user_roll]);

                $pdo->commit();
                $success = "Removed club role for {$user_roll} and set role to attendee.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = "Error removing role: " . $e->getMessage();
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');

// base SQL
$sql = "
    SELECT 
        cr.club_id,
        c.name AS club_name,
        cr.user_roll,
        cr.role,
        cr.can_post_questions,
        u.full_name,
        u.class
    FROM club_roles cr
    JOIN clubs c ON c.id = cr.club_id
    LEFT JOIN users u ON u.roll_no = cr.user_roll
";

// add search condition if needed
$params = [];
if ($search !== '') {
    $sql .= "
        WHERE 
            c.name LIKE :q
            OR cr.user_roll LIKE :q
            OR u.full_name LIKE :q
    ";
    $params[':q'] = '%' . $search . '%';
}

// order: club name, role order, then student name
$sql .= "
    ORDER BY 
        c.name ASC,
        FIELD(cr.role, 'club_secretary', 'club_joint_secretary', 'club_member'),
        u.full_name ASC
";

// run query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// group by club
$clubs = [];
foreach ($rows as $r) {
    $cid = (int)$r['club_id'];
    if (!isset($clubs[$cid])) {
        $clubs[$cid] = [
            'club_name' => $r['club_name'],
            'members'   => []
        ];
    }
    $clubs[$cid]['members'][] = $r;
}

$csrf = csrf_token();

function esc($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Club Officers & Members</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap {
            max-width: 1100px;
            margin: 20px auto;
            padding: 0 8px;
        }
        h2 {
            margin-top: 0;
        }
        .search-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
            align-items: center;
            flex-wrap: wrap;
        }
        .search-bar input[type="text"] {
            flex: 1;
            min-width: 220px;
            padding: 15px 10px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
        }
        .btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary {
            background: linear-gradient(90deg,#2563eb,#1d4ed8);
            color: #fff;
            padding: 13px 10px;
        }
        .btn-ghost {
            background: transparent;
            border: 1px solid #cbd5f5;
            color: #2563eb;
        }
        .btn-delete {
            background: #ef4444;
            color: #fff;
            padding: 6px 10px;
            font-size: 0.8rem;
        }
        .club-card {
            background: #fff;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
            padding: 14px 16px;
            margin-bottom: 14px;
            box-shadow: 0 6px 18px rgba(15,23,42,0.03);
        }
        .club-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 8px;
            margin-bottom: 8px;
        }
        .club-header h3 {
            margin: 0;
        }
        .muted {
            color: #6b7280;
            font-size: 0.9rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th, td {
            padding: 8px 6px;
            border-bottom: 1px solid #eef2ff;
            font-size: 0.9rem;
            text-align: left;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
        }
        .badge-role {
            display: inline-block;
            padding: 3px 7px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-secretary {
            background: #e0f2fe;
            color: #075985;
        }
        .badge-joint {
            background: #ede9fe;
            color: #5b21b6;
        }
        .badge-member {
            background: #ecfdf5;
            color: #065f46;
        }
        .badge-post {
            background: #f97316;
            color: #fff;
            border-radius: 999px;
            font-size: 0.7rem;
            padding: 2px 6px;
            margin-left: 4px;
        }
        .messages {
            margin-bottom: 12px;
        }
        .messages .error {
            color: #b91c1c;
            font-size: 0.9rem;
        }
        .messages .success {
            color: #065f46;
            font-size: 0.9rem;
        }
        @media (max-width: 640px) {
            table {
                font-size: 0.85rem;
            }
            .club-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
        <h2>Club Officers & Members</h2>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= e(BASE_URL) ?>/admin/dashboard.php">← Back to Admin</a>
    </div>
    
    <p class="muted">
        View club secretaries, joint secretaries and members, ordered club-wise.
    </p>

    <div class="messages">
        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $e): ?>
                    <div><?= esc($e) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success"><?= esc($success) ?></div>
        <?php endif; ?>
    </div>

    <form class="search-bar" method="get" action="">
        <input type="text"
               name="q"
               placeholder="Search by club name, student name, or roll number..."
               value="<?= esc($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="club_officers.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($clubs)): ?>
        <p class="muted">No club roles found<?= $search ? ' for this search.' : '.' ?></p>
    <?php else: ?>
        <?php foreach ($clubs as $cid => $club): ?>
            <section class="club-card">
                <div class="club-header">
                    <h3><?= esc($club['club_name']) ?></h3>
                    <span class="muted">
                        <?= count($club['members']) ?> member(s)
                    </span>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Roll No</th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Posting</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($club['members'] as $m): ?>
                        <tr>
                            <td>
                                <?php
                                    $role = $m['role'];
                                    $label = $role;
                                    $class = 'badge-role badge-member';
                                    if ($role === 'club_secretary') {
                                        $label = 'Club Secretary';
                                        $class = 'badge-role badge-secretary';
                                    } elseif ($role === 'club_joint_secretary') {
                                        $label = 'Joint Secretary';
                                        $class = 'badge-role badge-joint';
                                    } elseif ($role === 'club_member') {
                                        $label = 'Member';
                                        $class = 'badge-role badge-member';
                                    }
                                ?>
                                <span class="<?= esc($class) ?>"><?= esc($label) ?></span>
                            </td>
                            <td><?= esc($m['user_roll']) ?></td>
                            <td><?= esc($m['full_name'] ?? '') ?></td>
                            <td><?= esc($m['class'] ?? '') ?></td>
                            <td>
                                <?php if ((int)$m['can_post_questions'] === 1): ?>
                                    <span class="badge-post">Can post</span>
                                <?php else: ?>
                                    <span class="muted">No posting</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                    <input type="hidden" name="club_id" value="<?= (int)$m['club_id'] ?>">
                                    <input type="hidden" name="user_roll" value="<?= esc($m['user_roll']) ?>">
                                    <button type="submit"
                                            class="btn btn-delete"
                                            onclick="return confirm('Remove this member from the club and set their role to attendee?');">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
