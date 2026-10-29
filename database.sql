-- ============================================================
-- TrainHub Complete Database — Levels > Units > Lessons > Quiz > Exam
-- Run this ENTIRE file in phpMyAdmin → training_platform → SQL
-- ============================================================

CREATE DATABASE IF NOT EXISTS training_platform;
USE training_platform;

-- ─── USERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    whatsapp_number VARCHAR(20) NOT NULL,
    role ENUM('student','admin') DEFAULT 'student',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── SETTINGS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('company_name',            'TrainHub Academy'),
('ceo_name',                'John Smith'),
('ceo_title',               'Chief Executive Officer'),
('company_tagline',         'Excellence in Professional Training'),
('company_website',         'https://www.trainhub.com'),
('company_email',           'info@trainhub.com'),
('logo_path',               ''),
('signature_path',          ''),
('certificate_border_color','#1a1a6e'),
('certificate_accent_color','#c8a84b'),
('certificate_footer_text', 'This certificate is awarded upon successful completion of the training program.')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ─── LEVELS ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    order_index INT NOT NULL DEFAULT 1,
    color VARCHAR(20) DEFAULT '#0d2137',
    icon VARCHAR(10) DEFAULT '📘',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ─── UNITS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    order_index INT NOT NULL DEFAULT 1,
    badge_name VARCHAR(100) DEFAULT NULL,
    badge_icon VARCHAR(10) DEFAULT '🏅',
    badge_color VARCHAR(20) DEFAULT '#c8a84b',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE
);

-- ─── LESSONS (under units) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS lessons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    youtube_url VARCHAR(500) NOT NULL,
    youtube_id VARCHAR(50) NOT NULL,
    order_index INT NOT NULL DEFAULT 1,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- ─── QUIZ QUESTIONS (per lesson) ─────────────────────────────
CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lesson_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(300),
    option_b VARCHAR(300),
    option_c VARCHAR(300),
    option_d VARCHAR(300),
    correct_answer ENUM('A','B','C','D') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
);

-- ─── UNIT EXAMS (one per unit) ───────────────────────────────
CREATE TABLE IF NOT EXISTS unit_exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_id INT NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    pass_score INT DEFAULT 70 COMMENT 'Minimum % to pass',
    time_limit_minutes INT DEFAULT NULL COMMENT 'NULL = no limit',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE
);

-- ─── EXAM QUESTIONS ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(300),
    option_b VARCHAR(300),
    option_c VARCHAR(300),
    option_d VARCHAR(300),
    correct_answer ENUM('A','B','C','D') NOT NULL,
    marks INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES unit_exams(id) ON DELETE CASCADE
);

-- ─── STUDENT LESSON PROGRESS ─────────────────────────────────
CREATE TABLE IF NOT EXISTS student_progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    video_watched TINYINT(1) DEFAULT 0,
    quiz_submitted TINYINT(1) DEFAULT 0,
    quiz_score INT DEFAULT 0,
    screenshot_sent TINYINT(1) DEFAULT 0,
    admin_approved TINYINT(1) DEFAULT 0,
    approved_by INT DEFAULT NULL,
    approved_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    UNIQUE KEY unique_progress (user_id, lesson_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id)
);

-- ─── QUIZ ANSWERS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A','B','C','D'),
    is_correct TINYINT(1) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (lesson_id) REFERENCES lessons(id),
    FOREIGN KEY (question_id) REFERENCES questions(id)
);

-- ─── EXAM ATTEMPTS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    exam_id INT NOT NULL,
    score INT DEFAULT 0 COMMENT 'Percentage 0-100',
    total_marks INT DEFAULT 0,
    earned_marks INT DEFAULT 0,
    passed TINYINT(1) DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    attempt_number INT DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (exam_id) REFERENCES unit_exams(id)
);

-- ─── EXAM ANSWERS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS exam_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id INT NOT NULL,
    question_id INT NOT NULL,
    selected_answer ENUM('A','B','C','D'),
    is_correct TINYINT(1) DEFAULT 0,
    FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES exam_questions(id)
);

-- ─── BADGES (awarded at unit completion) ─────────────────────
CREATE TABLE IF NOT EXISTS student_badges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    unit_id INT NOT NULL,
    awarded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_badge (user_id, unit_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (unit_id) REFERENCES units(id)
);

-- ─── CERTIFICATES ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    certificate_number VARCHAR(50) NOT NULL UNIQUE,
    issued_by INT NOT NULL,
    cert_type ENUM('level','combined') DEFAULT 'level',
    level_id INT DEFAULT NULL COMMENT 'NULL if combined',
    course_name VARCHAR(200) NOT NULL,
    issued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    valid_until DATE DEFAULT NULL,
    status ENUM('active','revoked') DEFAULT 'active',
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (issued_by) REFERENCES users(id),
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE SET NULL
);

-- ─── LIVE SESSIONS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS live_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    host_id INT NOT NULL,
    room_code VARCHAR(20) NOT NULL UNIQUE,
    platform ENUM('jitsi','zoom','google_meet','teams','custom') DEFAULT 'jitsi',
    scheduled_at DATETIME NOT NULL,
    duration_minutes INT DEFAULT 60,
    status ENUM('upcoming','live','ended','cancelled') DEFAULT 'upcoming',
    recording_url VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES users(id)
);

