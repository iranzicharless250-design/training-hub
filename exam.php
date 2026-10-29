<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) { header('Location: admin/index.php'); exit; }

$user   = getCurrentUser();
$db     = getDB();
$unitId = (int)($_GET['unit_id'] ?? 0);
if (!$unitId) { header('Location: dashboard.php'); exit; }

$s=$db->prepare("SELECT u.*,l.title AS level_title FROM units u JOIN levels l ON u.level_id=l.id WHERE u.id=?"); $s->execute([$unitId]); $unit=$s->fetch();
if (!$unit) { header('Location: dashboard.php'); exit; }
if (!canAccessUnit($user['id'],$unitId) || !areAllLessonsCompleted($user['id'],$unitId)) { header('Location: dashboard.php'); exit; }

$s=$db->prepare("SELECT * FROM unit_exams WHERE unit_id=?"); $s->execute([$unitId]); $exam=$s->fetch();
if (!$exam) { header('Location: dashboard.php'); exit; }

$s=$db->prepare("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY id"); $s->execute([$exam['id']]); $questions=$s->fetchAll();

$s=$db->prepare("SELECT * FROM exam_attempts WHERE user_id=? AND exam_id=? ORDER BY attempt_number DESC"); $s->execute([$user['id'],$exam['id']]); $attempts=$s->fetchAll();
$lastPassed = !empty(array_filter($attempts, fn($a)=>$a['passed']));
$lastPendingReview = !empty(array_filter($attempts, fn($a)=>!empty($a['has_pending_review']) && !$a['passed']));
$attemptNum = count($attempts)+1;

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_exam'])) {
    $earned=0; $total=0; $hasOpen=false;
    foreach($questions as $q) { $total+=$q['marks']; }

    $s=$db->prepare("INSERT INTO exam_attempts (user_id,exam_id,attempt_number,submitted_at) VALUES (?,?,?,NOW())");
    $s->execute([$user['id'],$exam['id'],$attemptNum]); $attemptId=$db->lastInsertId();

    foreach($questions as $q) {
        $isOpen = ($q['question_type'] ?? 'multiple_choice') === 'open';
        if ($isOpen) {
            $hasOpen = true;
            $answerText = trim($_POST['q_'.$q['id']] ?? '');
            $db->prepare("INSERT INTO exam_answers (attempt_id,question_id,answer_text,needs_review,reviewed) VALUES (?,?,?,1,0)")
               ->execute([$attemptId,$q['id'],$answerText]);
        } else {
            $ans=strtoupper($_POST['q_'.$q['id']]??'');
            $ok=($ans===$q['correct_answer'])?1:0;
            if($ok) $earned+=$q['marks'];
            $db->prepare("INSERT INTO exam_answers (attempt_id,question_id,selected_answer,is_correct,reviewed) VALUES (?,?,?,?,1)")
               ->execute([$attemptId,$q['id'],$ans?:null,$ok]);
        }
    }

    $pct = $total>0 ? round(($earned/$total)*100) : 0;
    // If there are open questions still needing review, exam can't be marked
    // passed/failed with confidence yet — mark as pending until admin grades them.
    $passed = (!$hasOpen && $pct>=$exam['pass_score']) ? 1 : 0;
    $db->prepare("UPDATE exam_attempts SET score=?,earned_marks=?,total_marks=?,passed=?,has_pending_review=? WHERE id=?")
       ->execute([$pct,$earned,$total,$passed,$hasOpen?1:0,$attemptId]);

    if ($passed) { awardBadge($user['id'],$unitId); }

    header("Location: exam.php?unit_id=$unitId&result=$attemptId"); exit;
}

