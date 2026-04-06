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
     * Shared Email Design Wrapper
     */
    private function getTemplateWrapper($title, $content_html, $preheader = '') {
        $support_email    = getEmailClinicSetting('clinic_email', 'support@easymedclinic.com');
        $clinic_phone     = getEmailClinicSetting('clinic_phone', '+63-2-8123-4567');
        $clinic_address   = getEmailClinicSetting('clinic_address', '123 Healthcare Street, Medical District, Manila, Philippines');
        $year = date('Y');

        return '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . htmlspecialchars($title) . '</title>
            <style>
                body { font-family: "Inter", -apple-system, system-ui, sans-serif; line-height: 1.6; color: #1e293b; margin: 0; padding: 0; background: #f8fafc; }
                .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.1); border: 1px solid #e2e8f0; }
                .header { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #ffffff; padding: 48px 40px; text-align: center; }
                .header h1 { margin: 0; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.025em; }
                .header p { margin: 8px 0 0 0; opacity: 0.9; font-size: 0.95rem; }
                .content { padding: 48px; }
                .content h2 { color: #0f172a; font-size: 1.5rem; font-weight: 800; margin-top: 0; margin-bottom: 24px; letter-spacing: -0.02em; }
                .content p { font-size: 1.05rem; margin-bottom: 1.5rem; color: #334155; }
                .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin: 32px 0; }
                .info-box h3 { margin-top: 0; font-size: 0.85rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 16px; }
                .info-item { display: flex; margin-bottom: 12px; }
                .info-label { font-weight: 700; color: #475569; width: 140px; flex-shrink: 0; font-size: 0.9rem; }
                .info-value { color: #1e293b; font-weight: 500; font-size: 0.95rem; }
                .btn-wrap { text-align: center; margin: 32px 0; }
                .btn { display: inline-block; background: #2563eb; color: #ffffff !important; text-decoration: none !important; padding: 14px 32px; border-radius: 10px; font-weight: 700; font-size: 1rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2); }
                .footer { padding: 32px 48px; background: #f8fafc; border-top: 1px solid #f1f5f9; text-align: center; color: #64748b; font-size: 0.825rem; }
                .social-copy { margin-top: 16px; color: #94a3b8; }
                .security-note { background: #fef2f2; border-left: 4px solid #ef4444; border-radius: 8px; padding: 16px 20px; font-size: 0.875rem; color: #991b1b; }
                @media (max-width: 600px) { .content { padding: 32px 24px; } .header { padding: 40px 24px; } .info-label { width: 100px; } }
            </style>
        </head>
        <body>
            <div style="display: none; max-height: 0px; overflow: hidden;">' . ($preheader ?: 'EasyMed Clinical Update') . '</div>
            <div class="wrapper">
                <div class="header">
                    <h1>🏥 EasyMed</h1>
                    <p>Clinical Management System</p>
                </div>
                <div class="content">
                    ' . $content_html . '
                </div>
                <div class="footer">
                    <p><strong>EasyMed Clinic</strong></p>
                    <p>' . $clinic_address . '</p>
                    <p>Phone: ' . $clinic_phone . ' &nbsp;|&nbsp; Email: ' . $support_email . '</p>
                    <div class="social-copy">&copy; ' . $year . ' EasyMed. All rights reserved.</div>
                </div>
            </div>
        </body>
        </html>';
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
     * Send appointment rescheduled notification to doctor
     */
    public function sendDoctorAppointmentRescheduled($doctor_email, $doctor_name, $appointment_data) {
        $subject = "Schedule Update: Appointment #" . $appointment_data['appointment_id'] . " Rescheduled";
        $body = $this->getDoctorAppointmentRescheduledTemplate($appointment_data);
        
        return $this->sendEmail(
            $doctor_email,
            $doctor_name,
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
     * Send password reset OTP for patient/doctor self-recovery
     */
    public function sendPasswordResetOTP($to_email, $to_name, $otp) {
        $subject = "Your EasyMed Password Reset Code";
        $body    = $this->getPasswordResetOTPTemplate($to_name, $otp);

        return $this->sendEmail($to_email, $to_name, $subject, $body, true);
    }

    /**
     * Password reset email template
     */
    private function getPasswordResetTemplate($name, $new_password, $role) {
        $login_url = SITE_URL . '/index.php';
        $content = '
            <h2>Password Reset Notification</h2>
            <p>Dear <strong>' . htmlspecialchars($name) . '</strong>,</p>
            <p>An administrator has reset your <strong>' . ucfirst($role) . '</strong> account password. Please use the temporary credentials below to log in:</p>
            
            <div class="info-box" style="text-align: center; border: 2px dashed #2563eb;">
                <div style="font-size: 0.86rem; color: #64748b; margin-bottom: 8px; font-weight: 700;">TEMPORARY PASSWORD</div>
                <div style="font-size: 1.75rem; font-weight: 800; color: #1e3a8a; letter-spacing: 2px; font-family: monospace;">' . htmlspecialchars($new_password) . '</div>
            </div>

            <div style="background: #fffbeb; border-left: 4px solid #f59e0b; padding: 16px 20px; font-size: 0.9rem; color: #92400e; margin-bottom: 24px;">
                <strong>Action Required:</strong> Log in and change this temporary password immediately from your profile settings.
            </div>

            <div class="btn-wrap">
                <a href="' . $login_url . '" class="btn">Log In to EasyMed</a>
            </div>

            <div class="security-note">
                <strong>Security Alert:</strong> If you did not expect this, please contact support immediately.
            </div>';

        return $this->getTemplateWrapper("Password Reset", $content, "Your EasyMed password has been reset.");
    }

    /**
     * Password reset OTP email template
     */
    private function getPasswordResetOTPTemplate($name, $otp) {
        $content = '
            <h2>Verification Code</h2>
            <p>Hi <strong>' . htmlspecialchars($name) . '</strong>,</p>
            <p>We received a request to reset your password. Use the code below to proceed. It is valid for <strong>15 minutes</strong>.</p>
            
            <div class="info-box" style="text-align: center; background: #f1f5f9;">
                <div style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px; font-weight: 700;">SECURITY CODE</div>
                <div style="font-size: 3rem; font-weight: 800; color: #0891b2; letter-spacing: 0.2em; font-family: monospace;">' . $otp . '</div>
            </div>

            <p style="font-size: 0.95rem;">If you did not request this, you can safely ignore this email.</p>

            <div class="security-note">
                Never share this code with anyone. EasyMed staff will never ask for your verification code.
            </div>';

        return $this->getTemplateWrapper("Reset Verification", $content, "Your 6-digit password reset code.");
    }

    /**
     * Get appointment scheduled email template
     */
    private function getAppointmentScheduledTemplate($data) {
        $content = '
            <h2>Appointment Scheduled</h2>
            <p>Dear <strong>' . htmlspecialchars($data['patient_name']) . '</strong>,</p>
            <p>Your appointment has been successfully scheduled. We look forward to seeing you at our clinic.</p>
            
            <div class="info-box">
                <h3>📅 Appointment Details</h3>
                <div class="info-item">
                    <div class="info-label">Doctor</div>
                    <div class="info-value">Dr. ' . htmlspecialchars($data['doctor_name']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value">' . htmlspecialchars($data['appointment_date']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Time</div>
                    <div class="info-value">' . htmlspecialchars($data['appointment_time']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Service</div>
                    <div class="info-value">' . htmlspecialchars($data['purpose'] ?? $data['specialty'] ?? 'Consultation') . '</div>
                </div>
                <div class="info-item" style="margin-bottom: 0;">
                    <div class="info-label">Reference</div>
                    <div class="info-value">#APT-' . str_pad($data['appointment_id'], 5, "0", STR_PAD_LEFT) . '</div>
                </div>
            </div>

            <p><strong>Reminders:</strong> Please arrive 15 minutes early and bring a valid ID.</p>';

        return $this->getTemplateWrapper("Appointment Scheduled", $content, "Your appointment with EasyMed has been scheduled.");
    }

    private function getAppointmentRescheduledTemplate($data) {
        $content = '
            <h2 style="color: #d97706;">Appointment Rescheduled</h2>
            <p>Dear <strong>' . htmlspecialchars($data['patient_name']) . '</strong>,</p>
            <p>Your appointment schedule has been updated. Please note the new date and time below:</p>
            
            <div class="info-box" style="border-left: 4px solid #f59e0b;">
                <h3>📅 Updated Schedule</h3>
                <div class="info-item">
                    <div class="info-label">Doctor</div>
                    <div class="info-value">' . htmlspecialchars($data['doctor_name']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">New Date</div>
                    <div class="info-value">' . htmlspecialchars($data['appointment_date']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">New Time</div>
                    <div class="info-value">' . htmlspecialchars($data['appointment_time']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Reason</div>
                    <div class="info-value">' . htmlspecialchars($data['reason'] ?? 'Schedule adjustment') . '</div>
                </div>
            </div>

            <p>We apologize for any inconvenience this change may cause.</p>';

        return $this->getTemplateWrapper("Appointment Rescheduled", $content, "Your appointment has been rescheduled.");
    }
    
    /**
     * Get appointment cancelled email template
     */
    private function getAppointmentCancelledTemplate($data) {
        $content = '
            <h2 style="color: #ef4444;">Appointment Cancelled</h2>
            <p>Dear <strong>' . htmlspecialchars($data['patient_name']) . '</strong>,</p>
            <p>Your clinical appointment has been cancelled. Please see the original details below for your reference:</p>
            
            <div class="info-box" style="border-left: 4px solid #ef4444;">
                <h3>📅 Cancelled Details</h3>
                <div class="info-item">
                    <div class="info-label">Doctor</div>
                    <div class="info-value">Dr. ' . htmlspecialchars($data['doctor_name']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date</div>
                    <div class="info-value">' . htmlspecialchars($data['appointment_date']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Time</div>
                    <div class="info-value">' . htmlspecialchars($data['appointment_time']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Reference</div>
                    <div class="info-value">#APT-' . str_pad($data['appointment_id'], 5, "0", STR_PAD_LEFT) . '</div>
                </div>
            </div>

            <p>If you wish to rebook, please visit our online portal or contact us directly.</p>';

        return $this->getTemplateWrapper("Appointment Cancelled", $content, "Your appointment with EasyMed has been cancelled.");
    }

    /**
     * Get doctor appointment rescheduled email template
     */
    private function getDoctorAppointmentRescheduledTemplate($data) {
        $content = '
            <h2>Schedule Update</h2>
            <p>Dear <strong>' . htmlspecialchars($data['doctor_name']) . '</strong>,</p>
            <p>One of your clinical appointments has been rescheduled by an administrator. Below are the updated details:</p>
            
            <div class="info-box">
                <h3>👤 Patient Information</h3>
                <div class="info-item">
                    <div class="info-label">Patient</div>
                    <div class="info-value">' . htmlspecialchars($data['patient_name']) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">New Date</div>
                    <div class="info-value">' . date('F j, Y', strtotime($data['appointment_date'])) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">New Time</div>
                    <div class="info-value">' . date('g:i A', strtotime($data['appointment_time'])) . '</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Reference</div>
                    <div class="info-value">#APT-' . str_pad($data['appointment_id'], 5, "0", STR_PAD_LEFT) . '</div>
                </div>
            </div>
            <p>Your dashboard has been updated to reflect these changes.</p>';

        return $this->getTemplateWrapper("Schedule Update", $content, "An appointment in your schedule has been updated.");
    }
}
