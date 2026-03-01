<?php
// admin/questions_list.php
require_once __DIR__ . '/../../config/config.php';
require_admin($pdo);

$search = trim($_GET['q'] ?? '');

$sql = "
    SELECT 
        q.id,
        q.club_id,
        c.name AS club_name,
        q.test_id,
        t.test_type,
        t.test_date,
        q.question_text,
        o.option_a,
        o.option_b,
        o.option_c,
        o.option_d,
        o.correct_option
    FROM questions q
    LEFT JOIN options_four o ON o.question_id = q.id
    LEFT JOIN clubs c ON c.id = q.club_id
    LEFT JOIN tests t ON t.id = q.test_id
";

$params = [];
if ($search !== '') {
    $sql .= "
        WHERE
            q.question_text LIKE :q
            OR c.name LIKE :q
            OR t.test_type LIKE :q
            OR t.test_date LIKE :q
    ";
    $params[':q'] = '%' . $search . '%';
}

$sql .= "
    ORDER BY
       
        q.test_id ASC,
        
        q.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

function esc($v) { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Questions Bank</title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <link rel="stylesheet" href="/public/css/footer.css">
    <style>
        .wrap {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 8px;
        }
        h2 { margin-top: 0; }
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
            color:#fff;
            padding: 13px;
        }
        .btn-ghost {
            background:transparent;
            border:1px solid #cbd5f5;
            color:#2563eb;
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
            vertical-align:top;
        }
        th {
            background:#f9fafb;
            font-weight:600;
            color:#4b5563;
        }
        .q-meta {
            font-size:0.8rem;
            color:#6b7280;
        }
        .opt-label {
            font-weight:600;
            margin-right:4px;
        }
        .correct {
            font-weight:700;
            color:#16a34a;
        }
        .badge-club {
            display:inline-block;
            padding:2px 6px;
            border-radius:999px;
            background:#eff6ff;
            color:#1d4ed8;
            font-size:0.75rem;
            margin-right:6px;
        }
        .badge-test {
            display:inline-block;
            padding:2px 6px;
            border-radius:999px;
            background:#ecfdf5;
            color:#166534;
            font-size:0.75rem;
        }
        @media (max-width: 768px) {
            table {
                font-size:0.85rem;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/../../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content: space-between; align-items:center; gap:12px; margin:14px;">
        <h2>Questions Bank</h2>
        <a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= e(BASE_URL) ?>/../reports.php">← Back to Reports</a>
    </div>
    
    <p style="color:#6b7280; font-size:0.9rem;">
        Listing all questions from <code>questions</code> and <code>options_four</code> with correct option highlighted.
    </p>

    <form method="get" class="search-bar">
        <input type="text"
               name="q"
               placeholder="Search by question text, club, or test type/date..."
               value="<?= esc($search) ?>">
        <button type="submit" class="btn btn-primary">Search</button>
        <?php if ($search !== ''): ?>
            <a href="questions_list.php" class="btn btn-ghost">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($rows)): ?>
        <p style="color:#6b7280;">No questions found<?= $search ? ' for this search.' : '.' ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:60px;">Q.ID</th>
                    <th>Question & Metadata</th>
                    <th>Options</th>
                    <th style="width:120px;">Correct Option</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $q): ?>
                <tr>
                    <td><?= (int)$q['id'] ?></td>
                    <td>
                        <div><?= nl2br(esc($q['question_text'])) ?></div>
                        <div class="q-meta" style="margin-top:4px;">
                            <?php if ($q['club_name']): ?>
                                <span class="badge-club"><?= esc($q['club_name']) ?></span>
                            <?php endif; ?>
                            <?php if ($q['test_type'] || $q['test_date']): ?>
                                <span class="badge-test">
                                    <?= esc($q['test_type'] ?? '') ?>
                                    <?php if ($q['test_date']): ?>
                                        • <?= esc($q['test_date']) ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div><span class="opt-label">A)</span> <?= esc($q['option_a'] ?? '') ?></div>
                        <div><span class="opt-label">B)</span> <?= esc($q['option_b'] ?? '') ?></div>
                        <div><span class="opt-label">C)</span> <?= esc($q['option_c'] ?? '') ?></div>
                        <div><span class="opt-label">D)</span> <?= esc($q['option_d'] ?? '') ?></div>
                    </td>
                    <td>
                        <?php
                            $correctKey = strtoupper(trim($q['correct_option'] ?? ''));
                            $correctText = '';
                            if ($correctKey === 'A') $correctText = $q['option_a'] ?? '';
                            elseif ($correctKey === 'B') $correctText = $q['option_b'] ?? '';
                            elseif ($correctKey === 'C') $correctText = $q['option_c'] ?? '';
                            elseif ($correctKey === 'D') $correctText = $q['option_d'] ?? '';
                        ?>
                        <div class="correct">
                            <?= esc($correctKey) ?>
                            <?php if ($correctText): ?>
                                - <?= esc($correctText) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
