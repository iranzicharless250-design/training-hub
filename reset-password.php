<?php
require_once __DIR__ . '/includes/auth.php';
startSession();

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$user = getUserByResetToken($token);

$msg=''; $err=''; $done=false;

if (!$user) {
    $err = 'This reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD']==='POST') {
    $newPw     = $_POST['new_password'] ?? '';
    $confirmPw = $_POST['confirm_password'] ?? '';

    if (!$newPw || !$confirmPw) {
        $err = 'Please fill in both password fields.';
    } elseif (strlen($newPw) < 6) {
        $err = 'Password must be at least 6 characters.';
    } elseif ($newPw !== $confirmPw) {
        $err = 'Passwords do not match.';
    } else {
        resetUserPassword($user['id'], $newPw);
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--navy:#0d2137;--gold:#c8a84b;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9;}
body{font-family:'Nunito',sans-serif;background:var(--cream);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px;}
.card{background:#fff;border-radius:18px;padding:44px;width:100%;max-width:420px;box-shadow:0 20px 60px rgba(13,33,55,.1);text-align:left;}
.brand{font-weight:900;font-size:1.2rem;color:var(--navy);display:flex;align-items:center;gap:8px;margin-bottom:24px;}
.bd{width:9px;height:9px;background:var(--gold);border-radius:50%;}
h2{font-size:1.5rem;font-weight:900;color:var(--navy);margin-bottom:6px;}
.sub{color:var(--muted);margin-bottom:22px;font-size:.88rem;}
.fg{margin-bottom:14px;}
.fg label{display:block;font-size:.74rem;font-weight:800;color:var(--navy);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em;}
.fg input{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:8px;font-size:.93rem;font-family:'Nunito',sans-serif;color:var(--navy);background:#faf9f7;outline:none;transition:.2s;}
.fg input:focus{border-color:var(--gold);background:#fff;}
.error{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;padding:11px 15px;border-radius:8px;font-size:.84rem;margin-bottom:14px;}
.success{background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;padding:16px;border-radius:10px;font-size:.86rem;text-align:center;}
.btn{width:100%;padding:12px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:.92rem;font-weight:800;cursor:pointer;transition:.15s;}
.btn:hover{background:var(--gold);}
.back{display:block;text-align:center;margin-top:18px;color:var(--muted);text-decoration:none;font-size:.85rem;}
.back:hover{color:var(--navy);}
.success-icon{font-size:2.2rem;margin-bottom:8px;}
</style>
</head>
<body>
<div class="card">
  <div class="brand"><div class="bd"></div><?= e(SITE_NAME) ?></div>

  <?php if ($done): ?>
    <div class="success">
      <div class="success-icon">✅</div>
      <strong>Password reset successfully!</strong><br>
      You can now log in with your new password.
    </div>
    <a href="login.php" class="btn" style="display:block;text-align:center;text-decoration:none;margin-top:18px;box-sizing:border-box">Go to Login →</a>
  <?php else: ?>
    <h2>Set New Password</h2>
    <div class="sub"><?= $user ? 'Choose a strong new password for <strong>'.e($user['email']).'</strong>.' : 'Enter a valid reset link to continue.' ?></div>
    <?php if($err): ?><div class="error">⚠️ <?= e($err) ?></div><?php endif; ?>
    <?php if ($user): ?>
    <form method="POST">
      <input type="hidden" name="token" value="<?= e($token) ?>">
      <div class="fg"><label>New Password</label><input type="password" name="new_password" placeholder="Min. 6 characters" required></div>
      <div class="fg"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Repeat new password" required></div>
      <button type="submit" class="btn">Reset Password →</button>
    </form>
    <?php else: ?>
    <a href="forgot-password.php" class="btn" style="display:block;text-align:center;text-decoration:none;box-sizing:border-box">Request New Link →</a>
    <?php endif; ?>
  <?php endif; ?>

  <a href="login.php" class="back">← Back to Login</a>
</div>
</body>
</html>