-- ─── SESSION REGISTRATIONS ───────────────────────────────────
CREATE TABLE IF NOT EXISTS session_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    user_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    attended TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_reg (session_id, user_id),
    FOREIGN KEY (session_id) REFERENCES live_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ─── DEFAULT ADMIN ────────────────────────────────────────────
-- Login:    admin@training.com
-- Password: Admin@123
-- (change this from the admin panel or with a new UPDATE statement after first login)
INSERT INTO users (name, email, password, whatsapp_number, role)
VALUES ('Admin', 'admin@training.com',
        '$2y$10$MVfJTFvh3gcU8vxYHsfKCeVF9rQGyamti4qkYf5sEpDkyNAE/MC7q',
        '+1234567890', 'admin')
ON DUPLICATE KEY UPDATE
    password = '$2y$10$MVfJTFvh3gcU8vxYHsfKCeVF9rQGyamti4qkYf5sEpDkyNAE/MC7q';
-- ─── SAMPLE DATA ──────────────────────────────────────────────
-- Level 1
INSERT INTO levels (title, description, order_index, color, icon) VALUES
('Beginner', 'Start your journey with the fundamentals of web development.', 1, '#0d6efd', '📘'),
('Intermediate', 'Build on your knowledge with advanced concepts and projects.', 2, '#00897b', '📗'),
('Advanced', 'Master professional techniques and industry best practices.', 3, '#6f42c1', '📙');

-- Units for Level 1
INSERT INTO units (level_id, title, description, order_index, badge_name, badge_icon, badge_color) VALUES
(1, 'Web Fundamentals', 'HTML, CSS basics and how the web works.', 1, 'Web Pioneer', '🌐', '#c8a84b'),
(1, 'Styling & Layout', 'CSS styling, Flexbox, and responsive design.', 2, 'Style Master', '🎨', '#00897b'),
(2, 'JavaScript Basics', 'Variables, functions, loops and DOM manipulation.', 1, 'JS Coder', '⚡', '#f5a623'),
(2, 'JavaScript Advanced', 'Async, APIs, ES6+ and modern JS patterns.', 2, 'JS Pro', '🚀', '#6f42c1');

-- Lessons for Unit 1
INSERT INTO lessons (unit_id, title, description, youtube_url, youtube_id, order_index) VALUES
(1, 'How the Web Works', 'Learn about browsers, servers, HTTP and the internet.', 'https://www.youtube.com/watch?v=qz0aGYrrlhU', 'qz0aGYrrlhU', 1),
(1, 'HTML Fundamentals', 'Tags, structure, semantic HTML and best practices.', 'https://www.youtube.com/watch?v=UB1O30fR-EE', 'UB1O30fR-EE', 2),
(2, 'CSS Basics', 'Selectors, properties, box model and colors.', 'https://www.youtube.com/watch?v=yfoY53QXEnI', 'yfoY53QXEnI', 1),
(2, 'Flexbox & Grid', 'Modern CSS layout techniques.', 'https://www.youtube.com/watch?v=JJSoEo8JSnc', 'JJSoEo8JSnc', 2);

-- Quiz questions for lesson 1
INSERT INTO questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES
(1, 'What does HTML stand for?', 'Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language', 'A'),
(1, 'What protocol do browsers use to request web pages?', 'FTP', 'SMTP', 'HTTP', 'SSH', 'C'),
(1, 'What does CSS stand for?', 'Creative Style Sheets', 'Cascading Style Sheets', 'Computer Style Sheets', 'Colorful Style Sheets', 'B');

-- Quiz questions for lesson 2
INSERT INTO questions (lesson_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES
(2, 'Which tag creates the largest heading?', '<h6>', '<h1>', '<head>', '<title>', 'B'),
(2, 'Which tag is used for a hyperlink?', '<link>', '<href>', '<a>', '<url>', 'C'),
(2, 'What attribute specifies an image path?', 'href', 'src', 'link', 'path', 'B');

-- Unit Exam for Unit 1
INSERT INTO unit_exams (unit_id, title, description, pass_score, time_limit_minutes) VALUES
(1, 'Web Fundamentals Final Exam', 'Test your knowledge of HTML and how the web works.', 70, 30),
(2, 'Styling & Layout Final Exam', 'Test your CSS knowledge and layout skills.', 70, 30);

-- Exam questions for exam 1
INSERT INTO exam_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks) VALUES
(1, 'What does HTML stand for?', 'Hyper Text Markup Language', 'High Tech Modern Language', 'Hyper Transfer Markup Language', 'Home Tool Markup Language', 'A', 2),
(1, 'Which HTML tag creates an unordered list?', '<ol>', '<ul>', '<li>', '<list>', 'B', 2),
(1, 'What does the <head> section contain?', 'Page content', 'Metadata and links', 'Navigation links', 'Footer content', 'B', 2),
(1, 'Which attribute makes a link open in a new tab?', 'target="_new"', 'target="_top"', 'target="_blank"', 'open="new"', 'C', 2),
(1, 'What is the correct HTML for a paragraph?', '<p>Text</p>', '<para>Text</para>', '<pg>Text</pg>', '<par>Text</par>', 'A', 2);

SELECT 'Database setup complete!' AS result;
