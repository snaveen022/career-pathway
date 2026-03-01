<?php
// admin/report/user_report.php
require_once __DIR__ . '/../../config/config.php';
require_admin($pdo); // only admin

$errors   = [];
$search   = trim($_GET['q'] ?? '');
$viewRoll = trim($_GET['view_roll'] ?? '');

// small helper
function esc($v) {
    return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// 1) Fetch users (with optional search)
$sql = "
    SELECT 
        u.roll_no,
        u.full_name,
        u.email,
        u.class,
        u.batch,
        u.role,
        u.is_active
    FROM users u
";

$params = [];
if ($search !== '') {
    $sql .= "
        WHERE
            u.roll_no     LIKE :q
            OR u.full_name LIKE :q
            OR u.email    LIKE :q
            OR u.class    LIKE :q
            OR u.batch    LIKE :q
    ";
    $params[':q'] = '%' . $search . '%';
}

$sql .= "
    ORDER BY
    	u.roll_no ASC,
        u.class ASC
        
";

$stmt  = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// 2) If a user is selected, fetch that user's test attempts
$userTests    = [];
$selectedUser = null;

if ($viewRoll !== '') {
    // get user details
    $uStmt = $pdo->prepare("
        SELECT roll_no, full_name, email, class, batch, role, is_active
        FROM users
        WHERE roll_no = :r
        LIMIT 1
    ");
    $uStmt->execute([':r' => $viewRoll]);
    $selectedUser = $uStmt->fetch();

    if ($selectedUser) {
        // fetch attempts for this user (same idea as attendee dashboard)
        $aStmt = $pdo->prepare("
            SELECT 
                a.id,
                a.test_id,
                a.score,
                a.total_marks,
                a.submitted_at,
                t.test_type,
                t.test_date,
                COALESCE(c.name, 'Global') AS club_name
            FROM attempts a
            LEFT JOIN tests t ON t.id = a.test_id
            LEFT JOIN clubs c ON c.id = t.club_id
            WHERE a.user_roll = :r
            ORDER BY a.submitted_at DESC
        ");
        $aStmt->execute([':r' => $viewRoll]);
        $userTests = $aStmt->fetchAll();
    } else {
        $errors[] = "User not found for roll no: {$viewRoll}.";
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Reports</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 8px;
        }
        h2 {
            margin-top: 0;
        }
        .muted {
            color:#6b7280;
            font-size:0.9rem;
        }
        .search-bar {
            display:flex;
            gap:8px;
            align-items:center;
            flex-wrap:wrap;
            margin-bottom:14px;
        }
        .search-bar input[type="text"] {
            flex:1;
            min-width:220px;
            padding:15px 10px;
            border-radius:8px;
            border:1px solid #e5e7eb;
        }
        .btn {
            padding:8px 12px;
            border-radius:8px;
            border:0;
            cursor:pointer;
            font-weight:600;
            font-size:0.9rem;
            text-decoration:none;
            display:inline-flex;
            align-items:center;
            justify-content:center;
        }
        .btn-primary {
            background:linear-gradient(90deg,#2563eb,#1d4ed8);
            padding: 13px 10px;
            color:#fff;
        }
        .btn-ghost {
            background:transparent;
            border:1px solid #cbd5f5;
            color:#2563eb;
        }
        .badge {
            display:inline-block;
            padding:3px 7px;
            border-radius:999px;
            font-size:0.75rem;
            font-weight:600;
        }
        .badge-active {
            background:#dcfce7;
            color:#166534;
        }
        .badge-inactive {
            background:#fee2e2;
            color:#b91c1c;
        }
        .badge-role-admin {
            background:#fef3c7;
            color:#92400e;
        }
        .badge-role-attendee {
            background:#eff6ff;
            color:#1d4ed8;
        }
        .badge-role-other {
            background:#e0f2fe;
            color:#0369a1;
        }
        table {
            width:100%;
            border-collapse:collapse;
            background:#fff;
            border-radius:10px;
            overflow:hidden;
            border:1px solid #e5e7eb;
        }
        th, td {
            padding:8px 6px;
            border-bottom:1px solid #eef2ff;
            font-size:0.9rem;
            text-align:left;
        }
        th {
            background:#f9fafb;
            font-weight:600;
            color:#4b5563;
        }
        .section {
            margin-top:18px;
        }
        .msg-error {
            color:#b91c1c;
            font-size:0.9rem;
            margin-bottom:8px;
        }

        /* Floating modal styles for test history */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15,23,42,0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }
        .modal-card {
            background: #ffffff;
            border-radius: 12px;
            max-width: 900px;
            width: 95%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 45px rgba(15,23,42,0.35);
            padding: 16px 18px 20px;
        }
        .modal-header {
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:8px;
            margin-bottom:8px;
        }
        .modal-header h3 {
            margin:0;
        }
        .modal-close-btn {
            background:transparent;
            border:0;
            font-size:1.4rem;
            line-height:1;
            cursor:pointer;
            color:#6b7280;
            padding:4px 8px;
            border-radius:999px;
            text-decoration:none;
        }
        .modal-close-btn:hover {
            background:#e5e7eb;
            color:#111827;
        }
        .modal-note {
            font-size:0.8rem;
            color:#9ca3af;
            margin-top:4px;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
        <h2>User Reports</h2>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" 
           href="<?= esc(BASE_URL) ?>/../reports.php">← Back to Reports</a>
    </div>
  
    <p class="muted">
        <br>View all registered users and their test history.
    </p>

    <br>
    
    <?php if ($errors): ?>
        <div class="msg-error">
            <?php foreach ($errors as $e) echo esc($e) . '<br>'; ?>
        </div>
    <?php endif; ?>

    <!-- Search form -->
    <form method="get" class="search-bar">
        <input type="text"
               name="q"
               placeholder="Search by roll no, name, email, class or batch..."
               value="<?= esc($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="user_report.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <!-- Users list -->
    <section class="section">
        <h3>All Users</h3>
        <?php if (empty($users)): ?>
            <p class="muted">No users found<?= $search ? ' for this search.' : '.' ?></p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>S. NO.</th>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Class</th>
                        <th>Batch</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Tests</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $sn = 1;
                    foreach ($users as $u): ?>
                    <tr>
                        <td><?= $sn++ ?></td>
                        <td><?= esc($u['roll_no']) ?></td>
                        <td><?= esc($u['full_name'] ?? '') ?></td>
                        <td><?= esc($u['email'] ?? '') ?></td>
                        <td><?= esc($u['class'] ?? '') ?></td>
                        <td><?= esc($u['batch'] ?? '') ?></td>
                        <td>
                            <?php
                                $role = $u['role'] ?? 'attendee';
                                if ($role === 'admin') {
                                    echo '<span class="badge badge-role-admin">Admin</span>';
                                } elseif ($role === 'attendee') {
                                    echo '<span class="badge badge-role-attendee">Attendee</span>';
                                } else {
                                    echo '<span class="badge badge-role-other">'.esc($role).'</span>';
                                }
                            ?>
                        </td>
                        <td>
                            <?php if ((int)$u['is_active'] === 1): ?>
                                <span class="badge badge-active">Active</span>
                            <?php else: ?>
                                <span class="badge badge-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a class="btn btn-ghost"
                               href="user_report.php?view_roll=<?= urlencode($u['roll_no']) ?>&q=<?= urlencode($search) ?>">
                                View Tests
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <!-- Selected user's test history in floating modal -->
    <?php if ($selectedUser): ?>
        <div class="modal-overlay">
            <div class="modal-card">
                <div class="modal-header">
                    <h3>Test History: <?= esc($selectedUser['full_name'] ?? $selectedUser['roll_no']) ?></h3>
                    <!-- Close: reload same page without view_roll, keep search -->
                    <a href="user_report.php?q=<?= urlencode($search) ?>" class="modal-close-btn" title="Close">×</a>
                </div>

                <p class="muted">
                    Roll: <strong><?= esc($selectedUser['roll_no']) ?></strong> |
                    Class: <?= esc($selectedUser['class'] ?? '-') ?> |
                    Batch: <?= esc($selectedUser['batch'] ?? '-') ?>
                </p>
                <p class="modal-note">Press the × button to close this window.</p>

                <br>

                <?php if (empty($userTests)): ?>
                    <p class="muted">This user has not attended any tests yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>S. No.</th>
                                <th>Test Type</th>
                                <th>Date</th>
                                <th>Club</th>
                                <th>Score</th>
                                <th>Submitted At</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                            $sn=1;
                            foreach ($userTests as $t): ?>
                            <tr>
                                <td><?= $sn++ ?></td>
                                <td><?= esc($t['test_type'] ?? '') ?></td>
                                <td><?= esc($t['test_date'] ?? '') ?></td>
                                <td><?= esc($t['club_name'] ?? 'Global') ?></td>
                                <td>
                                    <strong><?= (int)$t['score'] ?></strong>
                                    / <?= (int)$t['total_marks'] ?>
                                </td>
                                <td><?= esc($t['submitted_at'] ?? '') ?></td>
                                <td>
                                    <!-- open as floating browser window with detailed PDF-style view -->
                                   <a class="btn btn-ghost"
   href="<?= esc(BASE_URL) ?>/user_test_result.php?attempt_id=<?= (int)$t['id'] ?>"
   onclick="openPopup(this.href); return false;">
    View Result
</a>

<script>
function openPopup(url) {
    const w = 900;
    const h = 600;
    const left = (screen.width - w) / 2;
    const top = (screen.height - h) / 2;

    window.open(
        url,
        "resultWin",
        `width=${w},height=${h},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );
}
</script>


                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
