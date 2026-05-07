-- =====================================================
-- GADA SECONDARY SCHOOL — GRADING SYSTEM DATABASE
-- Version 3.1 | Updated to match application schema
-- =====================================================

DROP DATABASE IF EXISTS gada_grading_system;
CREATE DATABASE gada_grading_system
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE gada_grading_system;

-- =====================================================
-- 1. USERS  (username is the natural PK / FK anchor)
-- =====================================================
CREATE TABLE users (
    username        VARCHAR(50)  PRIMARY KEY,
    password        VARCHAR(255) NOT NULL,
    email           VARCHAR(100) UNIQUE NOT NULL,
    full_name       VARCHAR(100) NOT NULL,
    phone           VARCHAR(20)  DEFAULT NULL,
    address         TEXT         DEFAULT NULL,
    role            ENUM('admin','teacher','student') NOT NULL,
    profile_image   VARCHAR(255) DEFAULT NULL,
    last_login      TIMESTAMP    NULL,
    last_ip         VARCHAR(45)  DEFAULT NULL,
    is_active       BOOLEAN      DEFAULT TRUE,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role     (role),
    INDEX idx_users_active   (is_active)
);

-- =====================================================
-- 2. TEACHER
-- =====================================================
CREATE TABLE teacher (
    teacher_id       VARCHAR(20)  PRIMARY KEY,
    username         VARCHAR(50)  UNIQUE NOT NULL,
    qualification    VARCHAR(100) DEFAULT 'Not Specified',
    specialization   VARCHAR(100) DEFAULT 'General',
    department       VARCHAR(50)  DEFAULT 'General',
    experience_years INT          DEFAULT 0,
    join_date        DATE,
    status           ENUM('active','inactive','on_leave') DEFAULT 'active',
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE,
    INDEX idx_teacher_status (status)
);

-- =====================================================
-- 3. STUDENT
-- =====================================================
CREATE TABLE student (
    student_id          VARCHAR(20)  PRIMARY KEY,
    username            VARCHAR(50)  UNIQUE NOT NULL,
    date_of_birth       DATE         DEFAULT NULL,
    age                 INT          DEFAULT NULL,
    sex                 ENUM('M','F') NOT NULL,
    blood_type          VARCHAR(5)   DEFAULT NULL,
    current_grade_level VARCHAR(10)  NOT NULL,
    current_section     VARCHAR(10)  NOT NULL,
    field_of_study      VARCHAR(50)  DEFAULT 'General',
    enrollment_date     DATE,
    student_status      ENUM('active','graduated','transferred','suspended') DEFAULT 'active',
    parent_name         VARCHAR(100) DEFAULT NULL,
    parent_phone        VARCHAR(20)  DEFAULT NULL,
    parent_email        VARCHAR(100) DEFAULT NULL,
    address             TEXT         DEFAULT NULL,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE,
    INDEX idx_student_status  (student_status),
    INDEX idx_student_grade   (current_grade_level, current_section)
);

-- =====================================================
-- 4. SUBJECT
-- =====================================================
CREATE TABLE subject (
    subject_id    VARCHAR(20)  PRIMARY KEY,
    subject_name  VARCHAR(100) NOT NULL,
    short_name    VARCHAR(20)  DEFAULT NULL,
    credits       INT          DEFAULT 3,
    grade_level   VARCHAR(10)  DEFAULT '9-12',
    subject_type  ENUM('core','elective','optional') DEFAULT 'core',
    description   TEXT         DEFAULT NULL,
    is_active     BOOLEAN      DEFAULT TRUE,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_subject_active (is_active)
);

