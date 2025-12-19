<?php
/**
 * Exception class for PHPMailer
 */

namespace PHPMailer\PHPMailer;

class Exception extends \Exception
{
    /**
     * Prettify error message output
     * @param string $message
     * @param int $code
     * @param Exception $previous
     */
    public function __construct($message = '', $code = 0, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Return error name
     * @return string
     */
    public function errorMessage()
    {
        return '<strong>' . htmlspecialchars($this->getMessage(), ENT_COMPAT | ENT_HTML401) . "</strong><br />\n";
    }
}