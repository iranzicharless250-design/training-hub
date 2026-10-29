<?php
require_once __DIR__.'/includes/auth.php'; requireLogin();
$user=getCurrentUser(); $db=getDB(); $roomCode=trim($_GET['room']??'');
if(!$roomCode){header('Location: live-sessions.php');exit;}
$s=$db->prepare("SELECT ls.*,u.name AS host_name FROM live_sessions ls JOIN users u ON ls.host_id=u.id WHERE ls.room_code=?"); $s->execute([$roomCode]); $session=$s->fetch();
if(!$session){header('Location: live-sessions.php');exit;}
$cleanRoom='TrainHub-'.preg_replace('/[^a-zA-Z0-9]/','',''.$roomCode);
$isHost=isAdmin()||$session['host_id']==$user['id'];
if($session){$db->prepare("INSERT IGNORE INTO session_registrations (session_id,user_id,attended,joined_at) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE attended=1,joined_at=NOW()")->execute([$session['id'],$user['id']]);}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= e($session['title']) ?> — Live</title>
<script src='https://meet.jit.si/external_api.js'></script>
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}html,body{height:100%;overflow:hidden;font-family:'Nunito',sans-serif;background:#070710;color:#fff;}
.layout{display:grid;grid-template-rows:52px 1fr;height:100vh;}
.topbar{background:#0d1a2a;border-bottom:1px solid #1a2a3a;padding:0 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;}
.topbar-brand{font-weight:900;font-size:.95rem;color:#fff;text-decoration:none;}
.topbar-brand span{color:#f5a623;}
.sess-name{font-size:.82rem;color:#5a7a90;flex:1;padding:0 14px;}
.live-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#f87171;padding:3px 11px;border-radius:20px;font-size:.7rem;font-weight:700;}
.live-chip-d{width:5px;height:5px;background:#ef4444;border-radius:50%;animation:blink 1s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.3}}
.leave-btn{background:#ef4444;color:#fff;border:none;padding:6px 15px;border-radius:7px;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;}
.leave-btn:hover{background:#dc2626;}
.user-pill{display:flex;align-items:center;gap:7px;font-size:.8rem;color:#7a9bb0;}
.ua{width:26px;height:26px;border-radius:50%;background:#f5a623;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.7rem;flex-shrink:0;}
#jitsi-container{width:100%;height:100%;position:relative;}
.loading{position:absolute;inset:0;background:#070710;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:10;}
.spinner{width:44px;height:44px;border:3px solid #1a2a3a;border-top-color:#f5a623;border-radius:50%;animation:spin .8s linear infinite;margin-bottom:18px;}
@keyframes spin{to{transform:rotate(360deg)}}</style>
</head><body><div class="layout">
<div class="topbar">
  <a href="live-sessions.php" class="topbar-brand">Train<span>Hub</span></a>
  <div class="sess-name"><?= e($session['title']) ?></div>
  <div class="live-chip"><div class="live-chip-d"></div> LIVE</div>
  <div class="user-pill"><div class="ua"><?= strtoupper(substr($user['name'],0,1)) ?></div><?= e($user['name']) ?><?php if($isHost): ?> <span style="color:#f5a623;font-size:.68rem;font-weight:700">HOST</span><?php endif; ?></div>
  <a href="live-sessions.php" class="leave-btn">← Leave</a>
</div>
<div id="jitsi-container"><div class="loading" id="loading"><div class="spinner"></div><div style="color:#5a7a90;font-size:.88rem">Connecting to <?= e($session['title']) ?>…</div></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const api=new JitsiMeetExternalAPI('meet.jit.si',{
    roomName:'<?= e($cleanRoom) ?>',width:'100%',height:'100%',
    parentNode:document.getElementById('jitsi-container'),
    userInfo:{displayName:'<?= e($user['name']) ?>',email:'<?= e($user['email']) ?>'},
    configOverwrite:{startWithAudioMuted:true,startWithVideoMuted:false,disableDeepLinking:true,enableWelcomePage:false,prejoinPageEnabled:false,subject:'<?= e($session['title']) ?>'},
    interfaceConfigOverwrite:{SHOW_JITSI_WATERMARK:false,APP_NAME:'TrainHub Live'}
  });
  api.addEventListener('videoConferenceJoined',()=>document.getElementById('loading').style.display='none');
  api.addEventListener('readyToClose',()=>window.location.href='live-sessions.php');
});
</script></body></html>
