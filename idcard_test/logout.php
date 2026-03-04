<?php
/**
 * UBIDS Student ID Card Photo Portal - Logout
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include required files
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in and logout accordingly
if (is_student_logged_in()) {
    student_logout();
    redirect_with_message('login.php', 'You have been logged out successfully.', 'success');
} elseif (is_admin_logged_in()) {
    admin_logout();
    redirect_with_message('admin/login.php', 'You have been logged out successfully.', 'success');
} else {
    // If not logged in, redirect to login page
    redirect('login.php');
}
?>
