-- ============================================================
-- PATCH: Email OTP + B2B (companies/teams/compliance) + notifications
-- Run INSIDE your database: phpMyAdmin -> click the DB -> SQL tab -> paste -> Go
-- (No "USE" line on purpose; no foreign keys on purpose, so deletes never get blocked)
-- ============================================================

-- 1) USERS: manager role, company link, OTP fields, activity tracking
ALTER TABLE users
  MODIFY COLUMN role ENUM('student','teacher','admin','manager') DEFAULT 'student',
  ADD COLUMN IF NOT EXISTS company_id INT NULL,
  ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 1,   -- existing users stay verified
  ADD COLUMN IF NOT EXISTS otp_hash VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS otp_expires DATETIME NULL,
  ADD COLUMN IF NOT EXISTS otp_attempts TINYINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS otp_last_sent DATETIME NULL,
  ADD COLUMN IF NOT EXISTS last_active_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS wa_opt_out TINYINT(1) NOT NULL DEFAULT 0;

-- 2) COMPANIES (one row per corporate client)
CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  billing_email VARCHAR(190) NULL,
  seats_total INT NOT NULL DEFAULT 10,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3) TEAMS: a manager owns a team; employees are members
CREATE TABLE IF NOT EXISTS teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  manager_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_company (company_id), KEY idx_manager (manager_id)
);
CREATE TABLE IF NOT EXISTS team_members (
  id INT AUTO_INCREMENT PRIMARY KEY,
  team_id INT NOT NULL,
  user_id INT NOT NULL,
  UNIQUE KEY uq_member (team_id, user_id)
);

-- 4) COMPLIANCE RECORDS: mandatory course progression + yearly renewal
--    "course" = a platform Unit (lessons + exam + badge)
CREATE TABLE IF NOT EXISTS compliance_records (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  user_id INT NOT NULL,
  unit_id INT NOT NULL,
  assigned_by INT NULL,
  is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('assigned','in_progress','completed','expired') NOT NULL DEFAULT 'assigned',
  due_date DATE NULL,
  completed_at DATETIME NULL,
  certificate_id INT NULL,
  renewal_months INT NOT NULL DEFAULT 12,
  expires_at DATE NULL,
  assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_unit (user_id, unit_id),
  KEY idx_company (company_id), KEY idx_due (due_date), KEY idx_exp (expires_at)
);

-- 5) NOTIFICATION LOG (audit + de-duplication; never stores message bodies or OTPs)
CREATE TABLE IF NOT EXISTS notification_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
  type VARCHAR(40) NOT NULL,
  ref_id INT NULL,
  status VARCHAR(20) NOT NULL,
  error VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user_type (user_id, type, ref_id)
);

-- 6) SUBSCRIPTION RECEIPTS (safe to run again)
CREATE TABLE IF NOT EXISTS subscription_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  approved_by INT DEFAULT NULL,
  approved_at TIMESTAMP NULL DEFAULT NULL,
  months INT DEFAULT 1
);
ALTER TABLE subscription_requests
  ADD COLUMN IF NOT EXISTS payment_screenshot VARCHAR(500) DEFAULT NULL;

SELECT 'Enterprise patch applied!' AS status;
