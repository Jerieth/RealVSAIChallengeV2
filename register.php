<?php
/**
 * User registration page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/template.php';
require_once __DIR__ . '/includes/ip_functions.php';
require_once __DIR__ . '/includes/username_validation.php';
require_once __DIR__ . '/models/User.php';

// Run the IP tracking database update if needed
if (!file_exists(__DIR__ . '/.ip_tracking_initialized')) {
    include_once __DIR__ . '/update_users_ip_tracking.php';
    // Create a marker file to prevent running the update again
    file_put_contents(__DIR__ . '/.ip_tracking_initialized', date('Y-m-d H:i:s'));
}

// Check if current IP is blocked
$user_ip = get_client_ip();
if (is_ip_blocked($user_ip)) {
    flash_message('Registration from your IP address is not allowed.', 'danger');
    header('Location: /index.php');
    exit;
}

// Check if user is already logged in
if (is_logged_in()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$username = '';
$email = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // Validate CSRF token
    if (!verify_csrf_token($csrf_token)) {
        $error = 'Invalid form submission, please try again.';
    }
    // Validate form data
    elseif (empty($username)) {
        $error = 'Username is required.';
    }
    elseif (strlen($username) < 3 || strlen($username) > 64) {
        $error = 'Username must be between 3 and 64 characters.';
    }
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = 'Username can only contain letters, numbers, and underscores.';
    }
    elseif (strtolower($username) === 'anonymous' || preg_match('/^an+on+[yi]+m+ou*s$/i', $username)) {
        $error = 'The username "Anonymous" is reserved. Please choose another username.';
    }
    elseif (is_offensive_username($username)) {
        $error = 'Invalid username. Please choose another.';
    }
    elseif (empty($email)) {
        $error = 'Email is required.';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    }
    elseif (empty($password)) {
        $error = 'Password is required.';
    }
    elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters with at least one letter and one number.';
    }
    elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    }
    else {
        // Check if username already exists
        if (get_user_by_username($username)) {
            $error = 'Username already taken. Please choose another one.';
        }
        // Check if email already exists
        elseif (get_user_by_email($email)) {
            $error = 'Email already registered. Please use another one or log in.';
        }
        else {
            // Create the user
            $user_id = create_user($username, $email, $password);

            if ($user_id) {
                // Get the user data
                $user = get_user_by_id($user_id);

                // Record the user's IP address
                record_user_ip_address($user_id, $user_ip);

                // Login the user with remember me set to true
                $login_result = login_user($username, $password, true);
                
                if ($login_result['success']) {
                    // Redirect to home page
                    set_flash_message('Registration successful! Welcome to ' . APP_NAME . '.', 'success');
                    header('Location: /index.php');
                    exit;
                }
            } else {
                $error = 'Failed to create account. Please try again.';
            }
        }
    }
}

// Generate CSRF token for the form
$csrf_token = create_csrf_token();

// Use the Template namespace
use RealAI\Template;

// Render the registration page
$content = Template\render_template('templates/register.php', [
    'error' => $error,
    'username' => $username,
    'email' => $email,
    'csrf_token' => $csrf_token
], true);

// Render the layout
echo Template\render_template('templates/layout.php', [
    'page_title' => 'Register - ' . APP_NAME,
    'content' => $content,
    'additional_scripts' => '<script src="/static/js/validation.js"></script>'
]);
?>