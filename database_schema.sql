-- UBIDS Student ID Card Photo Portal Database Schema
-- MySQL 8.0 compatible

CREATE DATABASE IF NOT EXISTS ubids_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ubids_portal;

-- Students Table (New Applicants)
CREATE TABLE students_new (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(20) UNIQUE NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    other_names VARCHAR(100),
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    department VARCHAR(100) NOT NULL,
    programme VARCHAR(150) NOT NULL,
    level VARCHAR(20) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_admission (admission_number),
    INDEX idx_email (email),
    INDEX idx_department (department),
    INDEX idx_academic_year (academic_year)
) ENGINE=InnoDB;

-- Students Table (Continuing/Replacement Applicants)
CREATE TABLE students_continuing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admission_number VARCHAR(20) UNIQUE NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    other_names VARCHAR(100),
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    department VARCHAR(100) NOT NULL,
    programme VARCHAR(150) NOT NULL,
    level VARCHAR(20) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    is_replacement BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_admission (admission_number),
    INDEX idx_email (email),
    INDEX idx_department (department),
    INDEX idx_academic_year (academic_year),
    INDEX idx_replacement (is_replacement)
) ENGINE=InnoDB;

-- Submissions Table
CREATE TABLE submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_type ENUM('new', 'continuing') NOT NULL,
    student_id INT NOT NULL,
    photo_filename VARCHAR(255),
    id_card_filename VARCHAR(255),
    identity_doc_filename VARCHAR(255),
    original_name VARCHAR(255),
    file_size_kb INT,
    width_px INT,
    height_px INT,
    background_brightness_score DECIMAL(5,2),
    accessory_flag BOOLEAN DEFAULT FALSE,
    status ENUM('submitted', 'under_review', 'approved', 'rejected', 'generated') DEFAULT 'submitted',
    rejection_reason TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (reviewed_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_student (student_type, student_id),
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_reviewed_by (reviewed_by)
) ENGINE=InnoDB;

-- Admins Table
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('admin', 'superadmin') DEFAULT 'admin',
    last_login TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Audit Log Table
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actor_type ENUM('student', 'admin', 'system') NOT NULL,
    actor_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    detail TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB;

-- Email Queue Table (for reliable email delivery)
CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(150) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_attempts (attempts)
) ENGINE=InnoDB;

-- System Settings Table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES admins(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO admins (username, password_hash, full_name, role) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'superadmin');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES 
('max_file_size_mb', '2', 'Maximum file upload size in MB'),
('min_photo_width', '390', 'Minimum photo width in pixels'),
('min_photo_height', '540', 'Minimum photo height in pixels'),
('session_timeout_minutes', '30', 'Session timeout in minutes'),
('login_attempts_limit', '10', 'Maximum login attempts per time window'),
('login_attempts_window', '15', 'Login attempts time window in minutes'),
('email_from_address', 'noreply@ubids.edu.gh', 'From email address'),
('email_from_name', 'UBIDS ID Card Portal', 'From email name'),
('institution_name', 'University of Business and Integrated Development Studies', 'Institution full name'),
('id_card_template_path', 'assets/templates/id_card_template.png', 'ID card template file path');
