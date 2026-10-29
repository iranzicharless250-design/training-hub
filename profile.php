<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (isAdmin()) { header('Location: admin/index.php'); exit; }

$user = getCurrentUser();
$db   = getDB();

$msg=''; $err='';
$pwMsg=''; $pwErr='';
$picMsg=''; $picErr='';

if ($_SERVER['REQUEST_METHOD']==='POST') {

    // ── UPDATE PROFILE INFO ──
    if (isset($_POST['update_info'])) {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $whatsapp = trim($_POST['whatsapp'] ?? '');

        if (!$name || !$email || !$whatsapp) {
            $err = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'Please enter a valid email address.';
        } else {
            $chk = $db->prepare("SELECT id FROM users WHERE email=? AND id!=?");
            $chk->execute([$email, $user['id']]);
            if ($chk->fetch()) {
                $err = 'That email is already used by another account.';
            } else {
                $db->prepare("UPDATE users SET name=?, email=?, whatsapp_number=? WHERE id=?")
                   ->execute([$name, $email, $whatsapp, $user['id']]);
                $user = getCurrentUser();
                startSession(); $_SESSION['name'] = $user['name'];
                $msg = 'Profile information updated!';
            }
        }
    }

    // ── UPLOAD PROFILE PICTURE ──
    elseif (isset($_POST['upload_picture'])) {
        if (empty($_FILES['picture']['name'])) {
            $picErr = 'Please choose an image to upload.';
        } else {
            $ext = strtolower(pathinfo($_FILES['picture']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['png','jpg','jpeg','gif','webp'])) {
                $picErr = 'Picture must be PNG, JPG, GIF, or WebP.';
            } elseif ($_FILES['picture']['size'] > 5*1024*1024) {
                $picErr = 'Picture must be under 5MB.';
            } else {
                $fname = 'uploads/profiles/user_'.$user['id'].'_'.time().'.'.$ext;
                if (move_uploaded_file($_FILES['picture']['tmp_name'], __DIR__.'/'.$fname)) {
                    if (!empty($user['profile_picture']) && file_exists(__DIR__.'/'.$user['profile_picture'])) {
                        @unlink(__DIR__.'/'.$user['profile_picture']);
                    }
                    $db->prepare("UPDATE users SET profile_picture=? WHERE id=?")->execute([$fname, $user['id']]);
                    $user = getCurrentUser();
                    $picMsg = 'Profile picture updated!';
                } else {
                    $picErr = 'Failed to upload picture. Check folder permissions on uploads/profiles/.';
                }
            }
        }
    }
    // ── REMOVE PROFILE PICTURE ──
    elseif (isset($_POST['remove_picture'])) {
        if (!empty($user['profile_picture']) && file_exists(__DIR__.'/'.$user['profile_picture'])) {
            @unlink(__DIR__.'/'.$user['profile_picture']);
        }
        $db->prepare("UPDATE users SET profile_picture=NULL WHERE id=?")->execute([$user['id']]);
        $user = getCurrentUser();
        $picMsg = 'Profile picture removed.';
    }

    // ── CHANGE PASSWORD ──
    elseif (isset($_POST['change_password'])) {
        $currentPw = $_POST['current_password'] ?? '';
        $newPw     = $_POST['new_password'] ?? '';
        $confirmPw = $_POST['confirm_password'] ?? '';

        if (!$currentPw || !$newPw || !$confirmPw) {
            $pwErr = 'All password fields are required.';
        } elseif (!password_verify($currentPw, $user['password'])) {
            $pwErr = 'Current password is incorrect.';
        } elseif (strlen($newPw) < 6) {
            $pwErr = 'New password must be at least 6 characters.';
        } elseif ($newPw !== $confirmPw) {
            $pwErr = 'New password and confirmation do not match.';
        } else {
            $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($newPw, PASSWORD_BCRYPT), $user['id']]);
            $pwMsg = 'Password changed successfully!';
        }
    }
}

