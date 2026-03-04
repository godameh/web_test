<?php
/**
 * UBIDS Student ID Card Photo Portal - Email System
 * 
 * Handles email notifications using PHPMailer
 */

// Prevent direct access
if (!defined('UBIDS_PORTAL')) {
    exit('Direct access denied');
}

// Import PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader if it exists
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Send email notification
 */
function send_email_notification($to_email, $to_name, $subject, $body_html, $body_text = '') {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // Disable verbose debug output
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = !empty(SMTP_USERNAME);
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body_html;
        $mail->AltBody = $body_text ?: strip_tags($body_html);
        
        // Send email
        $mail->send();
        
        return ['success' => true, 'message' => 'Email sent successfully'];
        
    } catch (Exception $e) {
        // Log error
        error_log("Email sending failed: " . $e->getMessage());
        
        // Queue email for retry
        queue_email($to_email, $to_name, $subject, $body_html, $body_text);
        
        return ['success' => false, 'message' => 'Email queued for delivery'];
    }
}

/**
 * Queue email for later delivery
 */
function queue_email($to_email, $to_name, $subject, $body_html, $body_text = '') {
    $sql = "INSERT INTO email_queue (recipient_email, recipient_name, subject, body_html, body_text) 
            VALUES (:email, :name, :subject, :html, :text)";
    
    $params = [
        ':email' => $to_email,
        ':name' => $to_name,
        ':subject' => $subject,
        ':html' => $body_html,
        ':text' => $body_text
    ];
    
    return db_execute($sql, $params);
}

/**
 * Process email queue
 */
function process_email_queue() {
    $sql = "SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 ORDER BY created_at ASC LIMIT 10";
    $emails = db_query($sql);
    
    $processed = 0;
    $failed = 0;
    
    foreach ($emails as $email) {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = !empty(SMTP_USERNAME);
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;
            
            // Recipients
            $mail->setFrom(EMAIL_FROM, EMAIL_FROM_NAME);
            $mail->addAddress($email['recipient_email'], $email['recipient_name']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $email['subject'];
            $mail->Body = $email['body_html'];
            $mail->AltBody = $email['body_text'] ?: strip_tags($email['body_html']);
            
            // Send email
            $mail->send();
            
            // Update queue
            $update_sql = "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = :id";
            db_execute($update_sql, [':id' => $email['id']]);
            
            $processed++;
            
        } catch (Exception $e) {
            // Update attempt count
            $update_sql = "UPDATE email_queue SET attempts = attempts + 1, last_attempt = NOW() WHERE id = :id";
            db_execute($update_sql, [':id' => $email['id']]);
            
            // Mark as failed after 3 attempts
            if ($email['attempts'] >= 2) {
                $fail_sql = "UPDATE email_queue SET status = 'failed' WHERE id = :id";
                db_execute($fail_sql, [':id' => $email['id']]);
            }
            
            $failed++;
        }
    }
    
    return ['processed' => $processed, 'failed' => $failed];
}

/**
 * Send submission confirmation email
 */
