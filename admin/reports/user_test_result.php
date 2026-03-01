<?php
// admin/report/user_test_result.php
require_once __DIR__ . '/../../config/config.php';
require_admin($pdo); // only admin can view

// small helper (in case e() is not used here)
if (!function_exists('esc')) {
    function esc($v) {
        return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// get attempt id
$attemptId = isset($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;
if ($attemptId <= 0) {
    http_response_code(400);
    echo "Invalid attempt id.";
    exit;
}

// fetch attempt + user + test + club for THIS attempt (from attempts/tests)
$sql = "
    SELECT
        a.id,
        a.user_roll,
        a.score,
        a.total_marks,
        a.submitted_at,
        u.full_name,
        u.email,
        u.class,
        u.batch,
        t.id   AS test_id,
        t.test_type,
        t.test_date,
        COALESCE(c.name, 'Global') AS club_name
    FROM attempts a
    JOIN users u ON u.roll_no = a.user_roll
    LEFT JOIN tests t ON t.id = a.test_id
    LEFT JOIN clubs c ON c.id = t.club_id
    WHERE a.id = :id
    LIMIT 1
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    http_response_code(404);
    echo "Attempt not found.";
    exit;
}

// simple pass/fail for THIS attempt (using attempts table total)
$score    = (int)$attempt['score'];
$total    = (int)$attempt['total_marks'];
$percent  = $total > 0 ? round(($score / $total) * 100, 2) : 0;
$passFail = $percent >= 50 ? 'Pass' : 'Fail';

// -------------------------------------------------------------------------
// Fetch ALL club-wise data for this user from attempts_tests
// -------------------------------------------------------------------------
$userRoll = $attempt['user_roll'];

// All mapped tests for this user (club-wise) from attempts_tests
$allSql = "
    SELECT 
        at.attempt_id,
        at.test_id,
        at.score,
        at.total_marks,
        a.submitted_at,
        t.test_type,
        t.test_date,
        COALESCE(c.name, 'Global') AS club_name
    FROM attempts_tests at
    JOIN attempts a ON a.id = at.attempt_id
    LEFT JOIN tests t ON t.id = at.test_id
    LEFT JOIN clubs c ON c.id = t.club_id
    WHERE a.user_roll = :r AND t.test_date = :td
    ORDER BY c.name ASC, t.test_date DESC, a.submitted_at DESC
";
$allStmt = $pdo->prepare($allSql);
$allStmt->execute([':r' => $userRoll,':td' => $attempt['test_date']]);
$allAttempts = $allStmt->fetchAll();

// build club-wise summary from attempts_tests
$clubSummary = [];
foreach ($allAttempts as $row) {
    $clubName = $row['club_name'] ?? 'Global';
    if (!isset($clubSummary[$clubName])) {
        $clubSummary[$clubName] = [
            'tests'       => 0,
            'total_score' => 0,
            'total_marks' => 0,
        ];
    }
    $clubSummary[$clubName]['tests']++;
    $clubSummary[$clubName]['total_score'] += (int)$row['score'];
    $clubSummary[$clubName]['total_marks'] += (int)$row['total_marks'];
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>User Test Result</title>
    <style>
        body {
            margin: 0;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI",
                         sans-serif;
            background: #e5e7eb;
        }
        .overlay {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .card {
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 18px 45px rgba(15,23,42,0.18);
            max-width: 900px;
            width: 100%;
            padding: 20px 22px;
            border: 1px solid #e5e7f0;
        }
        h2 {
            margin: 0 0 6px 0;
            font-size: 1.4rem;
            color: #0f172a;
        }
        h3 {
            margin: 14px 0 8px 0;
            font-size: 1.05rem;
            color: #111827;
        }
        .muted {
            color: #6b7280;
            font-size: 0.9rem;
        }
        .row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 18px;
            font-size: 0.92rem;
            margin-top: 6px;
        }
        .row span.label {
            font-weight: 600;
            color: #374151;
        }
        .score-box {
            display: inline-flex;
            align-items: baseline;
            gap: 6px;
            font-size: 1.1rem;
            margin-top: 4px;
        }
        .score-main {
            font-weight: 700;
            color: #2563eb;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 4px;
        }
        .badge-pass {
            background: #dcfce7;
            color: #166534;
        }
        .badge-fail {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-type {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .btn-row {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            margin-top: 14px;
        }
        .btn {
            padding: 8px 13px;
            border-radius: 8px;
            border: 0;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(90deg,#2563eb,#1d4ed8);
            color: #fff;
        }
        .btn-ghost {
            background: transparent;
            border: 1px solid #cbd5f5;
            color: #2563eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 0.9rem;
        }
        th, td {
            padding: 6px 6px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }
        th {
            background: #f9fafb;
            font-weight: 600;
            color: #4b5563;
        }
        .section {
            margin-top: 16px;
        }
        @media (max-width: 640px) {
            .card {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
<div class="overlay">
    <div class="card">
        <h2>Test Result</h2>
        <p class="muted">Attempt ID: <?= esc($attempt['id']) ?></p>

        <!-- User details -->
        <h3>User Details</h3>
        <div class="row">
            <div><span class="label">Name:</span> <?= esc($attempt['full_name'] ?? '') ?></div>
            <div><span class="label">Roll:</span> <?= esc($attempt['user_roll']) ?></div>
        </div>
        <div class="row">
            <div><span class="label">Class:</span> <?= esc($attempt['class'] ?? '-') ?></div>
            <div><span class="label">Batch:</span> <?= esc($attempt['batch'] ?? '-') ?></div>
        </div>
        <div class="row">
            <div><span class="label">Email:</span> <?= esc($attempt['email'] ?? '-') ?></div>
        </div>

        <!-- This specific attempt (from attempts/tests) -->
        <div class="section">
            <h3>Attempt</h3>
            <div class="row">
                <div>
                    <span class="label">Type:</span>
                    <span class="badge badge-type"><?= esc($attempt['test_type'] ?? '-') ?></span>
                </div>
                <div><span class="label">Date:</span> <?= esc($attempt['test_date'] ?? '-') ?></div>
            </div>
            
            <div class="row">
                <div><span class="label">Submitted at:</span> <?= esc($attempt['submitted_at'] ?? '-') ?></div>
            </div>

            <h3 style="margin-top:10px;">Score</h3>
            <div class="score-box">
                <span class="score-main"><?= $score ?></span>
                <span>/ <?= $total ?></span>
                <span class="muted">(<?= $percent ?>%)</span>
                <?php if ($passFail === 'Pass'): ?>
                    <span class="badge badge-pass">Pass</span>
                <?php else: ?>
                    <span class="badge badge-fail">Fail</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Club-wise summary for ALL attempts (from attempts_tests) -->
       <!-- <div class="section">
            <h3>Club-wise Summary (All Attempts)</h3>
            <?php if (empty($clubSummary)): ?>
                <p class="muted">No attempts found for this user.</p>
            <?php else: ?>
               <table>
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Tests Taken</th>
                            <th>Correct Answers</th>
                            <th>Total Marks</th>
                            <th>Average %</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($clubSummary as $clubName => $s): 
                        $avgPercent = $s['total_marks'] > 0
                            ? round(($s['total_score'] / $s['total_marks']) * 100, 2)
                            : 0;
                    ?>
                        <tr>
                            <td><?= esc($clubName) ?></td>
                            <td><?= (int)$s['tests'] ?></td>
                            <td><?= (int)$s['total_score'] ?></td>
                            <td><?= (int)$s['total_marks'] ?></td>
                            <td><?= $avgPercent ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div> -->

        <!-- All attempts list (club-wise from attempts_tests) -->
        <div class="section">
            <h3>Attempts (Club-wise)</h3>
            <?php if (empty($allAttempts)): ?>
                <p class="muted">No attempts found.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            
                            <th>Club</th>
                            
                            
                            <th>Score</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allAttempts as $row): ?>
                        <tr>
                            
                            <td><?= esc($row['club_name'] ?? 'Global') ?></td>
                            
                            
                            <td>
                                <strong><?= (int)$row['score'] ?></strong>
                                / <?= (int)$row['total_marks'] ?>
                            </td>
                            <td><?= esc($row['submitted_at'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <div class="btn-row">
            <button class="btn btn-ghost" type="button" onclick="window.close();">
                Close
            </button>
            <button class="btn btn-primary" type="button" onclick="window.print();">
                Download / Print
            </button>
        </div>
    </div>
</div>
</body>
</html>
