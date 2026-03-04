<?php
/**
 * UBIDS Student ID Card Photo Portal - Preview Page
 * 
 * Shows ID card preview before final submission
 */

// Define application constant
define('UBIDS_PORTAL', true);

// Include required files
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/id_card_generator.php';

// Require student login
require_student_login();

// Get student data
$student = get_current_student();

// Check if there's a temporary photo in session
if (!isset($_SESSION['temp_photo'])) {
    redirect_with_message('upload.php', 'No photo found. Please upload a photo first.', 'error');
}

$temp_photo = $_SESSION['temp_photo'];

// Handle preview generation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf_token)) {
        redirect_with_message('preview.php', 'Invalid request. Please try again.', 'error');
    }
    
    if ($action === 'confirm') {
        redirect_with_message('upload.php?step=3', 'Photo confirmed. Please upload your identity document.', 'success');
    } elseif ($action === 'retake') {
        // Remove temporary photo
        $temp_file = PHOTO_UPLOAD_PATH . '/' . $temp_photo['filename'];
        if (file_exists($temp_file)) {
            unlink($temp_file);
        }
        unset($_SESSION['temp_photo']);
        redirect_with_message('upload.php?step=1', 'Photo removed. Please upload a new photo.', 'info');
    }
}

// Generate preview ID card
$preview_result = generate_preview_id_card_from_temp($temp_photo);

// Get flash message
$flash = get_flash_message();

/**
 * Generate preview ID card from temporary photo
 */
function generate_preview_id_card_from_temp($temp_photo) {
    try {
        // Create base image
        $card_image = create_id_card_base();
        if (!$card_image) {
            return ['success' => false, 'message' => 'Failed to create card base'];
        }
        
        // Add student photo from temp file
        $photo_path = PHOTO_UPLOAD_PATH . '/' . $temp_photo['filename'];
        $photo_result = add_student_photo($card_image, $temp_photo['filename']);
        if (!$photo_result['success']) {
            imagedestroy($card_image);
            return $photo_result;
        }
        
        // Add placeholder student info
        $student = get_current_student();
        $info_result = add_student_info($card_image, $student, [
            'student_type' => $_SESSION['student_type'],
            'status' => 'preview'
        ]);
        if (!$info_result['success']) {
            imagedestroy($card_image);
            return $info_result;
        }
        
        // Add branding
        add_branding($card_image);
        
        // Save to temporary preview location
        $preview_filename = 'preview_' . uniqid() . '.png';
        $preview_path = CARD_UPLOAD_PATH . '/' . $preview_filename;
        
        if (imagepng($card_image, $preview_path, 9)) {
            imagedestroy($card_image);
            return ['success' => true, 'filename' => $preview_filename, 'path' => $preview_path];
        } else {
            imagedestroy($card_image);
            return ['success' => false, 'message' => 'Failed to save preview'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating preview: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Card Preview - <?php echo h(APP_NAME); ?></title>
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
                    <h1 class="display-font text-3xl font-bold text-gray-900">ID Card Preview</h1>
                    <p class="mt-2 text-gray-600">Review how your photo will appear on your official ID card</p>
                </div>
                <a href="upload.php?step=1" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M10 19l-7-7 7-7m-7 7h18"/>
                    </svg>
                    Back to Upload
                </a>
            </div>
        </div>

        <!-- Preview Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- ID Card Preview -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Your ID Card Preview</h2>
                <div class="border-2 border-gray-200 rounded-lg p-4 bg-gray-50">
                    <?php if ($preview_result['success']): ?>
                        <img src="<?php echo APP_URL . '/uploads/generated_cards/' . $preview_result['filename']; ?>" 
                             alt="ID Card Preview" 
                             class="w-full rounded-lg shadow-lg">
                    <?php else: ?>
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Failed to generate preview</p>
                            <p class="text-xs text-gray-400"><?php echo h($preview_result['message']); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h3 class="text-sm font-medium text-blue-800 mb-2">Important Notes:</h3>
                    <ul class="text-sm text-blue-700 space-y-1">
                        <li>• This is a preview of how your ID card will look</li>
                        <li>• The final card will be generated after approval</li>
                        <li>• Make sure you're satisfied with your photo appearance</li>
                        <li>• If not satisfied, you can retake your photo</li>
                    </ul>
                </div>
            </div>

            <!-- Photo Details & Actions -->
            <div class="space-y-6">
                <!-- Photo Details -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Photo Details</h2>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Original Name:</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo h($temp_photo['original_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">File Size:</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo format_file_size($temp_photo['file_size_kb'] * 1024); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Dimensions:</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo $temp_photo['width_px']; ?> × <?php echo $temp_photo['height_px']; ?> px</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Background Brightness:</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo $temp_photo['background_brightness_score']; ?>%</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-sm text-gray-500">Accessories Detected:</span>
                            <span class="text-sm font-medium text-gray-900"><?php echo $temp_photo['accessory_flag'] ? 'Yes' : 'No'; ?></span>
                        </div>
                    </div>
                </div>

                <!-- Original Photo -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Your Uploaded Photo</h2>
                    <div class="border-2 border-gray-200 rounded-lg p-4">
                        <img src="<?php echo APP_URL . '/uploads/photos/' . $temp_photo['filename']; ?>" 
                             alt="Your Photo" 
                             class="w-full rounded-lg">
                    </div>
                </div>

                <!-- Actions -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Next Steps</h2>
                    <div class="space-y-4">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="confirm">
                            <button type="submit" class="w-full px-4 py-3 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-800">
                                ✓ I'm Satisfied - Continue to Identity Document
                            </button>
                        </form>
                        
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="retake">
                            <button type="submit" class="w-full px-4 py-3 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                🔄 Retake Photo
                            </button>
                        </form>
                    </div>
                    
                    <div class="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <p class="text-sm text-yellow-800">
                            <strong>Remember:</strong> Once you proceed to upload your identity document, you cannot change your photo. Make sure you're completely satisfied with how it looks.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
