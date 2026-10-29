<?php
require_once __DIR__ . '/includes/auth.php'; startSession();
if (isLoggedIn()) { header('Location: dashboard.php'); exit; }
$error = '';
$subjects = getSubjects();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? ''; $confirm = $_POST['confirm'] ?? '';
    $whatsapp = trim($_POST['whatsapp'] ?? ''); $subjectId = (int)($_POST['subject_id'] ?? 0);
    if (!$name || !$email || !$password || !$whatsapp) $error = 'All fields are required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Please enter a valid email address.';
    elseif (!ent_phone($whatsapp)) $error = 'Please enter a valid WhatsApp number with country code (e.g. +250788123456).';
    elseif (!$subjectId) $error = 'Please choose a subject you want to study.';
    elseif ($password !== $confirm) $error = 'Passwords do not match.';
    elseif (strlen($password) < 6) $error = 'Password must be at least 6 characters.';
    else {
        $r = registerUser($name, $email, $password, $whatsapp, $subjectId);
        if (isset($r['error'])) { $error = $r['error']; }
        else {
            // Account starts LOCKED until the emailed code is entered
            getDB()->prepare("UPDATE users SET email_verified=0 WHERE id=?")->execute([$r['id']]);
            $sent = otp_send($email);
            $_SESSION['otp_email'] = $email;
            $_SESSION['otp_flash'] = $sent['ok'] ? 'We emailed you a 6-digit code.' : $sent['error'];
            if (!empty($sent['debug_code'])) $_SESSION['otp_debug'] = $sent['debug_code'];
            header('Location: verify-otp.php'); exit;
        }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Register — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}:root{--navy:#0d2137;--teal:#00897b;--gold:#c8a84b;--muted:#6b7e8f;--border:#dde6ec;--cream:#f4f8f9}
body{font-family:'Nunito',sans-serif;background:var(--cream);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:40px 20px}
.card{background:#fff;border-radius:18px;padding:44px;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(13,33,55,.1)}
.brand{font-weight:900;font-size:1.3rem;color:var(--navy);display:flex;align-items:center;gap:8px;margin-bottom:28px}.bd{width:9px;height:9px;background:var(--gold);border-radius:50%}
h2{font-size:1.7rem;font-weight:900;color:var(--navy);margin-bottom:5px}.sub{color:var(--muted);margin-bottom:24px;font-size:.9rem}.sub a{color:var(--teal);text-decoration:none;font-weight:700}
.fg{margin-bottom:14px}.fg label{display:block;font-size:.74rem;font-weight:800;color:var(--navy);margin-bottom:5px;text-transform:uppercase;letter-spacing:.05em}
.fg input{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:8px;font-size:.93rem;font-family:inherit;color:var(--navy);background:#faf9f7;outline:none}.fg input:focus{border-color:var(--teal);background:#fff}
.hint{font-size:.73rem;color:var(--muted);margin-top:3px}.error{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;padding:11px 15px;border-radius:8px;font-size:.84rem;margin-bottom:14px}
.btn{width:100%;padding:13px;background:var(--navy);color:#fff;border:none;border-radius:8px;font-size:.95rem;font-family:inherit;font-weight:800;cursor:pointer}.btn:hover{background:var(--gold)}
.subject-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:4px}.subject-opt{display:flex;align-items:center;gap:8px;padding:10px 12px;border:2px solid var(--border);border-radius:9px;cursor:pointer;font-size:.85rem;font-weight:700}
.subject-opt:hover{border-color:var(--teal);background:#f0fdfa}.subject-opt input{accent-color:var(--teal)}</style></head>
<body><div class="card">
  <div class="brand"><div class="bd"></div><?= e(SITE_NAME) ?></div>
  <h2>Create Account</h2><div class="sub">Already have one? <a href="login.php">Sign in →</a></div>
  <?php if ($error): ?><div class="error">⚠️ <?= e($error) ?></div><?php endif; ?>
  <form method="POST">
    <div class="fg"><label>Full Name</label><input type="text" name="name" value="<?= e($_POST['name'] ?? '') ?>" required></div>
    <div class="fg"><label>Email Address</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required><div class="hint">We'll email you a 6-digit activation code.</div></div>
    <div class="fg"><label>WhatsApp Number</label><input type="text" name="whatsapp" placeholder="+250788123456" value="<?= e($_POST['whatsapp'] ?? '') ?>" required><div class="hint">Include country code. Used for lesson updates and contacting admin.</div></div>
    <div class="fg"><label>Which subject do you want to study?</label>
      <?php if (!$subjects): ?><div class="hint" style="color:#dc2626">No subjects available yet. Please contact the admin.</div><?php else: ?>
      <div class="subject-grid"><?php foreach ($subjects as $s): ?><label class="subject-opt"><input type="radio" name="subject_id" value="<?= (int)$s['id'] ?>" required> <?= e($s['icon']) ?> <?= e($s['name']) ?></label><?php endforeach; ?></div><?php endif; ?></div>
    <div class="fg"><label>Password</label><input type="password" name="password" placeholder="Min. 6 characters" required></div>
    <div class="fg"><label>Confirm Password</label><input type="password" name="confirm" required></div>
    <button class="btn" <?= $subjects ? '' : 'disabled' ?>>Create Account &amp; Send Code →</button>
  </form>
</div></body></html>
