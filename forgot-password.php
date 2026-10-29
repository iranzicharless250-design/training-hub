<?php
require_once __DIR__ . '/includes/auth.php';
startSession();
if (isLoggedIn()) { header('Location: '.(isAdmin()?'admin/index.php':'dashboard.php')); exit; }

$msg=''; $err=''; $resetLink='';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $email = trim($_POST['email'] ?? '');
    if (!$email) {
        $err = 'Please enter your email address.';
    } else {
        $token = createPasswordResetToken($email);
        // Always show the same generic message, whether or not the email exists —
        // avoids leaking which emails are registered.
        $msg = 'If an account exists with that email, a password reset link has been generated below.';
        if ($token) {
            $resetLink = SITE_URL . '/reset-password.php?token=' . $token;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Forgot Password — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--navy:#0d2137;--gold:#c8a84b;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9;}
body{font-family:'Nunito',sans-serif;background:var(--cream);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;}
.card{background:#fff;border-radius:18px;padding:44px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(13,33,55,.1);}
.brand{font-weight:900;font-size:1.2rem;color:var(--navy);display:flex;align-items:center;gap:8px;margin-bottom:24px;}
.bd{width:9px;height:9px;background:var(--gold);border-radius:50%;}
h2{font-size:1.5rem;font-weight:900;color:var(--navy);margin-bottom:6px;}
.sub{color:var(--muted);margin-bottom:22px;font-size:.88rem;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:.74rem;font-weight:800;color:var(--navy);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em;}
.fg input{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:8px;font-size:.93rem;font-family:'Nunito',sans-serif;color:var(--navy);background:#faf9f7;outline:none;transition:.2s;}
.fg input:focus{border-color:var(--gold);background:#fff;}
.error{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;padding:11px 15px;border-radius:8px;font-size:.84rem;margin-bottom:14px;}
.success{background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;padding:11px 15px;border-radius:8px;font-size:.84rem;margin-bottom:14px;}
.reset-box{background:#fffbeb;border:2px solid #fde68a;border-radius:10px;padding:16px;margin-bottom:16px;}
.reset-box .lbl{font-size:.72rem;font-weight:800;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-bottom:6px;}
.reset-box a{word-break:break-all;color:#1d4ed8;font-size:.82rem;font-weight:700;text-decoration:none;}
.reset-box a:hover{text-decoration:underline;}
.reset-note{font-size:.72rem;color:#78350f;margin-top:8px;}
.btn{width:100%;padding:12px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:.92rem;font-weight:800;cursor:pointer;transition:.15s;}
.btn:hover{background:var(--gold);}
.back{display:block;text-align:center;margin-top:18px;color:var(--muted);text-decoration:none;font-size:.85rem;}
.back:hover{color:var(--navy);}
</style>
</head>
<body>
<div class="card">
  <div class="brand"><div class="bd"></div><?= e(SITE_NAME) ?></div>
  <h2>Forgot Password?</h2>
  <div class="sub">Enter your email and we'll help you reset your password.</div>

  <?php if($err): ?><div class="error">⚠️ <?= e($err) ?></div><?php endif; ?>
  <?php if($msg): ?><div class="success">✅ <?= e($msg) ?></div><?php endif; ?>

  <?php if($resetLink): ?>
  <div class="reset-box">
    <div class="lbl">🔗 Your Reset Link</div>
    <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
    <div class="reset-note">This link expires in 1 hour. In a live deployment this would be emailed to you automatically — for now it's shown here directly.</div>
  </div>
  <?php endif; ?>

  <?php if (!$resetLink): ?>
  <form method="POST">
    <div class="fg"><label>Email Address</label><input type="email" name="email" placeholder="you@email.com" value="<?= e($_POST['email']??'') ?>" required></div>
    <button type="submit" class="btn">Send Reset Link →</button>
  </form>
  <?php endif; ?>

  <a href="login.php" class="back">← Back to Login</a>
</div>
</body>
</html>
