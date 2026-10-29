# Install — OTP activation · Upgrade page · Landing page · B2B · WhatsApp

## 1. Database (once)
phpMyAdmin → click your database → **SQL** tab → paste `patch_enterprise.sql` → Go.
Existing users stay verified; only NEW sign-ups need the emailed code.

## 2. Upload the files (keep the folders)
```
includes/enterprise.php        NEW        includes/notify_config.php   NEW  (edit it!)
admin/companies.php            NEW        manager/index.php, export.php NEW   cron/nudge.php NEW
login.php  register.php  subscribe.php  home.php   → replace
verify-otp.php                 NEW
```
Do NOT overwrite includes/config.php (your DB password lives there).

## 3. Four small edits in files you already have
**includes/auth.php**
1. Very last line of the file, add:
   `require_once __DIR__ . '/enterprise.php';`
2. Replace the whole `awardBadge` function with:
   ```php
   function awardBadge($userId,$unitId) {
       getDB()->prepare("INSERT IGNORE INTO student_badges (user_id,unit_id) VALUES (?,?)")->execute([$userId,$unitId]);
       if (function_exists('onBadgeAwarded')) onBadgeAwarded((int)$userId,(int)$unitId);   // compliance + WhatsApp
   }
   ```
3. In `adminSidebar()`, under the Subjects link, add:
   `<a href="companies.php" class="nav-item <?=$active==='companies'?'active':''?>"><span class="nav-icon">🏢</span> Companies</a>`

**lesson.php** — the paywall button pointed to the wrong place. Change `../subscribe.php` to `subscribe.php`.
(This was my mistake earlier and is the likely reason "nothing changed" when you pressed Upgrade.)

**dashboard.php** — in the sidebar, after the Live Sessions link, add:
`<a href="subscribe.php" class="sb-item"><span class="sb-icon">⭐</span> Upgrade to Plus</a>`

## 4. Optional hooks
- **video-room.php** (WhatsApp when a student joins a live session), after the session + user are loaded:
  `notifyLiveJoin((int)$user['id'], $session['title'], (int)$session['id']);`
- **admin/certificates.php** (WhatsApp + link when a certificate is issued), right after the INSERT:
  `notifyCertificateIssued((int)$db->lastInsertId());`

## 5. Configure `includes/notify_config.php`
| Setting | What for |
|---|---|
| `MAIL_FROM` | sender address on YOUR domain (helps emails avoid spam) |
| `OTP_DEBUG_ON_SCREEN` | `true` only while testing: shows the code on screen if mail() fails |
| `PAY_NUMBER` + `PAY_USSD` | makes "Pay now" open the phone dialer with your MoMo code, e.g. `*182*8*1*{CODE}#` |
| `TWILIO_SID/TOKEN/WHATSAPP_FROM` | WhatsApp sending |
| `CRON_SECRET` | long random text; required for the nudge cron |

## 6. Cron (inactivity nudges)
Once a day: `php /path/cron/nudge.php`, or on hosts with no cron, schedule
`https://YOUR-SITE/cron/nudge.php?key=YOUR_CRON_SECRET` at cron-job.org.

## Test checklist
1. Register a new account → email with 6-digit code → enter it → login works.
2. Unverified account that tries to log in is sent back to the code screen.
3. Admin → Companies → create a company + manager → log in as manager → add employee → Assign course.
4. Student → Upgrade → Pay / Choose file / Take photo → Send → Admin → Plus Subscriptions shows the receipt.

## Know before you go live
- **Free hosting (InfinityFree) blocks outgoing connections.** Twilio (and SMTP) calls will fail there; failures are logged in `notification_log` and never break a page. WhatsApp needs a host that allows outbound HTTPS. PHP `mail()` may work but can be slow/limited or land in spam. If a code never arrives, activate by hand: `UPDATE users SET email_verified=1 WHERE email='x@y.com';`
- **WhatsApp rules:** outside a 24-hour chat window, businesses may only send pre-approved templates; the Twilio sandbox only reaches numbers that joined it. Users with `wa_opt_out=1` are never messaged.
- Landing-page testimonials, logo names and any prices are **placeholders** — replace with real, consented content before launch.
- Annual renewal is tracked (expiry date + alerts). Re-certification after expiry still needs the unit's progress reset by an admin.
