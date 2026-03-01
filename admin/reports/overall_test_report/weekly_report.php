<?php
// admin/weekly_report.php
require_once __DIR__ . '/../../../config/config.php';
require_admin($pdo);

function esc($v){ return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// fetch attempts for types other than daily (weekly, etc.)
$sql = "
    SELECT
        at.attempt_id,
        a.user_roll,
        u.full_name,
        u.class,
        t.test_type,
        t.test_date,
        at.test_id AS mapped_test_id,
        at.score,
        at.total_marks
    FROM attempts_tests at
    JOIN attempts a ON a.id = at.attempt_id
    JOIN users u ON u.roll_no = a.user_roll
    JOIN tests t ON t.id = at.test_id
    WHERE LOWER(t.test_type) = 'weekly'
    ORDER BY t.test_type, t.test_date DESC, u.full_name
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    $sql2 = "
        SELECT
            a.id AS attempt_id,
            a.user_roll,
            u.full_name,
            u.class,
            t.test_type,
            t.test_date,
            t.id AS mapped_test_id,
            a.score,
            a.total_marks
        FROM attempts a
        JOIN users u ON u.roll_no = a.user_roll
        JOIN tests t ON t.id = a.test_id
        WHERE LOWER(t.test_type) = 'weekly'
        ORDER BY t.test_type, t.test_date DESC, u.full_name
    ";
    $rows = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
}

// group by type|date
$weeklyTmp = [];
foreach ($rows as $r) {
    $type = $r['test_type'] ?? 'weekly';
    $date = $r['test_date'] ?? '';
    $key  = $type . '|' . $date;
    $roll = (string)$r['user_roll'];
    $name = $r['full_name'] ?? '';
    $cls  = $r['class'] ?? '';
    $score = isset($r['score']) ? (int)$r['score'] : 0;
    $total = isset($r['total_marks']) ? (int)$r['total_marks'] : 0;

    if (!isset($weeklyTmp[$key])) $weeklyTmp[$key] = [];
    if (!isset($weeklyTmp[$key][$roll])) {
        $weeklyTmp[$key][$roll] = ['roll_no'=>$roll,'full_name'=>$name,'class'=>$cls,'score_sum'=>0,'total_sum'=>0];
    }
    $weeklyTmp[$key][$roll]['score_sum'] += $score;
    $weeklyTmp[$key][$roll]['total_sum'] += $total;
}

$weeklyGrouped = [];
foreach ($weeklyTmp as $key => $byRoll) {
    $parts = explode('|', $key, 2);
    $typeLabel = ucfirst($parts[0] ?? 'weekly');
    $dateLabel = $parts[1] ?? '';
    $label = $typeLabel . ' • ' . $dateLabel;
    $items = array_values($byRoll);
    usort($items, function($a,$b){ return strcasecmp($a['roll_no'],$b['roll_no']); });
    $weeklyGrouped[$key] = ['label'=>$label,'items'=>$items];
}
uksort($weeklyGrouped, function($a,$b){
    $da = explode('|',$a,2)[1] ?? '';
    $db = explode('|',$b,2)[1] ?? '';
    return strcmp($db,$da);
});
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Weekly Test Report</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .btn { padding:8px 12px;border-radius:8px;text-decoration:none;font-weight:600; }
        .btn-primary { background:#2563eb;color:#fff; }
        .muted { color:#64748b; }
        @media print { body * { visibility:hidden } #report, #report * { visibility:visible } #report { position:absolute; left:0; top:0; } }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../../includes/header.php'; ?>

<main class="container">
    <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
        <h2>Weekly Test Report</h2>
         <div style="display:flex;gap:8px;">
              <button class="btn btn-primary" onclick="printReport()">Print</button>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= esc(BASE_URL) ?>/../overall_test_reports.php">← Back to Overall Report</a>
    </div>
         </div>
    <hr>
    

    <div id="report"><br>
        <?php if (empty($weeklyGrouped)): ?>
            <div class="muted">No weekly Test Attempts.</div>
        <?php else: ?>
            <?php foreach ($weeklyGrouped as $key => $grp): ?>
                <div style="margin-bottom:12px;">
                    <div style="font-weight:700;margin-bottom:6px;"><?= esc($grp['label']) ?></div>
                    <div style="background:#fff;border:1px solid #eef2ff;border-radius:8px;padding:8px;overflow:auto;">
                        <table style="width:100%;border-collapse:collapse;">
                            <thead style="color:#64748b;font-weight:700;">
                                <tr>
                                    <th style="padding:8px;text-align:left">S. No.</th>
                                    <th style="padding:8px;text-align:left">Name</th>
                                    <th style="padding:8px;text-align:left">Roll</th>
                                    <th style="padding:8px;text-align:left">Class</th>
                                    <th style="padding:8px;text-align:right">Score</th>
                                    <th style="padding:8px;text-align:right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $sn = 1; foreach ($grp['items'] as $row): ?>
                                    <tr>
                                        <td style="padding:8px;"><?= $sn++ ?></td>
                                        <td style="padding:8px;"><?= esc($row['full_name']) ?></td>
                                        <td style="padding:8px;"><?= esc($row['roll_no']) ?></td>
                                        <td style="padding:8px;"><?= esc($row['class']) ?></td>
                                        <td style="padding:8px;text-align:right"><strong><?= (int)$row['score_sum'] ?></strong></td>
                                        <td style="padding:8px;text-align:right"><?= (int)$row['total_sum'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../../includes/footer.php'; ?>
<script>
function printReport(){ const t=document.title; document.title='weekly_test_report'; window.print(); document.title=t; }
</script>
</body>
</html>
