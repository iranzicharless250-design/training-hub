<?php
require_once __DIR__.'/includes/auth.php';
requireLogin();
if(isAdmin()){header('Location: admin/sessions.php');exit;}
$user=getCurrentUser(); $db=getDB();
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['register_session'])){$sid=(int)($_POST['session_id']??0);if($sid){$db->prepare("INSERT IGNORE INTO session_registrations (session_id,user_id) VALUES (?,?)")->execute([$sid,$user['id']]);}header('Location: live-sessions.php?reg=1');exit;}
$sessions=$db->query("SELECT ls.*,u.name AS host_name,(SELECT id FROM session_registrations WHERE session_id=ls.id AND user_id={$user['id']}) AS my_reg FROM live_sessions ls JOIN users u ON ls.host_id=u.id WHERE ls.status!='cancelled' ORDER BY ls.scheduled_at ASC")->fetchAll();
$live=array_filter($sessions,fn($s)=>$s['status']==='live');
$upcoming=array_filter($sessions,fn($s)=>$s['status']==='upcoming');
$ended=array_filter($sessions,fn($s)=>$s['status']==='ended');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Live Sessions — <?= SITE_NAME ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}:root{--navy:#0d2137;--teal:#00897b;--orange:#f5a623;--green:#16a34a;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9;--card:#fff;}
body{font-family:'Nunito',sans-serif;background:var(--cream);color:var(--navy);}
nav{background:var(--navy);height:64px;padding:0 28px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.nav-brand{font-family:'Playfair Display',serif;font-weight:900;font-size:1.1rem;color:#fff;text-decoration:none;}
.back-btn{color:#7a9bb0;text-decoration:none;font-size:.82rem;border:1px solid #2a3a4a;padding:7px 16px;border-radius:7px;}
.back-btn:hover{color:#fff;}
.main{max-width:1000px;margin:0 auto;padding:36px 20px;}
.page-head{background:var(--navy);border-radius:16px;padding:32px 40px;margin-bottom:32px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;}
.page-head h1{font-family:'Playfair Display',serif;font-size:1.8rem;font-weight:900;color:#fff;}
.page-head p{color:#5a7a90;margin-top:4px;font-size:.88rem;}
.live-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:8px 18px;border-radius:20px;font-size:.78rem;font-weight:700;}
.live-d{width:7px;height:7px;background:#ef4444;border-radius:50%;animation:blink 1.2s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.2}}
.sec-title{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:700;margin-bottom:16px;display:flex;align-items:center;gap:8px;}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:36px;}
.sess-card{background:var(--card);border-radius:14px;border:2px solid var(--border);overflow:hidden;transition:.2s;}
.sess-card:hover{border-color:var(--teal);transform:translateY(-2px);}
.sess-card.is-live{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.1);}
.sess-top{padding:22px 22px 16px;}
.sess-status{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.7rem;font-weight:700;margin-bottom:11px;}
.s-live{background:#fef2f2;color:#dc2626;}.s-upcoming{background:#fff7ed;color:#c2410c;}.s-ended{background:#f9fafb;color:#6b7280;}
.sess-title{font-family:'Playfair Display',serif;font-weight:700;font-size:1rem;margin-bottom:7px;line-height:1.3;}
.sess-desc{color:var(--muted);font-size:.8rem;line-height:1.5;}
.sess-bottom{padding:14px 22px;border-top:1px solid var(--border);background:#fafaf8;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;}
.sess-meta{font-size:.74rem;color:var(--muted);}
.btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:7px;font-size:.78rem;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:.15s;}
.btn-teal{background:var(--teal);color:#fff;}.btn-teal:hover{background:#005f56;}
.btn-red{background:#ef4444;color:#fff;animation:pulse 2s infinite;}
@keyframes pulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.4)}50%{box-shadow:0 0 0 6px rgba(239,68,68,0)}}
.btn-reg{background:var(--navy);color:#fff;}.btn-reg:hover{background:var(--orange);}
.btn-done{background:#f0fdf4;color:var(--green);border:1px solid #bbf7d0;}
.flash{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:.86rem;background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;}
.empty{text-align:center;padding:40px;color:var(--muted);}
</style></head><body>
<nav>
  <a href="dashboard.php" class="nav-brand"><?= SITE_NAME ?></a>
  <a href="dashboard.php" class="back-btn">← Dashboard</a>
</nav>
<div class="main">
  <div class="page-head">
    <div><h1>Live Sessions</h1><p>Join scheduled video training sessions with your trainer.</p></div>
    <div class="live-badge"><div class="live-d"></div> Powered by Jitsi Meet</div>
  </div>
  <?php if(isset($_GET['reg'])): ?><div class="flash">✅ Registered successfully! You'll be able to join when the session goes live.</div><?php endif; ?>
  <?php if(!empty($live)): ?><div class="sec-title">🔴 Live Now <span style="background:#ef4444;color:#fff;padding:2px 10px;border-radius:20px;font-size:.7rem;font-family:'Nunito',sans-serif;font-weight:800;animation:blink 1s infinite">LIVE</span></div>
  <div class="grid"><?php foreach($live as $s): $jUrl=getJitsiUrl($s['room_code'],$user['name']); ?>
  <div class="sess-card is-live"><div class="sess-top"><span class="sess-status s-live">🔴 LIVE NOW</span><div class="sess-title"><?= e($s['title']) ?></div><div class="sess-desc"><?= e($s['description']) ?></div></div>
  <div class="sess-bottom"><div class="sess-meta">⏱ <?= $s['duration_minutes'] ?> min</div><a href="video-room.php?room=<?= urlencode($s['room_code']) ?>" class="btn btn-red">📹 Join Now</a></div></div>
  <?php endforeach; ?></div><?php endif; ?>
  <?php if(!empty($upcoming)): ?><div class="sec-title">📅 Upcoming Sessions</div>
  <div class="grid"><?php foreach($upcoming as $s): $dt=new DateTime($s['scheduled_at']); $isReg=!empty($s['my_reg']); ?>
  <div class="sess-card"><div class="sess-top"><span class="sess-status s-upcoming">UPCOMING</span><div class="sess-title"><?= e($s['title']) ?></div><div class="sess-desc"><?= e($s['description']) ?></div></div>
  <div class="sess-bottom"><div class="sess-meta">📅 <?= $dt->format('M j, Y @ g:i A') ?><br>⏱ <?= $s['duration_minutes'] ?> min</div>
  <?php if($isReg): ?><span class="btn btn-done">✓ Registered</span>
  <?php else: ?><form method="POST" style="display:inline"><input type="hidden" name="session_id" value="<?= $s['id'] ?>"><button type="submit" name="register_session" class="btn btn-reg">Register →</button></form><?php endif; ?>
  </div></div><?php endforeach; ?></div><?php endif; ?>
  <?php if(!empty($ended)): ?><div class="sec-title" style="color:var(--muted)">🎬 Past Sessions</div>
  <div class="grid"><?php foreach($ended as $s): $dt=new DateTime($s['scheduled_at']); ?>
  <div class="sess-card" style="opacity:.65"><div class="sess-top"><span class="sess-status s-ended">ENDED</span><div class="sess-title"><?= e($s['title']) ?></div></div>
  <div class="sess-bottom"><div class="sess-meta">📅 <?= $dt->format('M j, Y') ?></div><?php if($s['recording_url']): ?><a href="<?= e($s['recording_url']) ?>" target="_blank" class="btn btn-teal">▶ Recording</a><?php else: ?><span style="font-size:.74rem;color:#aaa">No recording</span><?php endif; ?></div></div>
  <?php endforeach; ?></div><?php endif; ?>
  <?php if(empty($sessions)): ?><div class="empty"><div style="font-size:2.5rem;margin-bottom:12px">📹</div><p>No sessions scheduled yet. Check back soon!</p></div><?php endif; ?>
</div></body></html>
