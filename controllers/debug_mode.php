<?php
/**
 * Debug Mode Controller
 * 
 * This controller returns the current debug mode setting from the server.
 * It is used by JavaScript to determine whether debug logs should be shown.
 */

require_once __DIR__ . '/../includes/functions.php';

// Check if the user is logged in and is an admin
$is_admin = is_admin();

// Get debug mode setting from database
$debug_enabled = false;

// Connect to the database
$db = get_db_connection();

// Check if debug mode is enabled in settings
$stmt = $db->prepare("SELECT value FROM settings WHERE name = 'debug_mode'");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    // Convert to integer to ensure proper comparison
    $debug_enabled = (int)$result['value'] === 1;
    error_log("Debug mode from database: " . $result['value'] . ", converted: " . ((int)$result['value'] === 1 ? 'true' : 'false'));
} else {
    error_log("Debug mode setting not found in database");
}

// Return the debug status as JSON
header('Content-Type: application/json');
echo json_encode([
    'debug_enabled' => $debug_enabled || $is_admin // Always enable debug logs for admins
]);