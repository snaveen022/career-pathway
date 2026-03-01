<?php
// attendee/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user($pdo);
$roll = $user['roll_no'] ?? $_SESSION['user_roll'] ?? '';

// ---------------------
// 1. HELPER FUNCTIONS
// ---------------------

function esc($v) { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

/**
 * Calculates Start Year, End Year, and Batch String based on Roll No pattern.
 */
function get_batch_details(string $roll) : ?array {
    $roll = trim($roll);
    if ($roll === '') return null;

    $startYear = null;
    $duration  = 4; 

    // PATTERN 1: 7 Digits (e.g. 2504030) -> 2 Years
    if (preg_match('/^(\d{2})\d{5}$/', $roll, $m)) {
        $yy = (int)$m[1];
        $startYear = 2000 + $yy;
        $duration  = 2;
    }
    // PATTERN 2: 2 Digits + 2 Letters + 3 Digits (e.g. 25AD123) -> 3 Years
    elseif (preg_match('/^(\d{2})[A-Za-z]{2}\d{3}$/', $roll, $m)) {
        $yy = (int)$m[1];
        $startYear = 2000 + $yy;
        $duration  = 3;
    }
    // FALLBACK
    elseif (preg_match('/^20(\d{2})/', $roll, $m)) {
        $startYear = 2000 + (int)$m[1];
        $duration = 4;
    }
    elseif (preg_match('/^(\d{2})/', $roll, $m)) {
        $startYear = 2000 + (int)$m[1];
        $duration = 4;
    }

    if ($startYear) {
        $endYear = $startYear + $duration;
        return [
            'start' => $startYear,
            'end'   => $endYear,
            'label' => $startYear . '-' . $endYear
        ];
    }
    return null;
}

function csv_to_array(string $raw) : array {
    if ($raw === '') return [];
    $parts = array_map('trim', explode(',', $raw));
    return array_values(array_filter($parts));
}

// ---------------------
// 2. CALCULATE USER BATCH
// ---------------------
$today = date('Y-m-d');
$userRoleNorm = strtolower($user['role'] ?? 'attendee');
$userRollNorm = strtolower($roll);

$batchDetails = get_batch_details($roll);
$userLabel = $batchDetails['label'] ?? null; 
$userStart = $batchDetails['start'] ?? null; 

// ---------------------
// 3. FETCH DATA
// ---------------------

// [A] Attempts
$stmt = $pdo->prepare("
    SELECT a.id, a.test_id, a.score, a.total_marks, a.submitted_at, t.test_type, t.test_date, COALESCE(c.name, 'Global') AS club_name
    FROM attempts a
    LEFT JOIN tests t ON t.id = a.test_id
    LEFT JOIN clubs c ON c.id = t.club_id
    WHERE a.user_roll = :r
    ORDER BY a.submitted_at DESC LIMIT 10
");
$stmt->execute([':r' => $roll]);
$attempts = $stmt->fetchAll();

// [B] Club Roles
$clubRolesStmt = $pdo->prepare("
    SELECT cr.club_id, cr.role, cr.can_post_questions, c.name AS club_name
    FROM club_roles cr
    JOIN clubs c ON c.id = cr.club_id
    WHERE cr.user_roll = :r
    ORDER BY c.name
");
$clubRolesStmt->execute([':r' => $roll]);
$clubRoles = $clubRolesStmt->fetchAll();
$userClubRoles = array_map('strtolower', array_column($clubRoles, 'role'));
$userClubIds   = array_map('intval', array_column($clubRoles, 'club_id'));

// [C] Alumni Meetings
$alumniClubId = null;
try {
    $alumniStmt = $pdo->query("SELECT id FROM clubs WHERE LOWER(name) IN ('alumni','alumini') LIMIT 1");
    if ($row = $alumniStmt->fetch()) { $alumniClubId = (int)$row['id']; }
} catch (Exception $e) {}

$upcomingMeetings = [];
if ($alumniClubId) {
    $meetSql = "
        SELECT title, meeting_date, meeting_time, mode, location, description, target_batches
        FROM alumni_meetings
        WHERE club_id = :cid AND meeting_date >= :today
        ORDER BY meeting_date ASC, meeting_time ASC
    ";
    $meetStmt = $pdo->prepare($meetSql);
    $meetStmt->execute([':cid' => $alumniClubId, ':today' => $today]);
    $allMeetings = $meetStmt->fetchAll();

    foreach ($allMeetings as $m) {
        $targetStr = trim($m['target_batches'] ?? '');
        
        if ($targetStr === '') {
            $upcomingMeetings[] = $m;
            continue;
        }

        if (!$userLabel) continue;

        $targets = csv_to_array($targetStr);
        $isMatch = false;

        foreach ($targets as $t) {
            if ($t === $userLabel) { $isMatch = true; break; }
            if (strpos($t, '-') === false && (int)$t === $userStart) { $isMatch = true; break; }
        }

        if ($isMatch) {
            $upcomingMeetings[] = $m;
        }

        if (count($upcomingMeetings) >= 5) break; 
    }
}

// [D] Internships (FIXED: Uses status='open')
$upcomingInterns = [];
try {
    $intSql = "
        SELECT title, company, location, last_date, apply_link, target_batches, stipend, type, mode, details
        FROM alumni_opportunities
        WHERE status = 'open' 
          AND (last_date IS NULL OR last_date >= :today)
        ORDER BY COALESCE(last_date, :today) ASC, id DESC
    ";
    $intStmt = $pdo->prepare($intSql);
    $intStmt->execute([':today' => $today]);
    $allInterns = $intStmt->fetchAll();

    foreach ($allInterns as $i) {
        $targetStr = trim($i['target_batches'] ?? '');
        
        // 1. If no target batch, show to everyone
        if ($targetStr === '') { 
            $upcomingInterns[] = $i; 
            continue; 
        }

        // 2. If user batch unknown, skip targeted
        if (!$userLabel) continue;

        // 3. Check for match
        $targets = csv_to_array($targetStr);
        $isMatch = false;
        foreach ($targets as $t) {
            // Strict String Match (e.g. '2025-2027')
            if ($t === $userLabel) { $isMatch = true; break; }
            // Start Year Match (e.g. '2025')
            if (strpos($t, '-') === false && (int)$t === $userStart) { $isMatch = true; break; }
        }
        
        if ($isMatch) $upcomingInterns[] = $i;
        if (count($upcomingInterns) >= 5) break;
    }
} catch (Exception $e) {
    $upcomingInterns = [];
}

// [E] Admin Messages
$adminMessages = [];
try {
    $msgStmt = $pdo->prepare("
        SELECT id, title, message_body, audience_type, audience_value, valid_from, valid_to, created_at
        FROM admin_messages
        WHERE is_active = 1
          AND (valid_from IS NULL OR valid_from <= :today)
          AND (valid_to   IS NULL OR valid_to   >= :today)
        ORDER BY created_at DESC LIMIT 100
    ");
    $msgStmt->execute([':today' => $today]);
    $rows = $msgStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $m) {
        $type = strtolower(trim($m['audience_type'] ?? 'all'));
        $raw  = trim((string)($m['audience_value'] ?? ''));
        $vals = csv_to_array($raw);
        $valsLower = array_map('strtolower', $vals);
        $matched = false;

        if ($type === 'all') {
            $matched = true;
        } else if (!empty($vals)) {
            switch ($type) {
                case 'batch':
                    if ($userLabel) {
                        foreach ($vals as $t) {
                            if ($t === $userLabel) { $matched = true; break; }
                            if (strpos($t, '-') === false && (int)$t === $userStart) { $matched = true; break; }
                        }
                    }
                    break;
                case 'role':
                    if (in_array($userRoleNorm, $valsLower, true)) $matched = true;
                    break;
                case 'club_role':
                    foreach ($valsLower as $v) {
                        if (in_array($v, $userClubRoles, true)) { $matched = true; break; }
                    }
                    break;
                case 'club':
                    foreach ($vals as $v) {
                        if (ctype_digit($v)) {
                            if (in_array((int)$v, $userClubIds, true)) { $matched = true; break; }
                        } else {
                            $cNames = array_map('strtolower', array_column($clubRoles, 'club_name'));
                            if (in_array(strtolower($v), $cNames, true)) { $matched = true; break; }
                        }
                    }
                    break;
                case 'roll_no':
                    if (in_array($userRollNorm, array_map('trim', $valsLower), true)) $matched = true;
                    break;
            }
        }
        if ($matched) $adminMessages[] = $m;
    }
} catch (Exception $e) {
    $adminMessages = [];
}

// [F] Monthly Tests
$assignedMonthlyTests = [];
try {
    $mtStmt = $pdo->prepare("
        SELECT t.id, t.title, t.test_date, t.club_id
        FROM monthly_test_users mtu
        JOIN tests t ON t.id = mtu.test_id
        WHERE mtu.user_roll = :r AND t.test_type = 'monthly'
        ORDER BY t.test_date DESC, t.id DESC
    ");
    $mtStmt->execute([':r' => $roll]);
    $assignedMonthlyTests = $mtStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $assignedMonthlyTests = [];
}
$attemptedMonthly = [];
if (!empty($assignedMonthlyTests)) {
    $testIds = array_map(function($x){ return (int)$x['id']; }, $assignedMonthlyTests);
    $placeholders = implode(',', array_fill(0, count($testIds), '?'));
    if (!empty($placeholders)) {
        $sql = "SELECT DISTINCT test_id FROM attempts WHERE user_roll = ? AND test_id IN ($placeholders)";
        $params = array_merge([$roll], $testIds);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $attemptedMonthly = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0));
    }
    $assignedMonthlyTests = array_filter($assignedMonthlyTests, function($mt) use ($attemptedMonthly) {
        return !in_array((int)$mt['id'], $attemptedMonthly, true);
    });
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard — Attendee</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <link rel="stylesheet" href="/public/css/dashboard.css">
    <style>
        .club-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .club-card { background:#fff; border:1px solid #e6eefb; border-radius:12px; padding:14px; box-shadow:0 6px 18px rgba(37,99,235,0.04); }
        .card ul { list-style:none; padding:0; margin:0; }
        .card li { padding:12px 0; border-bottom:1px solid #f1f5f9; }
        .card li:last-child { border-bottom: none; }

        /* Badge Styles */
        .badge { display:inline-block; padding:2px 6px; border-radius:4px; font-size:0.7rem; font-weight:600; margin-right:4px; vertical-align:middle; }
        .badge-type { background:#eef2ff; color:#4338ca; }
        .badge-mode { background:#f1f5f9; color:#0f172a; }
        .badge-batch { background:#f3e8ff; color:#6b21a8; border:1px solid #e9d5ff; }
        .badge-money { background:#ecfccb; color:#365314; border:1px solid #d9f99d; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
        <h2>Welcome, <?= esc($user['full_name'] ?? $roll) ?></h2>
        <div style="display:flex; gap:8px; align-items:center;">
            <?php if (is_admin($pdo)): ?>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/admin/dashboard.php">Admin Dashboard</a>
            <?php endif; ?>

            <?php if (!empty($clubRoles) ): ?>
                <?php $firstClubId = (int)$clubRoles[0]['club_id']; ?>
                <a class="btn btn-primary" href="<?= e(BASE_URL) ?>/club/dashboard.php?club_id=<?= $firstClubId ?>">Club Dashboard</a>
            <?php endif; ?>
        </div>
    </div>

    <h3>Take a Test</h3>
    <div class="cards" style="margin-top:1rem;">
        <div class="card">
            <h3>Daily Test</h3>
            <p>Pick a date up to today.</p>
            <a href="<?= e(BASE_URL) ?>/attendee/daily_test.php" style="display:inline-block; margin-top:8px; color:#2563eb; font-weight:600;">Open</a>
        </div>
        <div class="card">
            <h3>Weekly Test</h3>
            <p>Pick a Saturday.</p>
            <a href="<?= e(BASE_URL) ?>/attendee/weekly_test.php" style="display:inline-block; margin-top:8px; color:#2563eb; font-weight:600;">Open</a>
        </div>
        <?php if (!empty($assignedMonthlyTests)): ?>
            <?php foreach ($assignedMonthlyTests as $mt): ?>
                <div class="card">
                    <h3>Monthly Test</h3>
                    <p>Attend Monthly Test</p>
                    <a href="<?= esc(BASE_URL) ?>/attendee/take_test.php?test_type=monthly&test_date=<?= esc($mt['test_date']) ?>">Open</a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <br><br>
    
    <div class="cards" style="margin-top:1.5rem; display:grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap:1.5rem;">
        
        <div class="card" style="justify-content: start;">
            <h3 style="margin-bottom:10px;">Admin Messages</h3>
            <?php if (empty($adminMessages)): ?>
                <p style="color:#9ca3af; font-size:0.9rem;">No messages right now.</p>
            <?php else: ?>
                <ul style="max-height:300px; overflow-y:auto;">
                    <?php foreach ($adminMessages as $msg): ?>
                        <li>
                            <div style="font-weight:700; font-size:0.95rem;"><?= esc($msg['title']) ?></div>
                            <div style="font-size:0.9rem; color:#374151; margin-top:4px; white-space:pre-wrap;"><?= esc($msg['message_body']) ?></div>
                            <div style="font-size:0.75rem; color:#9ca3af; margin-top:4px;">Posted: <?= esc($msg['created_at']) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card" style="justify-content: start;">
            <h3 style="margin-bottom:10px;">Internships</h3>
            <?php if (empty($upcomingInterns)): ?>
                <p style="color:#9ca3af; font-size:0.9rem;">No active opportunities.</p>
            <?php else: ?>
                <ul style="max-height:300px; overflow-y:auto;">
                    <?php foreach ($upcomingInterns as $o): ?>
                        <li>
                            <div style="font-weight:600;"><?= esc($o['title']) ?></div>
                            
                            <div style="font-size:0.85rem; color:#6b7280; margin-bottom:4px;">
                                <?= esc($o['company'] ?? '') ?>
                                <?php if (!empty($o['location'])): ?> • <?= esc($o['location']) ?> <?php endif; ?>
                            </div>

                            <div style="margin-bottom:6px;">
                                <span class="badge badge-type"><?= esc(ucfirst($o['type'] ?? 'internship')) ?></span>
                                <span class="badge badge-mode"><?= esc(ucfirst($o['mode'] ?? 'online')) ?></span>
                                <?php if (!empty($o['stipend'])): ?>
                                    <span class="badge badge-money">💰 <?= esc($o['stipend']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($o['target_batches'])): ?>
                                    <span class="badge badge-batch">Batch: <?= esc($o['target_batches']) ?></span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($o['last_date'])): ?>
                                <div style="font-size:0.8rem; color:#d97706; margin-bottom:4px;">
                                    <strong>Last Date:</strong> <?= esc($o['last_date']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($o['apply_link'])): ?>
                                <div style="margin-top:4px;">
                                    <a href="<?= esc($o['apply_link']) ?>" target="_blank" style="font-size:0.85rem; color:#2563eb; font-weight:600;">View / Apply →</a>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="card" style="justify-content: start;">
            <h3 style="margin-bottom:10px;">Alumni Meetings</h3>
            <?php if (empty($upcomingMeetings)): ?>
                <p style="color:#9ca3af; font-size:0.9rem;">No upcoming meetings.</p>
            <?php else: ?>
                <ul style="max-height:300px; overflow-y:auto;">
                    <?php foreach ($upcomingMeetings as $m): ?>
                        <li>
                            <div style="font-weight:700; font-size:1rem; color:#111827;">
                                <?= esc($m['title']) ?>
                            </div>
                            
                            <div style="font-size:0.85rem; color:#4b5563; margin-top:4px; display:flex; gap:10px; align-items:center;">
                                <span style="font-weight:500;">📅 <?= esc($m['meeting_date']) ?></span>
                                <?php if (!empty($m['meeting_time'])): ?>
                                    <span>⏰ <?= esc(substr($m['meeting_time'], 0, 5)) ?></span>
                                <?php endif; ?>
                            </div>

                            <div style="font-size:0.85rem; color:#4b5563; margin-top:2px;">
                                <span style="font-weight:600;">📍 <?= esc(ucfirst($m['mode'] ?? 'online')) ?></span>
                                <?php if (!empty($m['location'])): ?>
                                    : <?= esc($m['location']) ?>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($m['description'])): ?>
                                <div style="font-size:0.9rem; color:#374151; margin-top:8px; white-space: ; background:#f9fafb; padding:8px; border-radius:6px; border:1px solid #e5e7eb;">
                                    <?= esc($m['description']) ?>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div>
    <hr style="margin:2rem 0; border:0; border-bottom:1px solid #e6eefb;">

    <h3>Your recent attempts</h3>
    <?php if (empty($attempts)): ?>
        <p style="color:#64748b; margin-top:10px;">No attempts yet.</p>
    <?php else: ?>
        <div style="margin-top:1rem; overflow-x:auto; background:#fff; border:1px solid #eef2ff; border-radius:8px; padding:8px;">
            <table style="width:100%; border-collapse:collapse; min-width:640px;">
                <thead>
                    <tr style="text-align:left; color:#64748b; font-weight:700;">
                        <th style="padding:10px;">Type</th>
                        <th style="padding:10px;">Date</th>
                        <th style="padding:10px;">Score</th>
                        <th style="padding:10px;">When</th>
                        <th style="padding:10px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attempts as $a): 
                        $tdate = rawurlencode($a['test_date'] ?? '');
                        $ttype = rawurlencode($a['test_type'] ?? '');
                    ?>
                        <tr style="border-bottom:1px solid #f8fafc;">
                            <td style="padding:10px;"><?= esc($a['test_type'] ?? '') ?></td>
                            <td style="padding:10px;"><?= esc($a['test_date'] ?? '') ?></td>
                            <td style="padding:10px;">
                                <strong style="color:#2563eb;"><?= (int)$a['score'] ?></strong>
                                <span style="color:#64748b;">/ <?= (int)$a['total_marks'] ?></span>
                            </td>
                            <td style="padding:10px;"><?= esc($a['submitted_at']) ?></td>
                            <td style="padding:10px;">
                                <a href="<?= esc(BASE_URL) ?>/attendee/view_test_by_date.php?test_date=<?= $tdate ?>&test_type=<?= $ttype ?>"
                                   class="btn" style="display:inline-block;padding:6px 8px;border-radius:8px;background:#eef2ff;color:#1e40af;text-decoration:none;font-weight:700;">
                                    View Answers
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
    <?php include '../chat.php'; ?>
</body>
</html>