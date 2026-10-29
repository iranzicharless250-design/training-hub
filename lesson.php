<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) { header('Location: admin/index.php'); exit; }
if (isTeacher()) { header('Location: teacher/index.php'); exit; }

$user = getCurrentUser();
$db   = getDB();
$lessonId = (int)($_GET['id'] ?? 0);

$s=$db->prepare("SELECT l.*,u.title AS unit_title,u.id AS unit_id,u.badge_icon,u.badge_name,lv.title AS level_title,lv.order_index AS level_order FROM lessons l JOIN units u ON l.unit_id=u.id JOIN levels lv ON u.level_id=lv.id WHERE l.id=? AND l.is_active=1");
$s->execute([$lessonId]); $lesson=$s->fetch();
if (!$lesson) { header('Location: dashboard.php'); exit; }

// ── ACCESS CHECK: prerequisite completion + Plus paywall ──
$access = canOpenLesson($user, $lessonId);
if (!$access['ok'] && $access['reason'] === 'locked') { header('Location: dashboard.php'); exit; }
$isPaywalled = (!$access['ok'] && $access['reason'] === 'paywall');

ensureProgress($user['id'], $lessonId);
$progress = getProgress($user['id'], $lessonId);
$contentType = $lesson['content_type'] ?? 'youtube';
$category = $lesson['category'] ?? 'general_science';

$quizResult=null; $quizError='';
if (!$isPaywalled && $_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['submit_quiz'])) {
    $s=$db->prepare("SELECT * FROM questions WHERE lesson_id=? ORDER BY id"); $s->execute([$lessonId]); $questions=$s->fetchAll();
    if (!$progress['video_watched']) { $quizError='Please view the lesson content first.'; }
    elseif ($progress['quiz_submitted']) { $quizError='Already submitted.'; }
    else {
        $score=0; $gradeable=0;
        $db->prepare("DELETE FROM quiz_answers WHERE user_id=? AND lesson_id=?")->execute([$user['id'],$lessonId]);
        foreach($questions as $q) {
            $isOpen = ($q['question_type'] ?? 'multiple_choice') === 'open';
            if ($isOpen) {
                $answerText = trim($_POST['q_'.$q['id']] ?? '');
                $db->prepare("INSERT INTO quiz_answers (user_id,lesson_id,question_id,answer_text,is_correct) VALUES (?,?,?,?,1)")->execute([$user['id'],$lessonId,$q['id'],$answerText]);
                $score++; $gradeable++;
            } else {
                $ans=strtoupper($_POST['q_'.$q['id']]??'');
                $ok=($ans===$q['correct_answer'])?1:0; if($ok) $score++;
                $gradeable++;
                $db->prepare("INSERT INTO quiz_answers (user_id,lesson_id,question_id,selected_answer,is_correct) VALUES (?,?,?,?,?)")->execute([$user['id'],$lessonId,$q['id'],$ans?:null,$ok]);
            }
        }
        $pct=$gradeable>0?round(($score/$gradeable)*100):0;
        $db->prepare("UPDATE student_progress SET quiz_submitted=1,quiz_score=? WHERE user_id=? AND lesson_id=?")->execute([$pct,$user['id'],$lessonId]);
        $progress=getProgress($user['id'],$lessonId);
        $quizResult=['score'=>$score,'total'=>$gradeable,'pct'=>$pct];
    }
}
$s=$db->prepare("SELECT * FROM questions WHERE lesson_id=? ORDER BY id"); $s->execute([$lessonId]); $questions=$s->fetchAll();

