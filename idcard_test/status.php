<?php
/**
 * UBIDS Student ID Card Photo Portal - Status Page
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Require student login
require_student_login();

// Get student data and submission
$student = get_current_student();
$submission = get_student_submission();

// Get flash message
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Status - <?php echo h(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        .display-font { font-family: 'Syne', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Navigation Header -->
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="h-8 w-8 bg-green-800 rounded-full flex items-center justify-center">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h1 class="display-font text-xl font-bold text-gray-900">UBIDS Portal</h1>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-900"><?php echo h($_SESSION['student_name']); ?></p>
                        <p class="text-xs text-gray-500"><?php echo h($_SESSION['admission_number']); ?></p>
                    </div>
                    <div class="relative">
                        <button class="flex items-center justify-center h-8 w-8 rounded-full bg-gray-200 hover:bg-gray-300 transition-colors">
                            <svg class="h-5 w-5 text-gray-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                        </button>
                    </div>
                    <a href="logout.php" class="text-sm text-gray-500 hover:text-gray-700">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Flash Message -->
        <?php if ($flash): ?>
            <div class="rounded-md p-4 mb-6 <?php echo $flash['type'] === 'error' ? 'bg-red-50 text-red-800' : ($flash['type'] === 'warning' ? 'bg-yellow-50 text-yellow-800' : ($flash['type'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800')); ?>">
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

        <!-- Page Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="display-font text-3xl font-bold text-gray-900">Submission Status</h1>
                    <p class="mt-2 text-gray-600">Track the progress of your ID card photo submission</p>
                </div>
                <a href="dashboard.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-800">
                    <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M10 19l-7-7 7-7m-7 7h18"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>

        <?php if ($submission): ?>
            <!-- Status Overview -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Current Status</h2>
                        <p class="text-sm text-gray-500 mt-1">Submission ID: #<?php echo str_pad($submission['id'], 6, '0', STR_PAD_LEFT); ?></p>
                    </div>
                    <div class="text-right">
                        <?php echo get_status_badge($submission['status']); ?>
                        <p class="text-sm text-gray-500 mt-1">Updated: <?php echo format_date($submission['updated_at']); ?></p>
                    </div>
                </div>

                <!-- Detailed Timeline -->
                <div class="space-y-6">
                    <!-- Step 1: Submitted -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 bg-green-600 text-white rounded-full">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Submitted</h3>
                                    <p class="text-sm text-gray-500">Your photo and documents have been submitted successfully.</p>
                                </div>
                                <p class="text-sm text-gray-500"><?php echo format_date($submission['submitted_at']); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Under Review -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 <?php echo in_array($submission['status'], ['under_review', 'approved', 'rejected', 'generated']) ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full">
                                <?php if (in_array($submission['status'], ['under_review', 'approved', 'rejected', 'generated'])): ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Under Review</h3>
                                    <p class="text-sm text-gray-500">Staff members are reviewing your submission for compliance.</p>
                                </div>
                                <?php if ($submission['reviewed_at']): ?>
                                    <p class="text-sm text-gray-500"><?php echo format_date($submission['reviewed_at']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Decision -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 <?php echo in_array($submission['status'], ['approved', 'rejected', 'generated']) ? 'bg-green-600 text-white' : ($submission['status'] === 'under_review' ? 'bg-yellow-500 text-white' : 'bg-gray-300 text-gray-600'); ?> rounded-full">
                                <?php if (in_array($submission['status'], ['approved', 'rejected', 'generated'])): ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                <?php elseif ($submission['status'] === 'under_review'): ?>
                                    <svg class="h-6 w-6 animate-spin" fill="currentColor" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">Decision</h3>
                                    <p class="text-sm text-gray-500">
                                        <?php 
                                        if ($submission['status'] === 'approved') {
                                            echo 'Your submission has been approved!';
                                        } elseif ($submission['status'] === 'rejected') {
                                            echo 'Your submission was rejected. Please review the feedback.';
                                        } elseif ($submission['status'] === 'under_review') {
                                            echo 'A decision will be made soon.';
                                        } else {
                                            echo 'Waiting for review to begin.';
                                        }
                                        ?>
                                    </p>
                                </div>
                                <?php if (in_array($submission['status'], ['approved', 'rejected']) && $submission['reviewed_at']): ?>
                                    <p class="text-sm text-gray-500"><?php echo format_date($submission['reviewed_at']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: ID Ready -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <div class="flex items-center justify-center w-10 h-10 <?php echo $submission['status'] === 'generated' ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full">
                                <?php if ($submission['status'] === 'generated'): ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                <?php else: ?>
                                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">ID Card Generated</h3>
                                    <p class="text-sm text-gray-500">
                                        <?php 
                                        if ($submission['status'] === 'generated') {
                                            echo 'Your ID card has been generated and is ready for collection.';
                                        } else {
                                            echo 'ID card will be generated after approval.';
                                        }
                                        ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rejection Details -->
            <?php if ($submission['status'] === 'rejected' && $submission['rejection_reason']): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-400" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-lg font-medium text-red-800">Rejection Reason</h3>
                            <p class="mt-2 text-red-700"><?php echo h($submission['rejection_reason']); ?></p>
                            <div class="mt-4">
                                <a href="upload.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                    <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                                    </svg>
                                    Upload New Photo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Submission Details -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Submission Details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-500">Submission Date</p>
                        <p class="font-medium text-gray-900"><?php echo format_date($submission['submitted_at'], 'M j, Y g:i A'); ?></p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Last Updated</p>
                        <p class="font-medium text-gray-900"><?php echo format_date($submission['updated_at'], 'M j, Y g:i A'); ?></p>
                    </div>
                    <?php if ($submission['reviewed_by']): ?>
                        <div>
                            <p class="text-sm text-gray-500">Reviewed By</p>
                            <p class="font-medium text-gray-900"><?php echo h($submission['reviewer_name'] ?? 'System'); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['file_size_kb']): ?>
                        <div>
                            <p class="text-sm text-gray-500">File Size</p>
                            <p class="font-medium text-gray-900"><?php echo format_file_size($submission['file_size_kb'] * 1024); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($submission['width_px'] && $submission['height_px']): ?>
                        <div>
                            <p class="text-sm text-gray-500">Photo Dimensions</p>
                            <p class="font-medium text-gray-900"><?php echo $submission['width_px']; ?> × <?php echo $submission['height_px']; ?> px</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- No Submission -->
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
                <svg class="mx-auto h-16 w-16 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                    <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2h-1a1 1 0 100-2 2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                </svg>
                <h3 class="mt-4 text-lg font-medium text-gray-900">No Submission Found</h3>
                <p class="mt-2 text-gray-600">You haven't submitted any photos yet.</p>
                <div class="mt-6">
                    <a href="upload.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-800">
                        <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                        Upload Photo
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // Auto-refresh every 60 seconds if status is pending
        <?php if ($submission && in_array($submission['status'], ['submitted', 'under_review'])): ?>
            setTimeout(function() {
                window.location.reload();
            }, 60000);
        <?php endif; ?>
    </script>
</body>
</html>