$picUrl = getProfilePictureUrl($user);
$companyName = getSetting('company_name', 'TrainHub');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Profile — <?= e(SITE_NAME) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
:root{--navy:#0d2137;--gold:#c8a84b;--gold-dark:#a8882e;--green:#16a34a;--red:#dc2626;--muted:#6b7e8f;--border:#e7e2d6;--cream:#f7f4ee;--card:#fff;--ink:#1a2e3d;}
body{font-family:'Nunito',sans-serif;background:var(--cream);color:var(--ink);min-height:100vh;}
nav{background:var(--navy);height:64px;padding:0 32px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:100;}
.nav-brand{font-family:'Playfair Display',serif;font-weight:900;font-size:1.15rem;color:#fff;text-decoration:none;}
.back-btn{color:#7a9bb0;text-decoration:none;font-size:.82rem;border:1px solid #2a3a4a;padding:7px 16px;border-radius:7px;transition:.2s;}
.back-btn:hover{color:#fff;border-color:#444;}
.main{max-width:760px;margin:0 auto;padding:36px 20px;}
h1{font-family:'Playfair Display',serif;font-size:1.7rem;font-weight:900;margin-bottom:6px;}
.sub{color:var(--muted);font-size:.88rem;margin-bottom:26px;}
.section{background:var(--card);border:2px solid var(--border);border-radius:16px;margin-bottom:20px;overflow:hidden;}
.section-head{padding:18px 24px;border-bottom:2px solid var(--border);}
.section-head h2{font-family:'Playfair Display',serif;font-size:1.02rem;font-weight:700;}
.section-body{padding:24px;}
.flash{padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.86rem;}
.flash-success{background:#f0fdf4;border:2px solid #bbf7d0;color:#166534;}
.flash-error{background:#fef2f2;border:2px solid #fecaca;color:#991b1b;}

.pic-row{display:flex;align-items:center;gap:20px;flex-wrap:wrap;}
.pic-preview{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--gold);flex-shrink:0;}
.pic-ph{width:96px;height:96px;border-radius:50%;background:var(--gold);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:2rem;font-family:'Playfair Display',serif;flex-shrink:0;}
.pic-actions{flex:1;min-width:200px;}
.upload-zone{border:2px dashed var(--border);border-radius:11px;padding:16px;text-align:center;cursor:pointer;transition:.2s;position:relative;background:#faf9f7;margin-bottom:10px;}
.upload-zone:hover{border-color:var(--gold);background:#fffaf0;}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
.upload-label{font-weight:700;font-size:.84rem;}
.upload-hint{color:var(--muted);font-size:.72rem;margin-top:2px;}

.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:.75rem;font-weight:800;color:var(--ink);margin-bottom:6px;text-transform:uppercase;letter-spacing:.05em;}
.form-group input{width:100%;padding:11px 14px;border:2px solid var(--border);border-radius:9px;font-size:.9rem;font-family:'Nunito',sans-serif;color:var(--ink);background:#faf9f7;outline:none;transition:.2s;}
.form-group input:focus{border-color:var(--gold);background:#fff;}
.hint{font-size:.74rem;color:var(--muted);margin-top:4px;}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}

.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 22px;border-radius:8px;font-size:.86rem;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;border:none;text-decoration:none;transition:.15s;}
.btn-navy{background:var(--navy);color:#fff;}
.btn-navy:hover{background:var(--gold);}
.btn-gold{background:var(--gold);color:#fff;}
.btn-gold:hover{background:var(--gold-dark);}
.btn-danger{background:#fef2f2;color:var(--red);border:1px solid #fecaca;}
.btn-danger:hover{background:#fee2e2;}
.btn-sm{padding:7px 15px;font-size:.78rem;}
.btn-full{width:100%;justify-content:center;}
.divider{border-top:1px solid var(--border);margin:18px 0;}
</style>
</head>
<body>
<nav>
  <a href="dashboard.php" class="nav-brand"><?= e($companyName) ?></a>
  <a href="dashboard.php" class="back-btn">← Dashboard</a>
</nav>
<div class="main">
  <h1>👤 My Profile</h1>
  <div class="sub">Manage your picture, personal information, and account security.</div>

  <!-- PROFILE PICTURE -->
  <div class="section">
    <div class="section-head"><h2>🖼 Profile Picture</h2></div>
    <div class="section-body">
      <?php if($picMsg): ?><div class="flash flash-success">✅ <?= e($picMsg) ?></div><?php endif; ?>
      <?php if($picErr): ?><div class="flash flash-error">⚠️ <?= e($picErr) ?></div><?php endif; ?>
      <div class="pic-row">
        <?php if($picUrl): ?>
        <img src="<?= e($picUrl) ?>" alt="Profile" class="pic-preview">
        <?php else: ?>
        <div class="pic-ph"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <?php endif; ?>
        <div class="pic-actions">
          <form method="POST" enctype="multipart/form-data">
            <div class="upload-zone">
              <input type="file" name="picture" accept="image/*">
              <div class="upload-label">📤 Click to choose a new photo</div>
              <div class="upload-hint">PNG, JPG, GIF, WebP — max 5MB</div>
            </div>
            <button type="submit" name="upload_picture" class="btn btn-gold btn-sm">Upload Picture</button>
            <?php if($picUrl): ?>
            <button type="submit" name="remove_picture" class="btn btn-danger btn-sm" onclick="return confirm('Remove your profile picture?')">Remove</button>
            <?php endif; ?>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- PERSONAL INFO -->
  <div class="section">
    <div class="section-head"><h2>✏️ Personal Information</h2></div>
    <div class="section-body">
      <?php if($msg): ?><div class="flash flash-success">✅ <?= e($msg) ?></div><?php endif; ?>
      <?php if($err): ?><div class="flash flash-error">⚠️ <?= e($err) ?></div><?php endif; ?>
      <form method="POST">
        <div class="form-group"><label>Full Name</label><input type="text" name="name" value="<?= e($user['name']) ?>" required></div>
        <div class="form-row">
          <div class="form-group"><label>Email Address</label><input type="email" name="email" value="<?= e($user['email']) ?>" required></div>
          <div class="form-group"><label>WhatsApp Number</label><input type="text" name="whatsapp" value="<?= e($user['whatsapp_number']) ?>" required></div>
        </div>
        <button type="submit" name="update_info" class="btn btn-navy">💾 Save Changes</button>
      </form>
    </div>
  </div>

  <!-- SECURITY -->
  <div class="section">
    <div class="section-head"><h2>🔐 Security — Change Password</h2></div>
    <div class="section-body">
      <?php if($pwMsg): ?><div class="flash flash-success">✅ <?= e($pwMsg) ?></div><?php endif; ?>
      <?php if($pwErr): ?><div class="flash flash-error">⚠️ <?= e($pwErr) ?></div><?php endif; ?>
      <form method="POST">
        <div class="form-group"><label>Current Password</label><input type="password" name="current_password" placeholder="Enter current password" required></div>
        <div class="divider"></div>
        <div class="form-row">
          <div class="form-group"><label>New Password</label><input type="password" name="new_password" placeholder="Min. 6 characters" required></div>
          <div class="form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" placeholder="Repeat new password" required></div>
        </div>
        <button type="submit" name="change_password" class="btn btn-navy">🔐 Change Password</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
