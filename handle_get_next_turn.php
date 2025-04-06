<?php
// Function to get a game image pair without refreshing the page

/**
 * Get a new game image pair for AJAX requests
 * 
 * @param string $difficulty The game difficulty
 * @param string $session_id The game session ID
 * @return array|bool Image pair data or false on failure
 */
function get_game_image_pair($difficulty, $session_id) {
    // Get game data to get shown images
    $game = get_game_state($session_id);
    if (!$game) {
        error_log("get_game_image_pair - Game not found for session ID: " . $session_id);
        return false;
    }

    // Check if we already have current images (resuming a game)
    $is_resuming_game = (!empty($game['current_real_image']) && !empty($game['current_ai_image']));

    if ($is_resuming_game) {
        error_log("get_game_image_pair - Resuming game with existing images for session: " . $session_id);
        error_log("get_game_image_pair - Current real image: " . $game['current_real_image']);
        error_log("get_game_image_pair - Current AI image: " . $game['current_ai_image']);
        error_log("get_game_image_pair - Left is real: " . ($game['left_is_real'] ? 'Yes' : 'No'));
        error_log("get_game_image_pair - Game mode: " . $game['game_mode']);

        // Use the existing images rather than getting new ones
        $real_image = $game['current_real_image'];
        $ai_image = $game['current_ai_image'];
        $left_is_real = $game['left_is_real'];

        // Only show the "Resuming previous game state" message for Endless Mode
        // For other modes, we'll still use the current images but won't show the message
        $is_resumed_turn = ($game['game_mode'] === 'endless');

        error_log("get_game_image_pair - Setting is_resumed_turn flag: " . ($is_resumed_turn ? 'Yes' : 'No'));

        // Return image pair data without updating shown images
        return [
            'left_image_path' => $left_is_real ? $real_image : $ai_image,
            'right_image_path' => $left_is_real ? $ai_image : $real_image,
            'left_is_real' => $left_is_real ? 1 : 0, // Ensure boolean is converted to integer
            'is_resumed_turn' => $is_resumed_turn // Flag to indicate if this is a resumed turn that should show the message
        ];
    } else {
        error_log("get_game_image_pair - Getting new images for session: " . $session_id);
    }

    // Get shown images
    $shown_images = !empty($game['shown_images']) ? explode(',', $game['shown_images']) : array();

    // Get random image pair
    list($real_image, $ai_image) = get_random_image_pair($shown_images, $difficulty);

    // If we ran out of images
    if (!$real_image || !$ai_image) {
        error_log("get_game_image_pair - No more unique images available");
        return false;
    }

    // Randomly determine the order (left or right)
    $left_is_real = random_int(0, 1) == 1;

    // Store the current image pair and order in the database
    update_current_images($session_id, $real_image, $ai_image, $left_is_real);

    // Set time penalty flag to false for first view of this image pair
    update_time_penalty_flag($session_id, false);

    // Update shown images in database
    update_shown_images($session_id, $real_image, $ai_image);

    // Return image pair data
    return [
        'left_image_path' => $left_is_real ? $real_image : $ai_image,
        'right_image_path' => $left_is_real ? $ai_image : $real_image,
        'left_is_real' => $left_is_real ? 1 : 0, // Ensure boolean is converted to integer
        'is_resumed_turn' => false // New turn, not resumed
    ];
}

