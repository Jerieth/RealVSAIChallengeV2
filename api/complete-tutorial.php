
<?php
/**
 * API Endpoint to mark the tutorial as completed
 */

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include required files 
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/Achievement.php';

// Set response header
header('Content-Type: application/json');

// Suppress PHP errors from being output
error_reporting(0);

// Default response
$response = [
    'success' => false,
    'message' => 'Error updating tutorial status'
];

// Only proceed if logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    
    try {
        // Get database connection
        $pdo = get_db_connection();
        
        // Update user profile to mark tutorial as completed
        $query = "UPDATE users SET tutorial_completed = 1, tutorial_completed_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$user_id]);
        
        // Check if the update was successful
        if ($stmt->rowCount() > 0) {
            $response = [
                'success' => true,
                'message' => 'Tutorial completion recorded'
            ];
        }
        
        // Log the tutorial completion
        $log_query = "INSERT INTO activity_logs (user_id, action, details, created_at) 
                      VALUES (?, 'tutorial_completed', 'User completed the tutorial', CURRENT_TIMESTAMP)";
        $log_stmt = $pdo->prepare($log_query);
        $log_stmt->execute([$user_id]);
        
        // Award the tutorial completion achievement
        $achievement_awarded = award_achievement_model($user_id, 'complete_tutorial');
        if ($achievement_awarded) {
            $response['achievement'] = [
                'awarded' => true,
                'title' => 'Tutorial Graduate',
                'message' => 'You\'ve earned the Tutorial Graduate achievement!'
            ];
        }
        
    } catch (PDOException $e) {
        $response = [
            'success' => false,
            'message' => 'Database error'
        ];
        error_log("Tutorial completion error: " . $e->getMessage());
    }
} else {
    $response = [
        'success' => true,
        'message' => 'Tutorial completion recorded (anonymous)'
    ];
}

// Send JSON response
echo json_encode($response);
