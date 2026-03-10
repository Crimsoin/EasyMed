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
     * Send password reset notification to a patient or doctor
     */
    public function sendPasswordResetNotification($to_email, $to_name, $new_password, $role = 'patient') {
        $subject = "Your EasyMed Account Password Has Been Reset";
        $body    = $this->getPasswordResetTemplate($to_name, $new_password, $role);

        return $this->sendEmail($to_email, $to_name, $subject, $body, true);
    }

    /**
     * Password reset email template
     */
    private function getPasswordResetTemplate($name, $new_password, $role) {
        $role_label       = ucfirst($role);
        $login_url        = SITE_URL . '/index.php';
        $support_email    = getEmailClinicSetting('clinic_email', 'support@easymedclinic.com');
        $clinic_phone     = getEmailClinicSetting('clinic_phone', '+63-2-8123-4567');
        $clinic_address   = getEmailClinicSetting('clinic_address', '123 Healthcare Street, Medical District, Manila, Philippines');

        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Password Reset Notification</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
                .wrapper { max-width: 620px; margin: 30px auto; background: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #1e3a8a, #2563eb); color: white; padding: 30px 40px; text-align: center; }
                .header h1 { margin: 0 0 5px 0; font-size: 1.8rem; }
                .header p  { margin: 0; opacity: 0.85; font-size: 0.95rem; }
                .content   { padding: 35px 40px; }
                .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px; padding: 15px 20px; margin: 20px 0; }
                .alert-box p { margin: 0; color: #92400e; font-size: 0.9rem; }
                .cred-box  { background: #f0f9ff; border: 2px dashed #2563eb; border-radius: 8px; padding: 20px 25px; margin: 20px 0; text-align: center; }
                .cred-box .label { font-size: 0.8rem; color: #64748b; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
                .cred-box .password { font-size: 1.6rem; font-weight: 800; color: #1e3a8a; letter-spacing: 3px; font-family: monospace; }
                .btn { display: inline-block; background: linear-gradient(135deg, #2563eb, #0891b2); color: #fff !important; text-decoration: none; padding: 13px 32px; border-radius: 8px; font-weight: 700; font-size: 1rem; margin: 20px 0; }
                .footer { background: #f8fafc; padding: 20px 40px; text-align: center; color: #64748b; font-size: 0.82rem; border-top: 1px solid #e2e8f0; }
                .security-note { background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 6px; padding: 12px 18px; margin-top: 20px; }
                .security-note p { margin: 0; color: #991b1b; font-size: 0.88rem; }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="header">
                    <h1>🏥 EasyMed Clinic</h1>
                    <p>Secure Account Notification</p>
                </div>

                <div class="content">
                    <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>

                    <p>This is an automated notification to inform you that an <strong>EasyMed administrator</strong> has reset the password for your <strong>' . $role_label . '</strong> account.</p>

                    <div class="cred-box">
                        <div class="label">🔑 Your Temporary Password</div>
                        <div class="password">' . htmlspecialchars($new_password) . '</div>
                    </div>

                    <div class="alert-box">
                        <p>⚠️ <strong>Action Required:</strong> Please log in immediately and change this temporary password from your profile settings to secure your account.</p>
                    </div>

                    <p style="text-align:center;">
                        <a href="' . $login_url . '" class="btn">🔐 Log In to EasyMed</a>
                    </p>

                    <div class="security-note">
                        <p>🛡️ <strong>Did not expect this?</strong> If you did not request a password reset, please contact our support team immediately at <a href="mailto:' . $support_email . '">' . $support_email . '</a> or call us at ' . $clinic_phone . '.</p>
                    </div>

                    <p style="margin-top: 25px;">Thank you for using <strong>EasyMed Clinic</strong>.</p>
                </div>

                <div class="footer">
                    <p>EasyMed Clinic &nbsp;|&nbsp; 📞 ' . $clinic_phone . ' &nbsp;|&nbsp; 📧 ' . $support_email . '</p>
                    <p>' . $clinic_address . '</p>
                    <p style="margin-top:10px; color:#94a3b8;">This is an automated security email. Please do not reply directly to this message.</p>
                </div>
            </div>
        </body>
        </html>';
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
                .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .appointment-details { background: white; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                .btn { background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; }
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
