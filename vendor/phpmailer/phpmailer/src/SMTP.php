<?php
/**
 * SMTP - A basic SMTP implementation for EasyMed
 */

namespace PHPMailer\PHPMailer;

class SMTP
{
    const DEBUG_OFF = 0;
    const DEFAULT_PORT = 25;
    
    protected $smtp_conn;
    protected $error = [];
    protected $last_reply = '';
    
    public function connect($host, $port = null, $timeout = 30)
    {
        if ($port === null) {
            $port = self::DEFAULT_PORT;
        }
        
        $errno = 0;
        $errstr = '';
        
        // Use stream_socket_client for better SSL/TLS support
        $context = stream_context_create();
        $this->smtp_conn = @stream_socket_client(
            $host . ':' . $port,
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        
        if (!$this->smtp_conn) {
            $this->error = ['error' => "Connection failed: $errstr ($errno)"];
            return false;
        }
        
        // Set timeout for subsequent operations
        stream_set_timeout($this->smtp_conn, $timeout);
        
        $this->last_reply = $this->get_lines();
        $code = substr($this->last_reply, 0, 3);
        
        return $code == '220';
    }
    
    public function hello($host)
    {
        return $this->sendCommand('EHLO', 'EHLO ' . $host, 250);
    }
    
    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        
        return @stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
    }
    
    public function authenticate($username, $password, $authtype = 'LOGIN')
    {
        switch ($authtype) {
            case 'LOGIN':
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                return $this->sendCommand('Password', base64_encode($password), 235);
                
            default:
                return false;
        }
    }
    
    public function mail($from)
    {
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>', 250);
    }
    
    public function recipient($to)
    {
        return $this->sendCommand('RCPT TO', 'RCPT TO:<' . $to . '>', [250, 251]);
    }
    
    public function data($message)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        
        // Send message
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $message));
        foreach ($lines as $line) {
            if (!empty($line) && $line[0] === '.') {
                $line = '.' . $line;
            }
            fwrite($this->smtp_conn, $line . "\r\n");
        }
        
        return $this->sendCommand('DATA END', '.', 250);
    }
    
    public function quit()
    {
        $result = $this->sendCommand('QUIT', 'QUIT', 221);
        $this->close();
        return $result;
    }
    
    protected function sendCommand($command, $commandstring, $expect)
    {
        if (!is_resource($this->smtp_conn)) {
            return false;
        }
        
        fwrite($this->smtp_conn, $commandstring . "\r\n");
        $this->last_reply = $this->get_lines();
        
        $code = (int)substr($this->last_reply, 0, 3);
        
        if (is_array($expect)) {
            return in_array($code, $expect);
        } else {
            return $code == $expect;
        }
    }
    
    protected function get_lines()
    {
        $data = '';
        $endtime = time() + 30; // 30 second timeout
        
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            if (time() > $endtime) {
                break; // Timeout
            }
            
            $str = @fgets($this->smtp_conn, 515);
            if ($str === false) {
                break; // Error reading
            }
            
            $data .= $str;
            if (!isset($str[3]) || $str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n") {
                break;
            }
        }
        return $data;
    }
    
    public function close()
    {
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
        }
    }
    
    public function getError()
    {
        return $this->error;
    }
}