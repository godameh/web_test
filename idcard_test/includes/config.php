<?php
/**
 * UBIDS Student ID Card Photo Portal - Configuration
 * 
 * This file contains all system configuration settings
 */

// Prevent direct access
if (!defined('UBIDS_PORTAL')) {
    exit('Direct access denied');
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'ubids_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Application Configuration
define('APP_NAME', 'UBIDS ID Card Portal');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/idcard_test');
define('APP_DEBUG', true);

// Security Configuration
define('HASH_ALGO', 'sha256');
define('SESSION_NAME', 'ubids_portal_session');
define('SESSION_LIFETIME', 1800); // 30 minutes
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 10);
define('LOGIN_ATTEMPTS_WINDOW', 900); // 15 minutes

// File Upload Configuration
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_PHOTO_TYPES', ['image/jpeg', 'image/png', 'image/heic']);
define('ALLOWED_DOC_TYPES', ['image/jpeg', 'image/png', 'application/pdf']);
define('MIN_PHOTO_WIDTH', 390);
define('MIN_PHOTO_HEIGHT', 540);
define('UPLOAD_PATH', __DIR__ . '/../uploads');
define('PHOTO_UPLOAD_PATH', UPLOAD_PATH . '/photos');
define('DOC_UPLOAD_PATH', UPLOAD_PATH . '/id_docs');
define('CARD_UPLOAD_PATH', UPLOAD_PATH . '/generated_cards');

// Email Configuration
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');
define('EMAIL_FROM', 'noreply@ubids.edu.gh');
define('EMAIL_FROM_NAME', 'UBIDS ID Card Portal');

// ID Card Configuration
define('ID_CARD_WIDTH', 800);
define('ID_CARD_HEIGHT', 500);
define('PHOTO_WIDTH', 200);
define('PHOTO_HEIGHT', 250);
define('TEMPLATE_PATH', __DIR__ . '/../assets/templates/id_card_template.png');

// Font Configuration (for ID card generation)
define('FONT_BOLD', __DIR__ . '/../assets/fonts/arialbd.ttf');
define('FONT_REGULAR', __DIR__ . '/../assets/fonts/arial.ttf');

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('Africa/Accra');

// Security Headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-XSS-Protection: 1; mode=block');

// Content Security Policy (CSP)
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
       "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
       "font-src 'self' https://fonts.gstatic.com; " .
       "img-src 'self' data: blob:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'none'; " .
       "base-uri 'self'; " .
       "form-action 'self';";
header("Content-Security-Policy: $csp");

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);
    session_start();
}

// Helper function for secure output
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Helper function for redirect
function redirect($url) {
    header("Location: $url");
    exit;
}

// Helper function for JSON response
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Check if application is installed
function is_installed() {
    return file_exists(__DIR__ . '/../installed.lock');
}

// Create upload directories if they don't exist
$upload_dirs = [UPLOAD_PATH, PHOTO_UPLOAD_PATH, DOC_UPLOAD_PATH, CARD_UPLOAD_PATH];
foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>
