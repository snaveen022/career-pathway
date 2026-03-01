<?php
// admin/login_logs.php
require_once __DIR__ . '/../../config/config.php';
require_admin($pdo);

$errors = [];
$success = null;

// helpers
function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// --- Handle POST actions: export CSV or cleanup ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token.';
    } else {
        // Export CSV (POST to avoid url length limits)
        if (isset($_POST['action']) && $_POST['action'] === 'export') {
            // reuse filter params from POST (safer)
            $roll = trim($_POST['filter_roll'] ?? '');
            $successFilter = isset($_POST['filter_success']) && $_POST['filter_success'] !== '' ? $_POST['filter_success'] : null;
            $from = trim($_POST['filter_from'] ?? '');
            $to   = trim($_POST['filter_to'] ?? '');

            // build where + params
            $where = [];
            $params = [];

            if ($roll !== '') {
                $where[] = 'roll_no LIKE :roll';
                $params[':roll'] = '%' . $roll . '%';
            }
            if ($successFilter !== null && in_array($successFilter, ['0','1'], true)) {
                $where[] = 'success = :succ';
                $params[':succ'] = (int)$successFilter;
            }
            if ($from !== '') {
                $where[] = 'created_at >= :from';
                $params[':from'] = $from . ' 00:00:00';
            }
            if ($to !== '') {
                $where[] = 'created_at <= :to';
                $params[':to'] = $to . ' 23:59:59';
            }

            $sql = 'SELECT id, roll_no, ip_address, user_agent, success, message, created_at FROM login_logs';
            if (!empty($where)) $sql .= ' WHERE ' . implode(' AND ', $where);
            $sql .= ' ORDER BY created_at DESC';

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // stream CSV to browser
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="login_logs_' . date('Ymd_His') . '.csv"');
            $out = fopen('php://output', 'w');
            // BOM for Excel (optional)
            echo "\xEF\xBB\xBF";
            fputcsv($out, ['id','roll_no','ip_address','user_agent','success','message','created_at']);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, [
                    $row['id'],
                    $row['roll_no'],
                    $row['ip_address'],
                    $row['user_agent'],
                    $row['success'],
                    $row['message'],
                    $row['created_at']
                ]);
            }
            fclose($out);
            exit;
        }

        // Cleanup: delete logs older than N days
        if (isset($_POST['action']) && $_POST['action'] === 'cleanup') {
            $days = (int)($_POST['days'] ?? 90);
            if ($days <= 0) $days = 90;
            try {
                $cutoff = (new DateTime("-{$days} days"))->format('Y-m-d H:i:s');
                $del = $pdo->prepare('DELETE FROM login_logs WHERE created_at < :cutoff');
                $del->execute([':cutoff' => $cutoff]);
                $success = "Logs older than {$days} days removed.";
            } catch (Exception $e) {
                $errors[] = 'Cleanup failed: ' . $e->getMessage();
            }
        }

        // Delete single log entry
        if (isset($_POST['action']) && $_POST['action'] === 'delete_single' && !empty($_POST['log_id'])) {
            $id = (int)$_POST['log_id'];
            try {
                $pdo->prepare('DELETE FROM login_logs WHERE id = :id')->execute([':id' => $id]);
                $success = "Log entry #{$id} deleted.";
            } catch (Exception $e) {
                $errors[] = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

// --- Filters & pagination (GET) ---
$filter_roll = trim($_GET['filter_roll'] ?? '');
$filter_success = isset($_GET['filter_success']) ? $_GET['filter_success'] : '';
$filter_from = trim($_GET['filter_from'] ?? '');
$filter_to   = trim($_GET['filter_to'] ?? '');

$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build where clause
$where = [];
$params = [];

if ($filter_roll !== '') {
    $where[] = 'roll_no LIKE :roll';
    $params[':roll'] = '%' . $filter_roll . '%';
}
if ($filter_success !== '' && in_array($filter_success, ['0','1'], true)) {
    $where[] = 'success = :succ';
    $params[':succ'] = (int)$filter_success;
}
if ($filter_from !== '') {
    $where[] = 'created_at >= :from';
    $params[':from'] = $filter_from . ' 00:00:00';
}
if ($filter_to !== '') {
    $where[] = 'created_at <= :to';
    $params[':to'] = $filter_to . ' 23:59:59';
}

$whereSql = '';
if (!empty($where)) $whereSql = 'WHERE ' . implode(' AND ', $where);

// total count
$countSql = "SELECT COUNT(*) FROM login_logs {$whereSql}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));

// fetch page
$dataSql = "SELECT id, roll_no, ip_address, user_agent, success, message, created_at
            FROM login_logs
            {$whereSql}
            ORDER BY created_at ASC
            LIMIT :lim OFFSET :off";
$stmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v);
$stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSRF
$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Login Logs — Admin</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap { max-width:1100px; margin:20px auto; padding:12px; }
        .filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px; }
        .filters input, .filters select { padding:8px; border-radius:8px; border:1px solid #e6eef8; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid #eef2ff; border-radius:8px; overflow:hidden; }
        th, td { padding:8px 10px; border-bottom:1px solid #eef6ff; font-size:0.9rem; text-align:left; vertical-align:top; }
        th { background:#fbfdff; font-weight:700; color:#334155; }
        .muted { color:#64748b; font-size:0.9rem; }
        .btn { padding:8px 10px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
        .btn-primary { background:linear-gradient(90deg,#2563eb,#1d4ed8); color:#fff; }
        .btn-ghost { background:transparent; border:1px solid #cfe3ff; color:#2563eb; }
        .btn-danger { background:#ef4444; color:#fff; }
        .pager { display:flex; gap:6px; align-items:center; margin-top:12px; }
        .small-note { font-size:0.85rem; color:#6b7280; }
        pre.ua { max-width: 420px; white-space: nowrap; overflow-x: auto; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
        <h2>Login Attempts Log</h2>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= e(BASE_URL) ?>/../reports.php">← Back to Reports</a>
    </div>
    
    <br>
    <p class="muted">Monitor recent login attempts. Use filters to narrow results, export CSV for offline analysis.</p>

    <?php if ($errors): ?>
        <div style="color:#b91c1c; margin:8px 0;">
            <?php foreach ($errors as $e) echo '<div>' . esc($e) . '</div>'; ?>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div style="color:#065f46; margin:8px 0;"><?= esc($success) ?></div>
    <?php endif; ?>

    <form method="get" class="filters" style="align-items:center;">
        <input type="text" name="filter_roll" placeholder="Roll no" value="<?= esc($filter_roll) ?>">
        <select name="filter_success">
            <option value="">All</option>
            <option value="1" <?= $filter_success === '1' ? 'selected' : '' ?>>Success</option>
            <option value="0" <?= $filter_success === '0' ? 'selected' : '' ?>>Failed</option>
        </select>
        <label class="small-note">From <input type="date" name="filter_from" value="<?= esc($filter_from) ?>"></label>
        <label class="small-note">To <input type="date" name="filter_to" value="<?= esc($filter_to) ?>"></label>

        <button type="submit" class="btn btn-primary">Apply</button>

        <a href="login_logs.php" class="btn btn-ghost">Reset</a>
    </form>

    <!-- Export & Cleanup -->
    <div style="display:flex; gap:8px; margin:12px 0; align-items:center;">
        <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
            <input type="hidden" name="action" value="export">
            <!-- pass current filters to POST for export -->
            <input type="hidden" name="filter_roll" value="<?= esc($filter_roll) ?>">
            <input type="hidden" name="filter_success" value="<?= esc($filter_success) ?>">
            <input type="hidden" name="filter_from" value="<?= esc($filter_from) ?>">
            <input type="hidden" name="filter_to" value="<?= esc($filter_to) ?>">
            <button type="submit" class="btn btn-primary">Export CSV</button>
        </form>

        <form method="post" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
            <input type="hidden" name="action" value="cleanup">
            <label class="small-note">Delete logs older than
                <input type="number" name="days" value="90" min="1" style="width:80px; padding:6px; margin-left:6px;">
                days
            </label>
            <button type="submit" class="btn btn-danger" style="margin-left:8px;" onclick="return confirm('Delete old logs? This cannot be undone.')">Cleanup</button>
        </form>
    </div>

    <!-- Logs table -->
    <div style="overflow-x:auto;">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Roll No</th>
                <th>IP</th>
                <th>User Agent</th>
                <th>Success</th>
                <th>Message</th>
                <th>When</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="muted">No log entries found.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= esc($r['id']) ?></td>
                        <td><?= esc($r['roll_no']) ?></td>
                        <td><?= esc($r['ip_address']) ?></td>
                        <td><pre class="ua"><?= esc($r['user_agent']) ?></pre></td>
                        <td><?= (int)$r['success'] === 1 ? '<span style="color:#065f46;font-weight:700;">Yes</span>' : '<span style="color:#b91c1c;font-weight:700;">No</span>' ?></td>
                        <td><?= esc($r['message']) ?></td>
                        <td><?= esc($r['created_at']) ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                <input type="hidden" name="action" value="delete_single">
                                <input type="hidden" name="log_id" value="<?= esc($r['id']) ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this log entry?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <div class="pager">
        <div class="muted">Showing <?= min($total, $offset+1) ?>–<?= min($total, $offset + count($rows)) ?> of <?= $total ?> </div>
        <div style="margin-left:auto;">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="?<?= http_build_query(array_merge($_GET, ['p'=> $page-1])) ?>">← Prev</a>
            <?php endif; ?>
            <span class="small-note" style="margin:0 8px;">Page <?= $page ?> / <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost" href="?<?= http_build_query(array_merge($_GET, ['p'=> $page+1])) ?>">Next →</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
