<?php
/**
 * Daily Challenge Bonus Game AJAX Handler
 * Processes AJAX requests for the bonus game
 */

// Bootstrap the application
require_once __DIR__ . '/../bootstrap.php';

// Include the required files
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf_functions.php';
require_once __DIR__ . '/../includes/daily_challenge_functions.php';

// Verify that the user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to play the bonus game.'
    ]);
    exit;
}

// Get current user
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Determine which action to take
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($action === 'submit_bonus_answer') {
    handle_submit_bonus_answer();
} else {
    // Invalid action
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid action'
    ]);
    exit;
}

/**
 * Handle bonus game answer submission
 */
function handle_submit_bonus_answer() {
    global $username, $user_id, $is_admin;
    
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token'
        ]);
        exit;
    }
    
    // Make sure we have session data for the bonus game
    if (!isset($_SESSION['bonus_game_images']) || !isset($_SESSION['bonus_game_correct_index'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Bonus game data not found. Please restart the game.'
        ]);
        exit;
    }
    
    // Get the answer
    $selected_index = isset($_POST['selected_index']) ? (int)$_POST['selected_index'] : -1;
    
    if ($selected_index < 0 || $selected_index >= count($_SESSION['bonus_game_images'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid selection'
        ]);
        exit;
    }
    
    // Get correct index from session
    $correct_index = $_SESSION['bonus_game_correct_index'];
    
    // Determine if the answer is correct
    $correct = ($selected_index === $correct_index);
    
    $response = [
        'success' => true,
        'correct' => $correct,
        'correct_index' => $correct_index,
        'selected_index' => $selected_index
    ];
    
    if ($correct) {
        // Award a random avatar if they got it right
        $avatar = d_award_random_avatar($username);
        
        if ($avatar) {
            $response['avatar'] = $avatar;
            $response['message'] = 'Correct! You found the real image and earned a new avatar: ' . $avatar;
        } else {
            $response['message'] = 'Correct! You found the real image, but no new avatars are available.';
        }
    } else {
        // Reset streak if they got it wrong
        d_reset_streak($username);
        
        // Get the new streak value
        $response['new_streak'] = 0;
        $response['message'] = 'Incorrect! The real image was Image ' . chr(65 + $correct_index) . '. Your daily challenge streak has been reset to 0.';
    }
    
    // Record all these images as seen
    $conn = get_db_connection();
    $real_image_ids = [];
    $ai_image_ids = [];
    
    foreach ($_SESSION['bonus_game_images'] as $image) {
        if ($image['type'] === 'real') {
            $real_image_ids[] = $image['id'];
        } else {
            $ai_image_ids[] = $image['id'];
        }
    }
    
    d_record_images_as_seen($conn, $username, $real_image_ids, $ai_image_ids);
    
    // Clear bonus game session data
    unset($_SESSION['bonus_game_images']);
    unset($_SESSION['bonus_game_correct_index']);
    
    // Return the response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}