<?php
/**
 * home.php — public landing page (hero · course grid · trust · pricing toggle). Light/dark aware.
 * Course cards come from your real Units; if there are none yet, sample cards are shown instead.
 */
require_once __DIR__ . '/includes/auth.php'; startSession();
$site = getSetting('company_name', 'IC Training Platform');
$logo = getSetting('logo_path', '');
$sponsors = function_exists('getSponsors') ? getSponsors() : [];
$loggedIn = isLoggedIn();

/* ── Course cards from the database (first lesson = the free preview) ── */
$courses = [];
try {
    $rows = getDB()->query("SELECT u.id, u.title, u.description, lv.title AS level_title, lv.order_index AS lvl,
        (SELECT COUNT(*) FROM lessons WHERE unit_id=u.id AND is_active=1) AS n,
        (SELECT youtube_id FROM lessons WHERE unit_id=u.id AND is_active=1 AND content_type='youtube' ORDER BY order_index LIMIT 1) AS yt
        FROM units u JOIN levels lv ON u.level_id=lv.id WHERE u.is_active=1 AND lv.is_active=1
        ORDER BY lv.order_index, u.order_index LIMIT 6")->fetchAll();
    foreach ($rows as $r) {
        $mins = max(1, (int)$r['n']) * 15;                                   // estimate: ~15 min per lesson
        $courses[] = ['title' => $r['title'], 'desc' => $r['description'], 'lvl' => (int)$r['lvl'], 'yt' => $r['yt'],
                      'dur' => ($mins >= 60 ? intdiv($mins, 60) . 'h ' : '') . ($mins % 60 ? ($mins % 60) . 'min' : ''), 'lessons' => (int)$r['n']];
    }
} catch (Throwable $e) {}
if (!$courses) {                                                              // SAMPLE CARDS — replace by creating real units
    foreach ([['Workplace Safety Essentials', 1], ['Customer Service Excellence', 1], ['Digital Skills for Teams', 2], ['Leadership Fundamentals', 2], ['Data Privacy & Compliance', 3], ['Advanced Project Delivery', 3]] as $c)
        $courses[] = ['title' => $c[0], 'desc' => 'Sample course — add your own units in the admin panel.', 'lvl' => $c[1], 'yt' => null, 'dur' => '2h 30min', 'lessons' => 10];
}
function home_diff(int $l): array { return $l <= 1 ? ['Beginner', 'b'] : ($l == 2 ? ['Intermediate', 'i'] : ['Advanced', 'a']); }

/* ── Testimonials: SAMPLE TEXT. Replace with real, consented quotes before going live. ── */
$testimonials = [
    ['Aline M.', 'Operations Officer', 5, 'The short lessons fit around my shifts, and the badges kept me motivated to finish each unit.'],
    ['Jean P.', 'Team Lead', 5, 'Assigning mandatory courses and seeing who has finished saves me hours every month.'],
    ['Grace K.', 'HR Coordinator', 4, 'Certificates and renewal dates are tracked for us, so audits are far less stressful.'],
];
/* ── Pricing: prices intentionally not invented. Individual Plus reads the setting 'plus_monthly_price' if you add one. ── */
$plusPrice = getSetting('plus_monthly_price', 'See upgrade page');
?>
<!DOCTYPE html><html lang="en" data-theme="auto"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($site) ?> — Professional training that gets finished</title>
<meta name="description" content="Video lessons, live classes, quizzes, badges and certificates — for individuals and corporate teams.">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root{--bg:#fff;--bg2:#f6f7fb;--ink:#0b1220;--mut:#5b6475;--line:#e6e8ef;--pri:#2f5bea;--pri2:#1e40af;--card:#fff;--shadow:0 10px 30px rgba(15,23,42,.08)}
html[data-theme=dark]{--bg:#0b1020;--bg2:#101730;--ink:#eef1fb;--mut:#9aa5bf;--line:#222c48;--card:#141b33;--shadow:0 10px 30px rgba(0,0,0,.4)}
@media(prefers-color-scheme:dark){html[data-theme=auto]{--bg:#0b1020;--bg2:#101730;--ink:#eef1fb;--mut:#9aa5bf;--line:#222c48;--card:#141b33;--shadow:0 10px 30px rgba(0,0,0,.4)}}
*{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth}body{font-family:Inter,system-ui,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55}
a{color:inherit;text-decoration:none}.wrap{max-width:1120px;margin:0 auto;padding:0 20px}section{padding:76px 0}h2{font-size:clamp(1.6rem,3vw,2.2rem);font-weight:800;letter-spacing:-.02em;margin-bottom:10px}.lead{color:var(--mut);max-width:620px}
nav{position:sticky;top:0;z-index:20;background:color-mix(in srgb,var(--bg) 88%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
nav .wrap{display:flex;align-items:center;justify-content:space-between;height:64px}.brand{font-weight:800;display:flex;align-items:center;gap:10px}.brand img{height:30px}
.links{display:flex;align-items:center;gap:18px;font-size:.92rem;font-weight:500}.links a:hover{color:var(--pri)}
.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 22px;border-radius:12px;font-weight:700;border:1px solid transparent;cursor:pointer;transition:.2s;font-size:.95rem;font-family:inherit}
.btn.pri{background:var(--pri);color:#fff;box-shadow:0 8px 20px rgba(47,91,234,.3)}.btn.pri:hover{background:var(--pri2);transform:translateY(-2px)}
.btn.ghost{background:transparent;border-color:var(--line);color:var(--ink)}.btn.ghost:hover{border-color:var(--pri);color:var(--pri)}.btn.sm{padding:8px 14px;font-size:.85rem}
.icon{background:none;border:1px solid var(--line);border-radius:10px;width:38px;height:38px;cursor:pointer;color:var(--ink);font-size:1rem}
.hero{padding:96px 0 70px;background:radial-gradient(900px 400px at 80% -10%,color-mix(in srgb,var(--pri) 18%,transparent),transparent)}
.hero h1{font-size:clamp(2.2rem,5.5vw,3.8rem);line-height:1.05;font-weight:800;letter-spacing:-.03em;max-width:760px}.hero h1 em{font-style:normal;color:var(--pri)}
.hero p{font-size:1.15rem;color:var(--mut);max-width:560px;margin:20px 0 30px}.cta{display:flex;gap:12px;flex-wrap:wrap}
.stats{display:flex;gap:34px;margin-top:44px;flex-wrap:wrap}.stats b{display:block;font-size:1.5rem}.stats span{color:var(--mut);font-size:.85rem}
.alt{background:var(--bg2)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(310px,1fr));gap:22px;margin-top:34px}
.course{background:var(--card);border:1px solid var(--line);border-radius:18px;overflow:hidden;transition:.25s;display:flex;flex-direction:column}.course:hover{transform:translateY(-6px);box-shadow:var(--shadow)}
.thumb{position:relative;aspect-ratio:16/9;background:linear-gradient(135deg,#1e3a8a,#2f5bea 60%,#7c3aed);display:flex;align-items:center;justify-content:center;border:0;width:100%;cursor:pointer;overflow:hidden}
.thumb img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:.4s}.thumb:hover img{transform:scale(1.06)}
.play{position:relative;z-index:1;background:rgba(255,255,255,.95);color:#0b1220;font-weight:700;font-size:.8rem;padding:9px 16px;border-radius:99px;display:flex;gap:6px;align-items:center;box-shadow:0 6px 18px rgba(0,0,0,.25)}
.cbody{padding:18px 20px 22px;display:flex;flex-direction:column;gap:10px;flex:1}.cbody h3{font-size:1.08rem}.cbody p{color:var(--mut);font-size:.9rem;flex:1}
.meta{display:flex;gap:10px;align-items:center;font-size:.82rem;color:var(--mut);flex-wrap:wrap}
.badge{font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:99px}.badge.b{background:#dcfce7;color:#166534}.badge.i{background:#fef3c7;color:#92400e}.badge.a{background:#fee2e2;color:#991b1b}
.ticker{overflow:hidden;margin-top:26px;mask-image:linear-gradient(90deg,transparent,#000 12%,#000 88%,transparent)}.track{display:flex;gap:46px;width:max-content;animation:slide 28s linear infinite}.ticker:hover .track{animation-play-state:paused}
.logo{font-weight:800;font-size:1.25rem;color:var(--mut);opacity:.75;white-space:nowrap;display:flex;align-items:center}.logo img{height:34px;filter:grayscale(1);opacity:.8}
@keyframes slide{to{transform:translateX(-50%)}}@media(prefers-reduced-motion:reduce){.track{animation:none}}
.tgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px;margin-top:40px}.t{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:24px}
.stars{color:#f5b301;letter-spacing:2px}.who{display:flex;align-items:center;gap:12px;margin-top:16px}.av{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--pri),#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700}.who small{color:var(--mut);display:block}
.toggle{display:inline-flex;background:var(--bg2);border:1px solid var(--line);border-radius:99px;padding:5px;margin:22px 0 34px}.toggle button{border:0;background:none;padding:10px 22px;border-radius:99px;font-weight:600;font-size:.92rem;font-family:inherit;color:var(--mut);cursor:pointer;transition:.25s}
.toggle button.on{background:var(--pri);color:#fff;box-shadow:0 6px 16px rgba(47,91,234,.35)}
.plans{display:none;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px}.plans.on{display:grid;animation:fade .35s}@keyframes fade{from{opacity:0;transform:translateY(8px)}}
.plan{background:var(--card);border:1px solid var(--line);border-radius:18px;padding:28px}.plan.hot{border:2px solid var(--pri);box-shadow:var(--shadow)}
.price{font-size:1.9rem;font-weight:800;margin:10px 0 16px}.plan ul{list-style:none;margin-bottom:22px}.plan li{padding:6px 0;color:var(--mut);font-size:.92rem}.plan li::before{content:"✓";color:#16a34a;font-weight:800;margin-right:10px}
footer{border-top:1px solid var(--line);padding:30px 0;color:var(--mut);font-size:.88rem}
.modal{position:fixed;inset:0;background:rgba(0,0,0,.8);display:none;align-items:center;justify-content:center;padding:20px;z-index:60}.modal.on{display:flex}.vid{width:100%;max-width:880px;aspect-ratio:16/9;background:#000;border-radius:14px;overflow:hidden;position:relative}.vid iframe{width:100%;height:100%;border:0}
.x{position:absolute;top:-44px;right:0;background:none;border:0;color:#fff;font-size:1.8rem;cursor:pointer}
@media(max-width:720px){.links a.h{display:none}section{padding:54px 0}}
</style></head><body>
<nav><div class="wrap"><a class="brand" href="home.php"><?php if ($logo && file_exists(__DIR__ . '/' . $logo)): ?><img src="<?= e($logo) ?>" alt=""><?php endif; ?><?= e($site) ?></a>
<div class="links"><a class="h" href="#courses">Courses</a><a class="h" href="#proof">Customers</a><a class="h" href="#pricing">Pricing</a>
<button class="icon" id="theme" aria-label="Toggle dark mode">🌓</button>
<?php if ($loggedIn): ?><a class="btn pri sm" href="dashboard.php">Dashboard</a><?php else: ?><a href="login.php">Log in</a><a class="btn pri sm" href="register.php">Start free</a><?php endif; ?></div></div></nav>

<header class="hero"><div class="wrap"><h1>Training your people will <em>actually finish.</em></h1>
<p>Bite-sized video lessons, live classes, quizzes and certificates — with the tracking managers need to keep a whole team compliant.</p>
<div class="cta"><a class="btn pri" href="register.php">Start Free Trial →</a><a class="btn ghost" href="#pricing" onclick="setPlan('corp')">View Enterprise Plans</a></div>
<div class="stats"><div><b>Free</b><span>First 2 lessons of every unit</span></div><div><b>Live</b><span>Interactive video sessions</span></div><div><b>Certified</b><span>Badges &amp; printable certificates</span></div></div></div></header>

<section id="courses" class="alt"><div class="wrap"><h2>Explore our courses</h2><p class="lead">Preview the first lesson of any course — no sign-up needed.</p>
<div class="grid"><?php foreach ($courses as $c): [$dl, $dc] = home_diff($c['lvl']); ?>
<article class="course"><button class="thumb" <?= $c['yt'] ? 'data-yt="' . e($c['yt']) . '"' : 'disabled style="cursor:default"' ?> aria-label="Preview lesson">
<?php if ($c['yt']): ?><img loading="lazy" src="https://img.youtube.com/vi/<?= e($c['yt']) ?>/hqdefault.jpg" alt=""><?php endif; ?>
<span class="play"><?= $c['yt'] ? '▶ Preview lesson' : 'Preview coming soon' ?></span></button>
<div class="cbody"><div class="meta"><span class="badge <?= $dc ?>"><?= $dl ?></span><span>⏱ ≈ <?= e($c['dur']) ?></span><span>📚 <?= (int)$c['lessons'] ?> lessons</span></div>
<h3><?= e($c['title']) ?></h3><p><?= e(mb_strimwidth((string)$c['desc'], 0, 110, '…')) ?></p><a class="btn ghost sm" href="register.php">Start learning →</a></div></article><?php endforeach; ?></div></div></section>

<section id="proof"><div class="wrap"><h2>Trusted by professionals</h2><p class="lead">Teams and learners who train with us.</p>
<div class="ticker"><div class="track"><?php for ($k = 0; $k < 2; $k++): // doubled for a seamless loop
  if ($sponsors): foreach ($sponsors as $s): ?><div class="logo"><?php if (!empty($s['logo_path']) && file_exists(__DIR__ . '/' . $s['logo_path'])): ?><img src="<?= e($s['logo_path']) ?>" alt="<?= e($s['name']) ?>"><?php else: ?><?= e($s['name']) ?><?php endif; ?></div><?php endforeach;
  else: foreach (['Your Company', 'Partner Logo', 'Client Name', 'Your Brand', 'Partner Logo', 'Client Name'] as $n): ?><div class="logo"><?= e($n) ?></div><?php endforeach; endif; endfor; ?></div></div>
<div class="tgrid"><?php foreach ($testimonials as $t): ?><div class="t"><div class="stars"><?= str_repeat('★', $t[2]) . str_repeat('☆', 5 - $t[2]) ?></div><p style="margin-top:12px">“<?= e($t[3]) ?>”</p>
<div class="who"><div class="av"><?= e(mb_substr($t[0], 0, 1)) ?></div><div><strong><?= e($t[0]) ?></strong><small><?= e($t[1]) ?></small></div></div></div><?php endforeach; ?></div></div></section>

<section id="pricing" class="alt"><div class="wrap" style="text-align:center"><h2>Simple pricing</h2><p class="lead" style="margin:0 auto">Start free. Upgrade when you're ready.</p>
<div class="toggle" role="tablist"><button class="on" data-p="ind">Individual Learner</button><button data-p="corp">Corporate Teams</button></div>
<div style="text-align:left"><div class="plans on" id="pl-ind">
<div class="plan"><h3>Free</h3><div class="price">$0</div><ul><li>First 2 lessons of every unit</li><li>Quizzes &amp; progress tracking</li><li>Join live sessions</li></ul><a class="btn ghost" href="register.php">Start Free Trial</a></div>
<div class="plan hot"><h3>Plus ⭐</h3><div class="price"><?= e($plusPrice) ?></div><ul><li>Every lesson unlocked</li><li>Unit exams, badges &amp; certificates</li><li>IC study assistant</li><li>Pay by Mobile Money</li></ul><a class="btn pri" href="<?= $loggedIn ? 'subscribe.php' : 'register.php' ?>">Get Plus</a></div></div>
<div class="plans" id="pl-corp">
<div class="plan"><h3>Team</h3><div class="price">Custom</div><ul><li>Seats for your employees</li><li>Manager dashboard &amp; CSV export</li><li>Assign mandatory courses</li><li>Deadline alerts</li></ul><a class="btn ghost" href="register.php">Talk to us</a></div>
<div class="plan hot"><h3>Enterprise</h3><div class="price">Custom</div><ul><li>Everything in Team</li><li>Yearly compliance renewals</li><li>WhatsApp nudges &amp; celebrations</li><li>Priority support</li></ul><a class="btn pri" href="register.php">Contact sales</a></div></div></div></div></section>

<footer><div class="wrap">© <?= date('Y') ?> <?= e($site) ?> · <a href="login.php">Log in</a> · <a href="register.php">Register</a></div></footer>

<div class="modal" id="vm"><div class="vid" id="vbox"><button class="x" id="vx" aria-label="Close">×</button></div></div>
<script>
// Dark/light toggle (remembered; wrapped in try because storage can be blocked)
const root=document.documentElement;try{const s=localStorage.getItem('theme');if(s)root.dataset.theme=s}catch(e){}
document.getElementById('theme').onclick=()=>{const dark=getComputedStyle(root).getPropertyValue('--bg').trim()==='#0b1020';const n=dark?'light':'dark';root.dataset.theme=n;try{localStorage.setItem('theme',n)}catch(e){}};
// Pricing toggle
function setPlan(p){document.querySelectorAll('.toggle button').forEach(b=>b.classList.toggle('on',b.dataset.p===p));document.getElementById('pl-ind').classList.toggle('on',p==='ind');document.getElementById('pl-corp').classList.toggle('on',p==='corp')}
document.querySelectorAll('.toggle button').forEach(b=>b.onclick=()=>setPlan(b.dataset.p));
// Preview modal: the iframe is only created on click, and removed on close (so audio stops)
const vm=document.getElementById('vm'),vb=document.getElementById('vbox');
document.querySelectorAll('.thumb[data-yt]').forEach(t=>t.onclick=()=>{const f=document.createElement('iframe');f.src='https://www.youtube-nocookie.com/embed/'+encodeURIComponent(t.dataset.yt)+'?autoplay=1&rel=0';f.allow='autoplay; encrypted-media';f.allowFullscreen=true;vb.appendChild(f);vm.classList.add('on')});
function closeV(){vm.classList.remove('on');vb.querySelector('iframe')?.remove()}
document.getElementById('vx').onclick=closeV;vm.onclick=e=>{if(e.target===vm)closeV()};document.onkeydown=e=>{if(e.key==='Escape')closeV()};
</script></body></html>
