<?php
/**
 * UBIDS Student ID Card Photo Portal - Helper Functions
 * 
 * Common utility functions used throughout the application
 */

// Prevent direct access
if (!defined('UBIDS_PORTAL')) {
    exit('Direct access denied');
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Generate random filename
 */
function generate_filename($prefix = '', $extension = 'jpg') {
    $timestamp = date('Y_m_d_His');
    $random = bin2hex(random_bytes(4));
    $filename = $prefix . $timestamp . '_' . $random . '.' . $extension;
    return $filename;
}

/**
 * Validate file upload
 */
function validate_file_upload($file, $allowed_types, $max_size = MAX_FILE_SIZE) {
    $errors = [];
    
    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'No file uploaded or file upload error';
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = 'File size exceeds maximum allowed size';
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        $errors[] = 'Invalid file type';
    }
    
    return $errors;
}

/**
 * Get image dimensions
 */
function get_image_dimensions($file_path) {
    $image_info = getimagesize($file_path);
    if (!$image_info) {
        return false;
    }
    
    return [
        'width' => $image_info[0],
        'height' => $image_info[1],
        'type' => $image_info[2],
        'mime' => $image_info['mime']
    ];
}

/**
 * Calculate image brightness
 */
function calculate_brightness($image_path) {
    $image_info = get_image_dimensions($image_path);
    if (!$image_info) {
        return 0;
    }
    
    $image = null;
    switch ($image_info['type']) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($image_path);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($image_path);
            break;
        default:
            return 0;
    }
    
    if (!$image) {
        return 0;
    }
    
    $width = imagesx($image);
    $height = imagesy($image);
    $total_brightness = 0;
    $pixel_count = 0;
    
    // Sample every 10th pixel for performance
    for ($x = 0; $x < $width; $x += 10) {
        for ($y = 0; $y < $height; $y += 10) {
            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            // Calculate brightness using luminance formula
            $brightness = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
            $total_brightness += $brightness;
            $pixel_count++;
        }
    }
    
    imagedestroy($image);
    
    return $pixel_count > 0 ? round(($total_brightness / $pixel_count) * 100, 2) : 0;
}

/**
 * Resize image
 */
function resize_image($source_path, $destination_path, $max_width, $max_height, $quality = 85) {
    $image_info = get_image_dimensions($source_path);
    if (!$image_info) {
        return false;
    }
    
    $source_image = null;
    switch ($image_info['type']) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        default:
            return false;
    }
    
    if (!$source_image) {
        return false;
    }
    
    $source_width = $image_info['width'];
    $source_height = $image_info['height'];
    
    // Calculate new dimensions
    $ratio = min($max_width / $source_width, $max_height / $source_height);
    $new_width = round($source_width * $ratio);
    $new_height = round($source_height * $ratio);
    
    // Create new image
    $new_image = imagecreatetruecolor($new_width, $new_height);
    
    // Handle transparency for PNG
    if ($image_info['type'] == IMAGETYPE_PNG) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
        $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
        imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // Resize image
    imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
    
    // Save image
    $result = false;
    switch ($image_info['type']) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($new_image, $destination_path, $quality);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($new_image, $destination_path, round($quality / 11));
            break;
    }
    
    imagedestroy($source_image);
    imagedestroy($new_image);
    
    return $result;
}

/**
 * Log audit trail
 */
function log_audit($actor_type, $actor_id, $action, $detail = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $sql = "INSERT INTO audit_log (actor_type, actor_id, action, detail, ip_address, user_agent) 
            VALUES (:actor_type, :actor_id, :action, :detail, :ip_address, :user_agent)";
    
    $params = [
        ':actor_type' => $actor_type,
        ':actor_id' => $actor_id,
        ':action' => $action,
        ':detail' => $detail,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent
    ];
    
    return db_execute($sql, $params);
}

/**
 * Check login attempts
 */
function check_login_attempts($identifier) {
    $sql = "SELECT COUNT(*) as attempts FROM audit_log 
            WHERE actor_type = 'student' 
            AND action = 'login_failed' 
            AND detail LIKE :identifier 
            AND created_at > DATE_SUB(NOW(), INTERVAL :window MINUTE)";
    
    $params = [
        ':identifier' => "%$identifier%",
        ':window' => LOGIN_ATTEMPTS_WINDOW / 60
    ];
    
    $attempts = db_query_column($sql, $params);
    return $attempts >= MAX_LOGIN_ATTEMPTS;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'M j, Y g:i A') {
    return date($format, strtotime($date));
}

/**
 * Get status badge HTML
 */
function get_status_badge($status) {
    $badges = [
        'submitted' => '<span class="px-2 py-1 text-xs font-medium bg-blue-100 text-blue-800 rounded-full">Submitted</span>',
        'under_review' => '<span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">Under Review</span>',
        'approved' => '<span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">Approved</span>',
        'rejected' => '<span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">Rejected</span>',
        'generated' => '<span class="px-2 py-1 text-xs font-medium bg-purple-100 text-purple-800 rounded-full">Generated</span>'
    ];
    
    return $badges[$status] ?? '<span class="px-2 py-1 text-xs font-medium bg-gray-100 text-gray-800 rounded-full">Unknown</span>';
}

/**
 * Sanitize filename
 */
function sanitize_filename($filename) {
    // Remove path components
    $filename = basename($filename);
    
    // Remove special characters except dots, hyphens, and underscores
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    
    // Replace multiple dots with single dot
    $filename = preg_replace('/\.+/', '.', $filename);
    
    // Ensure filename is not empty
    if (empty($filename)) {
        $filename = 'file';
    }
    
    return $filename;
}

/**
 * Get file extension
 */
function get_file_extension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Format file size
 */
function format_file_size($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Validate email format
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate secure password hash
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirect with message
 */
function redirect_with_message($url, $message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    redirect($url);
}

/**
 * Get flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        
        return ['message' => $message, 'type' => $type];
    }
    
    return null;
}

/**
 * Check if user is logged in
 */
function is_student_logged_in() {
    return isset($_SESSION['student_type']) && isset($_SESSION['student_id']);
}

/**
 * Check if admin is logged in
 */
function is_admin_logged_in() {
    return isset($_SESSION['admin_id']);
}

/**
 * Require student login
 */
function require_student_login() {
    if (!is_student_logged_in()) {
        redirect_with_message('login.php', 'Please login to continue', 'warning');
    }
}

/**
 * Require admin login
 */
function require_admin_login() {
    if (!is_admin_logged_in()) {
        redirect_with_message('admin/login.php', 'Please login to continue', 'warning');
    }
}

/**
 * Get student data
 */
function get_student_data($type, $id) {
    $table = $type === 'new' ? 'students_new' : 'students_continuing';
    $sql = "SELECT * FROM $table WHERE id = :id AND is_active = 1";
    
    return db_query_one($sql, [':id' => $id]);
}

/**
 * Get submission data
 */
function get_submission_data($id) {
    $sql = "SELECT s.*, a.full_name as reviewer_name 
            FROM submissions s 
            LEFT JOIN admins a ON s.reviewed_by = a.id 
            WHERE s.id = :id";
    
    return db_query_one($sql, [':id' => $id]);
}
?>
