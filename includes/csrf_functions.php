<?php
/**
 * CSRF Protection Functions
 * Functions for generating and validating CSRF tokens for form submissions
 */

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate a CSRF token and store it in the session
     * @return string The generated CSRF token
     */
    function generate_csrf_token() {
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Store token in session
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
}

if (!function_exists('validate_csrf_token')) {
    /**
     * Validate a submitted CSRF token against the one stored in the session
     * @param string $token The token to validate
     * @param int $timeout Optional timeout in seconds (default is 3600 = 1 hour)
     * @return bool True if token is valid, false otherwise
     */
    function validate_csrf_token($token, $timeout = 3600) {
    // Check if token exists in session
    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }
    
    // Check if token matches
    if ($_SESSION['csrf_token'] !== $token) {
        return false;
    }
    
    // Check if token has expired
    $token_time = $_SESSION['csrf_token_time'];
    if (time() - $token_time > $timeout) {
        // Token has expired, generate a new one
        generate_csrf_token();
        return false;
    }
    
    // Token is valid, generate a new one for next use
    generate_csrf_token();
    return true;
    }
}
?>