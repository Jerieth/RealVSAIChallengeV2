<?php
/**
 * Logout handler
 */

require_once __DIR__ . '/includes/auth.php';

// Get the old session ID for logging
$old_session_id = session_id();

// Logout the user - clear session
session_unset();
session_destroy();

// Start a completely new session with a new ID
session_start();
session_regenerate_id(true);

// Log session change for debugging
error_log("Logout complete. Old session ID: $old_session_id, New session ID: " . session_id());

// Set a flash message in the new session
$_SESSION['flash_message'] = [
    'type' => 'success',
    'message' => 'You have been successfully logged out.'
];

// Redirect to home page
header('Location: /index.php');
exit;