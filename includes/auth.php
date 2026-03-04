<?php
/**
 * UBIDS Student ID Card Photo Portal - Authentication
 * 
 * Handles student and admin authentication
 */

// Prevent direct access
if (!defined('UBIDS_PORTAL')) {
    exit('Direct access denied');
}

/**
 * Student login function
 */
function student_login($admission_number, $last_name, $student_type) {
    $admission_number = strtoupper(trim($admission_number));
    $last_name = strtoupper(trim($last_name));
    
    // Check for brute force attempts
    if (check_login_attempts($admission_number)) {
        log_audit('student', 0, 'login_blocked', "Too many attempts for admission number: $admission_number");
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }
    
    // Select appropriate table
    $table = $student_type === 'new' ? 'students_new' : 'students_continuing';
    
    // Query student data
    $sql = "SELECT id, admission_number, last_name, first_name, other_names, email, 
                   department, programme, level, academic_year 
            FROM $table 
            WHERE admission_number = :admission_number 
            AND UPPER(last_name) = :last_name 
            AND is_active = 1";
    
    $params = [
        ':admission_number' => $admission_number,
        ':last_name' => $last_name
    ];
    
    $student = db_query_one($sql, $params);
    
    if (!$student) {
        log_audit('student', 0, 'login_failed', "Invalid credentials for admission number: $admission_number");
        return ['success' => false, 'message' => 'Invalid admission number or last name'];
    }
    
    // Set session data
    session_regenerate_id(true);
    $_SESSION['student_type'] = $student_type;
    $_SESSION['student_id'] = $student['id'];
    $_SESSION['admission_number'] = $student['admission_number'];
    $_SESSION['student_name'] = trim($student['first_name'] . ' ' . $student['last_name']);
    $_SESSION['student_email'] = $student['email'];
    $_SESSION['login_time'] = time();
    
    // Log successful login
    log_audit('student', $student['id'], 'login_success', "Student logged in: $admission_number");
    
    return ['success' => true, 'student' => $student];
}

/**
 * Admin login function
 */
