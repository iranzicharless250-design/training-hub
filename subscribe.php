<?php
/**
 * subscribe.php — "Upgrade to Plus": Pay button + receipt upload (phone camera/gallery or computer file).
 * Self-contained: writes straight to subscription_requests (needs patch_enterprise.sql once).
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin() || isTeacher() || (($_SESSION['role'] ?? '') === 'manager')) { header('Location: dashboard.php'); exit; }

$user = getCurrentUser(); $db = getDB();
$msg = ''; $err = '';
$priceInfo = getSetting('plus_price_info', 'Contact the admin on WhatsApp for pricing and payment details.');
$waNum = preg_replace('/\D/', '', defined('ADMIN_WHATSAPP') ? ADMIN_WHATSAPP : '');
$waUrl = 'https://wa.me/' . $waNum . '?text=' . urlencode("Hi Admin! I'm {$user['name']} and I'd like to subscribe to Plus. Please share payment details.");

// "Pay now": opens the phone dialer with your Mobile Money USSD code (optional, set PAY_USSD in notify_config.php)
$payHref = (PAY_USSD !== '' && PAY_NUMBER !== '') ? 'tel:' . str_replace('#', '%23', str_replace('{CODE}', PAY_NUMBER, PAY_USSD)) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_receipt'])) {
    $months = in_array((int)($_POST['months'] ?? 1), [1, 3, 12], true) ? (int)$_POST['months'] : 1;
    $f = $_FILES['receipt'] ?? null;
    if (!ent_csrf_ok())                                   $err = 'Session expired. Please reload the page and try again.';
    elseif (!$f || $f['error'] === UPLOAD_ERR_NO_FILE)    $err = 'Please choose your payment screenshot first.';
    elseif ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 8 * 1024 * 1024) $err = 'The file is too large (max 8 MB) or failed to upload.';
    else {
        // Trust the real file contents, not the file name
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
        if (!$ext) { $err = 'Please upload a JPG, PNG or WebP screenshot of your receipt.'; }
        else {
            $dir = __DIR__ . '/uploads/payment-receipts/';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if (!file_exists($dir . '.htaccess')) @file_put_contents($dir . '.htaccess', "<FilesMatch \"\\.(php|phtml|php5|phar)$\">\nRequire all denied\n</FilesMatch>\n");
            $rel = 'uploads/payment-receipts/rcpt_' . (int)$user['id'] . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/' . $rel)) { $err = 'Could not save the file. Check that uploads/payment-receipts/ is writable.'; }
            else {
                // One open request per student: a new upload replaces the pending one
                $p = $db->prepare("SELECT id FROM subscription_requests WHERE user_id=? AND status='pending' ORDER BY id DESC LIMIT 1");
                $p->execute([$user['id']]); $pid = $p->fetchColumn();
                if ($pid) $db->prepare("UPDATE subscription_requests SET months=?, payment_screenshot=?, requested_at=NOW() WHERE id=?")->execute([$months, $rel, $pid]);
                else      $db->prepare("INSERT INTO subscription_requests (user_id, months, payment_screenshot) VALUES (?,?,?)")->execute([$user['id'], $months, $rel]);
                try { $db->prepare("UPDATE users SET subscription_requested_at=NOW() WHERE id=?")->execute([$user['id']]); } catch (Throwable $e) {}
                $msg = 'Receipt received! The admin will review it and activate Plus — usually within a few hours.';
            }
        }
    }
}
$last = $db->prepare("SELECT * FROM subscription_requests WHERE user_id=? ORDER BY id DESC LIMIT 1"); $last->execute([$user['id']]); $last = $last->fetch();
$subscribed = function_exists('isUserSubscribed') ? isUserSubscribed($user) : !empty($user['is_subscribed']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Upgrade to Plus — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}:root{--navy:#0d2137;--teal:#00897b;--gold:#c8a84b;--green:#16a34a;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9}
body{font-family:'Nunito',sans-serif;background:var(--cream);color:var(--navy)}
nav{background:var(--navy);height:60px;padding:0 24px;display:flex;align-items:center;justify-content:space-between}nav a{color:#fff;text-decoration:none;font-weight:800}nav .b{color:#7a9bb0;font-size:.82rem;border:1px solid #2a3a4a;padding:6px 14px;border-radius:7px;font-weight:600}
.main{max-width:640px;margin:0 auto;padding:28px 16px 60px}
.hero{background:linear-gradient(135deg,var(--navy),#1a3a5a);border-radius:18px;padding:32px 24px;text-align:center;color:#fff;margin-bottom:20px}
.hero .pl{display:inline-block;background:var(--gold);padding:5px 16px;border-radius:20px;font-weight:800;font-size:.78rem;margin-bottom:12px}.hero h1{font-family:'Playfair Display',serif;font-size:1.8rem;margin-bottom:6px}.hero p{color:#9ab0c0;font-size:.92rem}
.box{background:#fff;border:2px solid var(--border);border-radius:14px;padding:22px;margin-bottom:16px}.box h2{font-size:1rem;font-weight:900;margin-bottom:4px;display:flex;align-items:center;gap:10px}
.n{width:26px;height:26px;border-radius:50%;background:var(--navy);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem}
.sub{color:var(--muted);font-size:.85rem;margin-bottom:14px}.price{background:var(--cream);border-radius:10px;padding:14px;font-size:.92rem;line-height:1.6;white-space:pre-wrap;margin-bottom:14px}
.btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:14px;border-radius:10px;border:none;font:800 .95rem 'Nunito',sans-serif;cursor:pointer;text-decoration:none;margin-top:10px}
.pay{background:var(--green);color:#fff}.wa{background:#25d366;color:#fff}.up{background:var(--navy);color:#fff}.up:hover{background:var(--teal)}.send{background:var(--gold);color:#fff}.send:disabled{opacity:.45;cursor:not-allowed}
.two{display:grid;grid-template-columns:1fr 1fr;gap:10px}.two .btn{margin-top:0}
.dur{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px}.dur label{border:2px solid var(--border);border-radius:10px;padding:12px 6px;text-align:center;cursor:pointer;font-weight:800}
.dur input{display:none}.dur label:has(input:checked){background:var(--navy);color:#fff;border-color:var(--navy)}.dur small{display:block;font-weight:600;opacity:.75}
#prev{display:none;margin-top:12px;text-align:center}#prev img{max-width:100%;max-height:260px;border-radius:10px;border:2px solid var(--border)}#fn{font-size:.8rem;color:var(--muted);margin-top:6px}
.ok{background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;padding:12px;border-radius:10px;margin-bottom:16px;font-size:.9rem}.er{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;padding:12px;border-radius:10px;margin-bottom:16px;font-size:.9rem}
.st{padding:14px;border-radius:10px;margin-bottom:16px;font-weight:700;text-align:center;font-size:.9rem}.s-ok{background:#f0fdf4;color:var(--green);border:2px solid #bbf7d0}.s-pend{background:#fffbeb;color:#92400e;border:2px solid #fde68a}.s-no{background:#fef2f2;color:#991b1b;border:2px solid #fecaca}
.info{display:none;margin-top:10px;font-size:.88rem;background:var(--cream);padding:12px;border-radius:10px}</style></head><body>
<nav><a href="dashboard.php"><?= e(SITE_NAME) ?></a><a class="b" href="dashboard.php">← Dashboard</a></nav>
<div class="main">
  <div class="hero"><span class="pl">⭐ PLUS</span><h1>Unlock Every Lesson</h1><p>Free accounts get lessons 1–2 of each unit. Plus opens them all.</p></div>
  <?php if ($msg): ?><div class="ok">✅ <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="er">⚠️ <?= e($err) ?></div><?php endif; ?>

  <?php if ($subscribed): ?>
    <div class="st s-ok">✅ You're a Plus member — active until <?= e(date('F j, Y', strtotime($user['subscription_expires'] ?? 'today'))) ?>.</div>
  <?php elseif ($last && $last['status'] === 'pending' && !empty($last['payment_screenshot'])): ?>
    <div class="st s-pend">⏳ Receipt under review (sent <?= e(date('M j, g:ia', strtotime($last['requested_at']))) ?>). You can upload a new one below to replace it.</div>
  <?php elseif ($last && $last['status'] === 'rejected'): ?>
    <div class="st s-no">❌ Your last receipt was not accepted. Please check the payment and upload a clear screenshot.</div>
  <?php endif; ?>

  <?php if (!$subscribed): ?>
  <div class="box">
    <h2><span class="n">1</span> Pay</h2><div class="sub">Send the money, then keep the confirmation message/screenshot.</div>
    <div class="price"><?= e($priceInfo) ?></div>
    <?php if ($payHref): ?><a class="btn pay" href="<?= e($payHref) ?>">💳 Pay now with Mobile Money</a>
    <?php else: ?><button type="button" class="btn pay" onclick="document.getElementById('pinfo').style.display='block'">💳 Pay now — show me how</button><?php endif; ?>
    <div class="info" id="pinfo"><?php if (PAY_NUMBER !== ''): ?>Send to: <strong><?= e(PAY_NUMBER) ?></strong><br><?php endif; ?>Use the amount and number written above, then come back and upload your receipt in step 2.</div>
    <a class="btn wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">📲 Ask the admin on WhatsApp</a>
  </div>

  <div class="box">
    <h2><span class="n">2</span> Upload your receipt</h2><div class="sub">Works from your phone (camera or gallery) or your computer. The admin checks it and activates Plus — even if they don't have the phone that received the money.</div>
    <form method="POST" enctype="multipart/form-data" id="rf"><?= ent_csrf_field() ?>
      <div class="dur"><label><input type="radio" name="months" value="1" checked>1<small>month</small></label><label><input type="radio" name="months" value="3">3<small>months</small></label><label><input type="radio" name="months" value="12">12<small>months</small></label></div>
      <input type="file" id="f1" name="receipt" accept="image/*" hidden>
      <input type="file" id="f2" accept="image/*" capture="environment" hidden>
      <div class="two">
        <button type="button" class="btn up" onclick="document.getElementById('f1').click()">📁 Choose file</button>
        <button type="button" class="btn up" onclick="document.getElementById('f2').click()">📷 Take photo</button>
      </div>
      <div id="prev"><img id="pi" alt="Receipt preview"><div id="fn"></div></div>
      <button class="btn send" name="send_receipt" value="1" id="sb" disabled>✅ Send receipt for approval</button>
    </form>
  </div>
  <?php endif; ?>
</div>
<script>
// Keep one real <input name="receipt">: copy whichever file was picked (gallery/computer or camera) into it
const f1=document.getElementById('f1'), f2=document.getElementById('f2');
function show(file){
  if(!file) return;
  document.getElementById('pi').src=URL.createObjectURL(file);
  document.getElementById('fn').textContent=file.name+' ('+Math.round(file.size/1024)+' KB)';
  document.getElementById('prev').style.display='block';
  document.getElementById('sb').disabled=false;
}
f1.addEventListener('change',()=>show(f1.files[0]));
f2.addEventListener('change',()=>{ if(f2.files[0]){ const dt=new DataTransfer(); dt.items.add(f2.files[0]); f1.files=dt.files; show(f2.files[0]); }});
</script></body></html>
