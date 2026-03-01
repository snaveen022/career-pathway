<?php
// attendee/take_test.php
require_once __DIR__ . '/../config/config.php';
require_login();

$user_roll = $_SESSION['user_roll'];

$test_type = $_REQUEST['test_type'] ?? '';
$test_date = $_REQUEST['test_date'] ?? '';

if (!in_array($test_type, ['daily','weekly','monthly'], true)) {
    die('Invalid test type.');
}

$dt = DateTime::createFromFormat('Y-m-d', $test_date);
if (!$dt) die('Invalid date format.');

// weekly must be Saturday
if ($test_type === 'weekly' && (int)$dt->format('N') !== 6) {
    die('Weekly tests must be taken only on Saturdays.');
}

// Block ONLY tomorrow
$today = new DateTime('today');
$tomorrow = (clone $today)->modify('+1 day');
if ($dt == $tomorrow) {
    die("You cannot attempt tomorrow's test today.");
}

// Fetch ALL tests of that date (multiple clubs)
$stmt = $pdo->prepare("
    SELECT id, club_id, title
    FROM tests
    WHERE test_type = :tt
      AND test_date = :td
      AND active = 1
");
$stmt->execute([':tt' => $test_type, ':td' => $test_date]);
$testsForDate = $stmt->fetchAll();

if (!$testsForDate) {
    die("No test found for the selected date.");
}

// Get all test_ids from all clubs
$testIds = array_column($testsForDate, 'id');
$testCount = count($testIds);
if ($testCount === 0) {
    die("No active tests found for that date.");
}

// Check if user already attempted ANY test of this date
$placeholders = implode(',', array_fill(0, $testCount, '?'));
$execParams = array_merge([$user_roll], $testIds);

$sqlCheck = "SELECT a.id
    FROM attempts a
    JOIN tests t ON a.test_id = t.id
    WHERE a.user_roll = ?
      AND a.test_id IN ($placeholders)
    LIMIT 1";

$chk = $pdo->prepare($sqlCheck);
$chk->execute($execParams);

if ($chk->fetch()) {
    die("You already attempted the test for " . e($test_date));
}

// Fetch ALL questions from ALL clubs for this test-date
$placeholders2 = implode(',', array_fill(0, $testCount, '?'));
$sqlQ = "
    SELECT 
        q.id AS qid,
        q.question_text,
        o.option_a, o.option_b, o.option_c, o.option_d,
        c.name AS club_name,
        t.title AS test_title,
        q.test_id
    FROM questions q
    JOIN options_four o ON o.question_id = q.id
    JOIN tests t ON t.id = q.test_id
    JOIN clubs c ON c.id = t.club_id
    WHERE q.test_id IN ($placeholders2)
    ORDER BY c.name, q.id ASC
";
$qstmt = $pdo->prepare($sqlQ);
$qstmt->execute($testIds);
$questions = $qstmt->fetchAll();

if (!$questions) {
    die("No questions available yet for this test.");
}

$csrf = csrf_token();
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Take Test</title>
<link rel="stylesheet" href="/public/css/main.css">
<link rel="stylesheet" href="/public/css/footer.css">
<link rel="stylesheet" href="/public/css/header.css">
<style>
    .club-header {
        margin-top:25px;
        font-size:18px;
        color:#1e40af;
        border-bottom:2px solid #cbd5e1;
        padding-bottom:6px;
    }
    .question-block { margin:14px 0; padding:8px; border-radius:6px; }
    .club-tag { display:inline-block; font-size:12px; color:#475569; margin-left:8px; }
    .question-block p { margin:6px 0; }
    .question-block label { display:block; margin:6px 0; cursor:pointer; }
    button.submit-btn { background:linear-gradient(90deg,#16a34a,#10b981); color:#fff; padding:10px 14px; border:0; border-radius:8px; font-weight:700; cursor:pointer; }

    /* --- Copy-protection styles (target question and option text) --- */
    /* Non-selectable question text */
    .question-text {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Non-selectable option text */
    .option-text {
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        user-select: none;
    }

    /* Keep inputs selectable/usable */
    .question-block input[type="radio"],
    .question-block label {
        -webkit-user-select: text !important;
        -moz-user-select: text !important;
        -ms-user-select: text !important;
        user-select: text !important;
    }

    /* Visual: prevent highlight color showing */
    .question-text::selection,
    .option-text::selection { background: transparent; color: inherit; }
</style>

<script>
(function(){
  // Treat both .question-text and .option-text as protected areas
  function isProtected(node) {
    if (!node) return false;
    var el = (node.nodeType === 3) ? node.parentElement : node;
    if (!el || !el.closest) return false;
    return !!(el.closest('.question-text') || el.closest('.option-text'));
  }

  // Disable right-click on protected areas
  document.addEventListener('contextmenu', function(e){
    if (isProtected(e.target)) {
      e.preventDefault();
      flashMessage('Right-click is disabled on questions/options.');
    }
  }, false);

  // Prevent selectstart inside protected areas
  document.addEventListener('selectstart', function(e){
    if (isProtected(e.target)) {
      e.preventDefault();
    }
  }, false);

  // Prevent copy when selection originates from protected area
  document.addEventListener('copy', function(e){
    var sel = window.getSelection();
    if (!sel || sel.isCollapsed) return;
    var anchor = sel.anchorNode;
    if (isProtected(anchor)) {
      e.preventDefault();
      try { e.clipboardData.setData('text/plain', 'Copying questions/options is disabled.'); } catch(err){}
      flashMessage('Copying questions/options is disabled.');
    }
  }, false);

  // Block Ctrl/Cmd+C when selection is inside protected area
  document.addEventListener('keydown', function(e){
    var key = e.keyCode || e.which;
    var mod = e.ctrlKey || e.metaKey;
    if (!mod) return;
    if (key === 67) { // C
      var sel = window.getSelection();
      if (sel && !sel.isCollapsed && isProtected(sel.anchorNode)) {
        e.preventDefault();
        flashMessage('Copying questions/options is disabled.');
      }
    }
  }, false);

  // transient UI message
  function flashMessage(msg, timeout) {
    timeout = timeout || 1200;
    var el = document.createElement('div');
    el.textContent = msg;
    el.style.position = 'fixed';
    el.style.right = '12px';
    el.style.bottom = '12px';
    el.style.padding = '8px 12px';
    el.style.background = 'rgba(0,0,0,0.75)';
    el.style.color = '#fff';
    el.style.borderRadius = '6px';
    el.style.zIndex = 999999;
    el.style.fontSize = '13px';
    document.body.appendChild(el);
    setTimeout(function(){ el.remove(); }, timeout);
  }

})();
</script>
</head>
<body>

<?php include __DIR__ . '/../includes/header.php'; ?>

<main class="container">

    <h2><?= e(ucfirst($test_type) . ' Test') ?> — <?= e($test_date) ?></h2>

    <form method="post" action="<?= e(BASE_URL) ?>/attendee/submit_test.php">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <!-- send ALL test IDs (server can choose how to associate) -->
        <?php foreach ($testIds as $tid): ?>
            <input type="hidden" name="test_ids[]" value="<?= (int)$tid ?>">
        <?php endforeach; ?>

        <?php
        $currentClub = null;
        foreach ($questions as $i => $qq):
            if ($currentClub !== $qq['club_name']):
                $currentClub = $qq['club_name'];
        ?>
            <div class="club-header">
                <?= e($qq['club_name']) ?>
                <span class="club-tag"><?= e($qq['test_title']) ?></span>
            </div>
        <?php endif; ?>

        <div class="question-block" aria-labelledby="q<?= (int)$qq['qid'] ?>">
            <!-- question-text is non-selectable by CSS/JS above -->
            <p id="q<?= (int)$qq['qid'] ?>" class="question-text"><strong>Q<?= $i + 1 ?>.</strong> <?= nl2br(e($qq['question_text'])) ?></p>

            <label>
                <input type="radio" name="answer[<?= (int)$qq['qid'] ?>]" value="1" required>
                <span class="option-text"><?= e($qq['option_a']) ?></span>
            </label>

            <label>
                <input type="radio" name="answer[<?= (int)$qq['qid'] ?>]" value="2">
                <span class="option-text"><?= e($qq['option_b']) ?></span>
            </label>

            <label>
                <input type="radio" name="answer[<?= (int)$qq['qid'] ?>]" value="3">
                <span class="option-text"><?= e($qq['option_c']) ?></span>
            </label>

            <label>
                <input type="radio" name="answer[<?= (int)$qq['qid'] ?>]" value="4">
                <span class="option-text"><?= e($qq['option_d']) ?></span>
            </label>
        </div>
        <hr>

        <?php endforeach; ?>

        <div style="margin-top:14px;">
            <button type="submit" class="submit-btn">Submit Test</button>
        </div>
    </form>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>

</body>
</html>
