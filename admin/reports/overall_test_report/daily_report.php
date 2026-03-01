<?php
// admin/daily_report.php
require_once __DIR__ . '/../../../config/config.php';
require_admin($pdo);

function esc($v){ return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// fetch attempts (use attempts_tests if present)
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
    WHERE t.test_type = 'daily'
    ORDER BY t.test_date DESC, u.full_name
";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if (empty($rows)) {
    // fallback to attempts table
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
        WHERE t.test_type = 'daily'
        ORDER BY t.test_date DESC, u.full_name
    ";
    $rows = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
}

// aggregate per date and user
$dailyTmp = [];
foreach ($rows as $r) {
    $date = $r['test_date'] ?? '';
    $roll = (string)$r['user_roll'];
    $name = $r['full_name'] ?? '';
    $cls  = $r['class'] ?? '';
    $score = isset($r['score']) ? (int)$r['score'] : 0;
    $total = isset($r['total_marks']) ? (int)$r['total_marks'] : 0;

    if (!isset($dailyTmp[$date])) $dailyTmp[$date] = [];
    if (!isset($dailyTmp[$date][$roll])) {
        $dailyTmp[$date][$roll] = ['roll_no'=>$roll,'full_name'=>$name,'class'=>$cls,'score_sum'=>0,'total_sum'=>0];
    }
    $dailyTmp[$date][$roll]['score_sum'] += $score;
    $dailyTmp[$date][$roll]['total_sum'] += $total;
}

// create grouped list
$dailyGrouped = [];
foreach ($dailyTmp as $date => $byRoll) {
    $items = array_values($byRoll);
    usort($items, function($a,$b){ return strcasecmp($a['roll_no'],$b['roll_no']); });
    $dailyGrouped[$date] = ['label'=>$date,'items'=>$items];
}
// sort by date desc
uksort($dailyGrouped, function($a,$b){ return strcmp($b,$a); });
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Daily Test Report</title>
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
        <h2>Daily Test Report</h2>
         <div style="display:flex;gap:8px;">
              <button class="btn btn-primary" onclick="printReport()">Print</button>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= esc(BASE_URL) ?>/../overall_test_reports.php">← Back to Overall Reports</a>
    </div>
         </div>
    
    <hr>

    <div id="report"><br>
        <?php if (empty($dailyGrouped)): ?>
            <div class="muted">No daily attempts found.</div>
        <?php else: ?>
            <?php foreach ($dailyGrouped as $date => $grp): ?>
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
function printReport(){ const t=document.title; document.title='daily_test_report'; window.print(); document.title=t; }
</script>
</body>
</html>
