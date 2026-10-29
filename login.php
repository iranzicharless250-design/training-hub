<?php
require_once __DIR__ . '/includes/auth.php'; startSession();

/** Where each role lands after login */
function landingFor(string $role): string {
    return ['admin' => 'admin/index.php', 'teacher' => 'teacher/index.php', 'manager' => 'manager/index.php'][$role] ?? 'dashboard.php';
}
if (isLoggedIn()) { header('Location: ' . landingFor($_SESSION['role'] ?? 'student')); exit; }

$error = ''; $ok = isset($_GET['verified']) ? 'Email verified — you can now sign in.' : '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $u = loginUser($email, $_POST['password'] ?? '');
    if ($u) {
        if (isset($u['email_verified']) && (int)$u['email_verified'] === 0) {
            // Correct password but account not activated yet → drop the session and go to OTP screen
            $_SESSION = []; session_destroy(); startSession();
            $_SESSION['otp_email'] = $u['email']; $_SESSION['otp_autosend'] = 1;
            header('Location: verify-otp.php'); exit;
        }
        header('Location: ' . landingFor($u['role'])); exit;
    }
    $error = 'Invalid email or password.';
}
$companyName = getSetting('company_name', 'IC Training Platform');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Login — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Playfair+Display:ital,wght@0,700;0,900;1,700&display=swap" rel="stylesheet">
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}:root{--navy:#0d2137;--teal:#00897b;--gold:#c8a84b;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9}
body{font-family:'Nunito',sans-serif;background:var(--cream);min-height:100vh;display:grid;grid-template-columns:1fr 1fr}
.hero{background:var(--navy);display:flex;flex-direction:column;justify-content:center;padding:60px}
.hero-brand{font-family:'Playfair Display',serif;font-weight:900;font-size:1.6rem;color:#fff;display:flex;align-items:center;gap:10px;margin-bottom:48px}.bd{width:10px;height:10px;background:var(--gold);border-radius:50%}
.hero h1{font-family:'Playfair Display',serif;font-size:2.8rem;font-weight:900;color:#fff;line-height:1.1;margin-bottom:18px}.hero h1 em{color:var(--gold)}
.hero p{color:#5a7a90;line-height:1.7;max-width:380px}
.form-side{display:flex;flex-direction:column;justify-content:center;padding:60px}.form-card{max-width:400px;width:100%}
.form-title{font-family:'Playfair Display',serif;font-size:1.7rem;font-weight:900;color:var(--navy);margin-bottom:5px}
.form-sub{color:var(--muted);margin-bottom:28px;font-size:.9rem}.form-sub a{color:var(--teal);text-decoration:none;font-weight:700}
.fg{margin-bottom:16px}.fg label{display:block;font-size:.76rem;font-weight:800;color:var(--navy);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em}
.fg input{width:100%;padding:12px 15px;border:2px solid var(--border);border-radius:9px;font-size:.95rem;font-family:inherit;color:var(--navy);background:#faf9f7;outline:none}.fg input:focus{border-color:var(--teal);background:#fff}
.row{display:flex;align-items:center;justify-content:space-between}.forgot{font-size:.72rem;font-weight:700;color:var(--teal);text-decoration:none}
.error{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;padding:11px 15px;border-radius:8px;font-size:.86rem;margin-bottom:16px}.okb{background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;padding:11px 15px;border-radius:8px;font-size:.86rem;margin-bottom:16px}
.btn-submit{width:100%;padding:13px;background:var(--navy);color:#fff;border:none;border-radius:9px;font:700 1rem 'Playfair Display',serif;cursor:pointer}.btn-submit:hover{background:var(--gold)}
.btn-reg{display:block;text-align:center;padding:13px;border:2px solid var(--border);border-radius:9px;color:var(--navy);text-decoration:none;font-weight:600;margin-top:14px}.btn-reg:hover{background:var(--navy);color:#fff}
@media(max-width:768px){body{grid-template-columns:1fr}.hero{display:none}.form-side{padding:40px 20px}}</style></head><body>
<div class="hero"><div class="hero-brand"><div class="bd"></div><?= e($companyName) ?></div><h1>Learn.<br>Watch.<br><em>Grow.</em></h1><p>Structured video training with levels, units, quizzes, exams, badges and certificates.</p></div>
<div class="form-side"><div class="form-card">
  <div class="form-title">Welcome back</div><div class="form-sub">No account? <a href="register.php">Sign up free →</a></div>
  <?php if ($ok): ?><div class="okb">✅ <?= e($ok) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="error">⚠️ <?= e($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="fg"><label>Email</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
    <div class="fg"><div class="row"><label style="margin:0">Password</label><a class="forgot" href="forgot-password.php">Forgot password?</a></div><input type="password" name="password" required></div>
    <button class="btn-submit">Sign In →</button>
  </form>
  <a href="register.php" class="btn-reg">Create Account</a>
</div></div></body></html>