-- =====================================================
-- 5. ACADEMIC YEAR
-- =====================================================
CREATE TABLE academic_year (
    year_id    INT          PRIMARY KEY AUTO_INCREMENT,
    year_name  VARCHAR(20)  UNIQUE NOT NULL,
    start_date DATE,
    end_date   DATE,
    is_current BOOLEAN      DEFAULT FALSE,
    is_active  BOOLEAN      DEFAULT TRUE,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- 6. SEMESTER
-- =====================================================
CREATE TABLE semester (
    semester_id     INT         PRIMARY KEY AUTO_INCREMENT,
    year_id         INT         NOT NULL,
    semester_number INT         NOT NULL,
    semester_name   VARCHAR(50),
    start_date      DATE,
    end_date        DATE,
    is_current      BOOLEAN     DEFAULT FALSE,
    FOREIGN KEY (year_id) REFERENCES academic_year(year_id) ON DELETE CASCADE,
    UNIQUE KEY uk_semester (year_id, semester_number)
);

-- =====================================================
-- 7. TEACHER_SUBJECT
-- =====================================================
CREATE TABLE teacher_subject (
    assignment_id    INT         PRIMARY KEY AUTO_INCREMENT,
    teacher_id       VARCHAR(20) NOT NULL,
    subject_id       VARCHAR(20) NOT NULL,
    academic_year_id INT,
    semester_id      INT,
    is_primary       BOOLEAN     DEFAULT TRUE,
    assigned_date    DATE,
    FOREIGN KEY (teacher_id)       REFERENCES teacher(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)       REFERENCES subject(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_year(year_id),
    UNIQUE KEY uk_teacher_subject (teacher_id, subject_id, academic_year_id)
);

-- =====================================================
-- 8. STUDENT_SUBJECT
-- =====================================================
CREATE TABLE student_subject (
    enrollment_id    INT         PRIMARY KEY AUTO_INCREMENT,
    student_id       VARCHAR(20) NOT NULL,
    subject_id       VARCHAR(20) NOT NULL,
    academic_year_id INT,
    semester_id      INT,
    enrollment_date  DATE,
    is_active        BOOLEAN     DEFAULT TRUE,
    FOREIGN KEY (student_id)       REFERENCES student(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)       REFERENCES subject(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_year(year_id),
    UNIQUE KEY uk_student_subject (student_id, subject_id, academic_year_id)
);

-- =====================================================
-- 9. ASSESSMENT_TYPE
-- =====================================================
CREATE TABLE assessment_type (
    assessment_id   INT          PRIMARY KEY AUTO_INCREMENT,
    assessment_name VARCHAR(50)  UNIQUE NOT NULL,
    default_weight  DECIMAL(5,2) DEFAULT 0,
    description     TEXT         DEFAULT NULL
);

-- =====================================================
-- 10. MARK
-- =====================================================
CREATE TABLE mark (
    mark_id          INT          PRIMARY KEY AUTO_INCREMENT,
    student_id       VARCHAR(20)  NOT NULL,
    subject_id       VARCHAR(20)  NOT NULL,
    teacher_id       VARCHAR(20)  NOT NULL,
    assessment_type_id INT,
    academic_year_id INT,
    semester_id      INT,
    score            DECIMAL(5,2) CHECK (score >= 0 AND score <= 100),
    grade_date       DATE,
    remarks          TEXT         DEFAULT NULL,
    entered_by       VARCHAR(50),
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id)        REFERENCES student(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)        REFERENCES subject(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id)        REFERENCES teacher(teacher_id),
    FOREIGN KEY (assessment_type_id) REFERENCES assessment_type(assessment_id),
    FOREIGN KEY (academic_year_id)  REFERENCES academic_year(year_id),
    INDEX idx_mark_student  (student_id),
    INDEX idx_mark_subject  (subject_id),
    INDEX idx_mark_teacher  (teacher_id),
    INDEX idx_mark_date     (grade_date),
    INDEX idx_mark_year     (academic_year_id)
);

-- =====================================================
-- 11. FINAL_GRADE
-- =====================================================
CREATE TABLE final_grade (
    final_grade_id   INT          PRIMARY KEY AUTO_INCREMENT,
    student_id       VARCHAR(20)  NOT NULL,
    subject_id       VARCHAR(20)  NOT NULL,
    academic_year_id INT,
    semester_id      INT,
    total_score      DECIMAL(5,2),
    letter_grade     VARCHAR(3),
    grade_point      DECIMAL(3,2),
    remarks          VARCHAR(100) DEFAULT NULL,
    is_published     BOOLEAN      DEFAULT FALSE,
    published_date   DATE,
    calculated_date  DATE,
    FOREIGN KEY (student_id)       REFERENCES student(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id)       REFERENCES subject(subject_id) ON DELETE CASCADE,
    UNIQUE KEY uk_final_grade (student_id, subject_id, academic_year_id),
    INDEX idx_fg_year (academic_year_id)
);

-- =====================================================
-- 12. AUDIT_LOG
-- =====================================================
CREATE TABLE audit_log (
    log_id     INT          PRIMARY KEY AUTO_INCREMENT,
    username   VARCHAR(50),
    action     VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id  VARCHAR(50),
    old_data   TEXT,
    new_data   TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE SET NULL,
    INDEX idx_audit_user   (username),
    INDEX idx_audit_action (action),
    INDEX idx_audit_time   (created_at)
);

-- =====================================================
-- 13. LOGIN_ATTEMPTS
-- =====================================================
CREATE TABLE login_attempts (
    attempt_id   INT         PRIMARY KEY AUTO_INCREMENT,
    username     VARCHAR(50) NOT NULL,
    ip_address   VARCHAR(45),
    attempt_time TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_la_user (username),
    INDEX idx_la_ip   (ip_address)
);

-- =====================================================
-- 14. NOTIFICATION
-- =====================================================
CREATE TABLE notification (
    notification_id INT          PRIMARY KEY AUTO_INCREMENT,
    username        VARCHAR(50),
    title           VARCHAR(200) NOT NULL,
    message         TEXT,
    type            VARCHAR(20)  DEFAULT 'info',
    is_read         BOOLEAN      DEFAULT FALSE,
    link            VARCHAR(255),
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (username) REFERENCES users(username) ON DELETE CASCADE,
    INDEX idx_notif_user (username, is_read)
);

-- =====================================================
-- 15. SYSTEM_SETTINGS
-- =====================================================
CREATE TABLE system_settings (
    setting_id    INT          PRIMARY KEY AUTO_INCREMENT,
    setting_key   VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type  VARCHAR(20)  DEFAULT 'text',
    description   TEXT,
    updated_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- =====================================================
-- SEED DATA
-- =====================================================

-- Academic years
INSERT INTO academic_year (year_id, year_name, start_date, end_date, is_current) VALUES
(1, '2023/24', '2023-09-01', '2024-06-30', 0),
(2, '2025/26', '2025-09-01', '2026-06-30', 1);

-- Semesters
INSERT INTO semester (year_id, semester_number, semester_name, start_date, end_date, is_current) VALUES
(2, 1, 'First Semester',  '2025-09-01', '2026-01-30', 1),
(2, 2, 'Second Semester', '2026-02-01', '2026-06-30', 0);

-- Assessment types
INSERT INTO assessment_type (assessment_id, assessment_name, default_weight) VALUES
(1, 'Quiz',       10),
(2, 'Assignment', 10),
(3, 'Class Work',  5),
(4, 'Project',    15),
(5, 'Mid Exam',   20),
(6, 'Final Exam', 40);

-- System settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('passing_mark',           '50',                       'number', 'Minimum passing mark'),
('max_score',              '100',                      'number', 'Maximum score per assessment'),
('school_name',            'Gada Secondary School',    'text',   'School Name'),
('current_academic_year',  '2',                        'number', 'Current Academic Year ID'),
('current_semester',       '1',                        'number', 'Current Semester ID');

-- Subjects
INSERT INTO subject (subject_id, subject_name, short_name, credits, grade_level, subject_type) VALUES
('MATH101', 'Mathematics',          'Math',      5, '9-12',  'core'),
('ENG102',  'English Language',     'English',   4, '9-12',  'core'),
('PHY103',  'Physics',              'Physics',   5, '11-12', 'core'),
('CHEM104', 'Chemistry',            'Chemistry', 5, '11-12', 'core'),
('BIO105',  'Biology',              'Biology',   5, '10-12', 'core'),
('HIST106', 'History',              'History',   3, '9-12',  'core'),
('GEO107',  'Geography',            'Geography', 3, '9-12',  'core'),
('ICT108',  'Information Technology','ICT',      4, '9-12',  'elective');

-- Users (password: admin123 / teacher123 / student123 — all same hash for demo)
INSERT INTO users (username, password, email, full_name, phone, address, role, is_active) VALUES
('admin',        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@gadaschool.edu.et',   'System Administrator', '+251-911-000000', NULL,               'admin',   1),
('nahil.ahmed',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'nahil@gadaschool.edu.et',   'Mr. Nahil Ahmed',      '+251-912-345678', 'Addis Ababa',      'teacher', 1),
('abebe.b',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'abebe.b@student.edu.et',    'Abebe Bikila',         '+251-911-111111', 'Addis Ababa',      'student', 1),


INSERT INTO teacher (teacher_id, username, qualification, specialization, department, experience_years, join_date, status) VALUES
('TCH001', 'nahil.ahmed', 'MSc in Mathematics', 'Mathematics', 'Science', 5, '2020-09-01', 'active');

INSERT INTO student (student_id, username, date_of_birth, sex, current_grade_level, current_section, field_of_study, enrollment_date, parent_name, parent_phone, student_status) VALUES
('STU001', 'abebe.b',  '2008-05-15', 'M', '11', 'A', 'Science',       '2025-09-01', 'Bikila Tesfaye',   '+251-911-111111', 'active'),

-- Teacher assignments
INSERT INTO teacher_subject (teacher_id, subject_id, academic_year_id, is_primary) VALUES
('TCH001','MATH101',2,1),
('TCH001','ENG102', 2,1),
('TCH001','PHY103', 2,1);



--
-- Final grades
INSERT INTO final_grade (student_id, subject_id, academic_year_id, total_score, letter_grade, grade_point, is_published, calculated_date) VALUES
('STU001','MATH101',2, 82,'A-',3.75,1,'2025-12-15'),
('STU001','ENG102', 2, 89,'A', 4.00,1,'2025-12-15'),
('STU001','PHY103', 2, 75,'B+',3.50,1,'2025-12-15'),
('STU002','MATH101',2, 90,'A', 4.00,1,'2025-12-15'),
('STU002','ENG102', 2, 78,'B+',3.50,1,'2025-12-15'),
('STU003','ENG102', 2, 68,'B-',2.75,1,'2025-12-15');

-- Initial audit entries
INSERT INTO audit_log (username, action, table_name, record_id, ip_address) VALUES
('admin','SYSTEM_SETUP','users','admin','127.0.0.1'),
('admin','ADD_STUDENT', 'student','STU001','127.0.0.1'),
('admin','ADD_TEACHER', 'teacher','TCH001','127.0.0.1');

-- =====================================================
-- VERIFY
-- =====================================================
SELECT 'DATABASE SETUP COMPLETE!' AS Status;
SELECT COUNT(*) AS Total_Users    FROM users;
SELECT COUNT(*) AS Total_Students FROM student;
SELECT COUNT(*) AS Total_Teachers FROM teacher;
SELECT COUNT(*) AS Total_Subjects FROM subject;

SELECT '=============================================' AS '';
SELECT 'CREDENTIALS:' AS '';
SELECT 'Admin   — admin       / admin123'   AS Credentials;
SELECT 'Teacher — nahil.ahmed / teacher123' AS Credentials;
SELECT 'Student — abebe.b     / student123' AS Credentials;
SELECT '=============================================' AS '';
