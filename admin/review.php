<?php
/**
 * UBIDS Student ID Card Photo Portal - Admin Review Page
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include required files
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/id_card_generator.php';

// Require admin login
require_admin_login();

// Get current admin
$admin = get_current_admin();

// Get submission ID
$submission_id = $_GET['id'] ?? 0;
$submission_id = (int) $submission_id;

if ($submission_id <= 0) {
    redirect_with_message('submissions.php', 'Invalid submission ID', 'error');
}

// Get submission data
$submission = get_submission_data($submission_id);
if (!$submission) {
    redirect_with_message('submissions.php', 'Submission not found', 'error');
}

// Get student data
$student = get_student_data($submission['student_type'], $submission['student_id']);
if (!$student) {
    redirect_with_message('submissions.php', 'Student not found', 'error');
}

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf_token)) {
        redirect_with_message('review.php?id=' . $submission_id, 'Invalid request. Please try again.', 'error');
    }
    
    switch ($action) {
        case 'approve':
            // Update submission status
            $sql = "UPDATE submissions SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW() WHERE id = :id";
            $params = [':admin_id' => $admin['id'], ':id' => $submission_id];
            
            if (db_execute($sql, $params)) {
                log_audit('admin', $admin['id'], 'submission_approved', "Approved submission ID: $submission_id");
                redirect_with_message('review.php?id=' . $submission_id, 'Submission approved successfully!', 'success');
            } else {
                redirect_with_message('review.php?id=' . $submission_id, 'Failed to approve submission', 'error');
            }
            break;
            
        case 'reject':
            if (empty($rejection_reason)) {
                redirect_with_message('review.php?id=' . $submission_id, 'Rejection reason is required', 'error');
            }
            
            // Update submission status
            $sql = "UPDATE submissions SET status = 'rejected', rejection_reason = :reason, reviewed_by = :admin_id, reviewed_at = NOW(), updated_at = NOW() WHERE id = :id";
            $params = [':reason' => $rejection_reason, ':admin_id' => $admin['id'], ':id' => $submission_id];
            
            if (db_execute($sql, $params)) {
                log_audit('admin', $admin['id'], 'submission_rejected', "Rejected submission ID: $submission_id - Reason: $rejection_reason");
                redirect_with_message('review.php?id=' . $submission_id, 'Submission rejected', 'warning');
            } else {
                redirect_with_message('review.php?id=' . $submission_id, 'Failed to reject submission', 'error');
            }
            break;
            
        case 'generate_card':
            // Validate requirements
            $validation = validate_id_card_requirements($submission_id);
            if (!$validation['valid']) {
                redirect_with_message('review.php?id=' . $submission_id, $validation['message'], 'error');
            }
            
            // Generate ID card
            $result = generate_id_card($submission_id);
            if ($result['success']) {
                log_audit('admin', $admin['id'], 'id_card_generated', "Generated ID card for submission ID: $submission_id");
                redirect_with_message('review.php?id=' . $submission_id, 'ID card generated successfully!', 'success');
            } else {
                redirect_with_message('review.php?id=' . $submission_id, 'Failed to generate ID card: ' . $result['message'], 'error');
            }
            break;
            
        case 'regenerate_card':
            // Delete existing card and regenerate
            $delete_result = delete_id_card($submission_id);
            if ($delete_result['success']) {
                // Generate new card
                $result = generate_id_card($submission_id);
                if ($result['success']) {
                    log_audit('admin', $admin['id'], 'id_card_regenerated', "Regenerated ID card for submission ID: $submission_id");
                    redirect_with_message('review.php?id=' . $submission_id, 'ID card regenerated successfully!', 'success');
                } else {
                    redirect_with_message('review.php?id=' . $submission_id, 'Failed to regenerate ID card: ' . $result['message'], 'error');
                }
            } else {
                redirect_with_message('review.php?id=' . $submission_id, 'Failed to delete existing ID card', 'error');
            }
            break;
    }
}

// Get flash message
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Submission - <?php echo h(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        .display-font { font-family: 'Syne', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 text-white">
            <div class="p-6">
                <div class="flex items-center mb-8">
                    <div class="h-10 w-10 bg-yellow-500 rounded-full flex items-center justify-center">
                        <svg class="h-6 w-6 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h1 class="display-font text-lg font-bold">Admin Portal</h1>
                        <p class="text-xs text-gray-400">UBIDS ID Card System</p>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="space-y-2">
                    <a href="index.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="submissions.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-1 9h-4v4h-2v-4H9V9h4V5h2v4h4v2z"/>
                        </svg>
                        Submissions
                    </a>
                    <a href="students.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                        </svg>
                        Students
                    </a>
                    <a href="export.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/>
                        </svg>
                        Export
                    </a>
                    <a href="audit.php" class="flex items-center px-3 py-2 text-sm font-medium rounded-lg text-gray-300 hover:bg-gray-800 hover:text-white">
                        <svg class="h-5 w-5 mr-3" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        Audit Log
                    </a>
                </nav>

                <!-- Admin Profile -->
                <div class="absolute bottom-0 left-0 right-0 p-6 border-t border-gray-800">
                    <div class="flex items-center">
                        <div class="h-8 w-8 bg-gray-700 rounded-full flex items-center justify-center">
                            <svg class="h-5 w-5 text-gray-300" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-white"><?php echo h($admin['full_name']); ?></p>
                            <p class="text-xs text-gray-400"><?php echo h($admin['role']); ?></p>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="../logout.php" class="text-xs text-gray-400 hover:text-white">Sign out</a>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white shadow-sm border-b border-gray-200">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="display-font text-2xl font-bold text-gray-900">Review Submission</h1>
                            <p class="text-sm text-gray-500">Submission ID: #<?php echo str_pad($submission_id, 6, '0', STR_PAD_LEFT); ?></p>
                        </div>
                        <div class="flex space-x-3">
                            <a href="submissions.php" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                                ← Back to Submissions
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="mx-6 mt-6 rounded-md p-4 <?php echo $flash['type'] === 'error' ? 'bg-red-50 text-red-800' : ($flash['type'] === 'warning' ? 'bg-yellow-50 text-yellow-800' : ($flash['type'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800')); ?>">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <?php if ($flash['type'] === 'error'): ?>
                                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                </svg>
                            <?php elseif ($flash['type'] === 'warning'): ?>
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            <?php elseif ($flash['type'] === 'success'): ?>
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            <?php else: ?>
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium"><?php echo h($flash['message']); ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Review Content -->
            <div class="p-6">
                <!-- Student Information -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Student Information</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Full Name</p>
                            <p class="font-medium text-gray-900"><?php echo h(trim($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['other_names'])); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Admission Number</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['admission_number']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Student Type</p>
                            <p class="font-medium text-gray-900">
                                <?php 
                                echo ucfirst($student['student_type']);
                                if ($student['is_replacement']) {
                                    echo ' (Replacement)';
                                }
                                ?>
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Department</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['department']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Programme</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['programme']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Level</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['level']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Email</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['email']); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Phone</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['phone'] ?? 'Not provided'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Academic Year</p>
                            <p class="font-medium text-gray-900"><?php echo h($student['academic_year']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Submission Details -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Submission Details</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-500">Submission Status</p>
                            <div class="mt-1"><?php echo get_status_badge($submission['status']); ?></div>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Submitted On</p>
                            <p class="font-medium text-gray-900"><?php echo format_date($submission['submitted_at'], 'M j, Y g:i A'); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">File Size</p>
                            <p class="font-medium text-gray-900"><?php echo format_file_size($submission['file_size_kb'] * 1024); ?></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">Photo Dimensions</p>
                            <p class="font-medium text-gray-900"><?php echo $submission['width_px']; ?> × <?php echo $submission['height_px']; ?> px</p>
                        </div>
                        <?php if ($submission['background_brightness_score']): ?>
                            <div>
                                <p class="text-sm text-gray-500">Background Brightness</p>
                                <p class="font-medium text-gray-900"><?php echo $submission['background_brightness_score']; ?>%</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($submission['reviewed_at']): ?>
                            <div>
                                <p class="text-sm text-gray-500">Reviewed On</p>
                                <p class="font-medium text-gray-900"><?php echo format_date($submission['reviewed_at'], 'M j, Y g:i A'); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if ($submission['reviewer_name']): ?>
                            <div>
                                <p class="text-sm text-gray-500">Reviewed By</p>
                                <p class="font-medium text-gray-900"><?php echo h($submission['reviewer_name']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($submission['rejection_reason']): ?>
                        <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                            <p class="text-sm font-medium text-red-800">Rejection Reason:</p>
                            <p class="text-sm text-red-700 mt-1"><?php echo h($submission['rejection_reason']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Photo Review -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Photo Review</h2>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Uploaded Photo -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Uploaded Photo</h3>
                            <div class="border-2 border-gray-200 rounded-lg p-4">
                                <img src="<?php echo APP_URL . '/uploads/photos/' . $submission['photo_filename']; ?>" 
                                     alt="Student Photo" 
                                     class="w-full rounded-lg shadow-sm">
                            </div>
                        </div>
                        
                        <!-- ID Card Preview -->
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-2">ID Card Preview</h3>
                            <div class="border-2 border-gray-200 rounded-lg p-4">
                                <?php if ($submission['id_card_filename']): ?>
                                    <img src="<?php echo APP_URL . '/uploads/generated_cards/' . $submission['id_card_filename']; ?>" 
                                         alt="Generated ID Card" 
                                         class="w-full rounded-lg shadow-sm">
                                <?php else: ?>
                                    <div class="bg-gray-100 rounded-lg p-8 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">ID card not generated yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Identity Document -->
                <?php if ($submission['identity_doc_filename']): ?>
                    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-4">Identity Document</h2>
                        <div class="border-2 border-gray-200 rounded-lg p-4">
                            <?php
                            $doc_path = DOC_UPLOAD_PATH . '/' . $submission['identity_doc_filename'];
                            $doc_ext = get_file_extension($submission['identity_doc_filename']);
                            
                            if (in_array($doc_ext, ['jpg', 'jpeg', 'png'])) {
                                echo '<img src="' . APP_URL . '/uploads/id_docs/' . $submission['identity_doc_filename'] . '" alt="Identity Document" class="max-w-md rounded-lg shadow-sm">';
                            } else {
                                echo '<div class="bg-gray-100 rounded-lg p-8 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-500">PDF Document: ' . h($submission['identity_doc_filename']) . '</p>
                                        <a href="' . APP_URL . '/uploads/id_docs/' . $submission['identity_doc_filename'] . '" target="_blank" class="mt-2 inline-flex items-center px-3 py-1 border border-gray-300 text-sm font-medium rounded text-gray-700 bg-white hover:bg-gray-50">
                                            View Document
                                        </a>
                                      </div>';
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Review Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Review Actions</h2>
                    
                    <?php if ($submission['status'] === 'submitted' || $submission['status'] === 'under_review'): ?>
                        <!-- Approve/Reject Actions -->
                        <div class="space-y-4">
                            <form method="POST" class="flex space-x-4">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" class="px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700">
                                    ✓ Approve Submission
                                </button>
                            </form>
                            
                            <form method="POST" class="flex space-x-4">
                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                <input type="hidden" name="action" value="reject">
                                <input type="text" name="rejection_reason" placeholder="Rejection reason (required)" required
                                       class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                                <button type="submit" class="px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700">
                                    ✗ Reject Submission
                                </button>
                            </form>
                        </div>
                    <?php elseif ($submission['status'] === 'approved'): ?>
                        <!-- Generate ID Card Actions -->
                        <div class="space-y-4">
                            <?php if (!$submission['id_card_filename']): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                    <input type="hidden" name="action" value="generate_card">
                                    <button type="submit" class="px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700">
                                        🎴 Generate ID Card
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="flex space-x-4">
                                    <a href="<?php echo get_id_card_url($submission_id); ?>" target="_blank" 
                                       class="px-6 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700">
                                        📄 View Generated ID Card
                                    </a>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                        <input type="hidden" name="action" value="regenerate_card">
                                        <button type="submit" class="px-6 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                                            🔄 Regenerate ID Card
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($submission['status'] === 'rejected'): ?>
                        <!-- Rejected Status -->
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                            <p class="text-sm text-yellow-800">
                                This submission has been rejected. The student can upload a new photo after reviewing the rejection reason.
                            </p>
                        </div>
                    <?php elseif ($submission['status'] === 'generated'): ?>
                        <!-- Generated Status -->
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <p class="text-sm text-green-800">
                                ID card has been generated successfully. The student can collect their ID card.
                            </p>
                            <div class="mt-3">
                                <a href="<?php echo get_id_card_url($submission_id); ?>" target="_blank" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700">
                                    📄 View ID Card
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