$showResult=null;
if (isset($_GET['result'])) {
    $s=$db->prepare("SELECT * FROM exam_attempts WHERE id=? AND user_id=?"); $s->execute([(int)$_GET['result'],$user['id']]); $showResult=$s->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Exam — <?= e($unit['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--navy:#0d2137;--teal:#00897b;--orange:#f5a623;--green:#16a34a;--red:#dc2626;--gold:#c8a84b;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9;--card:#fff;--purple:#7c3aed;}
body{font-family:'Nunito',sans-serif;background:var(--cream);color:var(--navy);}
nav{background:var(--navy);height:64px;padding:0 36px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.nav-brand{font-family:'Playfair Display',serif;font-weight:900;font-size:1.2rem;color:#fff;text-decoration:none;}
.back-btn{color:#7a9bb0;text-decoration:none;font-size:.84rem;border:1px solid #2a3a4a;padding:7px 16px;border-radius:7px;transition:.2s;}
.back-btn:hover{color:#fff;border-color:#444;}
.main{max-width:760px;margin:0 auto;padding:36px 20px;}
.exam-header{background:var(--navy);border-radius:16px;padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
.exam-title{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:900;color:#fff;margin-bottom:4px;}
.exam-meta{color:#7a9bb0;font-size:.82rem;}
.timer-box{background:rgba(255,255,255,.1);border-radius:10px;padding:12px 20px;text-align:center;}
.timer-num{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:var(--orange);}
.timer-lbl{color:#7a9bb0;font-size:.7rem;}
.timer-box.warning .timer-num{color:#ef4444;animation:pulse 1s infinite;}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.5}}

.card{background:var(--card);border-radius:14px;border:2px solid var(--border);padding:24px;margin-bottom:16px;}
.q-num{font-size:.72rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;}
.q-text{font-weight:800;font-size:1rem;line-height:1.5;margin-bottom:16px;}
.open-q-hint{font-size:.76rem;color:var(--purple);font-weight:700;margin-bottom:10px;}
.open-textarea{width:100%;padding:13px 15px;border:2px solid var(--border);border-radius:9px;font-size:.9rem;font-family:'Nunito',sans-serif;color:var(--navy);background:#faf9f7;outline:none;resize:vertical;min-height:100px;transition:.2s;}
.open-textarea:focus{border-color:var(--purple);background:#fff;}
.open-answer-display{background:#f3e8ff;border-left:4px solid var(--purple);border-radius:8px;padding:14px 16px;font-size:.88rem;color:#4c1d95;white-space:pre-wrap;}
.options{display:grid;gap:8px;}
.opt-label{display:flex;align-items:center;gap:12px;padding:12px 16px;border:2px solid var(--border);border-radius:9px;cursor:pointer;transition:.15s;font-weight:600;font-size:.9rem;}
.opt-label:hover{border-color:var(--teal);background:#f0fdfa;}
.opt-label input{accent-color:var(--teal);width:18px;height:18px;}
.opt-label.correct{border-color:var(--green);background:#f0fdf4;}
.opt-label.wrong{border-color:var(--red);background:#fef2f2;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:13px 28px;border-radius:8px;font-size:.9rem;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:.15s;}
.btn-teal{background:var(--teal);color:#fff;}
.btn-teal:hover{background:#005f56;}
.btn-navy{background:var(--navy);color:#fff;}
.btn-navy:hover{background:var(--orange);}

.result-box{text-align:center;padding:40px 32px;background:var(--card);border-radius:16px;border:3px solid var(--border);margin-bottom:24px;}
.result-score{font-family:'Playfair Display',serif;font-size:5rem;font-weight:900;line-height:1;}
.result-score.passed{color:var(--green);}
.result-score.failed{color:var(--red);}
.result-score.pending{color:var(--purple);}
.result-label{font-size:1.1rem;font-weight:800;margin:10px 0 4px;}
.result-sub{color:var(--muted);font-size:.88rem;}
.result-badge{display:inline-flex;align-items:center;gap:8px;background:var(--gold);color:#fff;padding:10px 24px;border-radius:30px;font-weight:800;font-size:.9rem;margin-top:16px;}
.pending-note{background:#f3e8ff;border:2px solid #d8b4fe;color:#6b21a8;border-radius:12px;padding:16px 20px;margin-bottom:20px;font-size:.86rem;text-align:center;}
.attempts-list{margin-bottom:20px;}
.attempt-row{display:flex;align-items:center;gap:12px;padding:12px 16px;border-radius:8px;background:var(--cream);margin-bottom:6px;}
.attempt-score{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:900;}
</style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="nav-brand">📝 <?= e(SITE_NAME) ?> — Exam</a>
  <a href="dashboard.php" class="back-btn">← Dashboard</a>
</nav>
<div class="main">
  <div class="exam-header">
    <div>
      <div class="exam-title"><?= e($exam['title']) ?></div>
      <div class="exam-meta">
        <?= e($unit['level_title']) ?> › <?= e($unit['title']) ?> •
        <?= count($questions) ?> questions •
        Pass mark: <?= $exam['pass_score'] ?>%
        <?php if($exam['time_limit_minutes']): ?> • <?= $exam['time_limit_minutes'] ?> min<?php endif; ?>
      </div>
    </div>
    <?php if(!$showResult && !$lastPassed && !$lastPendingReview && $exam['time_limit_minutes']): ?>
    <div class="timer-box" id="timer-box">
      <div class="timer-num" id="timer-display"><?= sprintf('%02d:%02d',$exam['time_limit_minutes'],0) ?></div>
      <div class="timer-lbl">Time Remaining</div>
    </div>
    <?php endif; ?>
  </div>

  <?php if($showResult): ?>
  <?php if (!empty($showResult['has_pending_review']) && !$showResult['passed']): ?>
  <!-- PENDING MANUAL REVIEW -->
  <div class="result-box" style="border-color:var(--purple)">
    <div class="result-score pending">⏳</div>
    <div class="result-label">Awaiting Manual Review</div>
    <div class="result-sub">Your exam includes open-ended questions. The admin will review your answers and finalize your score soon.</div>
  </div>
  <div style="text-align:center;margin-bottom:24px"><a href="dashboard.php" class="btn btn-navy">← Back to Dashboard</a></div>

  <?php else: ?>
  <div class="result-box" style="border-color:<?= $showResult['passed']?'var(--green)':'var(--red)' ?>">
    <div class="result-score <?= $showResult['passed']?'passed':'failed' ?>"><?= $showResult['score'] ?>%</div>
    <div class="result-label"><?= $showResult['passed']?'🎉 Congratulations! You Passed!':'😔 Not Quite — You Can Try Again' ?></div>
    <div class="result-sub"><?= $showResult['earned_marks'] ?>/<?= $showResult['total_marks'] ?> marks • Pass mark: <?= $exam['pass_score'] ?>%</div>
    <?php if($showResult['passed']): ?>
    <div class="result-badge">🏅 <?= e($unit['badge_icon'].' '.$unit['badge_name']) ?> Badge Earned!</div>
    <?php endif; ?>
  </div>
  <?php if(!$showResult['passed']): ?>
  <div style="text-align:center;margin-bottom:24px">
    <a href="exam.php?unit_id=<?= $unitId ?>" class="btn btn-teal">🔄 Try Again</a>
    &nbsp;<a href="dashboard.php" class="btn btn-navy">← Dashboard</a>
  </div>
  <?php else: ?>
  <div style="text-align:center;margin-bottom:24px"><a href="dashboard.php" class="btn btn-navy">← Back to Dashboard</a></div>
  <?php endif; ?>
  <?php endif; ?>

  <!-- Review answers -->
  <h3 style="font-family:'Playfair Display',serif;margin-bottom:16px">Review Answers</h3>
  <?php
  $s=$db->prepare("SELECT ea.*,eq.question_text,eq.question_type,eq.option_a,eq.option_b,eq.option_c,eq.option_d,eq.correct_answer,eq.marks FROM exam_answers ea JOIN exam_questions eq ON ea.question_id=eq.id WHERE ea.attempt_id=? ORDER BY eq.id");
  $s->execute([$showResult['id']]); $reviewAnswers=$s->fetchAll();
  foreach($reviewAnswers as $ri=>$ra):
    $isOpen = ($ra['question_type']??'multiple_choice')==='open';
  ?>
  <div class="card">
    <div class="q-num">
      Question <?= $ri+1 ?>
      <?php if($isOpen): ?>
        <?= $ra['reviewed'] ? ($ra['is_correct']?'✅ Correct':'❌ Incorrect') : '⏳ Pending review' ?>
      <?php else: ?>
        <?= $ra['is_correct']?'✅ +'.$ra['marks'].' marks':'❌ 0 marks' ?>
      <?php endif; ?>
    </div>
    <div class="q-text"><?= e($ra['question_text']) ?></div>
    <?php if($isOpen): ?>
    <div class="open-answer-display"><?= $ra['answer_text']?e($ra['answer_text']):'<em>No answer given</em>' ?></div>
    <?php else: ?>
    <div class="options">
      <?php foreach(['A','B','C','D'] as $opt):
        $col='option_'.strtolower($opt); if(!$ra[$col]) continue;
        $cls=''; if($opt===$ra['correct_answer']) $cls='correct'; elseif($opt===$ra['selected_answer']&&!$ra['is_correct']) $cls='wrong';
      ?>
      <label class="opt-label <?= $cls ?>">
        <input type="radio" disabled <?= $ra['selected_answer']===$opt?'checked':'' ?>>
        <span><strong><?= $opt ?>.</strong> <?= e($ra[$col]) ?> <?= $opt===$ra['correct_answer']?' ✓':'' ?></span>
      </label>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php elseif($lastPendingReview): ?>
  <div class="pending-note">⏳ Your most recent exam attempt is awaiting manual review by the admin for its open-ended questions. Check back soon!</div>
  <div style="text-align:center"><a href="dashboard.php" class="btn btn-navy">← Dashboard</a></div>

  <?php elseif($lastPassed): ?>
  <div class="result-box" style="border-color:var(--green)">
    <div class="result-score passed">✅</div>
    <div class="result-label">You Already Passed This Exam!</div>
    <div class="result-sub">Your badge has been awarded. Head back to continue your journey.</div>
    <div class="result-badge">🏅 <?= e($unit['badge_icon'].' '.$unit['badge_name']) ?></div>
  </div>
  <?php if(!empty($attempts)): ?>
  <div class="card">
    <h3 style="font-family:'Playfair Display',serif;margin-bottom:14px">Your Attempts</h3>
    <?php foreach($attempts as $att): ?>
    <div class="attempt-row">
      <div class="attempt-score" style="color:<?= $att['passed']?'var(--green)':'var(--red)' ?>"><?= $att['score'] ?>%</div>
      <div style="flex:1"><div style="font-weight:700;font-size:.85rem">Attempt #<?= $att['attempt_number'] ?></div><div style="color:var(--muted);font-size:.76rem"><?= date('M j, Y g:ia',strtotime($att['submitted_at']??$att['started_at'])) ?></div></div>
      <span style="padding:3px 10px;border-radius:20px;font-size:.72rem;font-weight:700;background:<?= $att['passed']?'#dcfce7':'#fef2f2' ?>;color:<?= $att['passed']?'var(--green)':'var(--red)' ?>"><?= $att['passed']?'Passed':'Failed' ?></span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div style="text-align:center"><a href="dashboard.php" class="btn btn-navy">← Dashboard</a></div>

  <?php else: ?>
  <!-- TAKE EXAM -->
  <form method="POST" id="exam-form">
    <?php foreach($questions as $qi=>$q):
      $isOpen = ($q['question_type']??'multiple_choice')==='open';
    ?>
    <div class="card">
      <div class="q-num">Question <?= $qi+1 ?> of <?= count($questions) ?> • <?= $q['marks'] ?> mark<?= $q['marks']>1?'s':'' ?><?= $isOpen?' • Open answer':'' ?></div>
      <div class="q-text"><?= e($q['question_text']) ?></div>
      <?php if($isOpen): ?>
      <div class="open-q-hint">✍️ Type your answer below — will be manually reviewed by the admin</div>
      <textarea name="q_<?= $q['id'] ?>" class="open-textarea" placeholder="Type your answer here..." required></textarea>
      <?php else: ?>
      <div class="options">
        <?php foreach(['A','B','C','D'] as $opt): $col='option_'.strtolower($opt); if(!$q[$col]) continue; ?>
        <label class="opt-label"><input type="radio" name="q_<?= $q['id'] ?>" value="<?= $opt ?>" required><span><strong><?= $opt ?>.</strong> <?= e($q[$col]) ?></span></label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div style="text-align:center;padding:8px 0 20px">
      <button type="submit" name="submit_exam" class="btn btn-teal" onclick="return confirm('Submit exam? You cannot change answers after submitting.')">📤 Submit Exam</button>
    </div>
  </form>
  <?php endif; ?>
</div>

<?php if(!$showResult && !$lastPassed && !$lastPendingReview && $exam['time_limit_minutes']): ?>
<script>
let secs = <?= $exam['time_limit_minutes'] * 60 ?>;
const disp = document.getElementById('timer-display');
const box  = document.getElementById('timer-box');
const iv = setInterval(() => {
  secs--;
  if (secs<=0) { clearInterval(iv); document.getElementById('exam-form').submit(); return; }
  const m = Math.floor(secs/60), s = secs%60;
  disp.textContent = String(m).padStart(2,'0')+':'+String(s).padStart(2,'0');
  if (secs<=300) box.classList.add('warning');
}, 1000);
</script>
<?php endif; ?>
</body>
</html>