function admin_login($username, $password) {
    $username = trim($username);
    
    // Check for brute force attempts
    if (check_login_attempts($username)) {
        log_audit('admin', 0, 'login_blocked', "Too many attempts for username: $username");
        return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
    }
    
    // Query admin data
    $sql = "SELECT id, username, password_hash, full_name, role, is_active 
            FROM admins 
            WHERE username = :username";
    
    $params = [':username' => $username];
    $admin = db_query_one($sql, $params);
    
    if (!$admin || !$admin['is_active']) {
        log_audit('admin', 0, 'login_failed', "Invalid username: $username");
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Verify password
    if (!verify_password($password, $admin['password_hash'])) {
        log_audit('admin', $admin['id'], 'login_failed', "Invalid password for username: $username");
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    // Update last login
    db_execute("UPDATE admins SET last_login = NOW() WHERE id = :id", [':id' => $admin['id']]);
    
    // Set session data
    session_regenerate_id(true);
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];
    $_SESSION['login_time'] = time();
    
    // Log successful login
    log_audit('admin', $admin['id'], 'login_success', "Admin logged in: $username");
    
    return ['success' => true, 'admin' => $admin];
}

/**
 * Student logout function
 */
function student_logout() {
    if (is_student_logged_in()) {
        log_audit('student', $_SESSION['student_id'], 'logout', 'Student logged out');
    }
    
    // Destroy session
    session_destroy();
    
    // Start new session for flash messages
    session_start();
}

/**
 * Admin logout function
 */
function admin_logout() {
    if (is_admin_logged_in()) {
        log_audit('admin', $_SESSION['admin_id'], 'logout', 'Admin logged out');
    }
    
    // Destroy session
    session_destroy();
    
    // Start new session for flash messages
    session_start();
}

/**
 * Check session timeout
 */
function check_session_timeout() {
    if (isset($_SESSION['login_time'])) {
        $elapsed = time() - $_SESSION['login_time'];
        if ($elapsed > SESSION_LIFETIME) {
            session_destroy();
            return false;
        }
        // Refresh login time
        $_SESSION['login_time'] = time();
    }
    return true;
}

/**
 * Validate student session
 */
function validate_student_session() {
    if (!is_student_logged_in()) {
        return false;
    }
    
    if (!check_session_timeout()) {
        redirect_with_message('login.php', 'Session expired. Please login again.', 'warning');
    }
    
    // Verify student still exists and is active
    $student = get_student_data($_SESSION['student_type'], $_SESSION['student_id']);
    if (!$student) {
        session_destroy();
        redirect_with_message('login.php', 'Account not found or deactivated.', 'error');
    }
    
    return true;
}

/**
 * Validate admin session
 */
function validate_admin_session() {
    if (!is_admin_logged_in()) {
        return false;
    }
    
    if (!check_session_timeout()) {
        redirect_with_message('admin/login.php', 'Session expired. Please login again.', 'warning');
    }
    
    // Verify admin still exists and is active
    $sql = "SELECT id, username, full_name, role, is_active 
            FROM admins 
            WHERE id = :id AND is_active = 1";
    
    $admin = db_query_one($sql, [':id' => $_SESSION['admin_id']]);
    if (!$admin) {
        session_destroy();
        redirect_with_message('admin/login.php', 'Account not found or deactivated.', 'error');
    }
    
    return true;
}

/**
 * Check admin role permissions
 */
function check_admin_role($required_role = 'admin') {
    if (!is_admin_logged_in()) {
        return false;
    }
    
    $current_role = $_SESSION['admin_role'];
    
    // Superadmin has access to everything
    if ($current_role === 'superadmin') {
        return true;
    }
    
    // Check role hierarchy
    if ($required_role === 'admin' && $current_role === 'admin') {
        return true;
    }
    
    return false;
}

/**
 * Get current student data
 */
function get_current_student() {
    if (!is_student_logged_in()) {
        return null;
    }
    
    return get_student_data($_SESSION['student_type'], $_SESSION['student_id']);
}

/**
 * Get current admin data
 */
function get_current_admin() {
    if (!is_admin_logged_in()) {
        return null;
    }
    
    $sql = "SELECT id, username, full_name, role, last_login 
            FROM admins 
            WHERE id = :id";
    
    return db_query_one($sql, [':id' => $_SESSION['admin_id']]);
}

/**
 * Update student session data
 */
function update_student_session() {
    if (!is_student_logged_in()) {
        return;
    }
    
    $student = get_student_data($_SESSION['student_type'], $_SESSION['student_id']);
    if ($student) {
        $_SESSION['student_name'] = trim($student['first_name'] . ' ' . $student['last_name']);
        $_SESSION['student_email'] = $student['email'];
    }
}

/**
 * Update admin session data
 */
function update_admin_session() {
    if (!is_admin_logged_in()) {
        return;
    }
    
    $admin = get_current_admin();
    if ($admin) {
        $_SESSION['admin_name'] = $admin['full_name'];
        $_SESSION['admin_role'] = $admin['role'];
    }
}

/**
 * Check if student has submission
 */
function student_has_submission() {
    if (!is_student_logged_in()) {
        return false;
    }
    
    $sql = "SELECT id FROM submissions 
            WHERE student_type = :student_type 
            AND student_id = :student_id";
    
    $params = [
        ':student_type' => $_SESSION['student_type'],
        ':student_id' => $_SESSION['student_id']
    ];
    
    $submission = db_query_one($sql, $params);
    return !empty($submission);
}

/**
 * Get student submission
 */
function get_student_submission() {
    if (!is_student_logged_in()) {
        return null;
    }
    
    $sql = "SELECT * FROM submissions 
            WHERE student_type = :student_type 
            AND student_id = :student_id 
            ORDER BY submitted_at DESC 
            LIMIT 1";
    
    $params = [
        ':student_type' => $_SESSION['student_type'],
        ':student_id' => $_SESSION['student_id']
    ];
    
    return db_query_one($sql, $params);
}
?>
