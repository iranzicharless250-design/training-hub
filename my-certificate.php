<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user = getCurrentUser();
$db   = getDB();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php?tab=certs'); exit; }

// Security: student can ONLY see their own certificates
$s=$db->prepare("SELECT c.*,l.title AS level_title,l.color AS level_color,l.icon AS level_icon,a.name AS issued_by_name FROM certificates c LEFT JOIN levels l ON c.level_id=l.id JOIN users a ON c.issued_by=a.id WHERE c.id=? AND c.user_id=? AND c.status='active'");
$s->execute([$id, $user['id']]);
$cert = $s->fetch();
if (!$cert) { header('Location: dashboard.php?tab=certs'); exit; }

$sv          = getAllSettings();
$companyName = $sv['company_name']            ?? 'TrainHub Academy';
$ceoName     = $sv['ceo_name']                ?? 'Director';
$ceoTitle    = $sv['ceo_title']               ?? 'Chief Executive Officer';
$footerText  = $sv['certificate_footer_text'] ?? '';
$logoPath    = $sv['logo_path']               ?? '';
$sigPath     = $sv['signature_path']          ?? '';
$website     = $sv['company_website']         ?? '';
$issueDate   = date('F j, Y', strtotime($cert['issued_at']));

$logoAbs = $logoPath ? __DIR__.'/'.$logoPath : '';
$sigAbs  = $sigPath  ? __DIR__.'/'.$sigPath  : '';
$logoB64 = imgToBase64($logoAbs);
$sigB64  = imgToBase64($sigAbs);
$isCombined = $cert['cert_type']==='combined';