if (isset($_GET['watched'])) { markVideoWatched($user['id'],$lessonId); echo json_encode(['ok'=>true]); exit; }
$waUrl = getWhatsAppUrl($user['name'],$lesson['title'],($progress['quiz_score']??0).'%');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($lesson['title']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--navy:#0d2137;--teal:#00897b;--orange:#f5a623;--green:#16a34a;--red:#dc2626;--gold:#c8a84b;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9;--card:#fff;}
body{font-family:'Nunito',sans-serif;background:var(--cream);color:var(--navy);}
nav{background:var(--navy);height:64px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.nav-brand{font-family:'Playfair Display',serif;font-weight:900;font-size:1.1rem;color:#fff;text-decoration:none;}
.back-btn{color:#7a9bb0;text-decoration:none;font-size:.82rem;border:1px solid #2a3a4a;padding:7px 16px;border-radius:7px;transition:.2s;}
.back-btn:hover{color:#fff;border-color:#444;}
.main{max-width:820px;margin:0 auto;padding:32px 20px;}
.breadcrumb{font-size:.76rem;color:var(--muted);margin-bottom:16px;display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
.breadcrumb a{color:var(--teal);text-decoration:none;}
h1{font-family:'Playfair Display',serif;font-size:1.7rem;font-weight:900;margin-bottom:6px;}
.lesson-sub{color:var(--muted);margin-bottom:14px;font-size:.88rem;}
.tag-row{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;}
.content-type-tag,.category-tag{display:inline-flex;align-items:center;gap:5px;padding:3px 11px;border-radius:20px;font-size:.72rem;font-weight:700;}
.content-type-tag{background:var(--cream);border:1px solid var(--border);color:var(--muted);}
.pipeline{display:flex;background:var(--card);border-radius:12px;border:2px solid var(--border);overflow:hidden;margin-bottom:22px;}
.pipe-step{flex:1;padding:14px 10px;text-align:center;border-right:2px solid var(--border);}
.pipe-step:last-child{border-right:none;}
.pipe-step.done{background:#f0fdf4;}
.pipe-step.active{background:#f0fdfa;}
.pipe-icon{font-size:1.2rem;margin-bottom:3px;}
.pipe-label{font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);}
.pipe-step.done .pipe-label{color:var(--green);}
.pipe-step.active .pipe-label{color:var(--teal);}
.card{background:var(--card);border-radius:14px;border:2px solid var(--border);margin-bottom:18px;overflow:hidden;}
.card-head{padding:16px 22px;border-bottom:2px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
.card-head h2{font-family:'Playfair Display',serif;font-size:.98rem;font-weight:700;}
.card-body{padding:22px;}

.video-wrap{position:relative;padding-bottom:56.25%;border-radius:10px;overflow:hidden;background:#000;}
.video-wrap iframe,.video-wrap video{position:absolute;top:0;left:0;width:100%;height:100%;border:none;object-fit:contain;background:#000;}
.audio-wrap{background:linear-gradient(135deg,var(--navy),#1a3a5a);border-radius:14px;padding:40px 30px;text-align:center;}
.audio-icon{font-size:3rem;margin-bottom:14px;}
.image-wrap{border-radius:10px;overflow:hidden;background:#000;text-align:center;}
.image-wrap img{max-width:100%;max-height:520px;display:block;margin:0 auto;}
.pdf-wrap{border-radius:10px;overflow:hidden;border:2px solid var(--border);background:#525659;}
.pdf-wrap iframe{width:100%;height:560px;border:none;display:block;}
.pdf-fallback{padding:30px;text-align:center;background:var(--cream);}

.vid-overlay{position:absolute;inset:0;background:rgba(7,7,16,.88);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;z-index:2;}
.vid-overlay.hidden{display:none;}
.play-btn{background:var(--teal);border:none;color:#fff;padding:14px 32px;border-radius:30px;font-size:.95rem;font-family:'Nunito',sans-serif;font-weight:800;cursor:pointer;transition:.2s;}
.play-btn:hover{background:#005f56;transform:scale(1.04);}
.play-sub{color:#888;font-size:.78rem;}
.watched-note{background:#f0fdf4;border:2px solid #bbf7d0;color:var(--green);border-radius:8px;padding:11px 14px;margin-top:12px;font-size:.84rem;display:none;}
.watched-note.show{display:block;}
#mark-btn-wrap{margin-top:12px;display:none;}

.quiz-locked{background:#faf9f7;border-radius:8px;padding:22px;text-align:center;color:var(--muted);font-size:.88rem;}
.question{margin-bottom:22px;}
.q-text{font-weight:800;margin-bottom:11px;line-height:1.5;}
.open-q-hint{font-size:.74rem;color:#7c3aed;font-weight:700;margin-bottom:8px;}
.options{display:grid;gap:8px;}
.opt-label{display:flex;align-items:center;gap:11px;padding:10px 14px;border:2px solid var(--border);border-radius:8px;cursor:pointer;transition:.15s;font-weight:600;font-size:.88rem;}
.opt-label:hover{border-color:var(--teal);background:#f0fdfa;}
.opt-label input{accent-color:var(--teal);width:16px;height:16px;}
.opt-label.correct{border-color:var(--green);background:#f0fdf4;}
.opt-label.wrong{border-color:var(--red);background:#fef2f2;}
.open-textarea{width:100%;padding:12px 14px;border:2px solid var(--border);border-radius:9px;font-size:.88rem;font-family:'Nunito',sans-serif;color:var(--navy);background:#faf9f7;outline:none;resize:vertical;min-height:90px;transition:.2s;}
.open-textarea:focus{border-color:#7c3aed;background:#fff;}
.open-answer-display{background:#f3e8ff;border-left:4px solid #7c3aed;border-radius:8px;padding:12px 16px;font-size:.86rem;color:#4c1d95;white-space:pre-wrap;}
.quiz-result{text-align:center;padding:24px;background:var(--cream);border-radius:10px;margin-bottom:18px;}
.score-big{font-family:'Playfair Display',serif;font-size:3.2rem;font-weight:900;}
.wa-box{background:linear-gradient(135deg,#f0fff4,#dcfce7);border:2px solid #bbf7d0;border-radius:12px;padding:22px;}
.wa-box h3{font-family:'Playfair Display',serif;font-weight:700;color:#14532d;margin-bottom:6px;}
.wa-box p{color:#166534;font-size:.84rem;line-height:1.6;margin-bottom:16px;}
.wa-step{display:flex;align-items:flex-start;gap:9px;margin-bottom:8px;font-size:.8rem;color:#334;}
.wa-sn{width:21px;height:21px;background:#25d366;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0;}
.pending-box{background:#fffbeb;border:2px solid #fde68a;border-radius:12px;padding:22px;text-align:center;}
.pending-box h3{font-family:'Playfair Display',serif;font-weight:700;color:#92400e;margin-bottom:6px;}
.approved-box{background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:26px;text-align:center;}
.approved-box h3{font-family:'Playfair Display',serif;font-weight:700;font-size:1.4rem;color:var(--green);}

/* PAYWALL */
.paywall-box{background:linear-gradient(135deg,var(--navy),#1a3a5a);border-radius:18px;padding:44px 32px;text-align:center;color:#fff;position:relative;overflow:hidden;}
.paywall-box::before{content:'';position:absolute;width:280px;height:280px;background:radial-gradient(circle,rgba(200,168,75,.25),transparent 70%);top:-100px;right:-60px;}
.paywall-icon{font-size:3rem;margin-bottom:14px;position:relative;z-index:1;}
.paywall-box h2{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;margin-bottom:10px;position:relative;z-index:1;}
.paywall-box p{color:#9ab0c0;margin-bottom:24px;position:relative;z-index:1;max-width:420px;margin-left:auto;margin-right:auto;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:10px 20px;border-radius:7px;font-size:.84rem;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:.15s;}
.btn-teal{background:var(--teal);color:#fff;}
.btn-teal:hover{background:#005f56;}
.btn-wa{background:#25d366;color:#fff;}
.btn-wa:hover{background:#16a34a;}
.btn-navy{background:var(--navy);color:#fff;}
.btn-navy:hover{background:var(--orange);}
.btn-gold{background:var(--gold);color:#fff;position:relative;z-index:1;}
.btn-gold:hover{background:#a8882e;}
.btn-full{width:100%;justify-content:center;}
@media(max-width:640px){nav{padding:0 14px;}.main{padding:20px 12px;}.pipeline{flex-direction:column;}.pipe-step{border-right:none;border-bottom:2px solid var(--border);}.pipe-step:last-child{border-bottom:none;}.pdf-wrap iframe{height:400px;}}
</style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="nav-brand"><?= e(SITE_NAME) ?></a>
  <a href="dashboard.php" class="back-btn">← Dashboard</a>
</nav>
<div class="main">
  <div class="breadcrumb">
    <a href="dashboard.php">Dashboard</a> /
    <span>Level <?= $lesson['level_order'] ?>: <?= e($lesson['level_title']) ?></span> /
    <span><?= e($lesson['unit_title']) ?></span> /
    <span><?= e($lesson['title']) ?></span>
  </div>
  <h1><?= e($lesson['title']) ?></h1>
  <p class="lesson-sub">Unit: <?= e($lesson['unit_title']) ?> — <?= e($lesson['level_title']) ?></p>
  <div class="tag-row">
    <span class="content-type-tag"><?= contentTypeIcon($contentType) ?> <?= contentTypeLabel($contentType) ?></span>
    <span class="category-tag" style="background:<?= categoryColor($category) ?>15;color:<?= categoryColor($category) ?>"><?= categoryIcon($category) ?> <?= categoryLabel($category) ?></span>
    <?php if($isPaywalled): ?><span class="category-tag" style="background:#fef9c3;color:#92400e">⭐ Plus Lesson</span><?php endif; ?>
  </div>

  <?php if ($isPaywalled): ?>
  <!-- PAYWALL -->
  <div class="paywall-box">
    <div class="paywall-icon">⭐</div>
    <h2>This Lesson Needs Plus</h2>
    <p>You've used your free lessons for this unit. Upgrade to Plus to unlock this lesson and every other lesson across the platform.</p>
    <a href="../subscribe.php" class="btn btn-gold">⭐ Upgrade to Plus →</a>
  </div>

  <?php elseif($progress['admin_approved']): ?>
  <div class="approved-box"><h3>🎉 Lesson Completed!</h3><p style="color:#166534;margin-top:6px">This lesson is done. Head back to continue.</p><br><a href="dashboard.php" class="btn btn-navy">Continue →</a></div>
  <?php else: ?>

  <!-- CONTENT -->
  <div class="card">
    <div class="card-head"><h2><?= contentTypeIcon($contentType) ?> Step 1 — <?= in_array($contentType,['youtube','video'])?'Watch the Video':($contentType==='audio'?'Listen to the Audio':($contentType==='pdf'?'Read the Document':'View the Image')) ?></h2><?php if($progress['video_watched']): ?><span style="color:var(--green);font-weight:700;font-size:.82rem">✓ Viewed</span><?php endif; ?></div>
    <div class="card-body">

      <?php if ($contentType === 'youtube'): ?>
      <div class="video-wrap">
        <?php if(!$progress['video_watched']): ?>
        <div class="vid-overlay" id="vid-overlay"><button class="play-btn" onclick="loadVideo()">▶ Start Lesson</button><span class="play-sub">Click to begin</span></div>
        <?php endif; ?>
        <iframe id="yt" src="https://www.youtube.com/embed/<?= e($lesson['youtube_id']) ?>?enablejsapi=1&rel=0&modestbranding=1" allowfullscreen allow="autoplay;encrypted-media"></iframe>
      </div>

      <?php elseif ($contentType === 'video'): ?>
      <div class="video-wrap">
        <?php if(!$progress['video_watched']): ?>
        <div class="vid-overlay" id="vid-overlay"><button class="play-btn" onclick="loadVideo()">▶ Start Lesson</button><span class="play-sub">Click to begin</span></div>
        <?php endif; ?>
        <video id="local-video" controls preload="metadata"><source src="<?= e($lesson['media_path']) ?>" type="video/mp4">Your browser doesn't support video playback.</video>
      </div>

      <?php elseif ($contentType === 'audio'): ?>
      <div class="audio-wrap">
        <div class="audio-icon">🎧</div>
        <audio id="local-audio" controls preload="metadata" style="width:100%;max-width:420px"><source src="<?= e($lesson['media_path']) ?>">Your browser doesn't support audio playback.</audio>
        <?php if(!$progress['video_watched']): ?><div style="margin-top:14px"><button class="play-btn" onclick="loadVideo()" style="background:var(--teal)">▶ Start Listening</button></div><?php endif; ?>
      </div>

      <?php elseif ($contentType === 'pdf'): ?>
      <div class="pdf-wrap">
        <iframe src="<?= e($lesson['media_path']) ?>#toolbar=1" title="<?= e($lesson['title']) ?>"></iframe>
      </div>
      <div class="pdf-fallback" style="margin-top:8px;border-radius:8px;">
        <a href="<?= e($lesson['media_path']) ?>" target="_blank" class="btn btn-teal btn-sm" style="display:inline-flex">📄 Open PDF in New Tab</a>
      </div>
      <?php if(!$progress['video_watched']): ?><div style="margin-top:14px;text-align:center"><button class="play-btn" onclick="loadVideo()">📖 I've Read This Document</button></div><?php endif; ?>

      <?php elseif ($contentType === 'image'): ?>
      <div class="image-wrap"><img src="<?= e($lesson['media_path']) ?>" alt="<?= e($lesson['title']) ?>"></div>
      <?php if(!$progress['video_watched']): ?><div style="margin-top:14px;text-align:center"><button class="play-btn" onclick="loadVideo()">👀 I've Viewed This Image</button></div><?php endif; ?>
      <?php endif; ?>

      <div class="watched-note <?= $progress['video_watched']?'show':'' ?>" id="watched-note">✅ Marked as viewed — quiz unlocked below!</div>
      <?php if(!$progress['video_watched'] && in_array($contentType,['youtube','video'])): ?>
      <div style="margin-top:10px;color:var(--muted);font-size:.78rem">💡 A "Mark as Watched" button appears after 30 seconds.</div>
      <div id="mark-btn-wrap" style="margin-top:10px"><button class="btn btn-teal" onclick="markWatched()">✓ Mark as Watched</button></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- QUIZ -->
  <div class="card">
    <div class="card-head"><h2>📝 Step 2 — Quiz</h2><?php if($progress['quiz_submitted']): ?><span style="color:var(--green);font-weight:700;font-size:.82rem">Score: <?= $progress['quiz_score'] ?>%</span><?php endif; ?></div>
    <div class="card-body">
      <?php if(!$progress['video_watched']&&!$quizResult): ?>
      <div class="quiz-locked">🔒 View the lesson content first to unlock the quiz.</div>
      <?php elseif($progress['quiz_submitted']&&!$quizResult): ?>
      <div class="quiz-result"><div class="score-big"><?= $progress['quiz_score'] ?>%</div><div style="color:var(--muted);margin-top:6px">Quiz submitted!</div></div>
      <?php
      $ex=$db->prepare("SELECT qa.*,q.question_text,q.question_type FROM quiz_answers qa JOIN questions q ON qa.question_id=q.id WHERE qa.user_id=? AND qa.lesson_id=? ORDER BY q.id");
      $ex->execute([$user['id'],$lessonId]);
      foreach($ex->fetchAll() as $ea): if (($ea['question_type']??'')==='open'): ?>
      <div class="question"><div class="q-text"><?= e($ea['question_text']) ?></div><div class="open-answer-display"><?= $ea['answer_text']?e($ea['answer_text']):'<em>No answer given</em>' ?></div></div>
      <?php endif; endforeach; ?>
      <?php else: ?>
      <?php if($quizResult): ?><div class="quiz-result"><div class="score-big"><?= $quizResult['pct'] ?>%</div><div style="color:var(--muted);margin-top:6px"><?= $quizResult['score'] ?>/<?= $quizResult['total'] ?> correct</div></div><?php endif; ?>
      <?php if($quizError): ?><div style="color:var(--red);background:#fef2f2;padding:10px 14px;border-radius:7px;margin-bottom:14px;font-size:.84rem"><?= e($quizError) ?></div><?php endif; ?>
      <?php if(!$progress['quiz_submitted']): ?>
      <form method="POST">
        <?php foreach($questions as $qi=>$q): $isOpen=($q['question_type']??'multiple_choice')==='open'; ?>
        <div class="question">
          <div class="q-text"><?= ($qi+1) ?>. <?= e($q['question_text']) ?></div>
          <?php if($isOpen): ?>
          <div class="open-q-hint">✍️ Type your answer below</div>
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
        <button type="submit" name="submit_quiz" class="btn btn-teal btn-full">Submit Quiz →</button>
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- WHATSAPP -->
  <?php if($progress['quiz_submitted']): ?>
    <?php if($progress['screenshot_sent']): ?>
    <div class="pending-box"><h3>⏳ Awaiting Admin Approval</h3><p style="color:#78350f;font-size:.84rem">Screenshot sent. Please wait for admin to approve your progress.</p><br><a href="<?= $waUrl ?>" target="_blank" class="btn btn-wa">📲 Send Reminder</a></div>
    <?php else: ?>
    <div class="wa-box">
      <h3>📲 Step 3 — Share Your Results</h3>
      <p>Take a screenshot of this page and send it to the admin via WhatsApp to get approved for the next lesson.</p>
      <div style="margin-bottom:16px">
        <div class="wa-step"><div class="wa-sn">1</div>Screenshot this page showing your score</div>
        <div class="wa-step"><div class="wa-sn">2</div>Click the WhatsApp button below</div>
        <div class="wa-step"><div class="wa-sn">3</div>Send your screenshot in the chat</div>
        <div class="wa-step"><div class="wa-sn">4</div>Admin approves → next lesson unlocks</div>
      </div>
      <a href="<?= $waUrl ?>" target="_blank" class="btn btn-wa btn-full" onclick="markScreenshotSent()">📲 Open WhatsApp &amp; Send Screenshot</a>
    </div>
    <?php endif; ?>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?= icWidget() ?>
<script>
const lid=<?= $lessonId ?>;
function loadVideo(){
  const ov=document.getElementById('vid-overlay'); if(ov) ov.classList.add('hidden');
  const lv=document.getElementById('local-video'); if(lv) lv.play().catch(()=>{});
  setTimeout(()=>{const b=document.getElementById('mark-btn-wrap');if(b)b.style.display='block';markWatched();},30000);
}
function markWatched(){fetch('lesson.php?id='+lid+'&watched=1').then(r=>r.json()).then(()=>{const n=document.getElementById('watched-note');if(n)n.classList.add('show');const b=document.getElementById('mark-btn-wrap');if(b)b.style.display='none';setTimeout(()=>location.reload(),800);});}
function markScreenshotSent(){fetch('mark_screenshot.php?lesson_id='+lid).then(()=>setTimeout(()=>location.reload(),1500));}
</script>
</body></html>
