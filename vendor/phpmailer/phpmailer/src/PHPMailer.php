<?php
/**
 * PHPMailer - A basic implementation for EasyMed
 */

namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const CHARSET_UTF8 = 'utf-8';
    const ENCODING_BASE64 = 'base64';
    const ENCRYPTION_STARTTLS = 'tls';
    
    public $Mailer = 'smtp';
    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPSecure = '';
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $CharSet = 'utf-8';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $isHTML = true;
    public $ErrorInfo = '';
    
    protected $to = [];
    protected $smtp;
    
    public function __construct()
    {
        $this->smtp = new SMTP();
    }
    
    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }
    
    public function isHTML($isHtml = true)
    {
        $this->isHTML = $isHtml;
    }
    
    public function setFrom($address, $name = '')
    {
        $this->From = $address;
        $this->FromName = $name;
        return true;
    }
    
    public function addAddress($address, $name = '')
    {
        $this->to[] = [$address, $name];
        return true;
    }
    
    public function send()
    {
        try {
            if (!$this->smtp->connect($this->Host, $this->Port)) {
                $this->ErrorInfo = 'Could not connect to SMTP server';
                return false;
            }
            
            if (!$this->smtp->hello('localhost')) {
                $this->ErrorInfo = 'SMTP hello failed';
                return false;
            }
            
            if ($this->SMTPSecure === 'tls') {
                if (!$this->smtp->startTLS()) {
                    $this->ErrorInfo = 'StartTLS failed';
                    return false;
                }
                $this->smtp->hello('localhost');
            }
            
            if ($this->SMTPAuth) {
                if (!$this->smtp->authenticate($this->Username, $this->Password)) {
                    $this->ErrorInfo = 'SMTP authentication failed';
                    return false;
                }
            }
            
            if (!$this->smtp->mail($this->From)) {
                $this->ErrorInfo = 'MAIL FROM failed';
                return false;
            }
            
            foreach ($this->to as $recipient) {
                if (!$this->smtp->recipient($recipient[0])) {
                    $this->ErrorInfo = 'RCPT TO failed for ' . $recipient[0];
                    return false;
                }
            }
            
            $message = $this->createMessage();
            if (!$this->smtp->data($message)) {
                $this->ErrorInfo = 'DATA failed';
                return false;
            }
            
            $this->smtp->quit();
            return true;
            
        } catch (Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            return false;
        }
    }
    
    protected function createMessage()
    {
        $headers = [];
        $headers[] = 'From: ' . ($this->FromName ? $this->FromName . ' <' . $this->From . '>' : $this->From);
        $headers[] = 'Subject: ' . $this->Subject;
        $headers[] = 'MIME-Version: 1.0';
        
        if ($this->isHTML) {
            $headers[] = 'Content-Type: text/html; charset=' . $this->CharSet;
        } else {
            $headers[] = 'Content-Type: text/plain; charset=' . $this->CharSet;
        }
        
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $this->Body;
        return $message;
    }
}