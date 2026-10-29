<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) { header('Location: admin/index.php'); exit; }
if (isTeacher()) { header('Location: teacher/index.php'); exit; }

$user = getCurrentUser();
$db   = getDB();
$mySubject = !empty($user['subject_id']) ? getSubjectById($user['subject_id']) : null;
$levels = getLevelsForUser($user);

foreach ($levels as &$lv) {
    $lv['units'] = getUnitsForLevel($lv['id']);
    $lv['can_access'] = canAccessLevel($user['id'], $lv['id']);
    $lv['completed']  = isLevelCompleted($user['id'], $lv['id']);
    $lv['has_cert']   = hasCertForLevel($user['id'], $lv['id']);
    foreach ($lv['units'] as &$u) {
        $u['can_access'] = canAccessUnit($user['id'], $u['id']);
        $u['completed']  = isUnitCompleted($user['id'], $u['id']);
        $u['has_badge']  = hasBadge($user['id'], $u['id']);
        $s=$db->prepare("SELECT COUNT(*) FROM lessons WHERE unit_id=? AND is_active=1"); $s->execute([$u['id']]); $u['lessons_total']=(int)$s->fetchColumn();
        $s2=$db->prepare("SELECT COUNT(*) FROM student_progress sp JOIN lessons l ON sp.lesson_id=l.id WHERE l.unit_id=? AND sp.user_id=? AND sp.admin_approved=1"); $s2->execute([$u['id'],$user['id']]); $u['lessons_done']=(int)$s2->fetchColumn();
        $s3=$db->prepare("SELECT id FROM unit_exams WHERE unit_id=?"); $s3->execute([$u['id']]); $examRow=$s3->fetch();
        $u['exam_id']=$examRow?$examRow['id']:null;
        $u['exam_passed'] = $u['exam_id'] ? isUnitExamPassed($user['id'],$u['id']) : true;
        if ($u['completed'] && !$u['has_badge']) { awardBadge($user['id'],$u['id']); $u['has_badge']=true; }
    }
    unset($u);
}
unset($lv);

$badges      = getStudentBadges($user['id']);
$certs       = getStudentCertificates($user['id']);
$hasAllDone  = areAllLevelsCompleted($user['id'], $user['subject_id'] ?? null);
$hasCombined = hasCombinedCert($user['id']);
$ads         = getActiveAds();

$totalLevels     = count($levels);
$completedLevels = count(array_filter($levels, fn($l)=>$l['completed']));
$totalUnits = 0; $completedUnits = 0; $totalLessons = 0; $completedLessons = 0;
foreach ($levels as $lv) {
    foreach ($lv['units'] as $u) {
        $totalUnits++; if ($u['completed']) $completedUnits++;
        $totalLessons += $u['lessons_total'];
        $completedLessons += $u['lessons_done'];
    }
}
$totalBadges = count($badges);
$totalCerts  = count($certs);
$sPend = $db->prepare("SELECT COUNT(*) FROM student_progress WHERE user_id=? AND quiz_submitted=1 AND admin_approved=0");
$sPend->execute([$user['id']]); $pendingApprovals = (int)$sPend->fetchColumn();

$overallPct = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

$defaultOpenLevelId = null;
foreach ($levels as $lv) { if ($lv['can_access'] && !$lv['completed']) { $defaultOpenLevelId = $lv['id']; break; } }
if ($defaultOpenLevelId === null && !empty($levels)) $defaultOpenLevelId = $levels[0]['id'];

$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'levels';
if (!in_array($activeTab, ['levels','badges','certs'])) $activeTab = 'levels';

$companyName = getSetting('company_name', 'TrainHub');
$logoPath    = getSetting('logo_path', '');
$logoSrc = '';
if ($logoPath && file_exists(__DIR__.'/'.$logoPath)) { $logoSrc = imgToBase64(__DIR__.'/'.$logoPath); }
$userPicUrl = getProfilePictureUrl($user);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{
  --navy:#0d2137; --navy2:#163047; --gold:#c8a84b; --gold-dark:#a8882e;
  --green:#16a34a; --red:#dc2626; --muted:#6b7e8f; --border:#e7e2d6;
  --cream:#f7f4ee; --card:#ffffff; --ink:#1a2e3d;
}
body{font-family:'Nunito',sans-serif;background:var(--cream);color:var(--ink);min-height:100vh;display:grid;grid-template-columns:250px 1fr;}

