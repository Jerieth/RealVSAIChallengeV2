<?php
/**
 * Daily Challenge AJAX Handler
 * 
 * Processes AJAX requests for the Daily Challenge game mode
 * Supports both regular round answers and final round answers
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/daily_challenge_functions.php';
require_once __DIR__ . '/../includes/csrf_functions.php';
require_once __DIR__ . '/../includes/achievement_functions.php';

// Ensure user is logged in
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to play the Daily Challenge.'
    ]);
    exit;
}

// Initialize response
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Get the current user
$current_user = get_current_logged_in_user();
$username = $current_user['username'];
$user_id = $current_user['id'];
$is_admin = isset($current_user['is_admin']) && $current_user['is_admin'] == 1;

// Verify CSRF token if it's present
if (isset($_POST['csrf_token'])) {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid security token. Please refresh the page.'
        ]);
        exit;
    }
}

// Check if action is set
if (!isset($_POST['action'])) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Get the action
$action = $_POST['action'];

// Handle different actions
switch ($action) {
    case 'submit_daily_answer':
        handle_submit_daily_answer();
        break;
        
    case 'submit_daily_final_answer':
        handle_submit_daily_final_answer();
        break;
        
    default:
        // Unknown action
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Unknown action'
        ]);
        exit;
}

/**
 * Handle regular round answer submission
 */
function handle_submit_daily_answer() {
    global $username, $user_id, $is_admin;
    
    // Get the database connection
    $conn = get_db_connection();
    
    try {
        // Add detailed error tracking
        error_log("Daily Challenge Answer Handler - Starting");
        error_log("POST data: " . json_encode($_POST));
        error_log("SESSION data: " . json_encode($_SESSION));
        
        // Get current session data
        if (!isset($_SESSION['game_round']) || !isset($_SESSION['current_image_index'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Game session not found. Please restart the game.'
            ]);
            exit;
        }
        
        // Get current image info
        $current_index = $_SESSION['current_image_index'];
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        
        if ($image_id <= 0) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid image ID'
            ]);
            exit;
        }
        
        // Get the image from database
        $image_data = d_get_image_by_id($image_id);
        error_log("Image data for ID $image_id: " . json_encode($image_data));
        
        if (!$image_data) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Image not found'
            ]);
            exit;
        }
        
        // Get the answer
        $answer = isset($_POST['answer']) ? $_POST['answer'] : '';
        error_log("User answer: $answer");
        
        if (!in_array($answer, ['real', 'ai'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid answer'
            ]);
            exit;
        }
        
        // Determine if the answer is correct
        $correct = ($answer === $image_data['type']);
        error_log("Answer correct: " . ($correct ? "Yes" : "No") . " (Image is {$image_data['type']})");
        
        // Update the session data
        if ($correct) {
            $_SESSION['score'] += 10;
            $_SESSION['streak'] = isset($_SESSION['streak']) ? $_SESSION['streak'] + 1 : 1;
            
            $response = [
                'success' => true,
                'correct' => true,
                'message' => 'Correct! This is a ' . ucfirst($image_data['type']) . ' image.',
                'score' => $_SESSION['score'],
                'streak' => $_SESSION['streak']
            ];
        } else {
            $_SESSION['lives']--;
            $_SESSION['streak'] = 0;
            
            $response = [
                'success' => true,
                'correct' => false,
                'message' => 'Incorrect! This is a ' . ucfirst($image_data['type']) . ' image.',
                'lives' => $_SESSION['lives']
            ];
        }
        
        // Record this image as seen
        error_log("Recording image $image_id as viewed by $username");
        d_record_daily_challenge_image_view($username, $image_id);
        
        // Increment the round and image index
        $_SESSION['game_round']++;
        $_SESSION['current_image_index']++;
        error_log("Updated game round: {$_SESSION['game_round']}, image index: {$_SESSION['current_image_index']}");
        
        // Check if we've run out of lives or reached the final round
        $game_over = false;
        if ($_SESSION['lives'] <= 0) {
            $_SESSION['game_over'] = true;
            $response['game_over'] = true;
            $response['message'] .= ' Game over - you ran out of lives!';
            $game_over = true;
            
            // Update daily challenge record
            error_log("Game over - user ran out of lives");
            d_update_daily_challenge_record($username, false, $is_admin);
        } 
        // Check if we're now at the final round
        else if ($_SESSION['game_round'] > $_SESSION['total_rounds']) {
            $_SESSION['game_over'] = true;
            $response['game_over'] = true;
            $response['message'] .= ' You completed all rounds!';
            $game_over = true;
            
            // Update daily challenge record for completion
            error_log("Game over - user completed all rounds");
            d_update_daily_challenge_record($username, true, $is_admin);
        } else {
            $response['game_over'] = false;
        }
        
        // Save the current game state to the database
        if (isset($_SESSION['game_images'])) {
            error_log("Saving game progress with game_images: " . json_encode($_SESSION['game_images']));
            $images_seen = implode(',', array_slice($_SESSION['game_images'], 0, $_SESSION['current_image_index']));
        } else {
            $images_seen = '';
            error_log("Warning: No game_images in session when saving progress");
        }
        
        error_log("Saving daily challenge progress: round={$_SESSION['game_round']}, lives={$_SESSION['lives']}, score={$_SESSION['score']}, game_over=$game_over");
        
        d_save_daily_challenge_progress(
            $username, 
            $_SESSION['game_round'], 
            $_SESSION['lives'], 
            $_SESSION['score'],
            $game_over,
            $images_seen
        );
        
        // Return the response
        error_log("Sending response: " . json_encode($response));
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    
    } catch (Exception $e) {
        error_log("Daily Challenge Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        // Return error response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred during game processing. Please try again.'
        ]);
        exit;
    }
}

