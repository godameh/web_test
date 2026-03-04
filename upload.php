<?php
/**
 * UBIDS Student ID Card Photo Portal - Upload Page
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

// Redirect if student already has an approved submission
if ($submission && $submission['status'] === 'approved') {
    redirect_with_message('dashboard.php', 'Your submission has already been approved.', 'info');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $step = $_POST['step'] ?? 1;
    
    // Validate CSRF token
    if (!validate_csrf_token($csrf_token)) {
        redirect_with_message('upload.php', 'Invalid request. Please try again.', 'error');
    }
    
    switch ($step) {
        case 1:
            // Photo upload
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $errors = validate_file_upload($_FILES['photo'], ALLOWED_PHOTO_TYPES);
                
                if (empty($errors)) {
                    // Get image dimensions
                    $image_info = get_image_dimensions($_FILES['photo']['tmp_name']);
                    
                    if (!$image_info) {
                        redirect_with_message('upload.php', 'Invalid image file.', 'error');
                    }
                    
                    // Check minimum dimensions
                    if ($image_info['width'] < MIN_PHOTO_WIDTH || $image_info['height'] < MIN_PHOTO_HEIGHT) {
                        redirect_with_message('upload.php', 'Image must be at least ' . MIN_PHOTO_WIDTH . ' × ' . MIN_PHOTO_HEIGHT . ' pixels.', 'error');
                    }
                    
                    // Check orientation (portrait)
                    if ($image_info['width'] > $image_info['height']) {
                        redirect_with_message('upload.php', 'Photo must be in portrait orientation.', 'error');
                    }
                    
                    // Calculate brightness
                    $brightness = calculate_brightness($_FILES['photo']['tmp_name']);
                    
                    // Generate filename
                    $filename = generate_filename('photo_', 'jpg');
                    $upload_path = PHOTO_UPLOAD_PATH . '/' . $filename;
                    
                    // Resize and save image
                    if (resize_image($_FILES['photo']['tmp_name'], $upload_path, 800, 1000, 85)) {
                        // Store in session for next step
                        $_SESSION['temp_photo'] = [
                            'filename' => $filename,
                            'original_name' => $_FILES['photo']['name'],
                            'file_size_kb' => round(filesize($upload_path) / 1024),
                            'width_px' => $image_info['width'],
                            'height_px' => $image_info['height'],
                            'background_brightness_score' => $brightness,
                            'accessory_flag' => false // TODO: Implement accessory detection
                        ];
                        
                        redirect_with_message('upload.php?step=2', 'Photo uploaded successfully. Please review the preview.', 'success');
                    } else {
                        redirect_with_message('upload.php', 'Failed to process photo. Please try again.', 'error');
                    }
                } else {
                    redirect_with_message('upload.php', implode(', ', $errors), 'error');
                }
            } else {
                redirect_with_message('upload.php', 'Please select a photo to upload.', 'error');
            }
            break;
            
        case 2:
            // Preview confirmation
            if (isset($_POST['confirm_preview']) && $_POST['confirm_preview'] === 'yes') {
                redirect_with_message('upload.php?step=3', 'Please upload your identity document.', 'info');
            } else {
                // Remove temporary photo
                if (isset($_SESSION['temp_photo']['filename'])) {
                    $temp_file = PHOTO_UPLOAD_PATH . '/' . $_SESSION['temp_photo']['filename'];
                    if (file_exists($temp_file)) {
                        unlink($temp_file);
                    }
                }
                unset($_SESSION['temp_photo']);
                redirect_with_message('upload.php', 'Photo upload cancelled. Please upload a new photo.', 'info');
            }
            break;
            
        case 3:
            // Identity document upload
            if (isset($_FILES['id_doc']) && $_FILES['id_doc']['error'] === UPLOAD_ERR_OK) {
                $errors = validate_file_upload($_FILES['id_doc'], ALLOWED_DOC_TYPES);
                
                if (empty($errors)) {
                    // Generate filename
                    $filename = generate_filename('id_doc_', get_file_extension($_FILES['id_doc']['name']));
                    $upload_path = DOC_UPLOAD_PATH . '/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($_FILES['id_doc']['tmp_name'], $upload_path)) {
                        // Save submission to database
                        $photo_data = $_SESSION['temp_photo'];
                        
                        $sql = "INSERT INTO submissions (
                            student_type, student_id, photo_filename, identity_doc_filename,
                            original_name, file_size_kb, width_px, height_px,
                            background_brightness_score, accessory_flag, status
                        ) VALUES (
                            :student_type, :student_id, :photo_filename, :identity_doc_filename,
                            :original_name, :file_size_kb, :width_px, :height_px,
                            :background_brightness_score, :accessory_flag, :status
                        )";
                        
                        $params = [
                            ':student_type' => $_SESSION['student_type'],
                            ':student_id' => $_SESSION['student_id'],
                            ':photo_filename' => $photo_data['filename'],
                            ':identity_doc_filename' => $filename,
                            ':original_name' => $photo_data['original_name'],
                            ':file_size_kb' => $photo_data['file_size_kb'],
                            ':width_px' => $photo_data['width_px'],
                            ':height_px' => $photo_data['height_px'],
                            ':background_brightness_score' => $photo_data['background_brightness_score'],
                            ':accessory_flag' => $photo_data['accessory_flag'],
                            ':status' => 'submitted'
                        ];
                        
                        if (db_execute($sql, $params)) {
                            // Log submission
                            log_audit('student', $_SESSION['student_id'], 'photo_submitted', 'Photo and ID document submitted');
                            
                            // Clear session
                            unset($_SESSION['temp_photo']);
                            
                            redirect_with_message('status.php', 'Your submission has been received and is under review.', 'success');
                        } else {
                            // Clean up files on database error
                            unlink($upload_path);
                            unlink(PHOTO_UPLOAD_PATH . '/' . $photo_data['filename']);
                            redirect_with_message('upload.php', 'Failed to save submission. Please try again.', 'error');
                        }
                    } else {
                        redirect_with_message('upload.php', 'Failed to upload identity document. Please try again.', 'error');
                    }
                } else {
                    redirect_with_message('upload.php?step=3', implode(', ', $errors), 'error');
                }
            } else {
                redirect_with_message('upload.php?step=3', 'Please select an identity document to upload.', 'error');
            }
            break;
    }
}

// Get current step
$current_step = $_GET['step'] ?? 1;

// Get flash message
$flash = get_flash_message();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Photo - <?php echo h(APP_NAME); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700&family=Lora:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Lora', serif; }
        .display-font { font-family: 'Syne', sans-serif; }
        .drop-zone { transition: all 0.3s ease; }
        .drop-zone.dragover { background-color: #f0fdf4; border-color: #16a34a; }
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
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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

        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 <?php echo $current_step >= 1 ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full text-sm font-medium">1</div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Upload Photo</p>
                        <p class="text-xs text-gray-500">Submit your headshot</p>
                    </div>
                </div>
                <div class="flex-1 h-0.5 <?php echo $current_step >= 2 ? 'bg-green-600' : 'bg-gray-300'; ?> mx-4"></div>
                
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 <?php echo $current_step >= 2 ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full text-sm font-medium">2</div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Preview ID</p>
                        <p class="text-xs text-gray-500">Review your card</p>
                    </div>
                </div>
                <div class="flex-1 h-0.5 <?php echo $current_step >= 3 ? 'bg-green-600' : 'bg-gray-300'; ?> mx-4"></div>
                
                <div class="flex items-center">
                    <div class="flex items-center justify-center w-10 h-10 <?php echo $current_step >= 3 ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600'; ?> rounded-full text-sm font-medium">3</div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-gray-900">Identity Document</p>
                        <p class="text-xs text-gray-500">Upload ID proof</p>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($current_step == 1): ?>
            <!-- Step 1: Photo Upload -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Upload Your Photo</h2>
                
                <div class="mb-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Photo Requirements:</h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Recent, clear headshot with neutral expression</li>
                            <li>• Plain, light-colored background</li>
                            <li>• Face must be clearly visible and centered</li>
                            <li>• No glasses, hats, or veils</li>
                            <li>• Format: JPEG, PNG, or HEIC (Max 2MB)</li>
                            <li>• Minimum dimensions: 390 × 540 pixels</li>
                            <li>• Portrait orientation only</li>
                        </ul>
                    </div>
                </div>

                <form action="upload.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="step" value="1">
                    
                    <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-green-600 transition-colors" id="dropZone">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                            <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H6a2 2 0 00-2 2v6h16V7a2 2 0 00-2-2h-1a1 1 0 100-2 2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h.01a1 1 0 100-2H7zm3 0a1 1 0 000 2h3a1 1 0 100-2h-3zm-3 4a1 1 0 100 2h.01a1 1 0 100-2H7zm3 0a1 1 0 100 2h3a1 1 0 100-2h-3z" clip-rule="evenodd"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">Drag and drop your photo here, or</p>
                        <label for="photo" class="mt-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 cursor-pointer">
                            Choose File
                        </label>
                        <input id="photo" name="photo" type="file" class="hidden" accept="image/jpeg,image/png,image/heic" required>
                        <p class="mt-2 text-xs text-gray-500">Maximum file size: 2MB</p>
                    </div>
                    
                    <div id="fileInfo" class="hidden mt-4 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <svg class="h-8 w-8 text-green-600 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900" id="fileName"></p>
                                <p class="text-xs text-gray-500" id="fileSize"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <a href="dashboard.php" class="mr-4 px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                            Cancel
                        </a>
                        <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900">
                            Upload Photo
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($current_step == 2): ?>
            <!-- Step 2: Preview -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Review Your ID Card Preview</h2>
                
                <?php if (isset($_SESSION['temp_photo'])): ?>
                    <div class="text-center mb-6">
                        <img src="<?php echo APP_URL . '/uploads/photos/' . $_SESSION['temp_photo']['filename']; ?>" 
                             alt="ID Card Preview" 
                             class="mx-auto max-w-md rounded-lg shadow-lg">
                    </div>
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <p class="text-sm text-yellow-800">
                            <strong>Please review your photo carefully.</strong> This is how it will appear on your ID card. 
                            Make sure you are satisfied with the photo before proceeding.
                        </p>
                    </div>
                    
                    <form action="upload.php" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="step" value="2">
                        <input type="hidden" name="confirm_preview" value="yes">
                        
                        <div class="flex justify-between">
                            <button type="submit" formaction="upload.php?step=1" class="px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="h-4 w-4 inline mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M10 19l-7-7 7-7m-7 7h18"/>
                                </svg>
                                Retake Photo
                            </button>
                            <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900">
                                Confirm & Continue
                                <svg class="h-4 w-4 inline ml-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/>
                                </svg>
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="text-center py-8">
                        <p class="text-gray-600">No photo found. Please upload a photo first.</p>
                        <a href="upload.php?step=1" class="mt-4 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900">
                            Upload Photo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php elseif ($current_step == 3): ?>
            <!-- Step 3: Identity Document -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Upload Identity Document</h2>
                
                <div class="mb-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-blue-800 mb-2">Identity Document Requirements:</h3>
                        <ul class="text-sm text-blue-700 space-y-1">
                            <li>• Valid Ghana Card or Passport</li>
                            <li>• Clear, readable copy</li>
                            <li>• Format: JPEG, PNG, or PDF (Max 2MB)</li>
                            <li>• All information must be clearly visible</li>
                        </ul>
                    </div>
                </div>

                <form action="upload.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="step" value="3">
                    
                    <div class="drop-zone border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-green-600 transition-colors" id="dropZone">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <p class="mt-2 text-sm text-gray-600">Drag and drop your identity document here, or</p>
                        <label for="id_doc" class="mt-2 inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900 cursor-pointer">
                            Choose File
                        </label>
                        <input id="id_doc" name="id_doc" type="file" class="hidden" accept="image/jpeg,image/png,application/pdf" required>
                        <p class="mt-2 text-xs text-gray-500">Maximum file size: 2MB</p>
                    </div>
                    
                    <div id="fileInfo" class="hidden mt-4 p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <svg class="h-8 w-8 text-green-600 mr-3" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium text-gray-900" id="fileName"></p>
                                <p class="text-xs text-gray-500" id="fileSize"></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-6 flex justify-end">
                        <a href="upload.php?step=2" class="mr-4 px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50">
                            Back
                        </a>
                        <button type="submit" class="px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-green-800 hover:bg-green-900">
                            Submit Application
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <script>
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = dropZone.querySelector('input[type="file"]');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');

        // Prevent default drag behaviors
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        // Highlight drop zone when item is dragged over it
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.classList.add('dragover');
        }

        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }

        // Handle dropped files
        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                handleFiles(files);
            }
        }

        // Handle file selection
        fileInput.addEventListener('change', function(e) {
            handleFiles(this.files);
        });

        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                
                // Check file size
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    return;
                }
                
                // Display file info
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.remove('hidden');
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
