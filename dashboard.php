<?php
/**
 * UBIDS Student ID Card Photo Portal - Student Dashboard
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

// Get student data
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
    <title>Dashboard - <?php echo h(APP_NAME); ?></title>
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

        <!-- Welcome Section -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h2 class="display-font text-2xl font-bold text-gray-900 mb-2">Welcome, <?php echo h($student['first_name']); ?>!</h2>
            <p class="text-gray-600">Manage your ID card photo submission from here.</p>
        </div>

        <!-- Student Information -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <p class="text-sm text-gray-500">Admission Number</p>
                    <p class="font-medium text-gray-900"><?php echo h($student['admission_number']); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Full Name</p>
                    <p class="font-medium text-gray-900"><?php echo h(trim($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['other_names'])); ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="font-medium text-gray-900"><?php echo h($student['email']); ?></p>
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
            </div>
        </div>

        <!-- Submission Status -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Submission Status</h3>
            <?php if ($submission): ?>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-500">Current Status</p>
                        <div class="mt-1"><?php echo get_status_badge($submission['status']); ?></div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Submitted On</p>
                        <p class="font-medium text-gray-900"><?php echo format_date($submission['submitted_at']); ?></p>
                    </div>
                </div>
                
                <?php if ($submission['rejection_reason']): ?>
                    <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <p class="text-sm font-medium text-red-800">Rejection Reason:</p>
                        <p class="text-sm text-red-700 mt-1"><?php echo h($submission['rejection_reason']); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Progress Timeline -->
                <div class="mt-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 bg-green-600 text-white rounded-full text-sm font-medium">1</div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">Submitted</p>
                                <p class="text-xs text-gray-500">Your photo was submitted</p>
                            </div>
                        </div>
                        <div class="flex-1 h-0.5 bg-gray-200 mx-4"></div>
                        
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 <?php echo in_array($submission['status'], ['under_review', 'approved', 'rejected', 'generated']) ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full text-sm font-medium">2</div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">Under Review</p>
                                <p class="text-xs text-gray-500">Staff reviewing your photo</p>
                            </div>
                        </div>
                        <div class="flex-1 h-0.5 bg-gray-200 mx-4"></div>
                        
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 <?php echo in_array($submission['status'], ['approved', 'generated']) ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full text-sm font-medium">3</div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">Decision</p>
                                <p class="text-xs text-gray-500">Approval or rejection</p>
                            </div>
                        </div>
                        <div class="flex-1 h-0.5 bg-gray-200 mx-4"></div>
                        
                        <div class="flex items-center">
                            <div class="flex items-center justify-center w-8 h-8 <?php echo $submission['status'] === 'generated' ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full text-sm font-medium">4</div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900">ID Ready</p>
                                <p class="text-xs text-gray-500">Card generated</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($submission['status'] === 'rejected'): ?>
                    <div class="mt-6">
                        <a href="upload.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-800">
                            <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                            </svg>
                            Upload New Photo
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                        <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2h-1a1 1 0 100-2 2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No submission yet</h3>
                    <p class="mt-1 text-sm text-gray-500">Get started by uploading your ID card photo.</p>
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
        </div>

        <!-- Action Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Upload Photo -->
            <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <svg class="h-6 w-6 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Upload Photo</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">Submit your ID card photo for review.</p>
                <a href="upload.php" class="text-sm font-medium text-green-800 hover:text-green-900">Get Started →</a>
            </div>

            <!-- View Status -->
            <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <svg class="h-6 w-6 text-blue-600" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">View Status</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">Check the status of your submission.</p>
                <a href="status.php" class="text-sm font-medium text-blue-800 hover:text-blue-900">Check Status →</a>
            </div>

            <!-- Requirements -->
            <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow">
                <div class="flex items-center mb-4">
                    <div class="flex-shrink-0">
                        <div class="h-10 w-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <svg class="h-6 w-6 text-yellow-600" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900">Requirements</h3>
                    </div>
                </div>
                <p class="text-sm text-gray-600 mb-4">Photo specifications and guidelines.</p>
                <a href="#" onclick="showRequirements(); return false;" class="text-sm font-medium text-yellow-800 hover:text-yellow-900">View Guidelines →</a>
            </div>
        </div>
    </main>

    <!-- Requirements Modal -->
    <div id="requirementsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-xl bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Photo Requirements</h3>
                <button onclick="hideRequirements()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" d="M6.293 6.293a1 1 0 011.414 0L12 10.586l4.293-4.293a1 1 0 111.414 1.414L13.414 12l4.293 4.293a1 1 0 01-1.414 1.414L12 13.414l-4.293 4.293a1 1 0 01-1.414-1.414L10.586 12 6.293 7.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            
            <div class="space-y-4">
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Technical Specifications</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Format: JPEG, PNG, or HEIC</li>
                        <li>• Maximum file size: 2MB</li>
                        <li>• Minimum dimensions: 390 × 540 pixels</li>
                        <li>• Orientation: Portrait (vertical)</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Photo Guidelines</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Recent, clear headshot with neutral expression</li>
                        <li>• Plain, light-colored background</li>
                        <li>• Face must be clearly visible and centered</li>
                        <li>• No glasses, hats, or veils</li>
                        <li>• No filters or heavy editing</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 mb-2">Identity Document</h4>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Valid Ghana Card or Passport</li>
                        <li>• Clear, readable copy</li>
                        <li>• Same file size and format restrictions apply</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showRequirements() {
            document.getElementById('requirementsModal').classList.remove('hidden');
        }
        
        function hideRequirements() {
            document.getElementById('requirementsModal').classList.add('hidden');
        }
        
        // Close modal when clicking outside
        document.getElementById('requirementsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideRequirements();
            }
        });
    </script>
</body>
</html>