function send_submission_confirmation($student_email, $student_name, $submission_id) {
    $subject = "ID Card Photo Submission Received - UBIDS";
    
    $body_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ID Card Photo Submission</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a3a2a; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            .button { display: inline-block; padding: 12px 24px; background: #c8a84b; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>University of Business and Integrated Development Studies</h1>
                <p>ID Card Photo Portal</p>
            </div>
            <div class="content">
                <h2>Submission Received</h2>
                <p>Dear ' . htmlspecialchars($student_name) . ',</p>
                <p>We have successfully received your ID card photo submission. Your submission details are:</p>
                <ul>
                    <li><strong>Submission ID:</strong> #' . str_pad($submission_id, 6, '0', STR_PAD_LEFT) . '</li>
                    <li><strong>Status:</strong> Under Review</li>
                    <li><strong>Submitted Date:</strong> ' . date('M j, Y g:i A') . '</li>
                </ul>
                <p>Your submission is now being reviewed by our administrative staff. You will receive another email once a decision has been made.</p>
                <p>You can check the status of your submission at any time by logging into the portal.</p>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="' . APP_URL . '/status.php" class="button">Check Submission Status</a>
                </div>
                <p>If you have any questions, please contact the IT Department.</p>
                <p>Thank you for your patience.</p>
            </div>
            <div class="footer">
                <p>&copy; 2024 University of Business and Integrated Development Studies</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $body_text = "Dear $student_name,\n\nWe have successfully received your ID card photo submission (ID: #" . str_pad($submission_id, 6, '0', STR_PAD_LEFT) . ").\n\nYour submission is now under review. You will receive another email once a decision has been made.\n\nYou can check your status at: " . APP_URL . "/status.php\n\nThank you,\nUBIDS IT Department";
    
    return send_email_notification($student_email, $student_name, $subject, $body_html, $body_text);
}

/**
 * Send approval notification email
 */
function send_approval_notification($student_email, $student_name, $submission_id) {
    $subject = "ID Card Photo Approved - UBIDS";
    
    $body_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ID Card Photo Approved</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a7a4a; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            .button { display: inline-block; padding: 12px 24px; background: #c8a84b; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>University of Business and Integrated Development Studies</h1>
                <p>ID Card Photo Portal</p>
            </div>
            <div class="content">
                <h2>Submission Approved! 🎉</h2>
                <p>Dear ' . htmlspecialchars($student_name) . ',</p>
                <p>Great news! Your ID card photo submission has been approved by our administrative staff.</p>
                <ul>
                    <li><strong>Submission ID:</strong> #' . str_pad($submission_id, 6, '0', STR_PAD_LEFT) . '</li>
                    <li><strong>Status:</strong> Approved</li>
                    <li><strong>Approval Date:</strong> ' . date('M j, Y g:i A') . '</li>
                </ul>
                <p>Your ID card will be generated shortly and you will be notified when it is ready for collection.</p>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="' . APP_URL . '/status.php" class="button">Check Status</a>
                </div>
                <p>If you have any questions about the collection process, please contact the IT Department.</p>
                <p>Congratulations!</p>
            </div>
            <div class="footer">
                <p>&copy; 2024 University of Business and Integrated Development Studies</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $body_text = "Dear $student_name,\n\nGreat news! Your ID card photo submission has been approved (ID: #" . str_pad($submission_id, 6, '0', STR_PAD_LEFT) . ").\n\nYour ID card will be generated shortly. You will be notified when it is ready for collection.\n\nCheck your status at: " . APP_URL . "/status.php\n\nCongratulations!\nUBIDS IT Department";
    
    return send_email_notification($student_email, $student_name, $subject, $body_html, $body_text);
}

/**
 * Send rejection notification email
 */
function send_rejection_notification($student_email, $student_name, $submission_id, $rejection_reason) {
    $subject = "ID Card Photo Update - UBIDS";
    
    $body_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ID Card Photo Update</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #c0392b; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            .button { display: inline-block; padding: 12px 24px; background: #c8a84b; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
            .rejection-reason { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin: 15px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>University of Business and Integrated Development Studies</h1>
                <p>ID Card Photo Portal</p>
            </div>
            <div class="content">
                <h2>Submission Update</h2>
                <p>Dear ' . htmlspecialchars($student_name) . ',</p>
                <p>We have reviewed your ID card photo submission, but unfortunately it could not be approved at this time.</p>
                <div class="rejection-reason">
                    <strong>Reason for rejection:</strong><br>
                    ' . htmlspecialchars($rejection_reason) . '
                </div>
                <p>Please review the feedback and submit a new photo that meets all requirements.</p>
                <ul>
                    <li><strong>Submission ID:</strong> #' . str_pad($submission_id, 6, '0', STR_PAD_LEFT) . '</li>
                    <li><strong>Status:</strong> Rejected</li>
                    <li><strong>Review Date:</strong> ' . date('M j, Y g:i A') . '</li>
                </ul>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="' . APP_URL . '/upload.php" class="button">Upload New Photo</a>
                </div>
                <p>Remember to review all photo requirements before submitting:</p>
                <ul>
                    <li>Recent, clear headshot with neutral expression</li>
                    <li>Plain, light-colored background</li>
                    <li>Face must be clearly visible and centered</li>
                    <li>No glasses, hats, or veils</li>
                    <li>Minimum dimensions: 390 × 540 pixels</li>
                </ul>
                <p>If you need assistance, please contact the IT Department.</p>
            </div>
            <div class="footer">
                <p>&copy; 2024 University of Business and Integrated Development Studies</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $body_text = "Dear $student_name,\n\nYour ID card photo submission has been reviewed but could not be approved at this time.\n\nReason: $rejection_reason\n\nPlease submit a new photo that meets all requirements.\n\nUpload a new photo at: " . APP_URL . "/upload.php\n\nIf you need assistance, contact the IT Department.\n\nUBIDS IT Department";
    
    return send_email_notification($student_email, $student_name, $subject, $body_html, $body_text);
}

/**
 * Send ID card ready notification
 */
function send_id_card_ready_notification($student_email, $student_name, $submission_id) {
    $subject = "ID Card Ready for Collection - UBIDS";
    
    $body_html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>ID Card Ready</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #1a7a4a; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
            .button { display: inline-block; padding: 12px 24px; background: #c8a84b; color: white; text-decoration: none; border-radius: 4px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>University of Business and Integrated Development Studies</h1>
                <p>ID Card Photo Portal</p>
            </div>
            <div class="content">
                <h2>Your ID Card is Ready! 🎴</h2>
                <p>Dear ' . htmlspecialchars($student_name) . ',</p>
                <p>Exciting news! Your ID card has been generated and is ready for collection.</p>
                <ul>
                    <li><strong>Submission ID:</strong> #' . str_pad($submission_id, 6, '0', STR_PAD_LEFT) . '</li>
                    <li><strong>Status:</strong> Generated</li>
                    <li><strong>Ready Date:</strong> ' . date('M j, Y g:i A') . '</li>
                </ul>
                <p><strong>Collection Details:</strong></p>
                <ul>
                    <li><strong>Location:</strong> IT Department, Main Campus</li>
                    <li><strong>Hours:</strong> Monday - Friday, 9:00 AM - 4:00 PM</li>
                    <li><strong>Required:</strong> Valid identity document (Ghana Card/Passport)</li>
                </ul>
                <p>Please bring a valid identity document for verification when collecting your ID card.</p>
                <div style="text-align: center; margin: 20px 0;">
                    <a href="' . APP_URL . '/status.php" class="button">View Your Status</a>
                </div>
                <p>If you have any questions about collection, please contact the IT Department.</p>
                <p>We look forward to seeing you soon!</p>
            </div>
            <div class="footer">
                <p>&copy; 2024 University of Business and Integrated Development Studies</p>
                <p>This is an automated message. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>';
    
    $body_text = "Dear $student_name,\n\nYour ID card has been generated and is ready for collection!\n\nCollection Details:\n- Location: IT Department, Main Campus\n- Hours: Monday - Friday, 9:00 AM - 4:00 PM\n- Required: Valid identity document\n\nPlease bring a valid ID for verification.\n\nView your status: " . APP_URL . "/status.php\n\nWe look forward to seeing you soon!\nUBIDS IT Department";
    
    return send_email_notification($student_email, $student_name, $subject, $body_html, $body_text);
}

/**
 * Get email queue statistics
 */
function get_email_queue_stats() {
    $pending = db_query_column("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'");
    $sent = db_query_column("SELECT COUNT(*) FROM email_queue WHERE status = 'sent'");
    $failed = db_query_column("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'");
    
    return [
        'pending' => $pending,
        'sent' => $sent,
        'failed' => $failed,
        'total' => $pending + $sent + $failed
    ];
}

/**
 * Clean up old email queue entries
 */
function cleanup_email_queue($days = 30) {
    $sql = "DELETE FROM email_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
    return db_execute($sql, [':days' => $days]);
}
?>
