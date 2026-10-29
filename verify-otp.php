<?php
require_once __DIR__ . '/includes/auth.php'; startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }
$email = $_SESSION['otp_email'] ?? '';
if (!$email) { header('Location: login.php'); exit; }

$msg = $_SESSION['otp_flash'] ?? ''; unset($_SESSION['otp_flash']);
$err = ''; $debug = $_SESSION['otp_debug'] ?? ''; unset($_SESSION['otp_debug']);

// Came from login.php with an unverified account → send a fresh code once
if (!empty($_SESSION['otp_autosend'])) {
    unset($_SESSION['otp_autosend']);
    $r = otp_send($email);
    if ($r['ok']) { $msg = 'We emailed you a new 6-digit code.'; } else { $err = $r['error']; }
    $debug = $r['debug_code'] ?? $debug;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ent_csrf_ok()) { $err = 'Session expired. Please try again.'; }
    elseif (isset($_POST['resend'])) {
        $r = otp_send($email);
        if ($r['ok']) { $msg = 'A new code is on its way.'; } else { $err = $r['error']; }
        $debug = $r['debug_code'] ?? '';
    } else {
        $r = otp_verify($email, preg_replace('/\D/', '', (string)($_POST['code'] ?? '')));
        if ($r['ok']) {
            unset($_SESSION['otp_email']);
            notifyWelcome($r['user_id']);                       // WhatsApp welcome (silently skipped if Twilio isn't set up)
            header('Location: login.php?verified=1'); exit;
        }
        $err = $r['error'];
    }
}
$masked = preg_replace('/(?<=.).(?=[^@]*@)/', '*', $email);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Verify email — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800;900&display=swap" rel="stylesheet">
<style>*{box-sizing:border-box;margin:0;padding:0}body{font-family:'Nunito',sans-serif;background:#f4f8f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;color:#0d2137}
.card{background:#fff;border-radius:18px;padding:40px 34px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(13,33,55,.1);text-align:center}
h2{font-size:1.5rem;font-weight:900;margin-bottom:6px}p{color:#6b7e8f;font-size:.9rem;margin-bottom:18px}
input.code{width:100%;font-size:2rem;letter-spacing:12px;text-align:center;padding:12px;border:2px solid #dde6ec;border-radius:10px;font-family:inherit;outline:none}input.code:focus{border-color:#00897b}
.btn{width:100%;margin-top:14px;padding:13px;border:none;border-radius:9px;background:#0d2137;color:#fff;font:800 .95rem 'Nunito',sans-serif;cursor:pointer}.btn:hover{background:#c8a84b}
.link{background:none;border:none;color:#00897b;font:700 .85rem 'Nunito',sans-serif;cursor:pointer;margin-top:16px}
.ok{background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;padding:10px;border-radius:8px;font-size:.85rem;margin-bottom:14px}.er{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;padding:10px;border-radius:8px;font-size:.85rem;margin-bottom:14px}
.dbg{background:#fffbeb;border:2px dashed #f59e0b;padding:8px;border-radius:8px;font-size:.8rem;margin-bottom:14px}</style></head>
<body><div class="card">
  <div style="font-size:2.2rem;margin-bottom:6px">✉️</div>
  <h2>Check your email</h2><p>Enter the 6-digit code we sent to <strong><?= e($masked) ?></strong> to activate your account.</p>
  <?php if ($msg): ?><div class="ok"><?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="er"><?= e($err) ?></div><?php endif; ?>
  <?php if ($debug): ?><div class="dbg">TEST MODE — your code is <strong><?= e($debug) ?></strong></div><?php endif; ?>
  <form method="POST"><?= ent_csrf_field() ?>
    <input class="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="••••••" autofocus required>
    <button class="btn">Activate my account →</button>
  </form>
  <form method="POST"><?= ent_csrf_field() ?><button class="link" name="resend" value="1">Didn't get it? Resend code</button></form>
  <p style="margin-top:14px;font-size:.78rem">Check your spam folder too. <a href="login.php" style="color:#6b7e8f">Back to login</a></p>
</div></body></html>
