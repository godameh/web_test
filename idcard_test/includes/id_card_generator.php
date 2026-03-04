<?php
/**
 * UBIDS Student ID Card Photo Portal - ID Card Generator
 * 
 * Generates official ID cards using GD library
 */

// Prevent direct access
if (!defined('UBIDS_PORTAL')) {
    exit('Direct access denied');
}

/**
 * Generate ID card for a submission
 */
function generate_id_card($submission_id) {
    // Get submission data
    $submission = get_submission_data($submission_id);
    if (!$submission) {
        return ['success' => false, 'message' => 'Submission not found'];
    }
    
    // Get student data
    $student = get_student_data($submission['student_type'], $submission['student_id']);
    if (!$student) {
        return ['success' => false, 'message' => 'Student not found'];
    }
    
    try {
        // Create base image (template or blank)
        $card_image = create_id_card_base();
        if (!$card_image) {
            return ['success' => false, 'message' => 'Failed to create card base'];
        }
        
        // Add student photo
        $photo_result = add_student_photo($card_image, $submission['photo_filename']);
        if (!$photo_result['success']) {
            imagedestroy($card_image);
            return $photo_result;
        }
        
        // Add student information
        $info_result = add_student_info($card_image, $student, $submission);
        if (!$info_result['success']) {
            imagedestroy($card_image);
            return $info_result;
        }
        
        // Add university branding
        add_branding($card_image);
        
        // Generate filename
        $filename = generate_filename('id_card_', 'png');
        $output_path = CARD_UPLOAD_PATH . '/' . $filename;
        
        // Save the card
        if (imagepng($card_image, $output_path, 9)) {
            imagedestroy($card_image);
            
            // Update database
            $sql = "UPDATE submissions SET id_card_filename = :filename, status = 'generated', updated_at = NOW() WHERE id = :id";
            $params = [':filename' => $filename, ':id' => $submission_id];
            
            if (db_execute($sql, $params)) {
                // Log generation
                log_audit('admin', $_SESSION['admin_id'] ?? 0, 'id_card_generated', "ID card generated for submission ID: $submission_id");
                
                return ['success' => true, 'filename' => $filename, 'path' => $output_path];
            } else {
                unlink($output_path); // Clean up file on database error
                return ['success' => false, 'message' => 'Failed to update database'];
            }
        } else {
            imagedestroy($card_image);
            return ['success' => false, 'message' => 'Failed to save card image'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating ID card: ' . $e->getMessage()];
    }
}

/**
 * Create base ID card image
 */
function create_id_card_base() {
    // Create blank canvas
    $card = imagecreatetruecolor(ID_CARD_WIDTH, ID_CARD_HEIGHT);
    if (!$card) {
        return false;
    }
    
    // Set white background
    $white = imagecolorallocate($card, 255, 255, 255);
    imagefill($card, 0, 0, $white);
    
    // Add border
    $border_color = imagecolorallocate($card, 26, 58, 42); // primary color
    imagerectangle($card, 10, 10, ID_CARD_WIDTH - 10, ID_CARD_HEIGHT - 10, $border_color);
    
    // Add header background
    $header_color = imagecolorallocate($card, 26, 58, 42);
    imagefilledrectangle($card, 10, 10, ID_CARD_WIDTH - 10, 120, $header_color);
    
    return $card;
}

/**
 * Add student photo to ID card
 */
function add_student_photo($card_image, $photo_filename) {
    $photo_path = PHOTO_UPLOAD_PATH . '/' . $photo_filename;
    
    if (!file_exists($photo_path)) {
        return ['success' => false, 'message' => 'Photo file not found'];
    }
    
    // Load photo
    $photo_info = get_image_dimensions($photo_path);
    if (!$photo_info) {
        return ['success' => false, 'message' => 'Invalid photo file'];
    }
    
    $photo = null;
    switch ($photo_info['type']) {
        case IMAGETYPE_JPEG:
            $photo = imagecreatefromjpeg($photo_path);
            break;
        case IMAGETYPE_PNG:
            $photo = imagecreatefrompng($photo_path);
            break;
        default:
            return ['success' => false, 'message' => 'Unsupported photo format'];
    }
    
    if (!$photo) {
        return ['success' => false, 'message' => 'Failed to load photo'];
    }
    
    // Calculate photo position and size
    $photo_x = 30;
    $photo_y = 140;
    $photo_width = 200;
    $photo_height = 250;
    
    // Resize and copy photo
    $resized_photo = imagecreatetruecolor($photo_width, $photo_height);
    imagecopyresampled($resized_photo, $photo, 0, 0, 0, 0, $photo_width, $photo_height, $photo_info['width'], $photo_info['height']);
    
    // Add photo to card with border
    $border_color = imagecolorallocate($card_image, 200, 200, 200);
    imagerectangle($card_image, $photo_x - 2, $photo_y - 2, $photo_x + $photo_width + 2, $photo_y + $photo_height + 2, $border_color);
    imagecopy($card_image, $resized_photo, $photo_x, $photo_y, 0, 0, $photo_width, $photo_height);
    
    // Clean up
    imagedestroy($photo);
    imagedestroy($resized_photo);
    
    return ['success' => true];
}

/**
 * Add student information to ID card
 */
function add_student_info($card_image, $student, $submission) {
    // Colors
    $text_color = imagecolorallocate($card_image, 0, 0, 0);
    $header_color = imagecolorallocate($card_image, 255, 255, 255);
    $label_color = imagecolorallocate($card_image, 100, 100, 100);
    
    // Try to load fonts, fallback to built-in if not available
    $font_bold = file_exists(FONT_BOLD) ? FONT_BOLD : null;
    $font_regular = file_exists(FONT_REGULAR) ? FONT_REGULAR : null;
    
    // Add university name in header
    $university_text = "UNIVERSITY OF BUSINESS AND INTEGRATED DEVELOPMENT STUDIES";
    if ($font_bold) {
        imagettftext($card_image, 16, 0, 30, 40, $header_color, $font_bold, $university_text);
    } else {
        imagestring($card_image, 4, 30, 30, $university_text, $header_color);
    }
    
    // Add "STUDENT ID CARD" text
    $id_text = "STUDENT ID CARD";
    if ($font_bold) {
        imagettftext($card_image, 14, 0, 30, 65, $header_color, $font_bold, $id_text);
    } else {
        imagestring($card_image, 3, 30, 55, $id_text, $header_color);
    }
    
    // Add academic year
    $year_text = "ACADEMIC YEAR " . $student['academic_year'];
    if ($font_regular) {
        imagettftext($card_image, 10, 0, 30, 90, $header_color, $font_regular, $year_text);
    } else {
        imagestring($card_image, 2, 30, 80, $year_text, $header_color);
    }
    
    // Student information (right side)
    $info_x = 260;
    $info_y = 140;
    $line_height = 25;
    
    // Name
    $full_name = trim($student['first_name'] . ' ' . $student['last_name'] . ' ' . $student['other_names']);
    if ($font_bold) {
        imagettftext($card_image, 12, 0, $info_x, $info_y, $text_color, $font_bold, "NAME:");
        imagettftext($card_image, 11, 0, $info_x + 80, $info_y, $text_color, $font_regular, strtoupper($full_name));
    } else {
        imagestring($card_image, 2, $info_x, $info_y - 10, "NAME:", $text_color);
        imagestring($card_image, 2, $info_x + 80, $info_y - 10, strtoupper($full_name), $text_color);
    }
    
    // Admission Number
    $info_y += $line_height;
    if ($font_bold) {
        imagettftext($card_image, 12, 0, $info_x, $info_y, $text_color, $font_bold, "ADMISSION NO:");
        imagettftext($card_image, 11, 0, $info_x + 80, $info_y, $text_color, $font_regular, $student['admission_number']);
    } else {
        imagestring($card_image, 2, $info_x, $info_y - 10, "ADMISSION NO:", $text_color);
        imagestring($card_image, 2, $info_x + 80, $info_y - 10, $student['admission_number'], $text_color);
    }
    
    // Programme
    $info_y += $line_height;
    if ($font_bold) {
        imagettftext($card_image, 12, 0, $info_x, $info_y, $text_color, $font_bold, "PROGRAMME:");
        imagettftext($card_image, 11, 0, $info_x + 80, $info_y, $text_color, $font_regular, $student['programme']);
    } else {
        imagestring($card_image, 2, $info_x, $info_y - 10, "PROGRAMME:", $text_color);
        imagestring($card_image, 2, $info_x + 80, $info_y - 10, $student['programme'], $text_color);
    }
    
    // Department
    $info_y += $line_height;
    if ($font_bold) {
        imagettftext($card_image, 12, 0, $info_x, $info_y, $text_color, $font_bold, "DEPARTMENT:");
        imagettftext($card_image, 11, 0, $info_x + 80, $info_y, $text_color, $font_regular, $student['department']);
    } else {
        imagestring($card_image, 2, $info_x, $info_y - 10, "DEPARTMENT:", $text_color);
        imagestring($card_image, 2, $info_x + 80, $info_y - 10, $student['department'], $text_color);
    }
    
    // Level
    $info_y += $line_height;
    if ($font_bold) {
        imagettftext($card_image, 12, 0, $info_x, $info_y, $text_color, $font_bold, "LEVEL:");
        imagettftext($card_image, 11, 0, $info_x + 80, $info_y, $text_color, $font_regular, $student['level']);
    } else {
        imagestring($card_image, 2, $info_x, $info_y - 10, "LEVEL:", $text_color);
        imagestring($card_image, 2, $info_x + 80, $info_y - 10, $student['level'], $text_color);
    }
    
    // Student Type
    $info_y += $line_height;
    $type_text = $submission['student_type'] === 'new' ? 'NEW STUDENT' : 'CONTINUING STUDENT';
    if ($student['is_replacement']) {
        $type_text .= ' (REPLACEMENT)';
    }
    if ($font_bold) {
        imagettftext($card_image, 12, 0, $info_x, $info_y, $text_color, $font_bold, "TYPE:");
        imagettftext($card_image, 11, 0, $info_x + 80, $info_y, $text_color, $font_regular, $type_text);
    } else {
        imagestring($card_image, 2, $info_x, $info_y - 10, "TYPE:", $text_color);
        imagestring($card_image, 2, $info_x + 80, $info_y - 10, $type_text, $text_color);
    }
    
    return ['success' => true];
}

/**
 * Add university branding and security features
 */
function add_branding($card_image) {
    // Add logo placeholder (if available)
    $logo_x = ID_CARD_WIDTH - 80;
    $logo_y = 20;
    $logo_size = 60;
    
    // Draw placeholder circle for logo
    $logo_color = imagecolorallocate($card_image, 200, 168, 75); // accent color
    imagefilledellipse($card_image, $logo_x, $logo_y, $logo_size, $logo_size, $logo_color);
    
    // Add "UBIDS" text in logo
    $text_color = imagecolorallocate($card_image, 26, 58, 42); // primary color
    imagestring($card_image, 3, $logo_x - 20, $logo_y - 8, "UBIDS", $text_color);
    
    // Add issue date
    $issue_date = date('M j, Y');
    $date_text = "Issued: " . $issue_date;
    $text_color = imagecolorallocate($card_image, 100, 100, 100);
    imagestring($card_image, 2, 30, ID_CARD_HEIGHT - 30, $date_text, $text_color);
    
    // Add signature line
    $signature_y = ID_CARD_HEIGHT - 80;
    imageline($card_image, 260, $signature_y, 450, $signature_y, $text_color);
    imagestring($card_image, 2, 260, $signature_y + 5, "Signature", $text_color);
    
    // Add security watermark
    $watermark_color = imagecolorallocatealpha($card_image, 200, 200, 200, 60);
    imagestring($card_image, 5, ID_CARD_WIDTH - 150, ID_CARD_HEIGHT - 50, "UBIDS", $watermark_color);
    
    // Add card number (unique identifier)
    $card_number = "UBIDS-" . date('Y') . "-" . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
    imagestring($card_image, 2, ID_CARD_WIDTH - 150, 30, $card_number, $text_color);
}

/**
 * Generate preview ID card (not final)
 */
function generate_preview_id_card($submission_id) {
    // Similar to generate_id_card but saves to temporary location
    $result = generate_id_card($submission_id);
    
    if ($result['success']) {
        // Move to temporary preview location
        $preview_filename = 'preview_' . $result['filename'];
        $preview_path = CARD_UPLOAD_PATH . '/preview_' . $result['filename'];
        
        if (rename($result['path'], $preview_path)) {
            return ['success' => true, 'filename' => $preview_filename, 'path' => $preview_path];
        } else {
            return ['success' => false, 'message' => 'Failed to create preview'];
        }
    }
    
    return $result;
}

/**
 * Validate ID card generation requirements
 */
function validate_id_card_requirements($submission_id) {
    $submission = get_submission_data($submission_id);
    if (!$submission) {
        return ['valid' => false, 'message' => 'Submission not found'];
    }
    
    // Check if submission is approved
    if ($submission['status'] !== 'approved') {
        return ['valid' => false, 'message' => 'Submission must be approved first'];
    }
    
    // Check if photo exists
    $photo_path = PHOTO_UPLOAD_PATH . '/' . $submission['photo_filename'];
    if (!file_exists($photo_path)) {
        return ['valid' => false, 'message' => 'Student photo not found'];
    }
    
    // Check if ID card already exists
    if (!empty($submission['id_card_filename'])) {
        $card_path = CARD_UPLOAD_PATH . '/' . $submission['id_card_filename'];
        if (file_exists($card_path)) {
            return ['valid' => false, 'message' => 'ID card already generated'];
        }
    }
    
    return ['valid' => true];
}

/**
 * Get ID card image URL
 */
function get_id_card_url($submission_id) {
    $submission = get_submission_data($submission_id);
    if (!$submission || empty($submission['id_card_filename'])) {
        return null;
    }
    
    return APP_URL . '/uploads/generated_cards/' . $submission['id_card_filename'];
}

/**
 * Delete generated ID card
 */
function delete_id_card($submission_id) {
    $submission = get_submission_data($submission_id);
    if (!$submission || empty($submission['id_card_filename'])) {
        return ['success' => false, 'message' => 'No ID card found'];
    }
    
    $card_path = CARD_UPLOAD_PATH . '/' . $submission['id_card_filename'];
    
    if (file_exists($card_path)) {
        unlink($card_path);
    }
    
    // Update database
    $sql = "UPDATE submissions SET id_card_filename = NULL, status = 'approved', updated_at = NOW() WHERE id = :id";
    $params = [':id' => $submission_id];
    
    if (db_execute($sql, $params)) {
        log_audit('admin', $_SESSION['admin_id'] ?? 0, 'id_card_deleted', "ID card deleted for submission ID: $submission_id");
        return ['success' => true];
    } else {
        return ['success' => false, 'message' => 'Failed to update database'];
    }
}
?>
