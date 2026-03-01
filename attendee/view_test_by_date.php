<?php
// attendee/view_test_by_date.php
require_once __DIR__ . '/../config/config.php';
require_login();

$user = current_user($pdo);
$user_roll = $user['roll_no'] ?? ($_SESSION['user_roll'] ?? '');
if (!$user_roll) {
    http_response_code(403);
    echo "User not identified.";
    exit;
}

function esc($v){ return htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }

// Config: table names / answer column
$QUESTIONS_TABLE = 'questions';
$OPTIONS_TABLE   = 'options_four';   // contains option_a, option_b, ...
$ANS_TABLE       = 'answers';
$ANS_COL         = 'selected_option';

// read params
$test_date = isset($_GET['test_date']) ? trim($_GET['test_date']) : '';
$test_type = isset($_GET['test_type']) ? trim($_GET['test_type']) : '';
if ($test_date === '' || $test_type === '') {
    http_response_code(400);
    echo "Missing test_date or test_type.";
    exit;
}

// fetch tests for given date + type
try {
    $tStmt = $pdo->prepare("
        SELECT id, title, test_type, test_date, club_id
        FROM tests
        WHERE test_date = :td
          AND test_type = :tt
        ORDER BY id ASC
    ");
    $tStmt->execute([':td' => $test_date, ':tt' => $test_type]);
    $tests = $tStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = $e->getMessage();
    $tests = [];
}

// ---------- Attempt lookup statements ----------
// 1) Try attempts_tests join (preferred when per-test rows exist)
$getAttemptFromAttemptsTests = $pdo->prepare("
    SELECT at.attempt_id AS attempt_id, at.score AS score, at.total_marks AS total_marks, at.submitted_at AS submitted_at
    FROM attempts_tests at
    JOIN attempts a ON a.id = at.attempt_id
    WHERE at.test_id = :tid
      AND a.user_roll = :uro
    ORDER BY at.submitted_at DESC, at.id DESC
    LIMIT 1
");

// 2) Fallback: attempts table directly (test_id + user_roll)
$getAttemptFromAttempts = $pdo->prepare("
    SELECT id AS attempt_id, score, total_marks, submitted_at
    FROM attempts
    WHERE test_id = :tid
      AND user_roll = :uro
    ORDER BY submitted_at DESC, id DESC
    LIMIT 1
");

// helper: convert CSV string to array
function csv_to_array(string $raw) : array {
    if ($raw === '') return [];
    $parts = array_map('trim', explode(',', $raw));
    return array_values(array_filter($parts, function($x){ return $x !== ''; }));
}

/**
 * Load questions + options + user's selected answers for a test.
 * This version expects options stored in one row in options_four with columns:
 *   option_a, option_b, option_c, ... (and optional is_correct_a, is_correct_b, ...)
 * Also supports a single column indicating correct option: correct_option / answer_key / correct
 */
function load_test_questions(PDO $pdo, int $testId, ?int $attemptId, string $QUESTIONS_TABLE, string $OPTIONS_TABLE, string $ANS_TABLE, string $ANS_COL) {
    // 1) questions
    $qStmt = $pdo->prepare("SELECT id, question_text FROM {$QUESTIONS_TABLE} WHERE test_id = :tid ORDER BY id ASC");
    $qStmt->execute([':tid' => $testId]);
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($questions)) return [];

    $qIds = array_map(function($r){ return (int)$r['id']; }, $questions);

    // 2) fetch options rows for questions (one row per question in this schema)
    $placeholders = implode(',', array_fill(0, count($qIds), '?'));
    $optSql = "SELECT * FROM {$OPTIONS_TABLE} WHERE question_id IN ({$placeholders})";
    $optStmt = $pdo->prepare($optSql);
    foreach ($qIds as $i => $qid) $optStmt->bindValue($i+1, $qid, PDO::PARAM_INT);
    $optStmt->execute();
    $optionsRows = $optStmt->fetchAll(PDO::FETCH_ASSOC);

    $optionsByQ = [];
    foreach ($optionsRows as $row) {
        $qid = isset($row['question_id']) ? (int)$row['question_id'] : null;
        if ($qid === null) continue;
        $optionsByQ[$qid] = $row;
    }

    // 3) answers for the attempt
    $answersMap = [];
    if (!empty($attemptId)) {
        try {
            $aStmt = $pdo->prepare("SELECT question_id, {$ANS_COL} AS sel FROM {$ANS_TABLE} WHERE attempt_id = :aid");
            $aStmt->execute([':aid' => $attemptId]);
            $rows = $aStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) $answersMap[(int)$r['question_id']] = $r['sel'];
        } catch (Exception $e) {
            $answersMap = [];
        }
    }

    // letters to use
    $letters = range('a','h'); // supports up to option_h

    $out = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        $optsRow = $optionsByQ[$qid] ?? null;
        $norm = [];

        // Build options from columns option_a, option_b, ...
        if ($optsRow !== null) {
            foreach ($letters as $letter) {
                $col = "option_{$letter}";
                if (array_key_exists($col, $optsRow) && trim((string)$optsRow[$col]) !== '') {
                    $text = $optsRow[$col];
                    $norm[$letter] = [
                        'id' => null, // no separate id in this schema; keep null
                        'text' => (string)$text,
                        'is_correct' => false,
                        'raw' => null
                    ];
                }
            }

            // Strategy 1: single-correct key column (various possible names)
            $possibleCorrectCols = ['correct_option','answer_key','correct','correct_ans','correct_option_key','right_option'];
            $foundCorrect = null;
            foreach ($possibleCorrectCols as $cname) {
                if (isset($optsRow[$cname]) && trim((string)$optsRow[$cname]) !== '') {
                    $foundCorrect = trim((string)$optsRow[$cname]);
                    break;
                }
            }
            if ($foundCorrect !== null) {
                $ck = strtolower($foundCorrect);
                // numeric -> map 1->a,2->b...
                if (preg_match('/^\d+$/', $ck)) {
                    $num = (int)$ck;
                    $mapping = [];
                    for ($i=0;$i<count($letters);$i++) $mapping[$i+1] = $letters[$i];
                    if (isset($mapping[$num]) && isset($norm[$mapping[$num]])) {
                        $norm[$mapping[$num]]['is_correct'] = true;
                    }
                } else {
                    // letter or text
                    $letter = strtolower($ck);
                    if (isset($norm[$letter])) {
                        $norm[$letter]['is_correct'] = true;
                    } else {
                        // maybe stored as the option text - try to match text
                        foreach ($norm as $k=>$o) {
                            if (strtolower(trim((string)$o['text'])) === strtolower(trim($foundCorrect))) {
                                $norm[$k]['is_correct'] = true;
                                break;
                            }
                        }
                    }
                }
            } else {
                // Strategy 2: check boolean columns is_correct_a, is_correct_b...
                foreach ($letters as $letter) {
                    $colIs = "is_correct_{$letter}";
                    if (array_key_exists($colIs, $optsRow)) {
                        $val = $optsRow[$colIs];
                        if ($val === '1' || $val === 1 || strtolower((string)$val) === 'true' || strtolower((string)$val) === 'yes') {
                            if (isset($norm[$letter])) $norm[$letter]['is_correct'] = true;
                        }
                    }
                }
                // also support A_correct / a_correct variations
                foreach ($letters as $letter) {
                    $colIs2 = strtoupper($letter) . "_correct";
                    if (array_key_exists($colIs2, $optsRow)) {
                        $val = $optsRow[$colIs2];
                        if ($val === '1' || $val === 1 || strtolower((string)$val) === 'true' || strtolower((string)$val) === 'yes') {
                            if (isset($norm[$letter])) $norm[$letter]['is_correct'] = true;
                        }
                    }
                }
            }
        }

        // Determine correct key (first true) if any
        $correctKey = null;
        foreach ($norm as $k=>$o) {
            if (!empty($o['is_correct'])) { $correctKey = $k; break; }
        }

        // Selected option from answersMap: multiple strategies
        $selRaw = $answersMap[$qid] ?? null;
        $selectedKey = null;
        if ($selRaw !== null) {
            $selStr = strtolower(trim((string)$selRaw));
            // numeric -> 1->a,2->b
            if (preg_match('/^\d+$/', $selStr)) {
                $num = (int)$selStr;
                if ($num >= 1 && $num <= count($letters)) {
                    $try = $letters[$num-1];
                    if (isset($norm[$try])) $selectedKey = $try;
                }
            }
            // letter like 'a'
            if ($selectedKey === null && preg_match('/^[a-z]$/', $selStr)) {
                if (isset($norm[$selStr])) $selectedKey = $selStr;
            }
            // match by option text
            if ($selectedKey === null) {
                foreach ($norm as $k=>$o) {
                    if ($o['text'] !== null && strtolower(trim((string)$o['text'])) === $selStr) { $selectedKey = $k; break; }
                }
            }
            // last resort: if selRaw equals option column name like 'option_a'
            if ($selectedKey === null && preg_match('/^option_([a-z])$/i', $selStr, $m)) {
                $lk = strtolower($m[1]);
                if (isset($norm[$lk])) $selectedKey = $lk;
            }
        }

        $out[] = [
            'id' => $qid,
            'question_text' => $q['question_text'],
            'options' => $norm,
            'correct_key' => $correctKey,
            'selected_key' => $selectedKey
        ];
    }

    return $out;
}