.sidebar{background:var(--navy);min-height:100vh;position:sticky;top:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column;}
.sb-top{padding:26px 22px 18px;display:flex;align-items:center;gap:10px;}
.sb-logo-img{height:34px;object-fit:contain;}
.sb-logo-ph{width:34px;height:34px;border-radius:9px;background:var(--gold);display:flex;align-items:center;justify-content:center;color:#fff;font-family:'Playfair Display',serif;font-weight:900;font-size:1rem;}
.sb-brand{font-family:'Playfair Display',serif;font-weight:900;font-size:1.15rem;color:#fff;}
.sb-subject-chip{margin:0 22px 14px;background:rgba(255,255,255,.06);color:#8aa4b4;padding:6px 12px;border-radius:9px;font-size:.74rem;font-weight:700;display:flex;align-items:center;gap:6px;}
.sb-section-label{color:#3a5a70;font-size:.66rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;margin:18px 0 8px 22px;}
.sb-nav{padding:0 12px;}
.sb-item{display:flex;align-items:center;gap:11px;padding:11px 14px;border-radius:10px;color:#8aa4b4;text-decoration:none;font-size:.87rem;font-weight:700;margin-bottom:3px;transition:.15s;}
.sb-item:hover{background:rgba(255,255,255,.06);color:#fff;}
.sb-item.active{background:var(--gold);color:#fff;}
.sb-icon{font-size:1rem;width:20px;text-align:center;}
.sb-badge{margin-left:auto;background:var(--red);color:#fff;border-radius:10px;padding:1px 7px;font-size:.65rem;font-weight:800;}
.sb-live-dot{width:6px;height:6px;background:#ef4444;border-radius:50%;animation:blink 1.2s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.25}}
.sb-footer{margin-top:auto;padding:18px 22px;border-top:1px solid rgba(255,255,255,.06);}
.sb-logout{display:flex;align-items:center;gap:9px;color:#5a7a90;text-decoration:none;font-size:.83rem;font-weight:700;transition:.15s;}
.sb-logout:hover{color:#fff;}

.main{padding:30px 36px;}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;}
.topbar h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:900;color:var(--navy);}
.topbar-sub{color:var(--muted);font-size:.86rem;margin-top:3px;}
.topbar-right{display:flex;align-items:center;gap:14px;}
.tb-bell{width:38px;height:38px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:1rem;background:#fff;position:relative;}
.tb-bell .dot{position:absolute;top:6px;right:8px;width:7px;height:7px;background:var(--red);border-radius:50%;}
.tb-avatar{width:38px;height:38px;border-radius:50%;background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;}

.stats-row{display:grid;grid-template-columns:repeat(6,1fr);gap:14px;margin-bottom:22px;}
.stat-card{background:var(--card);border:2px solid var(--border);border-radius:14px;padding:16px 16px 14px;transition:.2s;}
.stat-card:hover{border-color:var(--gold);transform:translateY(-2px);}
.stat-card-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.stat-icon-box{width:34px;height:34px;border-radius:9px;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:1.05rem;}
.stat-arrow{color:#ccc;font-size:.95rem;}
.stat-num{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;color:var(--navy);}
.stat-lbl{color:var(--muted);font-size:.76rem;margin-top:2px;}

.mid-row{display:grid;grid-template-columns:1.3fr 1fr 1fr;gap:16px;margin-bottom:22px;align-items:stretch;}
.mid-card{background:var(--card);border:2px solid var(--border);border-radius:16px;overflow:hidden;}
.ad-video-box{position:relative;background:#000;padding-bottom:56%;height:0;}
.ad-video-box video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
.ad-overlay-text{position:absolute;bottom:0;left:0;right:0;padding:14px 16px;background:linear-gradient(0deg,rgba(0,0,0,.75),transparent);color:#fff;}
.ad-overlay-title{font-weight:800;font-size:.86rem;}
.ad-overlay-badge{position:absolute;top:12px;right:12px;background:rgba(255,255,255,.15);color:#fff;font-size:.62rem;font-weight:800;padding:3px 10px;border-radius:20px;backdrop-filter:blur(4px);}
.no-ad-box{padding:40px 20px;text-align:center;background:linear-gradient(135deg,var(--navy),#1a3a5a);color:#7a9bb0;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.no-ad-box .nic{font-size:2rem;margin-bottom:8px;}

.eng-card{padding:20px 22px;display:flex;flex-direction:column;}
.eng-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.eng-title{font-weight:800;font-size:.92rem;color:var(--navy);}
.eng-num{font-family:'Playfair Display',serif;font-size:2.1rem;font-weight:900;color:var(--navy);margin-bottom:2px;}
.eng-sub{color:var(--muted);font-size:.78rem;margin-bottom:14px;}
.eng-chart{flex:1;display:flex;align-items:flex-end;}
.eng-chart svg{width:100%;height:60px;}

.prof-card{padding:20px 22px;}
.prof-head{display:flex;align-items:center;gap:11px;margin-bottom:12px;}
.prof-avatar{width:42px;height:42px;border-radius:50%;background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1rem;flex-shrink:0;}
.prof-name{font-weight:800;font-size:.9rem;color:var(--navy);}
.prof-role{color:var(--muted);font-size:.76rem;}
.prof-desc{color:var(--muted);font-size:.78rem;line-height:1.6;margin-bottom:14px;}
.prof-bar-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;}
.prof-bar-label{font-size:.76rem;color:var(--muted);font-weight:700;}
.prof-bar-pct{font-size:.8rem;font-weight:800;color:var(--navy);}
.prof-bar-track{height:7px;background:var(--border);border-radius:4px;overflow:hidden;margin-bottom:14px;}
.prof-bar-fill{height:100%;background:var(--gold);border-radius:4px;transition:width 1s;}
.prof-btn{display:flex;align-items:center;justify-content:center;gap:8px;background:var(--navy);color:#fff;text-decoration:none;padding:11px;border-radius:9px;font-weight:800;font-size:.83rem;transition:.15s;}
.prof-btn:hover{background:var(--gold);}

.learn-card{background:var(--card);border:2px solid var(--border);border-radius:16px;overflow:hidden;}
.learn-head{padding:20px 24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;}
.learn-title{font-family:'Playfair Display',serif;font-weight:900;font-size:1.15rem;color:var(--navy);}
.learn-tabs{display:flex;gap:4px;}
.learn-tab{padding:7px 16px;border-radius:20px;font-size:.78rem;font-weight:800;color:var(--muted);text-decoration:none;transition:.15s;}
.learn-tab.active{background:var(--navy);color:#fff;}
.learn-tab:hover:not(.active){background:var(--cream);}

.level-item{border-top:1px solid var(--border);}
.level-item:first-child{border-top:none;}
.level-item.locked{opacity:.55;}
.level-item > summary{list-style:none;cursor:pointer;padding:18px 24px;display:flex;align-items:center;gap:14px;}
.level-item > summary::-webkit-details-marker{display:none;}
.lvl-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;}
.lvl-info{flex:1;min-width:0;}
.lvl-title{font-weight:800;font-size:.94rem;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.lvl-sub{color:var(--muted);font-size:.78rem;margin-top:2px;}
.lvl-right{display:flex;align-items:center;gap:10px;flex-shrink:0;}
.lvl-pill{padding:4px 13px;border-radius:20px;font-size:.72rem;font-weight:800;white-space:nowrap;}
.pill-done{background:#dcfce7;color:var(--green);}
.pill-active{background:#fef3c7;color:#92400e;}
.pill-locked{background:#f5f5f5;color:#aaa;}
.lvl-chev{color:var(--muted);font-size:1.15rem;transition:transform .2s;}
.level-item[open] .lvl-chev{transform:rotate(90deg);}

.units-body{padding:0 24px 18px;}
.unit-blk{border:2px solid var(--border);border-radius:12px;margin-bottom:12px;overflow:hidden;}
.unit-blk.completed{border-color:#bbf7d0;}
.unit-head2{padding:14px 18px;display:flex;align-items:center;gap:11px;background:var(--cream);flex-wrap:wrap;}
.unit-bicon{font-size:1.3rem;}
.unit-info2{flex:1;min-width:130px;}
.unit-title2{font-weight:800;font-size:.87rem;display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
.unit-sub2{color:var(--muted);font-size:.73rem;}
.uprog{display:flex;align-items:center;gap:7px;}
.uprog-bar{width:90px;height:5px;background:var(--border);border-radius:3px;overflow:hidden;}
.uprog-fill{height:100%;background:var(--gold);border-radius:3px;}
.uprog-txt{font-size:.7rem;color:var(--muted);white-space:nowrap;}

.lessons2{padding:10px 18px 14px;}
.lrow{display:flex;align-items:center;gap:11px;padding:9px 12px;border-radius:8px;text-decoration:none;color:inherit;margin-bottom:3px;transition:.15s;}
a.lrow:hover{background:var(--cream);}
.lrow.locked-r{opacity:.5;cursor:not-allowed;}
.lnum{width:26px;height:26px;border-radius:7px;background:var(--cream);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;color:var(--muted);flex-shrink:0;}
.lrow.done-r .lnum{background:#dcfce7;border-color:#bbf7d0;color:var(--green);}
.lrow-title{flex:1;font-weight:700;font-size:.84rem;}
.lstatus{padding:3px 10px;border-radius:20px;font-size:.66rem;font-weight:800;white-space:nowrap;}
.s-done{background:#dcfce7;color:var(--green);}
.s-pending{background:#fef9c3;color:#92400e;}
.s-watch{background:#fff7ed;color:#c2410c;}
.s-quiz{background:#eff6ff;color:#0037ff;}
.s-locked{background:#f5f5f5;color:#aaa;}

.exam-r{margin:6px 18px 14px;padding:12px 16px;border-radius:9px;border:2px dashed var(--border);display:flex;align-items:center;gap:11px;text-decoration:none;color:inherit;}
a.exam-r:hover{border-color:var(--gold);background:#fffbeb;}
.exam-r.locked-r{opacity:.5;cursor:not-allowed;}
.exam-r.exam-p{border-color:var(--green);background:#f0fdf4;border-style:solid;}
.exam-icon2{font-size:1.3rem;}
.exam-info2{flex:1;}
.exam-t2{font-weight:800;font-size:.84rem;}
.exam-s2{font-size:.72rem;color:var(--muted);}

.badge-disp{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:20px;font-size:.7rem;font-weight:800;border:2px solid;}
.locked-note{padding:12px 24px 16px;color:var(--muted);font-size:.82rem;}

.badges-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;padding:22px 24px;}
.badge-c{text-align:center;padding:20px 14px;border:2px solid var(--border);border-radius:13px;transition:.2s;}
.badge-c:hover{transform:translateY(-3px);}
.badge-c-icon{font-size:2.6rem;margin-bottom:7px;display:block;}
.badge-c-name{font-weight:800;font-size:.82rem;}
.badge-c-unit{font-size:.7rem;color:var(--muted);}
.badge-c-date{font-size:.66rem;color:#aaa;margin-top:3px;}

.certs-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;padding:22px 24px;}
.cert-c{border:2px solid var(--border);border-radius:13px;overflow:hidden;transition:.2s;}
.cert-c:hover{border-color:var(--gold);}
.cert-c-top{padding:18px;background:linear-gradient(135deg,var(--navy),#1a3a5a);}
.cert-c-icon{font-size:2.2rem;margin-bottom:6px;}
.cert-c-title{font-family:'Playfair Display',serif;font-size:.94rem;font-weight:700;color:#fff;margin-bottom:3px;}
.cert-c-sub{color:#7a9bb0;font-size:.72rem;}
.cert-c-body{padding:14px 18px;}
.cert-c-date{font-size:.74rem;color:var(--muted);}

.empty-tab2{text-align:center;padding:56px 20px;color:var(--muted);}
.empty-tab2 .ei2{font-size:2.8rem;margin-bottom:10px;}

.btn2{display:inline-flex;align-items:center;gap:6px;padding:8px 17px;border-radius:8px;font-size:.8rem;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:.15s;}
.btn2-gold{background:var(--gold);color:#fff;}
.btn2-gold:hover{background:var(--gold-dark);}
.btn2-navy{background:var(--navy);color:#fff;}
.btn2-navy:hover{background:var(--gold);}

.mobile-toggle-btn{display:none;}
.sidebar-overlay2{display:none;}
@media(max-width:1200px){.stats-row{grid-template-columns:repeat(3,1fr);}.mid-row{grid-template-columns:1fr;}}
@media(max-width:900px){
  body{grid-template-columns:1fr;}
  .sidebar{position:fixed;top:0;left:0;height:100vh;width:250px;z-index:1000;transform:translateX(-100%);transition:transform .25s ease;box-shadow:4px 0 24px rgba(0,0,0,.25);}
  .sidebar.open{transform:translateX(0);}
  .mobile-toggle-btn{display:flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9px;border:none;background:var(--navy);color:#fff;font-size:1.1rem;cursor:pointer;}
  .sidebar-overlay2{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:999;}
  .sidebar-overlay2.open{display:block;}
  .stats-row{grid-template-columns:repeat(2,1fr);}
  .main{padding:24px 18px;}
  .topbar h1{font-size:1.2rem;}
}
@media(max-width:600px){
  .stats-row{grid-template-columns:repeat(2,1fr);gap:10px;}
  .main{padding:18px 12px;}
  .learn-head{flex-direction:column;align-items:flex-start;}
  .learn-tabs{overflow-x:auto;width:100%;padding-bottom:4px;}
  .level-item > summary{padding:14px 16px;flex-wrap:wrap;}
  .lvl-info{min-width:100%;order:1;}
  .lvl-right{order:2;width:100%;justify-content:space-between;margin-top:6px;}
  .units-body{padding:0 14px 14px;}
  .unit-head2{padding:12px 14px;}
  .badges-grid,.certs-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));padding:16px;}
}
</style>
</head>
<body>

<div class="sidebar-overlay2" id="dashSidebarOverlay" onclick="document.getElementById('dashSidebar').classList.remove('open');this.classList.remove('open');"></div>
<div class="sidebar" id="dashSidebar">
  <div class="sb-top">
    <?php if($logoSrc): ?><img src="<?= $logoSrc ?>" class="sb-logo-img" alt="Logo">
    <?php else: ?><div class="sb-logo-ph"><?= strtoupper(substr($companyName,0,1)) ?></div><?php endif; ?>
    <div class="sb-brand"><?= e($companyName) ?></div>
  </div>
  <?php if($mySubject): ?>
  <div class="sb-subject-chip"><?= e($mySubject['icon']) ?> Studying: <?= e($mySubject['name']) ?></div>
  <?php endif; ?>

  <div class="sb-section-label">Learning</div>
  <nav class="sb-nav">
    <a href="dashboard.php?tab=levels" class="sb-item <?= $activeTab==='levels'?'active':'' ?>"><span class="sb-icon">📊</span> Dashboard <?php if($pendingApprovals>0): ?><span class="sb-badge"><?= $pendingApprovals ?></span><?php endif; ?></a>
    <a href="dashboard.php?tab=badges" class="sb-item <?= $activeTab==='badges'?'active':'' ?>"><span class="sb-icon">🏅</span> My Badges</a>
    <a href="dashboard.php?tab=certs" class="sb-item <?= $activeTab==='certs'?'active':'' ?>"><span class="sb-icon">🎓</span> Certificates</a>
    <a href="live-sessions.php" class="sb-item"><span class="sb-icon"><span class="sb-live-dot"></span></span> Live Sessions</a>
  </nav>

  <div class="sb-section-label">Account</div>
  <nav class="sb-nav">
    <a href="profile.php" class="sb-item"><span class="sb-icon">👤</span> My Profile</a>
  </nav>

  <div class="sb-footer">
    <a href="logout.php" class="sb-logout"><span class="sb-icon">🚪</span> Logout</a>
  </div>
</div>

<div class="main">
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <button class="mobile-toggle-btn" onclick="document.getElementById('dashSidebar').classList.toggle('open');document.getElementById('dashSidebarOverlay').classList.toggle('open');">☰</button>
      <div>
        <h1>Welcome back, <?= e(explode(' ',$user['name'])[0]) ?> 👋</h1>
        <div class="topbar-sub"><?= $completedLevels ?> of <?= $totalLevels ?> levels completed<?= $mySubject?' in '.e($mySubject['name']):'' ?></div>
      </div>
    </div>
    <div class="topbar-right">
      <div class="tb-bell">🔔<?php if($pendingApprovals>0): ?><span class="dot"></span><?php endif; ?></div>
      <?php if($userPicUrl): ?><img src="<?= e($userPicUrl) ?>" alt="" style="width:38px;height:38px;border-radius:50%;object-fit:cover;">
      <?php else: ?><div class="tb-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div><?php endif; ?>
    </div>
  </div>

  <div class="stats-row">
    <div class="stat-card"><div class="stat-card-top"><div class="stat-icon-box">📊</div><span class="stat-arrow">→</span></div><div class="stat-num"><?= $totalLevels ?></div><div class="stat-lbl">Levels</div></div>
    <div class="stat-card"><div class="stat-card-top"><div class="stat-icon-box">📦</div><span class="stat-arrow">→</span></div><div class="stat-num"><?= $completedUnits ?>/<?= $totalUnits ?></div><div class="stat-lbl">Units Done</div></div>
    <div class="stat-card"><div class="stat-card-top"><div class="stat-icon-box">📚</div><span class="stat-arrow">→</span></div><div class="stat-num"><?= $completedLessons ?>/<?= $totalLessons ?></div><div class="stat-lbl">Lessons Done</div></div>
    <div class="stat-card"><div class="stat-card-top"><div class="stat-icon-box">⏳</div><span class="stat-arrow">→</span></div><div class="stat-num"><?= $pendingApprovals ?></div><div class="stat-lbl">Pending</div></div>
    <div class="stat-card"><div class="stat-card-top"><div class="stat-icon-box">🏅</div><span class="stat-arrow">→</span></div><div class="stat-num"><?= $totalBadges ?></div><div class="stat-lbl">Badges</div></div>
    <div class="stat-card"><div class="stat-card-top"><div class="stat-icon-box">🎓</div><span class="stat-arrow">→</span></div><div class="stat-num"><?= $totalCerts ?></div><div class="stat-lbl">Certificates</div></div>
  </div>

  <div class="mid-row">
    <div class="mid-card">
      <?php if (!empty($ads)): $ad = $ads[0]; ?>
      <div class="ad-video-box">
        <video controls muted autoplay loop playsinline><source src="<?= e($ad['video_path']) ?>" type="video/mp4"></video>
        <div class="ad-overlay-badge"><?= e($companyName) ?></div>
        <div class="ad-overlay-text"><div class="ad-overlay-title"><?= e($ad['title']) ?></div></div>
      </div>
      <?php else: ?>
      <div class="no-ad-box"><div class="nic">🎬</div><div style="font-weight:700;font-size:.88rem">No intro video yet</div></div>
      <?php endif; ?>
    </div>

    <div class="mid-card eng-card">
      <div class="eng-head"><div class="eng-title">Your Progress</div></div>
      <div class="eng-num"><?= $overallPct ?>%</div>
      <div class="eng-sub">Overall course completion</div>
      <div class="eng-chart">
        <svg viewBox="0 0 200 60" preserveAspectRatio="none">
          <?php
          $pts = []; $steps = 8;
          for ($i=0;$i<=$steps;$i++){ $frac = $steps>0 ? $i/$steps : 0; $val = min(100, $overallPct * (0.3 + 0.7*$frac)); $x = $i*(200/$steps); $y = 55 - ($val/100*45); $pts[] = "$x,$y"; }
          $path = 'M'.implode(' L',$pts);
          ?>
          <path d="<?= $path ?>" fill="none" stroke="#c8a84b" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      </div>
    </div>

    <div class="mid-card prof-card">
      <div class="prof-head">
        <?php if($userPicUrl): ?><img src="<?= e($userPicUrl) ?>" alt="" style="width:42px;height:42px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        <?php else: ?><div class="prof-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div><?php endif; ?>
        <div><div class="prof-name"><?= e($user['name']) ?></div><div class="prof-role">Student<?= $mySubject?' · '.e($mySubject['name']):'' ?></div></div>
      </div>
      <div class="prof-desc">Keep learning to unlock more badges and certificates.</div>
      <div class="prof-bar-row"><span class="prof-bar-label">Overall Completion</span><span class="prof-bar-pct"><?= $overallPct ?>%</span></div>
      <div class="prof-bar-track"><div class="prof-bar-fill" style="width:<?= $overallPct ?>%"></div></div>
      <a href="dashboard.php?tab=levels" class="prof-btn">📖 Continue Learning</a>
    </div>
  </div>

  <div class="learn-card">
    <div class="learn-head">
      <div class="learn-title"><?= $activeTab==='levels'?'My Learning':($activeTab==='badges'?'My Badges':'My Certificates') ?></div>
      <div class="learn-tabs">
        <a href="dashboard.php?tab=levels" class="learn-tab <?= $activeTab==='levels'?'active':'' ?>">Levels</a>
        <a href="dashboard.php?tab=badges" class="learn-tab <?= $activeTab==='badges'?'active':'' ?>">Badges (<?= $totalBadges ?>)</a>
        <a href="dashboard.php?tab=certs" class="learn-tab <?= $activeTab==='certs'?'active':'' ?>">Certificates (<?= $totalCerts ?>)</a>
      </div>
    </div>

    <?php if ($activeTab==='levels'): ?>
      <?php if (empty($levels)): ?>
      <div class="empty-tab2"><div class="ei2">📊</div><p><?= $mySubject ? 'No levels available yet for '.e($mySubject['name']).'.' : 'No subject selected. Contact admin.' ?></p></div>
      <?php endif; ?>
      <?php foreach ($levels as $lv): ?>
      <details class="level-item <?= !$lv['can_access']?'locked':'' ?>" <?= ($lv['can_access'] && $lv['id']===$defaultOpenLevelId) ? 'open' : '' ?>>
        <summary>
          <div class="lvl-icon" style="background:<?= e($lv['color']) ?>22"><?= e($lv['icon']) ?></div>
          <div class="lvl-info">
            <div class="lvl-title">
              Level <?= $lv['order_index'] ?>: <?= e($lv['title']) ?>
              <?php if($lv['completed']): ?><span style="color:var(--green);font-size:.72rem">✓ Completed</span><?php endif; ?>
              <?php if($lv['has_cert']): ?><span style="color:var(--gold-dark);font-size:.72rem">🎓 Certified</span><?php endif; ?>
            </div>
            <div class="lvl-sub"><?= e($lv['description']) ?></div>
          </div>
          <div class="lvl-right">
            <?php if(!$lv['can_access']): ?><span class="lvl-pill pill-locked">🔒 Locked</span>
            <?php elseif($lv['completed']): ?><span class="lvl-pill pill-done">✅ Complete</span>
            <?php else: ?><span class="lvl-pill pill-active">📖 In Progress</span>
            <?php endif; ?>
            <span class="lvl-chev">›</span>
          </div>
        </summary>

        <?php if($lv['can_access']): ?>
        <div class="units-body">
          <?php foreach($lv['units'] as $u): ?>
          <div class="unit-blk <?= $u['completed']?'completed':'' ?>">
            <div class="unit-head2">
              <div class="unit-bicon"><?= e($u['badge_icon']) ?></div>
              <div class="unit-info2">
                <div class="unit-title2">
                  <?= e($u['title']) ?>
                  <?php if($u['has_badge']): ?><span class="badge-disp" style="background:<?= e($u['badge_color']) ?>20;border-color:<?= e($u['badge_color']) ?>;color:<?= e($u['badge_color']) ?>"><?= e($u['badge_icon']) ?> <?= e($u['badge_name']) ?></span><?php endif; ?>
                </div>
                <div class="unit-sub2"><?= e($u['description']) ?></div>
              </div>
              <div class="uprog">
                <div class="uprog-bar"><div class="uprog-fill" style="width:<?= $u['lessons_total']>0?round(($u['lessons_done']/$u['lessons_total'])*100):0 ?>%"></div></div>
                <span class="uprog-txt"><?= $u['lessons_done'] ?>/<?= $u['lessons_total'] ?></span>
              </div>
            </div>

            <?php if($u['can_access']): ?>
            <div class="lessons2">
              <?php
              $lessons = getLessonsForUnit($u['id']);
              foreach($lessons as $lesson):
                $canAcc = canAccessLesson($user['id'], $lesson['id']);
                $prog   = getProgress($user['id'], $lesson['id']);
                $isDone = $prog && (int)$prog['admin_approved']===1;
                $isPend = $prog && (int)$prog['quiz_submitted']===1 && !$isDone;
                $isQuiz = $prog && (int)$prog['video_watched']===1 && (int)$prog['quiz_submitted']===0;
                $rowClass = 'lrow'.($isDone?' done-r':'').(!$canAcc?' locked-r':'');
              ?>
              <?php if ($canAcc): ?><a href="lesson.php?id=<?= (int)$lesson['id'] ?>" class="<?= $rowClass ?>">
              <?php else: ?><div class="<?= $rowClass ?>"><?php endif; ?>
                <div class="lnum"><?= $isDone?'✓':$lesson['order_index'] ?></div>
                <div class="lrow-title"><?= e($lesson['title']) ?></div>
                <?php if($isDone): ?><span class="lstatus s-done">✓ Done</span>
                <?php elseif($isPend): ?><span class="lstatus s-pending">⏳ Pending</span>
                <?php elseif($isQuiz): ?><span class="lstatus s-quiz">📝 Quiz</span>
                <?php elseif(!$canAcc): ?><span class="lstatus s-locked">🔒</span>
                <?php else: ?><span class="lstatus s-watch">▶ Watch</span>
                <?php endif; ?>
              <?php echo $canAcc ? '</a>' : '</div>'; ?>
              <?php endforeach; ?>
            </div>

            <?php if($u['exam_id']): ?>
            <?php $examUnlocked = areAllLessonsCompleted($user['id'],$u['id']); ?>
            <?php $examRowClass = 'exam-r'.($u['exam_passed']?' exam-p':'').(!$examUnlocked?' locked-r':''); ?>
            <?php if ($examUnlocked): ?><a href="exam.php?unit_id=<?= (int)$u['id'] ?>" class="<?= $examRowClass ?>">
            <?php else: ?><div class="<?= $examRowClass ?>"><?php endif; ?>
              <div class="exam-icon2">📝</div>
              <div class="exam-info2"><div class="exam-t2">Unit Final Exam</div><div class="exam-s2"><?= $u['exam_passed']?'✅ Passed':'Complete all lessons to unlock' ?></div></div>
              <?php if($u['exam_passed']): ?><span class="lstatus s-done">Passed ✓</span>
              <?php elseif($examUnlocked): ?><span class="lstatus s-quiz">Take Exam →</span>
              <?php else: ?><span class="lstatus s-locked">🔒</span>
              <?php endif; ?>
            <?php echo $examUnlocked ? '</a>' : '</div>'; ?>
            <?php endif; ?>
            <?php else: ?>
            <div style="padding:12px 18px 14px;color:var(--muted);font-size:.8rem">🔒 Complete the previous unit to unlock this one.</div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>

          <?php if($lv['completed'] && !$lv['has_cert']): ?>
          <div style="padding:12px 0 4px;text-align:center;color:var(--muted);font-size:.8rem">🎉 Level complete! Your instructor will issue your certificate shortly.</div>
          <?php elseif($lv['has_cert']): ?>
          <div style="padding:12px 0 4px;text-align:center"><a href="dashboard.php?tab=certs" class="btn2 btn2-gold">🎓 View Certificate</a></div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="locked-note">🔒 Complete the previous level to unlock this one.</div>
        <?php endif; ?>
      </details>
      <?php endforeach; ?>

    <?php elseif ($activeTab==='badges'): ?>
      <?php if(empty($badges)): ?>
      <div class="empty-tab2"><div class="ei2">🏅</div><p>No badges earned yet. Complete a unit exam to earn your first badge!</p></div>
      <?php else: ?>
      <div class="badges-grid">
        <?php foreach($badges as $b): ?>
        <div class="badge-c" style="border-color:<?= e($b['badge_color']) ?>40">
          <span class="badge-c-icon"><?= e($b['badge_icon']) ?></span>
          <div class="badge-c-name" style="color:<?= e($b['badge_color']) ?>"><?= e($b['badge_name']) ?></div>
          <div class="badge-c-unit"><?= e($b['unit_title']) ?></div>
          <div class="badge-c-date">Earned <?= date('M j, Y',strtotime($b['awarded_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    <?php elseif ($activeTab==='certs'): ?>
      <?php if(empty($certs)): ?>
      <div class="empty-tab2"><div class="ei2">🎓</div><p>No certificates yet. Complete a full level to receive one.</p></div>
      <?php else: ?>
      <div class="certs-grid">
        <?php foreach($certs as $c): ?>
        <div class="cert-c">
          <div class="cert-c-top" style="background:linear-gradient(135deg,<?= e($c['level_color']??'#0d2137') ?>,#1a3a5a)">
            <div class="cert-c-icon"><?= e($c['level_icon']??'🎓') ?></div>
            <div class="cert-c-title"><?= e($c['course_name']) ?></div>
            <div class="cert-c-sub"><?= $c['cert_type']==='combined'?'Combined All Levels':'Level Certificate' ?></div>
          </div>
          <div class="cert-c-body">
            <div class="cert-c-date">Issued: <?= date('F j, Y',strtotime($c['issued_at'])) ?></div>
            <div style="margin-top:10px"><a href="my-certificate.php?id=<?= (int)$c['id'] ?>" target="_blank" class="btn2 btn2-gold">🖨 View &amp; Print</a></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
