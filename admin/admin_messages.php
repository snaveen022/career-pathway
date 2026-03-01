<?php
// admin/admin_messages.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/phpmailer_config.php';
require_admin($pdo);

$errors = [];
$success = null;

// helpers
function esc($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Normalize comma-separated input
 */
function normalize_list(string $s): array {
    $parts = array_filter(array_map('trim', explode(',', $s)), fn($x) => $x !== '');
    return array_values($parts);
}

/**
 * Resolve target emails based on audience
 */
function resolve_message_target_emails(PDO $pdo, string $type, ?string $value): array
{
    if ($type === 'all') {
        return $pdo->query("
            SELECT email FROM users WHERE email IS NOT NULL
        ")->fetchAll(PDO::FETCH_COLUMN);
    }

    $vals = $value ? array_map('trim', explode(',', $value)) : [];
    if (!$vals) return [];

    $in = implode(',', array_fill(0, count($vals), '?'));

    if ($type === 'batch') {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE batch IN ($in) AND email IS NOT NULL");
        $stmt->execute($vals);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($type === 'role') {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE role IN ($in) AND email IS NOT NULL");
        $stmt->execute($vals);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($type === 'club_role') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.email
            FROM users u
            JOIN club_members cm ON cm.user_id = u.id
            WHERE cm.role IN ($in) AND u.email IS NOT NULL
        ");
        $stmt->execute($vals);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($type === 'club') {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.email
            FROM users u
            JOIN club_members cm ON cm.user_id = u.id
            WHERE cm.club_id IN ($in) AND u.email IS NOT NULL
        ");
        $stmt->execute($vals);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if ($type === 'roll') {
        $stmt = $pdo->prepare("SELECT email FROM users WHERE roll_no IN ($in) AND email IS NOT NULL");
        $stmt->execute($vals);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    return [];
}

/* =====================================================
   HANDLE POST
===================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {

        /* ---------- CREATE MESSAGE ---------- */
        if (isset($_POST['create_message'])) {

            $title = trim($_POST['title'] ?? '');
            $body  = trim($_POST['message_body'] ?? '');
            $audience_type  = $_POST['audience_type'] ?? 'all';
            $audience_value = trim($_POST['audience_value'] ?? '');
            $valid_from = $_POST['valid_from'] ?: null;
            $valid_to   = $_POST['valid_to'] ?: null;
            $is_active  = isset($_POST['is_active']) ? 1 : 0;
            $created_by = $_SESSION['user_roll'] ?? null;

            if ($title === '' || $body === '') {
                $errors[] = 'Title and message are required.';
            } else {
                try {
                    $normalized = null;
                    if ($audience_type !== 'all') {
                        $normalized = implode(',', normalize_list($audience_value));
                    }

                    // Insert message
                    $stmt = $pdo->prepare("
                        INSERT INTO admin_messages
                        (title, message_body, audience_type, audience_value, valid_from, valid_to, is_active, created_by)
                        VALUES (:t,:b,:at,:av,:vf,:vt,:ia,:cb)
                    ");
                    $stmt->execute([
                        ':t' => $title,
                        ':b' => $body,
                        ':at'=> $audience_type,
                        ':av'=> $normalized,
                        ':vf'=> $valid_from,
                        ':vt'=> $valid_to,
                        ':ia'=> $is_active,
                        ':cb'=> $created_by
                    ]);

                    /* 🔔 SEND EMAIL TO TARGET USERS */
                    $emails = resolve_message_target_emails(
                        $pdo,
                        $audience_type,
                        $normalized
                    );

                    $emailBody  = "<h3>" . esc($title) . "</h3>";
                    $emailBody .= "<p>" . nl2br(esc($body)) . "</p>";
                    $emailBody .= "<p><small>— Career Pathway Admin</small></p>";

                    foreach ($emails as $em) {
                        sendMail($em, $title, $emailBody);
                    }

                    $success = 'Message created and email sent to target users.';

                } catch (Exception $e) {
                    $errors[] = 'Error: ' . $e->getMessage();
                }
            }
        }

        /* ---------- TOGGLE ACTIVE ---------- */
        if (isset($_POST['toggle_active'], $_POST['id'])) {
            $pdo->prepare("UPDATE admin_messages SET is_active = 1 - is_active WHERE id = :id")
                ->execute([':id' => (int)$_POST['id']]);
            $success = 'Message status updated.';
        }

        /* ---------- DELETE ---------- */
        if (isset($_POST['delete'], $_POST['id'])) {
            $pdo->prepare("DELETE FROM admin_messages WHERE id = :id")
                ->execute([':id' => (int)$_POST['id']]);
            $success = 'Message deleted.';
        }
    }
}

/* =====================================================
   FETCH MESSAGES
===================================================== */
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$total = (int)$pdo->query("SELECT COUNT(*) FROM admin_messages")->fetchColumn();

$stmt = $pdo->prepare("
    SELECT * FROM admin_messages
    ORDER BY created_at DESC
    LIMIT :l OFFSET :o
");
$stmt->bindValue(':l', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':o', $offset, PDO::PARAM_INT);
$stmt->execute();
$messages = $stmt->fetchAll();

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admin — Messages</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap { max-width:1100px; margin:18px auto; padding:0 12px; }
        .grid { display:grid; grid-template-columns: 1fr ; gap:18px; align-items:start; }
        .card { background:#fff;border:1px solid #e8eefb;border-radius:10px;padding:14px; }
        .muted { color:#64748b; font-size:0.95rem; }
        label { display:block; font-weight:600; margin-bottom:6px; }
        input[type="text"], input[type="date"], textarea, select { width:100%; padding:8px 10px; border-radius:8px; border:1px solid #e6eefb; box-sizing:border-box; }
        textarea { min-height:120px; resize:vertical; }
        .btn { padding:8px 12px; border-radius:8px; border:0; cursor:pointer; font-weight:700; }
        .btn-primary { background:linear-gradient(90deg,#2563eb,#1d4ed8); color:#fff; }
        .btn-ghost { background:transparent; border:1px solid #cbd5f5; color:#2563eb; }
        table { width:100%; border-collapse:collapse; margin-top:12px; font-size:0.95rem; }
        th, td { padding:8px 6px; border-bottom:1px solid #eef2ff; text-align:left; vertical-align:top; }
        th { background:#f9fafb; font-weight:700; color:#475569; }
        .small { font-size:0.85rem; color:#6b7280; }
        .aud-badge { display:inline-block; padding:4px 8px; border-radius:999px; background:#f1f5f9; color:#0f172a; font-weight:700; margin-right:6px; font-size:0.8rem; }
        .meta { color:#9ca3af; font-size:0.85rem; }
        .hint { font-size:0.85rem; color:#6b7280; margin-top:6px; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
        <h2>Admin Messages</h2>
        <a class="btn btn-ghost" href="<?= e(BASE_URL) ?>/admin/dashboard.php">← Back to Admin</a>
    </div>

    <?php if ($errors): ?>
        <div style="background:#fee2e2;padding:10px;border-radius:8px;margin:10px 0;color:#b91c1c;">
            <?php foreach ($errors as $er) echo '<div>' . esc($er) . '</div>'; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div style="background:#dcfce7;padding:10px;border-radius:8px;margin:10px 0;color:#065f46;font-weight:600;">
            <?= esc($success) ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <!-- left: messages list -->
        <div>
            <div class="card">
                <h3 style="margin-top:0;">Existing Messages (total <?= $total ?>)</h3>
                <?php if (empty($messages)): ?>
                    <p class="muted">No messages yet.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width:40%;">Title & Audience</th>
                                <th style="width:35%;">Preview / Validity</th>
                                <th style="width:25%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($messages as $m): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:700;"><?= esc($m['title']) ?></div>
                                        <div class="small" style="margin-top:6px;">
                                            <span class="aud-badge"><?= esc($m['audience_type']) ?></span>
                                            <?php if ($m['audience_value']): ?>
                                                <span class="small">→ <?= esc($m['audience_value']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="meta" style="margin-top:6px;">Posted by <?= esc($m['created_by'] ?? '-') ?> on <?= esc($m['created_at']) ?></div>
                                    </td>
                                    <td>
                                        <div style="max-height:80px; overflow:auto; white-space:pre-wrap;"><?= esc(substr($m['message_body'],0,400)) ?><?= strlen($m['message_body'])>400 ? '...' : '' ?></div>
                                        <div class="small" style="margin-top:8px;">
                                            Valid: <?= esc($m['valid_from'] ?? '—') ?> to <?= esc($m['valid_to'] ?? '—') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <form method="post" style="display:inline-block;">
                                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                            <button type="submit" name="toggle_active" class="btn" style="background:<?= $m['is_active'] ? '#ef4444' : '#10b981' ?>; color:#fff;border-radius:8px;">
                                                <?= $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>

                                        <form method="post" style="display:inline-block;margin-left:6px;" onsubmit="return confirm('Delete this message?');">
                                            <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">
                                            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                            <button type="submit" name="delete" class="btn btn-ghost" style="margin-top:6px;">Delete</button>
                                        </form>

                                        <!-- <div style="margin-top:8px;">
                                            <a href="#" onclick="alert(<?= json_encode($m['message_body']) ?>); return false;" class="small">View full</a>
                                        </div> -->
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- simple pager -->
                    <?php if ($total > $perPage):
                        $last = (int)ceil($total / $perPage);
                    ?>
                        <div style="margin-top:12px; display:flex; gap:8px; flex-wrap:wrap;">
                            <?php if ($page > 1): ?>
                                <a class="btn btn-ghost" href="?page=<?= $page-1 ?>">Prev</a>
                            <?php endif; ?>
                            <span class="small" style="align-self:center;">Page <?= $page ?> of <?= $last ?></span>
                            <?php if ($page < $last): ?>
                                <a class="btn btn-ghost" href="?page=<?= $page+1 ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- right: create new -->
        <div>
            <div class="card">
                <h3 style="margin-top:0;">Create Message</h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= esc($csrf) ?>">

                    <label for="title">Title</label>
                    <input id="title" name="title" type="text" required>

                    <label for="message_body" style="margin-top:8px;">Message</label>
                    <textarea id="message_body" name="message_body" required></textarea>

                    <label for="audience_type" style="margin-top:8px;">Audience Type</label>
                    <select id="audience_type" name="audience_type" onchange="toggleAudienceValue(this.value)">
                        <option value="all">All users (everyone)</option>
                        <option value="batch">Batch (e.g. 2025-2027)</option>
                        <option value="role">User role (e.g. admin, attendee)</option>
                        <option value="club_role">Club role (e.g. club_secretary)</option>
                        <option value="club">Club (by name or id)</option>
                        <option value="roll">Specific roll number(s) (single or group)</option>
                    </select>

                    <div id="audience_value_wrap" style="margin-top:8px; display:none;">
                        <label for="audience_value">Audience value</label>
                        <input id="audience_value" name="audience_value" type="text" placeholder="Enter comma-separated values">
                        <div class="hint">
                            Hints / examples:
                            <ul style="margin:6px 0 0 18px;">
                                <li><strong>All users</strong>: pick 'All users' — leave value blank.</li>
                                <li><strong>Batch</strong>: <code>2025-2027</code> or <code>2025-2027,2026-2028</code>.</li>
                                <li><strong>User role</strong>: <code>attendee</code> or <code>admin</code> (comma-separated allowed).</li>
                                <li><strong>Club role</strong>: <code>club_secretary</code>, <code>club_joint_secretary</code>, <code>club_member</code>.</li>
                                <li><strong>Club</strong>: prefer IDs (<code>3</code>) or names (<code>alumni</code>), comma-separated.</li>
                                <li><strong>Roll</strong>: single or group like <code>22aa552</code> or <code>22aa552,2504022</code>.</li>
                            </ul>
                        </div>
                    </div>

                    <label style="margin-top:8px;">Valid From</label>
                    <input type="date" name="valid_from">

                    <label style="margin-top:8px;">Valid To</label>
                    <input type="date" name="valid_to">

                    <label style="margin-top:8px; display:flex; align-items:center; gap:10px;">
                        <input type="checkbox" name="is_active" value="1" checked>
                        Active
                    </label>

                    <div style="margin-top:10px;">
                        <button type="submit" name="create_message" class="btn btn-primary">Create Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
function toggleAudienceValue(val){
    var wrap = document.getElementById('audience_value_wrap');
    if(val === 'all'){
        wrap.style.display = 'none';
    } else {
        wrap.style.display = 'block';
    }
}
window.addEventListener('DOMContentLoaded', function(){
    var sel = document.getElementById('audience_type');
    toggleAudienceValue(sel.value);
});
</script>

</body>
</html>