/**
 * Handle final round answer submission
 */
function handle_submit_daily_final_answer() {
    global $username, $user_id, $is_admin;
    
    // Get the database connection
    $conn = get_db_connection();
    
    try {
        // Add detailed error tracking
        error_log("Daily Challenge Final Answer Handler - Starting");
        error_log("POST data: " . json_encode($_POST));
        error_log("SESSION data: " . json_encode($_SESSION));
        
        // Check if this is a final round
        if (!isset($_SESSION['final_round_real_image']) || !isset($_SESSION['final_round_ai_image']) || !isset($_SESSION['final_round_left_is_real'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Final round data not found. Please restart the game.'
            ]);
            exit;
        }
        
        // Get the answer
        $answer = isset($_POST['answer']) ? $_POST['answer'] : '';
        error_log("User final answer: $answer");
        
        if (!in_array($answer, ['left_real', 'right_real'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid answer'
            ]);
            exit;
        }
        
        // Determine if the answer is correct
        $left_is_real = $_SESSION['final_round_left_is_real'];
        $correct = ($answer === 'left_real' && $left_is_real) || ($answer === 'right_real' && !$left_is_real);
        error_log("Final answer correct: " . ($correct ? "Yes" : "No") . " (Left is real: " . ($left_is_real ? "Yes" : "No") . ")");
        
        // Update the session data
        if ($correct) {
            $_SESSION['score'] += 20; // Final round is worth more points
            
            $response = [
                'success' => true,
                'correct' => true,
                'message' => 'Correct! You identified the real image!',
                'score' => $_SESSION['score']
            ];
        } else {
            $_SESSION['lives']--;
            
            $response = [
                'success' => true,
                'correct' => false,
                'message' => 'Incorrect! You misidentified the real image.',
                'lives' => $_SESSION['lives']
            ];
        }
        
        // Game is over after final round - mark it as completed
        $_SESSION['game_over'] = true;
        $response['game_over'] = true;
        
        // Update daily challenge record
        $game_completed = $_SESSION['lives'] > 0;
        error_log("Final round completed, game status: " . ($game_completed ? "Completed successfully" : "Failed"));
        d_update_daily_challenge_record($username, $game_completed, $is_admin);
        
        // Save the final state to the database
        $images_seen = '';
        if (isset($_SESSION['game_images'])) {
            $images_array = $_SESSION['game_images'];
            if (isset($_SESSION['final_round_real_image']) && isset($_SESSION['final_round_ai_image'])) {
                $images_array[] = $_SESSION['final_round_real_image'];
                $images_array[] = $_SESSION['final_round_ai_image'];
            }
            $images_seen = implode(',', $images_array);
            error_log("Final images seen: " . $images_seen);
        } else {
            error_log("Warning: No game_images in session for final round");
        }
        
        $final_round = isset($_SESSION['total_rounds']) ? $_SESSION['total_rounds'] + 1 : 11;
        error_log("Saving final challenge progress: round={$final_round}, lives={$_SESSION['lives']}, score={$_SESSION['score']}, game_over=true");
        
        d_save_daily_challenge_progress(
            $username, 
            $final_round, // Final round is after all normal rounds
            $_SESSION['lives'], 
            $_SESSION['score'],
            true, // Game is definitely over after final round
            $images_seen
        );
        
        // Return the response
        error_log("Sending final response: " . json_encode($response));
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    
    } catch (Exception $e) {
        error_log("Daily Challenge Final Round Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        // Return error response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'An error occurred during the final round. Please try again.'
        ]);
        exit;
    }
}