// Sponsors
$sponsors = getSponsors();
$sponsorImgs = [];
foreach ($sponsors as $sp) {
    $abs = !empty($sp['logo_path']) ? __DIR__.'/'.$sp['logo_path'] : '';
    $b64 = imgToBase64($abs);
    if ($b64) $sponsorImgs[] = ['name'=>$sp['name'], 'src'=>$b64];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>My Certificate — <?= e($cert['course_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;700;800&family=Playfair+Display:ital,wght@0,700;0,900;1,400&family=Lato:wght@300;400;700&family=Great+Vibes&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
@media screen {
  body{background:#c8c8c8;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:32px 20px 60px;font-family:'Nunito',sans-serif;}
  .controls{background:#fff;border-radius:10px;padding:14px 24px;margin-bottom:28px;display:flex;align-items:center;gap:14px;box-shadow:0 4px 20px rgba(0,0,0,.18);width:100%;max-width:960px;}
  .ctrl-title{font-weight:800;font-size:.95rem;flex:1;}
  .btn{padding:9px 22px;border-radius:7px;font-weight:800;font-family:'Nunito',sans-serif;cursor:pointer;border:none;font-size:.85rem;text-decoration:none;display:inline-flex;align-items:center;gap:7px;transition:.15s;}
  .btn-print{background:#0d2137;color:#fff;}
  .btn-print:hover{background:#f5a623;}
  .btn-back{background:#f0f0f0;color:#333;border:1px solid #ddd;}
  .cert-shadow{box-shadow:0 24px 80px rgba(0,0,0,.35);width:100%;max-width:960px;}
}
@media print {
  body{margin:0;padding:0;background:#fff;}
  .controls{display:none!important;}
  .cert-shadow{box-shadow:none;width:100%;}
  @page{size:A4 landscape;margin:0;}
}

.certificate{background:#fff;width:100%;aspect-ratio:1.414/1;position:relative;display:flex;flex-direction:column;align-items:center;justify-content:space-between;padding:44px 80px 34px;overflow:hidden;font-family:'Lato',sans-serif;}
.certificate::before{content:'';position:absolute;inset:0;background:radial-gradient(ellipse 70% 50% at 50% 0%,#f5f0e820,transparent 60%),radial-gradient(ellipse 50% 40% at 50% 100%,#f0ece020,transparent 60%);}

.cert-type-banner{position:absolute;top:0;right:0;background:<?= $isCombined?'#c8a84b':'#0d2137' ?>;color:#fff;font-size:.58rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;padding:4px 14px;border-bottom-left-radius:8px;z-index:3;}

.cert-header{display:flex;flex-direction:column;align-items:center;gap:6px;position:relative;z-index:2;}
.cert-logo{max-height:76px;max-width:210px;object-fit:contain;display:block;margin:0 auto 6px;}
.cert-logo-placeholder{width:66px;height:66px;border-radius:50%;background:#0d2137;color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:900;font-size:1.7rem;margin:0 auto 6px;}
.cert-company{font-family:'Lato',sans-serif;font-size:.7rem;font-weight:700;letter-spacing:.2em;text-transform:uppercase;color:#555;text-align:center;}

.cert-title-line{text-align:center;position:relative;z-index:2;margin-top:-6px;}
.cert-title-line .title-text{font-family:'Lato',sans-serif;font-size:.92rem;font-weight:300;letter-spacing:.08em;color:#444;}

.cert-recipient-block{text-align:center;position:relative;z-index:2;}
.cert-recipient-name{font-family:'Lato',sans-serif;font-size:2.3rem;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:#111;line-height:1.1;}
.cert-course-text{margin-top:9px;font-family:'Lato',sans-serif;font-size:.76rem;font-weight:300;color:#555;line-height:1.6;max-width:520px;margin-left:auto;margin-right:auto;}
.cert-course-name{font-weight:700;color:#222;}

.cert-medal-row{display:flex;justify-content:center;position:relative;z-index:2;}
.medal{width:100px;height:122px;display:flex;flex-direction:column;align-items:center;}
.medal-circle{width:82px;height:82px;border-radius:50%;background:<?= $isCombined?'radial-gradient(circle at 35% 35%,#fff8d0 0%,#f0c040 30%,#c8880a 65%,#8a5500 100%)':'radial-gradient(circle at 35% 35%,#e0e8ff 0%,#4060d0 30%,#1030a0 65%,#001080 100%)' ?>;box-shadow:0 0 0 4px <?= $isCombined?'#c8a030':'#2040b0' ?>,0 0 0 7px <?= $isCombined?'#e8c050':'#4060d0' ?>,0 4px 18px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;position:relative;}
.medal-circle::before{content:'';position:absolute;inset:8px;border-radius:50%;border:3px solid rgba(255,255,255,.3);box-shadow:inset 0 0 6px rgba(0,0,0,.2);}
.medal-inner{display:flex;flex-direction:column;align-items:center;gap:2px;position:relative;z-index:1;}
.medal-crown{font-size:1.4rem;filter:drop-shadow(0 1px 2px rgba(0,0,0,.3));}
.medal-stars{font-size:.46rem;color:#fff8d0;letter-spacing:2px;}
.medal-ribbon{display:flex;justify-content:center;gap:3px;margin-top:-4px;}
.ribbon-left,.ribbon-right{width:16px;height:28px;background:linear-gradient(180deg,#cc1111 0%,#880000 100%);}
.ribbon-left{clip-path:polygon(0 0,100% 0,100% 80%,50% 100%,0 80%);transform:rotate(-8deg) translateY(2px);}
.ribbon-right{clip-path:polygon(0 0,100% 0,100% 80%,50% 100%,0 80%);transform:rotate(8deg) translateY(2px);}

.cert-footer{width:100%;display:flex;align-items:flex-end;justify-content:space-between;position:relative;z-index:2;}
.cert-sig-block{text-align:center;min-width:150px;}
.cert-sig-img{height:46px;max-width:170px;object-fit:contain;display:block;margin:0 auto;}
.cert-sig-placeholder{height:46px;display:flex;align-items:flex-end;justify-content:center;}
.cert-sig-placeholder span{font-family:'Great Vibes',cursive;font-size:1.8rem;color:#222;line-height:1;}
.cert-sig-line{width:150px;height:1px;background:#888;margin:4px auto 5px;}
.cert-sig-name{font-family:'Lato',sans-serif;font-size:.68rem;font-weight:700;color:#222;letter-spacing:.03em;}
.cert-sig-title{font-size:.6rem;color:#777;font-family:'Lato',sans-serif;}
.cert-center-meta{text-align:center;}
.cert-date-val{font-family:'Lato',sans-serif;font-size:.68rem;color:#888;}
.cert-footer-text{position:absolute;bottom:30px;left:90px;right:90px;font-size:.46rem;color:#bbb;text-align:center;}

.cert-sponsors{position:relative;z-index:2;width:100%;display:flex;align-items:center;justify-content:center;gap:20px;padding-top:6px;flex-wrap:wrap;}
.cert-sponsors-label{font-size:.48rem;letter-spacing:.15em;text-transform:uppercase;color:#aaa;margin-right:6px;}
.cert-sponsor-logo{max-height:22px;max-width:70px;object-fit:contain;opacity:.85;}
</style>
</head>
<body>
<div class="controls">
  <div class="ctrl-title">🎓 My Certificate — <?= e($cert['course_name']) ?></div>
  <a href="dashboard.php?tab=certs" class="btn btn-back">← My Certificates</a>
  <button onclick="window.print()" class="btn btn-print">🖨 Print / Save PDF</button>
</div>
<div class="cert-shadow">
<div class="certificate">
  <div class="cert-type-banner"><?= $isCombined?'🏆 Combined Certificate':'📊 '.e($cert['level_title']??'Level').' Certificate' ?></div>

  <div class="cert-header">
    <?php if($logoB64): ?>
      <img src="<?= $logoB64 ?>" alt="<?= e($companyName) ?>" class="cert-logo">
    <?php else: ?>
      <div class="cert-logo-placeholder"><?= strtoupper(substr($companyName,0,1)) ?></div>
      <div class="cert-company"><?= e($companyName) ?></div>
    <?php endif; ?>
  </div>

  <div class="cert-title-line">
    <div class="title-text"><?= $isCombined?'With great pride, this certificate is awarded to':'This certificate is proudly awarded to' ?></div>
  </div>

  <div class="cert-recipient-block">
    <div class="cert-recipient-name"><?= e($cert['student_name']??$user['name']) ?></div>
    <div class="cert-course-text">
      in recognition of the dedication and hard work shown in completing<br>
      <span class="cert-course-name"><?= e($cert['course_name']) ?></span>
    </div>
  </div>

  <div class="cert-medal-row">
    <div class="medal">
      <div class="medal-circle">
        <div class="medal-inner">
          <div class="medal-crown"><?= $isCombined?'🏆':'👑' ?></div>
          <div class="medal-stars">★ ★ ★ ★ ★</div>
        </div>
      </div>
      <div class="medal-ribbon"><div class="ribbon-left"></div><div class="ribbon-right"></div></div>
    </div>
  </div>

  <div class="cert-footer">
    <div class="cert-sig-block">
      <?php if($sigB64): ?>
        <img src="<?= $sigB64 ?>" alt="Signature" class="cert-sig-img">
      <?php else: ?>
        <div class="cert-sig-placeholder"><span><?= e($ceoName) ?></span></div>
      <?php endif; ?>
      <div class="cert-sig-line"></div>
      <div class="cert-sig-name"><?= e($ceoName) ?></div>
      <div class="cert-sig-title"><?= e($ceoTitle) ?></div>
    </div>
    <div class="cert-center-meta">
      <div class="cert-date-val"><?= e($issueDate) ?></div>
      <?php if($website): ?><div style="font-size:.48rem;color:#ccc;margin-top:3px"><?= e($website) ?></div><?php endif; ?>
    </div>
    <div class="cert-sig-block">
      <div class="cert-sig-placeholder"><span><?= e($cert['issued_by_name']) ?></span></div>
      <div class="cert-sig-line"></div>
      <div class="cert-sig-name"><?= e($cert['issued_by_name']) ?></div>
      <div class="cert-sig-title">Program Mentor</div>
    </div>
  </div>

  <?php if (!empty($sponsorImgs)): ?>
  <div class="cert-sponsors">
    <span class="cert-sponsors-label">Sponsored by</span>
    <?php foreach ($sponsorImgs as $si): ?>
    <img src="<?= $si['src'] ?>" alt="<?= e($si['name']) ?>" title="<?= e($si['name']) ?>" class="cert-sponsor-logo">
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if($footerText): ?><div class="cert-footer-text"><?= e($footerText) ?></div><?php endif; ?>
</div>
</div>
</body>
</html>