// Function to handle getting the next turn without refreshing
function handle_get_next_turn() {
    global $response;

    try {
        error_log("handle_get_next_turn - STARTING FUNCTION");
        error_log("handle_get_next_turn - SESSION data: " . print_r($_SESSION, true));

        // Get the game mode from request parameters if available
        // Check both GET and POST parameters since AJAX calls use POST
        $game_mode = isset($_POST['mode']) ? $_POST['mode'] : (isset($_GET['mode']) ? $_GET['mode'] : null);
        error_log("handle_get_next_turn - Game mode from request: " . ($game_mode ? $game_mode : "not provided"));
        $session_id = null;

        // Check for mode-specific session ID first
        if ($game_mode && isset($_SESSION['game_session_id_' . $game_mode])) {
            $session_id = $_SESSION['game_session_id_' . $game_mode];
            error_log("handle_get_next_turn - Using mode-specific session ID for $game_mode: $session_id");

            // Sync to legacy variable for compatibility
            if (!isset($_SESSION['game_session_id']) || $_SESSION['game_session_id'] !== $session_id) {
                $_SESSION['game_session_id'] = $session_id;
                error_log("handle_get_next_turn - Syncing mode-specific session ID to legacy variable");
            }
        }
        // Fall back to legacy session ID if needed
        else if (isset($_SESSION['game_session_id'])) {
            $session_id = $_SESSION['game_session_id'];
            error_log("handle_get_next_turn - Using legacy session ID: $session_id");

            // Sync to mode-specific variable if mode is known
            if ($game_mode && (!isset($_SESSION['game_session_id_' . $game_mode]) || $_SESSION['game_session_id_' . $game_mode] !== $session_id)) {
                $_SESSION['game_session_id_' . $game_mode] = $session_id;
                error_log("handle_get_next_turn - Syncing legacy session ID to mode-specific variable for $game_mode");
            }
        }

        // Check if we have a valid session ID
        if (!$session_id) {
            error_log("handle_get_next_turn - No game session ID found in session");
            $response['message'] = 'No active game session';
            echo json_encode($response);
            exit;
        }

        error_log("handle_get_next_turn - Processing with session ID: " . $session_id);

        // Get game data
        $game = get_game_state($session_id);

        // Check if game exists
        if (!$game) {
            error_log("handle_get_next_turn - Game session not found for ID: " . $session_id);
            $response['message'] = 'Game session not found';
            echo json_encode($response);
            exit;
        }

        // Check if game is completed or player has no lives left
        if ((isset($game['completed']) && $game['completed'] == 1) || 
            (isset($game['lives']) && $game['lives'] <= 0)) {
            
            $reason = (isset($game['lives']) && $game['lives'] <= 0) ? 
                      'No lives remaining' : 
                      'Game is already completed';
                      
            error_log("handle_get_next_turn - Game is over: " . $reason);
            
            $response = [
                'success' => false,
                'message' => $reason,
                'completed' => true,
                'lives' => isset($game['lives']) ? $game['lives'] : 0
            ];
            echo json_encode($response);
            exit;
        }

        // ALWAYS increment the turn when get_next_turn is called
        error_log("handle_get_next_turn - ALWAYS incrementing turn for session: " . $session_id);
        
        try {
            $conn = get_db_connection();
            
            // Check if this is a multiplayer game
            $is_multiplayer = isset($game['game_mode']) && $game['game_mode'] === 'multiplayer';
            
            if ($is_multiplayer) {
                // Get the current turn value first
                $checkStmt = $conn->prepare("SELECT current_turn FROM multiplayer_games WHERE session_id = ?");
                $checkStmt->execute([$session_id]);
                $currentTurn = (int)$checkStmt->fetchColumn();
                error_log("handle_get_next_turn - Current turn before increment: " . $currentTurn);
                
                // Update the turn in the multiplayer_games table - using the actual value + 1 to avoid race conditions
                $stmt = $conn->prepare("UPDATE multiplayer_games SET current_turn = ? WHERE session_id = ?");
                $newTurn = $currentTurn + 1;
                $stmt->execute([$newTurn, $session_id]);
                error_log("handle_get_next_turn - Incrementing turn in multiplayer_games table from {$currentTurn} to {$newTurn} for session: " . $session_id);
            } else {
                // Update the turn in the games table for single-player games
                $stmt = $conn->prepare("UPDATE games SET current_turn = current_turn + 1 WHERE session_id = ?");
                $stmt->execute([$session_id]);
                error_log("handle_get_next_turn - Incrementing turn in games table for session: " . $session_id);
            }
            
            // Log how many rows were affected (should always be 1)
            $rowCount = $stmt->rowCount();
            error_log("handle_get_next_turn - Rows affected by turn increment: " . $rowCount);
            
            // Refresh game state after update to get the new turn count
            $game = get_game_state($session_id);
            error_log("handle_get_next_turn - Turn incremented to: " . $game['current_turn']);
            error_log("handle_get_next_turn - Current score is: " . $game['score']);
            error_log("handle_get_next_turn - Current streak is: " . (isset($game['current_streak']) ? $game['current_streak'] : 'not set'));
            error_log("handle_get_next_turn - FULL GAME STATE: " . print_r($game, true));
        } catch (PDOException $e) {
            error_log("handle_get_next_turn - DATABASE ERROR incrementing turn: " . $e->getMessage());
            // Continue execution anyway - we'll fix the turn value below if needed
        }


        // Get a new pair of images (1 real, 1 AI)
        error_log("handle_get_next_turn - Calling get_game_image_pair for session: " . $session_id);
        $image_pair = get_game_image_pair($game['difficulty'], $session_id);

        if (!$image_pair) {
            error_log("handle_get_next_turn - Failed to get image pair - Likely no more images available");
            $response = [
                'success' => false,
                'message' => 'Failed to get images',
                'no_more_images' => true  // Add this flag to indicate no more images are available
            ];
            // Use safe_json_output from game_actions.php instead of direct echo
            require_once 'game_actions.php';
            safe_json_output($response);
            exit;
        }

        error_log("handle_get_next_turn - Successfully got image pair for session: " . $session_id);

        // Get image paths with cache-busting time parameter
        $cache_buster = time();
        $left_image = 'static/images/game-image.php?id=' . urlencode(base64_encode($image_pair['left_image_path'])) . '&_t=' . $cache_buster;
        $right_image = 'static/images/game-image.php?id=' . urlencode(base64_encode($image_pair['right_image_path'])) . '&_t=' . $cache_buster;

        // Ensure we have a valid current_turn value
        if (!isset($game['current_turn']) || !is_numeric($game['current_turn'])) {
            $game['current_turn'] = 1; // Default to 1 if not set or not valid
            error_log("handle_get_next_turn - Fixed invalid turn value, set to: 1");
            // Save the game state with the corrected value
            update_game_state($session_id, $game);
        }

        // Prepare response with new image data
        $current_score = isset($game['score']) ? intval($game['score']) : 0;
        
        // Get current streak directly from the database for the most accurate value
        $game = get_game_state($session_id); // Refresh game state one more time
        $current_streak = isset($game['current_streak']) ? intval($game['current_streak']) : 0;
        
        error_log("handle_get_next_turn - Current score for response: " . $current_score);
        error_log("handle_get_next_turn - Current streak for response: " . $current_streak . " (type: " . gettype($current_streak) . ")");
        
        // Get descriptions for both real and AI images
        $real_image_path = $image_pair['left_is_real'] ? $image_pair['left_image_path'] : $image_pair['right_image_path'];
        $ai_image_path = $image_pair['left_is_real'] ? $image_pair['right_image_path'] : $image_pair['left_image_path'];
        
        $real_image_details = get_image_details_by_path($real_image_path);
        $ai_image_details = get_image_details_by_path($ai_image_path);
        
        // Log the details we found for debugging
        error_log("handle_get_next_turn - Real image details: " . print_r($real_image_details, true));
        error_log("handle_get_next_turn - AI image details: " . print_r($ai_image_details, true));
        
        // Get descriptions if available
        $real_image_description = ($real_image_details && isset($real_image_details['description'])) 
                               ? $real_image_details['description'] 
                               : "A real photograph.";
        
        // Check if this is a daily challenge and if it's the final turn
        $is_daily_challenge = (isset($game['game_mode']) && $game['game_mode'] === 'daily_challenge');
        $is_daily_final_round = $is_daily_challenge && isset($game['total_turns']) && $game['current_turn'] >= $game['total_turns'];
        
        $response = [
            'success' => true,
            'leftImage' => $left_image,
            'rightImage' => $right_image,
            'leftIsReal' => $image_pair['left_is_real'],
            'isResumedTurn' => isset($image_pair['is_resumed_turn']) ? $image_pair['is_resumed_turn'] : false,
            'turn' => $game['current_turn'],
            'totalTurns' => isset($game['total_turns']) ? $game['total_turns'] : 0,
            'score' => $current_score, // Include the current score in the response
            'current_streak' => isset($game['current_streak']) ? intval($game['current_streak']) : 0, // Include current streak counter
            'lives' => isset($game['lives']) ? intval($game['lives']) : null, // Include current lives count
            'is_final_turn' => isset($game['total_turns']) && $game['current_turn'] >= $game['total_turns'], // Flag for final turn
            'is_daily_final_round' => $is_daily_final_round, // Flag specifically for daily challenge final round
            'completed' => isset($game['completed']) ? (bool)$game['completed'] : false, // Game completion status
            'real_image_description' => $real_image_description // Include the description for the real image
        ];

        // Use safe_json_output from game_actions.php
        require_once 'game_actions.php';
        safe_json_output($response);

    } catch (Exception $e) {
        error_log("handle_get_next_turn - Error: " . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ];
        // Use safe_json_output from game_actions.php
        require_once 'game_actions.php';
        safe_json_output($response);
    }
    exit;
}