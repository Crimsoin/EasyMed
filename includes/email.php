<?php
/**
 * Email Class for EasyMed
 * Handles all email notifications using Gmail SMTP
 */

// Use our manual PHPMailer installation
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

$phpmailer_available = true;

// Helper function for email clinic settings
function getEmailClinicSetting($key, $default = '') {
    // You can expand this to get settings from database
    $settings = [
        'clinic_phone' => '+63-2-8123-4567',
        'clinic_email' => 'info@easymedclinic.com',
        'clinic_address' => '123 Healthcare Street, Medical District, Manila, Philippines'
    ];
    
    return isset($settings[$key]) ? $settings[$key] : $default;
}

class EmailService {
    private $phpmailer_available;
    
    public function __construct() {
        global $phpmailer_available;
        $this->phpmailer_available = $phpmailer_available;
    }
    
    /**
     * Send email using PHPMailer or fallback to PHP mail()
     */
    public function sendEmail($to_email, $to_name, $subject, $body, $is_html = true) {
        // Always try PHPMailer first since we have it configured
        $result = $this->sendWithPHPMailer($to_email, $to_name, $subject, $body, $is_html);
        
        // If PHPMailer fails, try PHP mail as fallback
        if (!$result['success']) {
            error_log("PHPMailer failed: " . $result['message'] . ". Trying PHP mail() fallback.");
            return $this->sendWithPHPMail($to_email, $to_name, $subject, $body, $is_html);
        }
        
        return $result;
    }
    
    /**
     * Send email using PHPMailer (recommended)
     */
    private function sendWithPHPMailer($to_email, $to_name, $subject, $body, $is_html = true) {
        try {
            $mail = new PHPMailer();
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = SMTP_ENCRYPTION;
            $mail->Port = SMTP_PORT;
            
            // Recipients  
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to_email, $to_name);
            
            // Content
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $result = $mail->send();
            
            if ($result) {
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send email: ' . $mail->ErrorInfo];
            }
            
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Fallback email using PHP's built-in mail() function
     */
    private function sendWithPHPMail($to_email, $to_name, $subject, $body, $is_html = true) {
        $headers = [];
        $headers[] = 'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>';
        $headers[] = 'Reply-To: ' . SMTP_FROM_EMAIL;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        
        if ($is_html) {
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/html; charset=UTF-8';
        }
        
        $to = !empty($to_name) ? "$to_name <$to_email>" : $to_email;
        
        if (mail($to, $subject, $body, implode("\r\n", $headers))) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send email'];
        }
    }
    
    /**
     * Send appointment scheduled notification
     */
    public function sendAppointmentScheduled($patient_email, $patient_name, $appointment_data) {
        $subject = "Appointment Scheduled - EasyMed Clinic";
        $body = $this->getAppointmentScheduledTemplate($appointment_data);
        
        return $this->sendEmail(
            $patient_email,
            $patient_name,
            $subject,
            $body,
            true
        );
    }
    
    /**
     * Send appointment rescheduled notification
     */
    public function sendAppointmentRescheduled($patient_email, $patient_name, $appointment_data) {
        $subject = "Appointment Rescheduled - EasyMed Clinic";
        $body = $this->getAppointmentRescheduledTemplate($appointment_data);
        
        return $this->sendEmail(
            $patient_email,
            $patient_name,
            $subject,
            $body,
            true
        );
    }
    
    /**
     * Send appointment cancelled notification
     */
    public function sendAppointmentCancelled($patient_email, $patient_name, $appointment_data) {
        $subject = "Appointment Cancelled - EasyMed Clinic";
        $body = $this->getAppointmentCancelledTemplate($appointment_data);
        
        return $this->sendEmail(
            $patient_email,
            $patient_name,
            $subject,
            $body,
            true
        );
    }
    
    /**
     * Get appointment scheduled email template
     */
    private function getAppointmentScheduledTemplate($data) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Appointment Scheduled</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #00bcd4; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .appointment-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .btn { background: #00bcd4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 EasyMed Clinic</h1>
                    <h2>Appointment Scheduled</h2>
                </div>
                
                <div class="content">
                    <p>Dear ' . htmlspecialchars($data['patient_name']) . ',</p>
                    
                    <p>Your appointment has been successfully scheduled. Here are the details:</p>
                    
                    <div class="appointment-details">
                        <h3>📅 Appointment Details</h3>
                        <p><strong>Date:</strong> ' . htmlspecialchars($data['appointment_date']) . '</p>
                        <p><strong>Time:</strong> ' . htmlspecialchars($data['appointment_time']) . '</p>
                        <p><strong>Doctor:</strong> ' . htmlspecialchars($data['doctor_name']) . '</p>
                        <p><strong>Specialty:</strong> ' . htmlspecialchars($data['specialty'] ?? 'General Medicine') . '</p>
                        <p><strong>Reason:</strong> ' . htmlspecialchars($data['reason'] ?? 'General consultation') . '</p>
                        <p><strong>Reference:</strong> #' . $data['appointment_id'] . '</p>
                    </div>
                    
