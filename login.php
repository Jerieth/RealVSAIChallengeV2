<?php
/**
 * Login page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/template.php';
require_once __DIR__ . '/includes/ip_functions.php';
require_once __DIR__ . '/models/User.php';

// Run the IP tracking database update if needed
if (!file_exists(__DIR__ . '/.ip_tracking_initialized')) {
    include_once __DIR__ . '/update_users_ip_tracking.php';
    // Create a marker file to prevent running the update again
    file_put_contents(__DIR__ . '/.ip_tracking_initialized', date('Y-m-d H:i:s'));
}

// Check if user is already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$username = '';
$redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';
    $redirect_to = $_POST['redirect_to'] ?? '';
    
    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid form submission, please try again.';
    }
    // Validate form data
    elseif (empty($username)) {
        $error = 'Username is required.';
    }
    elseif (empty($password)) {
        $error = 'Password is required.';
    }
    else {
        // Authenticate user
        $user = verify_user_credentials($username, $password);
        
        if ($user) {
            // Login successful - regenerate session ID to prevent session fixation
            $old_session_data = $_SESSION; // Backup any existing non-user session data
            session_regenerate_id(true);   // Generate new session ID and delete old session
            
            // Reset session array but keep any existing non-user session data
            $_SESSION = [];
            foreach ($old_session_data as $key => $value) {
                // Skip any existing user data from a previous login
                if (!in_array($key, ['user_id', 'username', 'is_admin', 'is_anonymous'])) {
                    $_SESSION[$key] = $value;
                }
            }
            
            // Set session variables for the logged-in user
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            $_SESSION['last_activity'] = time();
            
            // Update login tracking for achievements
            update_user_login_count($user['id']);
            
            // Track user IP address using the client IP function
            $user_ip = get_client_ip();
            record_user_ip_address($user['id'], $user_ip);
            
            // Check if this IP address is blocked
            if (is_ip_blocked($user_ip)) {
                // Log the blocked IP access attempt
                error_log("Warning: Blocked IP address {$user_ip} attempted to log in as {$user['username']}");
                
                // Clear session data
                session_unset();
                session_destroy();
                
                // Redirect to login page with error
                flash_message('Access denied. Your IP address has been blocked.', 'danger');
                header('Location: /login.php');
                exit;
            }
            
            // Clear any game session data on login to prevent resuming games from another user
            if (isset($_SESSION['game_session_id'])) {
                unset($_SESSION['game_session_id']);
            }
            
            // Log session regeneration for debugging
            error_log("Login successful. New session ID: " . session_id() . ", IP: {$user_ip}");
            
            // Redirect to appropriate page
            $redirect_url = !empty($redirect_to) ? urldecode($redirect_to) : '/index.php';
            header("Location: $redirect_url");
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    }
}

// Generate CSRF token for the form
$csrf_token = create_csrf_token();

// Render the login page
// Use the namespaced render_template function
use RealAI\Template;
$content = Template\render_template('templates/login.php', [
    'error' => $error,
    'username' => $username,
    'csrf_token' => $csrf_token,
    'redirect_to' => $redirect_to
], true);

// Use the namespaced render_template function for the entire layout
echo Template\render_template('templates/layout.php', [
    'page_title' => 'Login - ' . APP_NAME,
    'content' => $content
]);