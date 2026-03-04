<?php
/**
 * UBIDS Student ID Card Photo Portal - Student Login
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Check if student is already logged in
if (is_student_logged_in()) {
    redirect('dashboard.php');
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $student_type = $_POST['student_type'] ?? '';
    $admission_number = $_POST['admission_number'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf_token)) {
        redirect_with_message('login.php', 'Invalid request. Please try again.', 'error');
    }
    
    // Validate input
    if (empty($student_type) || !in_array($student_type, ['new', 'continuing'])) {
        redirect_with_message('login.php', 'Please select student type.', 'error');
    }
    
    if (empty($admission_number) || empty($last_name)) {
        redirect_with_message('login.php', 'Please fill in all required fields.', 'error');
    }
    
    // Attempt login
    $result = student_login($admission_number, $last_name, $student_type);
    
    if ($result['success']) {
        redirect_with_message('dashboard.php', 'Login successful!', 'success');
    } else {
        redirect_with_message('login.php', $result['message'], 'error');
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
    <title>Student Login - <?php echo h(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        .display-font { font-family: 'Syne', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-green-50 to-emerald-100 min-h-screen">
    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="max-w-md w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <div class="mx-auto h-16 w-16 bg-green-800 rounded-full flex items-center justify-center mb-4">
                    <svg class="h-10 w-10 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <h1 class="display-font text-3xl font-bold text-gray-900 mb-2">UBIDS ID Card Portal</h1>
                <p class="text-gray-600">Student Login</p>
            </div>

            <!-- Flash Message -->
            <?php if ($flash): ?>
                <div class="rounded-md p-4 <?php echo $flash['type'] === 'error' ? 'bg-red-50 text-red-800' : ($flash['type'] === 'warning' ? 'bg-yellow-50 text-yellow-800' : ($flash['type'] === 'success' ? 'bg-green-50 text-green-800' : 'bg-blue-50 text-blue-800')); ?>">
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

            <!-- Login Form -->
            <form class="mt-8 space-y-6" action="login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="space-y-4">
                    <!-- Student Type Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Student Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative">
                                <input type="radio" name="student_type" value="new" class="peer sr-only" required>
                                <div class="peer-checked:bg-green-800 peer-checked:text-white peer-checked:border-green-800 bg-white border-2 border-gray-200 rounded-lg p-4 cursor-pointer transition-all hover:border-green-600">
                                    <div class="text-center">
                                        <svg class="h-8 w-8 mx-auto mb-2 text-green-600 peer-checked:text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        <p class="font-medium">New Student</p>
                                        <p class="text-xs opacity-75">First-time applicant</p>
                                    </div>
                                </div>
                            </label>
                            
                            <label class="relative">
                                <input type="radio" name="student_type" value="continuing" class="peer sr-only">
                                <div class="peer-checked:bg-green-800 peer-checked:text-white peer-checked:border-green-800 bg-white border-2 border-gray-200 rounded-lg p-4 cursor-pointer transition-all hover:border-green-600">
                                    <div class="text-center">
                                        <svg class="h-8 w-8 mx-auto mb-2 text-green-600 peer-checked:text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                        </svg>
                                        <p class="font-medium">Continuing</p>
                                        <p class="text-xs opacity-75">Returning/Replacement</p>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Admission Number -->
                    <div>
                        <label for="admission_number" class="block text-sm font-medium text-gray-700 mb-1">Admission Number</label>
                        <input id="admission_number" name="admission_number" type="text" required
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-green-800 focus:border-green-800 focus:z-10 sm:text-sm"
                               placeholder="Enter your admission number"
                               style="text-transform: uppercase;">
                    </div>

                    <!-- Last Name -->
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                        <input id="last_name" name="last_name" type="text" required
                               class="appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-lg focus:outline-none focus:ring-green-800 focus:border-green-800 focus:z-10 sm:text-sm"
                               placeholder="Enter your last name"
                               style="text-transform: uppercase;">
                    </div>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="group relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-800 transition-colors">
                        <span class="absolute left-0 inset-y-0 flex items-center pl-3">
                            <svg class="h-5 w-5 text-green-600 group-hover:text-green-500" fill="currentColor" viewBox="0 0 24 24">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Admin Login Link -->
            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Are you an administrator? 
                    <a href="admin/login.php" class="font-medium text-green-800 hover:text-green-900">Admin Login</a>
                </p>
            </div>

            <!-- Help Section -->
            <div class="mt-8 border-t border-gray-200 pt-6">
                <div class="text-center">
                    <h3 class="text-sm font-medium text-gray-900 mb-2">Need Help?</h3>
                    <p class="text-xs text-gray-500 mb-3">Contact the IT Department for assistance</p>
                    <div class="flex justify-center space-x-4 text-xs text-gray-500">
                        <span>📧 it@ubids.edu.gh</span>
                        <span>📞 050-123-4567</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-uppercase admission number and last name
        document.getElementById('admission_number').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        document.getElementById('last_name').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
    </script>
</body>
</html>