                    <p><strong>⚠️ Important Reminders:</strong></p>
                    <ul>
                        <li>Please arrive 15 minutes before your scheduled time</li>
                        <li>Bring a valid ID and any previous medical records</li>
                        <li>If you need to reschedule, please contact us at least 24 hours in advance</li>
                    </ul>
                    
                    <p>If you have any questions or need to make changes to your appointment, please contact us.</p>
                    
                    <p>Thank you for choosing EasyMed Clinic!</p>
                </div>
                
                <div class="footer">
                    <p>EasyMed Clinic | 📞 ' . getEmailClinicSetting('clinic_phone', '+63-2-8123-4567') . ' | 📧 ' . getEmailClinicSetting('clinic_email', 'info@easymed.com') . '</p>
                    <p>' . getEmailClinicSetting('clinic_address', '123 Healthcare Street, Medical District, Manila, Philippines') . '</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get appointment rescheduled email template
     */
    private function getAppointmentRescheduledTemplate($data, $old_date = null, $old_time = null) {
        $old_info = '';
        if ($old_date && $old_time) {
            $old_info = '
                <div style="background: #fff3cd; padding: 10px; border-radius: 5px; margin: 10px 0;">
                    <p><strong>Previous Schedule:</strong></p>
                    <p>Date: ' . date('F j, Y', strtotime($old_date)) . '</p>
                    <p>Time: ' . date('g:i A', strtotime($old_time)) . '</p>
                </div>';
        }
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Appointment Rescheduled</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff9800; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .appointment-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 EasyMed Clinic</h1>
                    <h2>Appointment Rescheduled</h2>
                </div>
                
                <div class="content">
                    <p>Dear ' . htmlspecialchars($data['patient_name']) . ',</p>
                    
                    <p>Your appointment has been rescheduled. Here are the updated details:</p>
                    
                    ' . $old_info . '
                    
                    <div class="appointment-details">
                        <h3>📅 New Appointment Details</h3>
                        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($data['appointment_date'])) . '</p>
                        <p><strong>Time:</strong> ' . date('g:i A', strtotime($data['appointment_time'])) . '</p>
                        <p><strong>Doctor:</strong> Dr. ' . htmlspecialchars($data['doctor_name']) . '</p>
                        <p><strong>Specialty:</strong> ' . htmlspecialchars($data['specialty'] ?? 'General Medicine') . '</p>
                        <p><strong>Reason:</strong> ' . htmlspecialchars($data['reason']) . '</p>
                        <p><strong>Reference:</strong> #' . $data['appointment_id'] . '</p>
                    </div>
                    
                    <p>We apologize for any inconvenience caused by this change.</p>
                    
                    <p>Thank you for your understanding!</p>
                </div>
                
                <div class="footer">
                    <p>EasyMed Clinic | 📞 ' . getEmailClinicSetting('clinic_phone', '+63-2-8123-4567') . ' | 📧 ' . getEmailClinicSetting('clinic_email', 'info@easymed.com') . '</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Get appointment cancelled email template
     */
    private function getAppointmentCancelledTemplate($data) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Appointment Cancelled</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #f44336; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .appointment-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>🏥 EasyMed Clinic</h1>
                    <h2>Appointment Cancelled</h2>
                </div>
                
                <div class="content">
                    <p>Dear ' . htmlspecialchars($data['patient_name']) . ',</p>
                    
                    <p>Your appointment has been cancelled. Here were the details:</p>
                    
                    <div class="appointment-details">
                        <h3>📅 Cancelled Appointment</h3>
                        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($data['appointment_date'])) . '</p>
                        <p><strong>Time:</strong> ' . date('g:i A', strtotime($data['appointment_time'])) . '</p>
                        <p><strong>Doctor:</strong> Dr. ' . htmlspecialchars($data['doctor_name']) . '</p>
                        <p><strong>Reference:</strong> #' . $data['appointment_id'] . '</p>
                    </div>
                    
                    <p>If you would like to schedule a new appointment, please contact us or use our online booking system.</p>
                    
                    <p>Thank you for choosing EasyMed Clinic!</p>
                </div>
                
                <div class="footer">
                    <p>EasyMed Clinic | 📞 ' . getEmailClinicSetting('clinic_phone', '+63-2-8123-4567') . ' | 📧 ' . getEmailClinicSetting('clinic_email', 'info@easymed.com') . '</p>
                </div>
            </div>
        </body>
        </html>';
    }
}
