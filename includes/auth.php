<?php
/**
 * Authentication and session management
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a random session ID
 * 
 * @param int $length Length of the session ID
 * @return string Random session ID
 */
if (!function_exists('generate_session_id')) {
    function generate_session_id($length = 16) {
        return bin2hex(random_bytes($length));
    }
}

// Login and logout functions are now provided by functions.php

/**
 * Check if a user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
// Function definition for is_logged_in
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Get the current logged-in user
 * 
 * @return array|false User data if logged in, false otherwise
 */
function get_current_logged_in_user() {
    if (!is_logged_in()) {
        return false;
    }
    
    return get_user_by_id($_SESSION['user_id']);
}

/**
 * Check if the current user is an admin
 * 
 * @return bool True if user is an admin, false otherwise
 */
function is_current_user_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

/**
 * Require login for a page
 * If user is not logged in, redirect to login page
 * 
 * @param string $redirect_url URL to redirect to after login
 * @return void
 */
function require_login($redirect_url = null) {
    if (!is_logged_in()) {
        $redirect_to = $redirect_url ? urlencode($redirect_url) : '';
        header("Location: /login.php?redirect_to=" . $redirect_to);
        exit;
    }
    
    // Check session timeout (30 minutes)
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
        logout_user();
        header("Location: /login.php");
        exit;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = time();
}

/**
 * Set a flash message in the session
 * 
 * @param string $message The message to display
 * @param string $type The message type (success, danger, warning, info)
 * @return void
 */
if (!function_exists('set_flash_message')) {
    function set_flash_message($message, $type = 'info') {
        $_SESSION['flash'][$type] = $message;
    }
}

/**
 * Require admin for a page
 * If user is not an admin, redirect to home page
 * 
 * @return void
 */
function require_admin() {
    require_login();
    
    if (!is_current_user_admin()) {
        set_flash_message("You don't have permission to access that page.", "danger");
        header("Location: /index.php");
        exit;
    }
}

/**
 * Ensure user is authenticated as admin
 * More explicit function name for clarity
 * 
 * @return void
 */
function ensure_admin_authenticated() {
    require_admin();
}

/**
 * Create CSRF token
 * 
 * @return string CSRF token
 */
function create_csrf_token() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Verify CSRF token
 * 
 * @param string $token CSRF token to verify
 * @return bool True if token is valid, false otherwise
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    $stored_token = $_SESSION['csrf_token'];
    
    // Remove token from session to prevent reuse
    unset($_SESSION['csrf_token']);
    
    return hash_equals($stored_token, $token);
}