// --- main: for each test fetch latest attempt and load Qs ---
$error = null;
if (!empty($tests)) {
    try {
        foreach ($tests as &$t) {
            $t['latest_attempt'] = null;
            $t['questions'] = [];

            // 1) Try to get the attempt via attempts_tests (preferred when present)
            $getAttemptFromAttemptsTests->execute([':tid' => $t['id'], ':uro' => $user_roll]);
            $row1 = $getAttemptFromAttemptsTests->fetch(PDO::FETCH_ASSOC);

            if ($row1 && !empty($row1['attempt_id'])) {
                // attempt_id here references attempts.id
                $attemptId = (int)$row1['attempt_id'];
                $t['latest_attempt'] = [
                    'attempt_id' => $attemptId,
                    'score' => $row1['score'],
                    'total_marks' => $row1['total_marks'],
                    'submitted_at' => $row1['submitted_at']
                ];
            } else {
                // 2) fallback: find attempt directly in attempts table by test_id + user_roll
                $getAttemptFromAttempts->execute([':tid' => $t['id'], ':uro' => $user_roll]);
                $row2 = $getAttemptFromAttempts->fetch(PDO::FETCH_ASSOC);
                if ($row2 && !empty($row2['attempt_id'])) {
                    $attemptId = (int)$row2['attempt_id'];
                    $t['latest_attempt'] = [
                        'attempt_id' => $attemptId,
                        'score' => $row2['score'],
                        'total_marks' => $row2['total_marks'],
                        'submitted_at' => $row2['submitted_at']
                    ];
                } else {
                    $attemptId = null;
                }
            }

            // load questions + options + user's answers for this test using the attempt id we found (if any)
            $t['questions'] = load_test_questions($pdo, (int)$t['id'], $attemptId, $QUESTIONS_TABLE, $OPTIONS_TABLE, $ANS_TABLE, $ANS_COL);
        }
        unset($t);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Questions — <?= esc($test_type) ?> <?= esc($test_date) ?></title>
    <link rel="stylesheet" href="/public/css/main.css">
    <link rel="stylesheet" href="/public/css/header.css">
    <style>
        .wrap { max-width:1100px; margin:18px auto; padding:0 12px; }
        .card { background:#fff; border:1px solid #eef2ff; border-radius:10px; padding:14px; margin-bottom:12px; }
        .q-card { border:1px solid #eef2ff; border-radius:8px; padding:10px; margin-bottom:10px; background:#fff; }
        .option { padding:8px 10px; border-radius:6px; margin:6px 0; border:1px solid #eef2ff; display:flex; justify-content:space-between; align-items:center; }
        .option.correct { background:#ecfdf5; border-color:#a7f3d0; }
        .option.selected { background:#eef2ff; border-color:#bfdbfe; }
        .option.selected.correct { background:#dcfce7; border-color:#86efac; }
        .badge { font-weight:700; padding:4px 8px; border-radius:6px; font-size:0.82rem; }
        .small-muted { font-size:0.9rem; color:#6b7280; }
        .legend { display:flex; gap:8px; margin-top:8px; align-items:center; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="wrap">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>Questions — <?= esc($test_type) ?> / <?= esc($test_date) ?></h2>
        <div><a style="background:transparent; color:#2563eb; border:1px solid #cfe3ff; padding:8px 10px; border-radius:8px; text-decoration:none;" href="<?= esc(BASE_URL) ?>/attendee/dashboard.php" class="btn">← Back to Dashboard</a></div>
    </div>
    
    <br><br>

    <?php if (!empty($error)): ?>
        <div class="card"><div class="small-muted">Error: <?= esc($error) ?></div></div>
    <?php endif; ?>

    <?php if (empty($tests)): ?>
        <div class="card"><div class="small-muted">No tests found for this date & type.</div></div>
    <?php else: ?>
        <?php foreach ($tests as $t): ?>
            <div class="card">
                <div style="display:flex; justify-content:space-between; gap:12px; align-items:flex-start;">
                    <div>
                        <h3 style="margin:0 0 6px 0;"><?= esc($t['title'] ?: ('Test #'.(int)$t['id'])) ?></h3>
                        <div class="small-muted">Date: <?= esc($t['test_date']) ?> · Type: <?= esc($t['test_type']) ?> · Club ID: <?= esc($t['club_id']) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <?php if (!empty($t['latest_attempt'])): ?>
                            <div class="badge score">Score: <?= (int)$t['latest_attempt']['score'] ?> / <?= (int)$t['latest_attempt']['total_marks'] ?></div>
                            <div class="small-muted" style="margin-top:6px;">Submitted: <?= esc($t['latest_attempt']['submitted_at']) ?></div>
                        <?php else: ?>
                            <div class="small-muted">You haven't attempted this test.</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <?php if (empty($t['questions'])): ?>
                        <div class="small-muted">No questions found for this test.</div>
                    <?php else: ?>
                        <?php foreach ($t['questions'] as $i => $q):
                            $opts = $q['options'] ?? [];
                            $correct = $q['correct_key'] ?? null;
                            $selected = $q['selected_key'] ?? null;
                        ?>
                            <div class="q-card">
                                <div style="font-weight:700;">Q<?= $i+1 ?>. <?= esc($q['question_text']) ?></div>

                                <div style="margin-top:8px;">
                                    <?php if (empty($opts)): ?>
                                        <div class="small-muted">Options missing for this question.</div>
                                    <?php else: ?>
                                        <?php foreach ($opts as $k => $o):
                                            $isCorrect = ($k === $correct) || (!empty($o['is_correct']));
                                            $isSelected = ($selected !== null && strtolower($selected) === strtolower($k));
                                            $classes = 'option' . ($isCorrect ? ' correct' : '') . ($isSelected ? ' selected' : '');
                                        ?>
                                            <div class="<?= esc($classes) ?>">
                                                <div style="max-width:85%;">
                                                    <strong><?= strtoupper(esc($k)) ?>.</strong> <?= esc($o['text']) ?>
                                                </div>

                                                <div style="margin-left:12px; font-size:0.9rem;">
                                                    <?php if ($isSelected && $isCorrect): ?>
                                                        <span style="color:#065f46; font-weight:700;">Your answer ✓</span>
                                                    <?php elseif ($isSelected && !$isCorrect): ?>
                                                        <span style="color:#b91c1c; font-weight:700;">Your answer ✕</span>
                                                    <?php elseif ($isCorrect): ?>
                                                        <span style="color:#065f46; font-weight:700;">Correct Answer</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- <div style="margin-top:10px;" class="legend">
        <div class="badge" style="background:#ecfdf5;color:#065f46;">Correct option</div>
        <div class="badge" style="background:#eef2ff;color:#1e40af;">Your selected option</div>
        <div class="small-muted" style="margin-left:12px;">(This view reads option columns like option_a/option_b and highlights correct/selected.)</div>
    </div>-->
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
