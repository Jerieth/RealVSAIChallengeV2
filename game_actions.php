<?php
/**
 * Game Actions - Handles AJAX requests for game functions
 */

require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/multiplayer_functions.php';
require_once 'includes/achievement_functions.php';
require_once 'includes/image_functions.php';
require_once 'handle_get_next_turn.php';

// Enable detailed error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure session is always started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug logging
error_log("game_actions.php - Session ID: " . session_id());
error_log("game_actions.php - POST data: " . print_r($_POST, true));
error_log("game_actions.php - SESSION data: " . print_r($_SESSION, true));

// Force response to be JSON
header('Content-Type: application/json');

// Make sure we catch any PHP errors before sending JSON response
ob_start();

// Log the raw request for debugging
error_log("game_actions.php - Raw input: " . file_get_contents('php://input'));

// Log HTTP headers
$headers = [];
foreach (getallheaders() as $name => $value) {
    $headers[$name] = $value;
}
error_log("game_actions.php - Request headers: " . print_r($headers, true));

// Initialize response array
$response = [
    'success' => false,
    'message' => 'Invalid request'
];

// Check if action is set
if (!isset($_POST['action'])) {
    error_log("game_actions.php - No action provided in request: " . print_r($_POST, true));
    safe_json_output($response);
}

// Get action
$action = $_POST['action'];

// Handle different actions
switch ($action) {
    case 'submit_answer':
        handle_submit_answer();
        // Each handler function has its own exit, so we never reach here
        break;
        
    case 'submit_multiplayer_answer':
        handle_submit_multiplayer_answer();
        break;
        
    case 'get_bonus_images':
        handle_get_bonus_images();
        break;
        
    case 'handle_bonus_result':
        handle_bonus_result();
        break;
        
    case 'get_multiplayer_bonus_images':
        handle_get_multiplayer_bonus_images();
        break;
        
    case 'handle_multiplayer_bonus_result':
        handle_multiplayer_bonus_result();
        break;
        
    case 'start_multiplayer_bonus_game':
        handle_start_multiplayer_bonus_game();
        break;
        
    case 'multiplayer_chest_selection':
        handle_multiplayer_chest_selection();
        break;
        
    case 'get_next_turn':
        handle_get_next_turn();
        break;
        
    default:
        // Unknown action
        $response['message'] = 'Unknown action';
        safe_json_output($response);
}

// This should never be reached since all handlers call exit
// Adding this as a safety to ensure we don't output multiple responses
exit;

/**
 * Safe JSON Output - ensures no PHP errors get mixed into JSON output
 * @param array $response The response array to encode as JSON
 */
function safe_json_output($response) {
    // Check if an output buffer exists before trying to manipulate it
    if (ob_get_level() > 0) {
        // If there was any output (like PHP errors), capture and log it
        $output = ob_get_clean();
        if (!empty($output)) {
            error_log("WARNING: Captured output before JSON response: " . $output);
        }
    }
    
    // Ensure output buffering is off for clean JSON output
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Ensure we have a proper response object if one wasn't provided
    if (!is_array($response)) {
        error_log("WARNING: Invalid response type passed to safe_json_output: " . gettype($response));
        $response = array('success' => false, 'message' => 'Internal server error');
    }
    
    // Set headers only if they haven't been sent yet
    if (!headers_sent()) {
        header('Content-Type: application/json');
    } else {
        error_log("WARNING: Headers already sent before JSON output. Output may be corrupted.");
    }
    
    // Encode with error handling
    $json = json_encode($response);
    if ($json === false) {
        error_log("ERROR: JSON encoding failed: " . json_last_error_msg());
        // Try to send a simplified response
        echo json_encode(array('success' => false, 'message' => 'JSON encoding error: ' . json_last_error_msg()));
    } else {
        echo $json;
    }
    
    // Always exit after outputting JSON to prevent further execution
    exit;
}

// Submit answer for single player or endless mode
function handle_submit_answer() {
    global $response;
    
    try {
        error_log("handle_submit_answer - STARTING FUNCTION");
        error_log("handle_submit_answer - SESSION data: " . print_r($_SESSION, true));
        error_log("handle_submit_answer - POST data: " . print_r($_POST, true));
        
        // Check if this is a daily challenge submission
        $is_daily_challenge = isset($_POST['daily']) && $_POST['daily'] == 1;
        error_log("handle_submit_answer - Is Daily Challenge: " . ($is_daily_challenge ? "Yes" : "No"));
        
        // ANTI-CHEAT: Check for encrypted data first
        if (isset($_POST['secure_data']) && !empty($_POST['secure_data'])) {
            error_log("handle_submit_answer - Found encrypted data, attempting to decrypt");
            $decrypted = decrypt_game_data($_POST['secure_data']);
            
            if ($decrypted && is_array($decrypted)) {
                // Merge decrypted data with POST, but don't override existing values
                foreach ($decrypted as $key => $value) {
                    if (!isset($_POST[$key])) {
                        $_POST[$key] = $value;
                        error_log("handle_submit_answer - Added decrypted data: $key");
                    }
                }
            } else {
                error_log("handle_submit_answer - Failed to decrypt secure data");
            }
        }
        
        // Get the game mode from POST data if available
        $game_mode = isset($_POST['mode']) ? $_POST['mode'] : null;
        error_log("handle_submit_answer - Game mode from POST: " . ($game_mode ?: "not specified"));
        
        // Check for session ID using multiple strategies
        $session_id = null;
        
        // 1. Check POST data first (for AJAX calls)
        if (isset($_POST['session_id'])) {
            $session_id = $_POST['session_id'];
            error_log("handle_submit_answer - Found session ID in POST data: $session_id");
        }
        // 2. Check mode-specific session variable if mode is known
        else if ($game_mode && isset($_SESSION['game_session_id_' . $game_mode])) {
            $session_id = $_SESSION['game_session_id_' . $game_mode];
            error_log("handle_submit_answer - Using mode-specific session ID for $game_mode: $session_id");
        }
        // 3. Fall back to legacy session ID
        else if (isset($_SESSION['game_session_id'])) {
            $session_id = $_SESSION['game_session_id'];
            error_log("handle_submit_answer - Using legacy session ID: $session_id");
        }
        
        // Verify we have a valid session ID
        if (!$session_id) {
            error_log("handle_submit_answer - No game session ID found in session or POST data");
            $response['message'] = 'No active game session';
            safe_json_output($response);
        }
        
        // Store session ID in mode-specific variables if game mode is known
        if ($game_mode && !isset($_SESSION['game_session_id_' . $game_mode])) {
            $_SESSION['game_session_id_' . $game_mode] = $session_id;
            error_log("handle_submit_answer - Set mode-specific session ID for $game_mode: $session_id");
        }
        
        // Also update legacy session ID for backward compatibility
        if (!isset($_SESSION['game_session_id'])) {
            $_SESSION['game_session_id'] = $session_id;
            error_log("handle_submit_answer - Set legacy session ID: $session_id");
        }
        
        error_log("handle_submit_answer - Processing with session ID: " . $session_id);
        
        // Get database connection to check for errors
        $conn = get_db_connection();
        if (!$conn) {
            error_log("handle_submit_answer - Failed to get database connection");
            $response['message'] = 'Database connection error';
            safe_json_output($response);
        }
        error_log("handle_submit_answer - Database connection successful");
        
        // Get game data with error handling
        error_log("handle_submit_answer - Fetching game state for session ID: " . $session_id);
        $game = get_game_state($session_id);
        error_log("handle_submit_answer - Game state result: " . ($game ? "Found" : "Not found"));
        
        // Check if game exists
        if (!$game) {
            error_log("handle_submit_answer - Game session not found for ID: " . $session_id);
            $response['message'] = 'Game session not found';
            safe_json_output($response);
        }
        
        // Check if selection was made
        if (!isset($_POST['selected'])) {
            error_log("handle_submit_answer - No selection made in POST data");
            $response['message'] = 'No selection made';
            safe_json_output($response);
        }
        
        // Get selection
        $selected = $_POST['selected'];
        
        // Get response time if available
        $response_time = isset($_POST['response_time']) ? intval($_POST['response_time']) : 0;
        
        // Check if correct
        $correct = ($selected === 'real');
        
        // Check if this answer has already been processed
        // Use session variables to store the last processed image pair and answer
        $lastProcessedImagePair = isset($_SESSION['last_processed_image_pair']) ? $_SESSION['last_processed_image_pair'] : '';
        $lastProcessedAnswer = isset($_SESSION['last_processed_answer']) ? $_SESSION['last_processed_answer'] : '';
        $currentImagePair = $game['current_real_image'] . ',' . $game['current_ai_image'];
        
        $duplicateSubmission = false;
        if ($lastProcessedImagePair === $currentImagePair && $lastProcessedAnswer === $selected) {
            error_log("handle_submit_answer - Detected duplicate answer submission for the same image pair");
            $duplicateSubmission = true;
        }
        
        // Only update score and lives if this is not a duplicate submission
        if (!$duplicateSubmission) {
            error_log("handle_submit_answer - Processing new answer submission");
            
            // Update game state
            if ($correct) {
                // Base score increment
                $score_increment = 10;
                
                // Check if time penalty flag is set (from page refresh)
                $time_penalty = isset($game['time_penalty']) && $game['time_penalty'] == 1;
                if ($time_penalty) {
                    error_log("handle_submit_answer - Time penalty in effect, no time bonus will be awarded");
                }
                
                // Calculate time bonus if applicable - only for hard difficulty, multiplayer, and endless modes
                // AND only if there's no time penalty from refresh
                $time_bonus = 0;
                if (!$time_penalty && $response_time > 0 && ($game['difficulty'] === 'hard' || $game['game_mode'] === 'multiplayer' || $game['game_mode'] === 'endless')) {
                    // Time bonus formula:
                    // - Maximum +20 points for answers under 1 second
                    // - Gradually decreases to +0 for answers over 30 seconds
                    if ($response_time < 1000) {
                        $time_bonus = 20;
                    } else if ($response_time < 30000) {
                        $time_bonus = max(0, 20 - floor(($response_time - 1000) / 1500));
                    }
                    
                    error_log("handle_submit_answer - Awarding time bonus of $time_bonus points");
                }
                
                // Increment streak counter with detailed logging
                error_log("handle_submit_answer - Before increment, streak value is: " . (isset($game['current_streak']) ? $game['current_streak'] : 'not set'));
                $new_streak_value = isset($game['current_streak']) ? intval($game['current_streak']) + 1 : 1;
                $game['current_streak'] = $new_streak_value;
                
                // Directly update the streak in the database for extra reliability
                update_streak($session_id, $new_streak_value);
                
                error_log("handle_submit_answer - After increment, streak value is: " . $game['current_streak']);
                error_log("handle_submit_answer - Game state after increment: " . print_r($game, true));
                
                // Calculate streak bonus - rewritten to be more reliable
                $streak_bonus = 0;
                
                // Get the game mode from the game state
                $game_mode = isset($game['game_mode']) ? $game['game_mode'] : 'single';
                
                // Only calculate streak bonus when streak is a multiple of 5 AND only in Single Player mode
                if ($game_mode === 'single' && $game['current_streak'] > 0 && $game['current_streak'] % 5 === 0) {
                    // Calculate which 5-streak milestone we're on (1 for 5, 2 for 10, etc.)
                    $streak_milestone = (int)($game['current_streak'] / 5);
                    
                    // First 5-streak (streak_milestone=1) gives 10 points
                    // Second 5-streak (streak_milestone=2) gives 20 points
                    // Third 5-streak (streak_milestone=3) gives 40 points
                    // and so on... doubling each time (10 * 2^(milestone-1))
                    $streak_bonus = 10 * pow(2, $streak_milestone - 1);
                    
                    error_log("handle_submit_answer - STREAK BONUS: Player has {$game['current_streak']} streak, milestone $streak_milestone, awarding $streak_bonus bonus points in Single Player mode");
                } else {
                    error_log("handle_submit_answer - No streak bonus for current streak: {$game['current_streak']}");
                }
                
                // Log current score before adding
                error_log("handle_submit_answer - Current score before adding: {$game['score']}");
                
                // Add score, time bonus and streak bonus
                $game['score'] += $score_increment + $time_bonus + $streak_bonus;
                
                // Log total score after increment
                error_log("handle_submit_answer - New score after adding: {$game['score']} (+$score_increment base, +$time_bonus time bonus, +$streak_bonus streak bonus)");
                
                // Save all bonus details in response for display and debugging
                $response['streak_bonus'] = $streak_bonus;
                $response['time_bonus'] = $time_bonus;
                // Make sure the current_streak value is stored as an integer
                $current_streak = isset($game['current_streak']) ? intval($game['current_streak']) : 0;
                $response['current_streak'] = $current_streak;
                
                // Debug the exact value being sent to the client 
                error_log("handle_submit_answer - Sending current_streak to client: " . $current_streak . " (type: " . gettype($current_streak) . ")");
                
                // Log the complete response data for debugging
                error_log("handle_submit_answer - Complete response data: " . print_r($response, true));
            } else {
                error_log("handle_submit_answer - LIVES DEBUG: Before decrement, lives value is: " . $game['lives']);
                $game['lives']--;
                error_log("handle_submit_answer - LIVES DEBUG: After decrement, lives value is: " . $game['lives']);
                
                // Get the description for the real image when user answers incorrectly
                // This way we only display descriptions for real photos, not AI images
                // Since we only show descriptions when the answer is wrong, we need the real image details
                $real_image_path = $game['current_real_image'];
                $image_details = null;
                
                if (!empty($real_image_path)) {
                    error_log("===== DESCRIPTION DEBUG ===== Getting description for real image: " . $real_image_path);
                    
                    // Get the actual database connection to check the image record directly
                    $db = get_db_connection();
                    if ($db) {
                        // Extract just the filename without any path
                        $filename = basename($real_image_path);
                        error_log("===== DESCRIPTION DEBUG ===== Extracted filename: " . $filename);
                        
                        // Direct query to fetch the image record
                        $stmt = $db->prepare("SELECT * FROM images WHERE filename = :filename");
                        $stmt->execute(['filename' => $filename]);
                        $direct_image = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($direct_image) {
                            error_log("===== DESCRIPTION DEBUG ===== Direct DB query found image with ID: " . $direct_image['id']);
                            error_log("===== DESCRIPTION DEBUG ===== Image type: " . $direct_image['type']);
                            error_log("===== DESCRIPTION DEBUG ===== Image description: " . ($direct_image['description'] ?? 'NULL'));
                        } else {
                            error_log("===== DESCRIPTION DEBUG ===== Direct DB query found NO IMAGE with filename: " . $filename);
                            
                            // Try with numeric part only
                            $numericId = preg_replace('/[^0-9]/', '', $filename);
                            if (!empty($numericId)) {
                                $stmt = $db->prepare("SELECT * FROM images WHERE filename LIKE :pattern");
                                $stmt->execute(['pattern' => $numericId . '.%']);
                                $numeric_image = $stmt->fetch(PDO::FETCH_ASSOC);
                                
                                if ($numeric_image) {
                                    error_log("===== DESCRIPTION DEBUG ===== Found by numeric part: " . $numericId);
                                    error_log("===== DESCRIPTION DEBUG ===== Matching filename: " . $numeric_image['filename']);
                                    error_log("===== DESCRIPTION DEBUG ===== Image description: " . ($numeric_image['description'] ?? 'NULL'));
                                } else {
                                    error_log("===== DESCRIPTION DEBUG ===== No match even with numeric part: " . $numericId);
                                }
                            }
                        }
                        
                        // Show table structure
                        $result = $db->query("PRAGMA table_info(images)");
                        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
                        error_log("===== DESCRIPTION DEBUG ===== Table columns: " . print_r($columns, true));
                        
                        // Show sample records
                        $stmt = $db->query("SELECT * FROM images WHERE description IS NOT NULL AND description != '' LIMIT 3");
                        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        error_log("===== DESCRIPTION DEBUG ===== Sample records with description: " . print_r($samples, true));
                    }
                    
                    // Now use our function to get image details
                    $image_details = get_image_details_by_path($real_image_path);
                    
                    // Log full image details for debugging
                    error_log("===== DESCRIPTION DEBUG ===== Function returned details: " . print_r($image_details, true));
                    
                    if ($image_details && isset($image_details['description']) && !empty($image_details['description'])) {
                        error_log("===== DESCRIPTION DEBUG ===== FOUND DESCRIPTION: " . $image_details['description']);
                    } else {
                        error_log("===== DESCRIPTION DEBUG ===== NO DESCRIPTION FOUND");
                    }
                }
                
                // THIS IS THE KEY PART - we need to guarantee an image description is added to the response
                // The description must be added directly to a local variable first until we create the final response array
                error_log("===== CRITICAL DESCRIPTION SECTION =====");
                
                // Create a variable to hold our image description
                $image_description = null;
                
                // First check if we have an actual description from the database
                if ($image_details && isset($image_details['description']) && !empty($image_details['description'])) {
                    error_log("===== FOUND VALID DESCRIPTION =====: " . $image_details['description']);
                    // Set our local variable
                    $image_description = $image_details['description'];
                    error_log("===== STORED DESCRIPTION IN LOCAL VARIABLE =====");
                } 
                // If not, provide a helpful default description for players
                else {
                    error_log("===== NO DESCRIPTION FOUND, USING DEFAULT =====");
                    
                    // Determine if this was a real or AI image
                    $is_real = false;
                    if ($image_details) {
                        if (isset($image_details['is_real'])) {
                            // Use the is_real flag from database if available
                            $is_real = $image_details['is_real'] === 't' || $image_details['is_real'] === true || $image_details['is_real'] === 1;
                            error_log("===== Using is_real column: " . ($is_real ? 'true' : 'false'));
                        } else if (isset($image_details['type'])) {
                            // Use the type column if available
                            $is_real = $image_details['type'] === 'real';
                            error_log("===== Using type column: " . ($is_real ? 'true' : 'false'));
                        } else {
                            error_log("===== No type or is_real column found in image details");
                        }
                    } else {
                        // If we don't even have image details, assume it's a real image (since that's what we're supposedly describing)
                        $is_real = true;
                        error_log("===== No image details at all, assuming real image");
                    }
                    
                    // Directly create a default description for the real image in our local variable
                    $image_description = "This is a real photograph. The full description wasn't available.";
                    if (!empty($real_image_path)) {
                        $image_description .= " (Image: " . $real_image_path . ")";  
                    }
                    
                    error_log("===== STORED DEFAULT DESCRIPTION IN LOCAL VARIABLE: " . $image_description);
                }
                
                // Double check that we've actually set a description
                if (empty($image_description)) {
                    error_log("===== CRITICAL ERROR - NO DESCRIPTION SET AFTER PROCESSING =====");
                    $image_description = "This is a real photograph. Description system failure.";
                    error_log("===== EMERGENCY FALLBACK DESCRIPTION ADDED TO LOCAL VARIABLE =====");
                }
                
                // Reset streak on wrong answer - set to 0 in both memory and database
                $game['current_streak'] = 0;
                // Directly update the streak in the database for extra reliability
                update_streak($session_id, 0);
                error_log("handle_submit_answer - Wrong answer, reset streak to 0");
            }
            
            // Use session variables instead of database fields for duplicate submission detection
            $_SESSION['last_processed_image_pair'] = $currentImagePair;
            $_SESSION['last_processed_answer'] = $selected;
            error_log("handle_submit_answer - Stored last processed data in session instead of DB");
        } else {
            error_log("handle_submit_answer - Skipped processing duplicate submission");
        }
        
        // We'll no longer increment turn counter here - the client will handle it
        // when the user clicks the "Next" button. Just make sure we have a valid current_turn value.
        
        // Log information about the current images for debugging
        if (!empty($game['current_real_image']) && !empty($game['current_ai_image'])) {
            error_log("handle_submit_answer - User is submitting answer for existing images: Real: {$game['current_real_image']}, AI: {$game['current_ai_image']}");
        } else {
            error_log("handle_submit_answer - WARNING: No current images found, should not happen in normal flow");
            // Check if this is an endless game - we log this but will continue as normal
            if ($game['game_mode'] === 'endless') {
                error_log("handle_submit_answer - This is an endless mode game with session ID: {$session_id}");
            }
        }
        
        // Make sure current_turn is valid (but don't increment it)
        if (!isset($game['current_turn']) || !is_numeric($game['current_turn'])) {
            $game['current_turn'] = 1; // Default to 1 if not set or not valid
            error_log("handle_submit_answer - Fixed invalid turn value, set to: 1");
        } else {
            error_log("handle_submit_answer - Current turn is: " . $game['current_turn']);
        }
        
        // Check if game is over (no more lives or reached max turns)
        $game_over = ($game['lives'] <= 0 || ($game['total_turns'] > 0 && $game['current_turn'] > $game['total_turns']));
        
        if ($game_over) {
            $game['completed'] = 1;
            
            // Add more detailed logging for game over condition
            if ($game['lives'] <= 0) {
                error_log("handle_submit_answer - GAME OVER: Player has run out of lives ({$game['lives']} lives remaining)");
            } else {
                error_log("handle_submit_answer - GAME OVER: Player has reached maximum turns ({$game['current_turn']} of {$game['total_turns']})");
            }
            
            // If this is a daily challenge, update the user's daily challenge record
            if ($is_daily_challenge && isset($_SESSION['user_id'])) {
                error_log("handle_submit_answer - Daily Challenge completed by user: " . $_SESSION['user_id']);
                $username = $_SESSION['username'];
                
                // Get database connection
                $conn = get_db_connection();
                if ($conn) {
                    try {
                        // Check if user has a daily challenge record
                        $stmt = $conn->prepare("SELECT * FROM daily_challenge WHERE username = :username");
                        $stmt->execute(['username' => $username]);
                        $daily_record = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $current_date = date('Y-m-d');
                        $tomorrow = date('Y-m-d', strtotime('+1 day'));
                        
                        // Check if user is admin for special handling
                        $is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
                        
                        // Use the daily challenge function to update the record, with admin flag
                        d_update_daily_challenge_record($username, true, $is_admin);
                        error_log("handle_submit_answer - Updated daily challenge record for user: $username (Admin: " . ($is_admin ? "Yes" : "No") . ") using the dedicated function");
                    } catch (PDOException $e) {
                        error_log("handle_submit_answer - Error updating daily challenge record: " . $e->getMessage());
                    }
                }
            }
        }
        
        // Debug lives count before saving
        error_log("handle_submit_answer - LIVES DEBUG: Final lives count before saving to DB: " . $game['lives']);
        
        // Save updated game state
        $update_result = update_game_state($session_id, $game);
        error_log("handle_submit_answer - LIVES DEBUG: Result of update_game_state: " . ($update_result ? "SUCCESS" : "FAILED"));
        
        // Double-check by reading the current game state from DB again
        $current_game = get_game_state($session_id);
        if ($current_game) {
            error_log("handle_submit_answer - LIVES DEBUG: After save, DB has lives = " . $current_game['lives']);
        } else {
            error_log("handle_submit_answer - LIVES DEBUG: Failed to read game state after save");
        }
        
        // Reset current images to prevent refresh cheating
        // This clears the stored image pair and time penalty flag after answer is submitted
        reset_current_images($session_id);
        error_log("handle_submit_answer - Reset current images for session: " . $session_id);
        
        // Ensure current_streak is a proper integer value before adding to response
        $current_streak = isset($game['current_streak']) ? intval($game['current_streak']) : 0;
        error_log("handle_submit_answer - Final current_streak value for response: " . $current_streak);
        
        // Prepare response
        $response = [
            'success' => true,
            'correct' => $correct,
            'score' => $game['score'],
            'lives' => $game['lives'],
            'turn' => $game['current_turn'],
            'totalTurns' => $game['total_turns'],
            'completed' => $game_over,
            'current_streak' => $current_streak, // Use the properly converted integer
            'streak_bonus' => isset($streak_bonus) ? $streak_bonus : 0,
            'time_bonus' => isset($time_bonus) ? $time_bonus : 0
        ];
        
        // CRITICAL FIX: Add image description to the response when answer is wrong
        if (!$correct && isset($image_description) && !empty($image_description)) {
            $response['image_description'] = $image_description;
            error_log("===== DESCRIPTION DEBUG ===== ADDED IMAGE DESCRIPTION TO RESPONSE: " . $image_description);
        } 
        // Emergency fallback - if we don't have the image_description variable but we need one
        else if (!$correct && (!isset($image_description) || empty($image_description))) {
            error_log("===== DESCRIPTION DEBUG ===== No image description variable found - this should not happen!");
            // Add a default description since we're missing one for some reason
            if (isset($game['current_real_image'])) {
                $response['image_description'] = "This is a real photograph taken at " . $game['current_real_image'];
                error_log("===== DESCRIPTION DEBUG ===== Added emergency fallback description: " . $response['image_description']);
            }
        }
        
        // ANTI-CHEAT: Generate secure hash for the score
        // This will be verified on the next request to prevent score tampering
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $hash_data = generate_score_hash($game['score'], $user_id);
        $response['score_hash'] = $hash_data['encoded'];
        $response['score_verification'] = $hash_data['timestamp'];
        
        // ANTI-CHEAT: Generate obfuscated JavaScript for score calculation
        // Make it harder for cheaters to figure out how scores are calculated
        $response['obfuscated_calc'] = get_obfuscated_score_logic(
            10, // basePoints
            isset($time_bonus) ? $time_bonus : 0, // timeBonus
            isset($streak_bonus) ? $streak_bonus : 0 // streakBonus
        );
        
        // If this is a daily challenge and game is over, add redirection URL 
        if ($is_daily_challenge && $game_over) {
            // Determine whether to redirect to victory or game over page
            if ($game['lives'] > 0) {
                // Successfully completed the daily challenge
                $response['redirect_url'] = '/daily-victory';
                error_log("handle_submit_answer - Daily Challenge redirecting to victory page");
            } else {
                // Failed the daily challenge (no more lives)
                $response['redirect_url'] = '/daily-game-over';
                error_log("handle_submit_answer - Daily Challenge redirecting to game over page");
            }
        }
        
        // Log the final response for debugging
        error_log("handle_submit_answer - Final response: " . print_r($response, true));
        
        safe_json_output($response);
    } catch (Exception $e) {
        error_log("handle_submit_answer - Error: " . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ];
        safe_json_output($response);
    }
}

// Submit answer for multiplayer mode
function handle_submit_multiplayer_answer() {
    global $response;
    
    // Check for mode-specific session ID first (multiplayer mode)
    if (isset($_SESSION['game_session_id_multiplayer'])) {
        $session_id = $_SESSION['game_session_id_multiplayer'];
        error_log("handle_submit_multiplayer_answer - Using mode-specific multiplayer session ID: $session_id");
    }
    // Fall back to legacy session ID if needed
    else if (isset($_SESSION['game_session_id'])) {
        $session_id = $_SESSION['game_session_id'];
        error_log("handle_submit_multiplayer_answer - Using legacy session ID: $session_id");
    }
    else {
        error_log("handle_submit_multiplayer_answer - No game session ID found in session");
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game data
    $game = get_multiplayer_game_state($session_id);
    
    // Check if game exists
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    // Check if selection was made
    if (!isset($_POST['selected'])) {
        $response['message'] = 'No selection made';
        safe_json_output($response);
    }
    
    // Get selection
    $selected = $_POST['selected'];
    
    // Check if correct
    $correct = ($selected === 'real');
    
    // Get current user
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Determine player number
    $player_num = null;
    for ($i = 1; $i <= 4; $i++) {
        $player_id_field = "player{$i}_id";
        if ($game[$player_id_field] == $user_id) {
            $player_num = $i;
            break;
        }
    }
    
    // Update score for this player
    if ($player_num) {
        $score_field = "player{$player_num}_score";
        $streak_field = "player{$player_num}_streak";
        
        if ($correct) {
            // Increment streak counter
            $game[$streak_field] = isset($game[$streak_field]) ? $game[$streak_field] + 1 : 1;
            
            // Calculate base score
            $score_increment = 1;
            
            // In multiplayer mode, we track streak for display purposes only but don't award bonus points
            // The game spec requires streak bonuses only in Single Player mode
            $streak_bonus = 0;
            
            // Still track streak for display purposes
            if ($game[$streak_field] > 0 && $game[$streak_field] % 5 === 0) {
                error_log("handle_submit_multiplayer_answer - Player $player_num has streak of {$game[$streak_field]} but NO streak bonus (only available in Single Player mode)");
                
                // Set streak in response for display but with zero bonus
                $response['streak_bonus'] = 0;
                $response['current_streak'] = $game[$streak_field];
            }
            
            // Increment score with bonus
            $game[$score_field] += $score_increment + $streak_bonus;
        } else {
            // Reset streak on wrong answer
            $game[$streak_field] = 0;
        }
    }
    
    // Check if all players have answered for this turn
    $current_turn = $game['current_turn'];
    $total_players = $game['player_count'];
    $turn_completed = true; // We'll assume this player is the last to answer
    
    // TODO: In a real implementation, you'd track individual player turns
    
    // If turn is complete, we DON'T increment the turn counter here
    // The turn will be incremented when "Get Next Turn" is clicked
    if ($turn_completed) {
        // Fix for turns jumping - ensure we have a valid current turn value
        if (!isset($game['current_turn']) || !is_numeric($game['current_turn'])) {
            $game['current_turn'] = 1; // Default to 1 if not set or not valid
            error_log("handle_submit_multiplayer_answer - Fixed invalid turn value, set to: 1");
        }
        // We don't increment the turn here since it will be incremented in handle_get_next_turn
        error_log("handle_submit_multiplayer_answer - NOT incrementing turn here to prevent double increment");
    }
    
    // Check if game is over (reached max turns)
    $game_over = ($game['current_turn'] > $game['total_turns']);
    
    if ($game_over) {
        $game['completed'] = 1;
    }
    
    // Save updated game state
    update_multiplayer_game_state($session_id, $game);
    
    // Get all scores
    $scores = [
        'player1' => $game['player1_score'],
        'player2' => $game['player2_score'],
        'player3' => $game['player3_score'],
        'player4' => $game['player4_score']
    ];
    
    // Get all streaks
    $streaks = [
        'player1' => $game['player1_streak'],
        'player2' => $game['player2_streak'],
        'player3' => $game['player3_streak'],
        'player4' => $game['player4_streak']
    ];
    
    // Get image data for showing real image description
    $real_image_description = '';
    $chosen_img_type = $selected == 'real' ? 'real' : 'ai';
    
    if (!$correct) {
        // If answer is wrong, get the real image information for explanation
        $real_image = null;
        
        $image_pair = json_decode($game['current_images_json'], true);
        if ($image_pair && is_array($image_pair)) {
            foreach ($image_pair as $img) {
                if ($img['type'] === 'real') {
                    $real_image = $img;
                    break;
                }
            }
        }
        
        // If we have the real image data, get its description
        if ($real_image && isset($real_image['id'])) {
            $real_image_description = get_image_description($real_image['id'], 'real');
        }
    }
    
    // Prepare response
    $response = [
        'success' => true,
        'correct' => $correct,
        'scores' => $scores,
        'streaks' => $streaks,
        'turn' => $game['current_turn'],
        'totalTurns' => $game['total_turns'],
        'completed' => $game_over,
        'streak_bonus' => isset($streak_bonus) ? $streak_bonus : 0,
        'current_streak' => isset($game["{$streak_field}"]) ? $game["{$streak_field}"] : 0,
        'chosen_image_type' => $chosen_img_type,
        'image_description' => $real_image_description // Adding description for wrong answers
    ];
    
    // Make sure the success flag is set correctly (for debugging purposes)
    error_log("handle_submit_multiplayer_answer - Sending response with success=true");
    
    safe_json_output($response);
}

// Get images for bonus mini-game
function handle_get_bonus_images() {
    global $response;
    
    // Get the game mode from GET/POST parameters if available
    $game_mode = isset($_POST['mode']) ? $_POST['mode'] : (isset($_GET['mode']) ? $_GET['mode'] : null);
    $session_id = null;
    
    // Check for mode-specific session ID first
    if ($game_mode && isset($_SESSION['game_session_id_' . $game_mode])) {
        $session_id = $_SESSION['game_session_id_' . $game_mode];
        error_log("handle_get_bonus_images - Using mode-specific session ID for $game_mode: $session_id");
    }
    // Fall back to legacy session ID if needed
    else if (isset($_SESSION['game_session_id'])) {
        $session_id = $_SESSION['game_session_id'];
        error_log("handle_get_bonus_images - Using legacy session ID: $session_id");
    }
    
    // Check if we have a valid session ID
    if (!$session_id) {
        error_log("handle_get_bonus_images - No game session ID found in session");
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game state
    $game = get_game_state($session_id);
    
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    // Get list of already shown images
    $shown_images = !empty($game['shown_images']) ? explode(',', $game['shown_images']) : array();
    
    // Determine which bonus game type to show
    $difficulty = isset($game['difficulty']) ? $game['difficulty'] : 'easy';
    $game_mode = isset($game['game_mode']) ? $game['game_mode'] : 'standard';
    
    // Don't show bonus games in endless or multiplayer modes
    if ($game_mode === 'endless' || $game_mode === 'multiplayer') {
        $response['success'] = false;
        $response['message'] = 'Bonus games not available in this mode';
        safe_json_output($response);
    }
    
    // Hard difficulty always uses original 4-image mini-game (100% of the time)
    $use_original_game = ($difficulty === 'hard');
    
    // For other difficulties, use a 50/50 chance between original and single-image game
    if (!$use_original_game) {
        $use_original_game = (mt_rand(1, 100) <= 50);
    }
    
    // Get real images
    $real_images = get_available_images('real');
    $unused_real = array_filter($real_images, function($img) use ($shown_images) {
        return !in_array($img, $shown_images);
    });
    
    // Get AI images
    $ai_images = get_available_images('ai');
    $unused_ai = array_filter($ai_images, function($img) use ($shown_images) {
        return !in_array($img, $shown_images);
    });
    
    // Get hard difficulty images if we need a single hard difficulty image
    $hard_real_images = array();
    $hard_ai_images = array();
    
    if (!$use_original_game) {
        // Get hard difficulty real images
        $hard_real_images = get_available_images('real', 'hard');
        $unused_hard_real = array_filter($hard_real_images, function($img) use ($shown_images) {
            return !in_array($img, $shown_images);
        });
        
        // Get hard difficulty AI images
        $hard_ai_images = get_available_images('ai', 'hard');
        $unused_hard_ai = array_filter($hard_ai_images, function($img) use ($shown_images) {
            return !in_array($img, $shown_images);
        });
        
        // If we don't have enough hard difficulty images, fallback to original game
        if (count($unused_hard_real) < 1 || count($unused_hard_ai) < 1) {
            $use_original_game = true;
        }
    }
    
    // ORIGINAL GAME MODE (4 images - 1 real, 3 AI)
    if ($use_original_game) {
        // If we don't have enough unused images, just use any
        if (count($unused_real) < 1) $unused_real = $real_images;
        if (count($unused_ai) < 3) $unused_ai = $ai_images;
        
        // Shuffle and get 1 real, 3 AI
        shuffle($unused_real);
        shuffle($unused_ai);
        
        $real_image = array_shift($unused_real);
        $ai_image1 = array_shift($unused_ai);
        $ai_image2 = array_shift($unused_ai);
        $ai_image3 = array_shift($unused_ai);
        
        // Create array with 4 images and randomize position of real image
        $images = [$real_image, $ai_image1, $ai_image2, $ai_image3];
        
        // Track the real image position
        $real_image_index = 0;
        
        // Shuffle images
        shuffle($images);
        
        // Find the new position of the real image
        for ($i = 0; $i < 4; $i++) {
            if ($images[$i] === $real_image) {
                $real_image_index = $i;
                break;
            }
        }
        
        // Process image paths to prevent giving away the answer by using an image proxy
        $processed_images = array_map(function($img) {
            return 'static/images/game-image.php?id=' . urlencode(base64_encode($img));
        }, $images);
        
        // Return the images and the real image index for original game mode
        $response = [
            'success' => true,
            'images' => $processed_images,
            'realImageIndex' => $real_image_index,
            'gameType' => 'original'
        ];
    } 
    // SINGLE IMAGE GAME MODE (1 hard difficulty image - either real or AI)
    else {
        // Decide if we're showing a real or AI image (50/50 chance)
        $is_real = (mt_rand(1, 100) <= 50);
        
        // Get the image based on type
        if ($is_real) {
            shuffle($unused_hard_real);
            $single_image = array_shift($unused_hard_real);
        } else {
            shuffle($unused_hard_ai);
            $single_image = array_shift($unused_hard_ai);
        }
        
        // Process image path
        $processed_image = 'static/images/game-image.php?id=' . urlencode(base64_encode($single_image));
        
        // Return the single image and whether it's real for single image mode
        $response = [
            'success' => true,
            'image' => $processed_image,
            'isReal' => $is_real,
            'gameType' => 'single'
        ];
    }
    
    safe_json_output($response);
}

// Handle bonus mini-game result
function handle_bonus_result() {
    global $response;
    
    // Get the game mode from GET/POST parameters if available
    $game_mode = isset($_POST['mode']) ? $_POST['mode'] : (isset($_GET['mode']) ? $_GET['mode'] : null);
    $session_id = null;
    
    // Check for mode-specific session ID first
    if ($game_mode && isset($_SESSION['game_session_id_' . $game_mode])) {
        $session_id = $_SESSION['game_session_id_' . $game_mode];
        error_log("handle_bonus_result - Using mode-specific session ID for $game_mode: $session_id");
    }
    // Fall back to legacy session ID if needed
    else if (isset($_SESSION['game_session_id'])) {
        $session_id = $_SESSION['game_session_id'];
        error_log("handle_bonus_result - Using legacy session ID: $session_id");
    }
    
    // Check if we have a valid session ID
    if (!$session_id) {
        error_log("handle_bonus_result - No game session ID found in session");
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game data
    $game = get_game_state($session_id);
    
    // Check if game exists
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    // Check if result was provided
    if (!isset($_POST['correct'])) {
        $response['message'] = 'No result provided';
        safe_json_output($response);
    }
    
    // Get result and game type
    $correct = (bool)$_POST['correct'];
    $game_type = isset($_POST['game_type']) ? $_POST['game_type'] : 'original';
    
    // Check if player has max lives already
    $max_lives = 3; // Default max
    
    // Determine max lives based on difficulty
    if ($game['difficulty'] == 'easy') {
        $max_lives = 5;
    } elseif ($game['difficulty'] == 'medium') {
        $max_lives = 3;
    } elseif ($game['difficulty'] == 'hard') {
        $max_lives = 1;
    }
    
    // Flag to track if alternative reward was given
    $gave_points_reward = false;
    
    // Update game based on result
    if ($correct) {
        // Check if player already has maximum lives
        if ($game['lives'] >= $max_lives) {
            // Award 50 bonus points instead
            $game['score'] += 50;
            $gave_points_reward = true;
        } else {
            // Award an extra life
            $game['lives']++;
        }
        
        // Award achievement for logged-in users
        if (isset($game['user_id']) && $game['user_id'] > 0) {
            // Check if achievement functions exist
            if (function_exists('award_achievement')) {
                // Award different achievements based on game type
                if ($game_type === 'single') {
                    award_achievement($game['user_id'], 'single_image_bonus_win');
                } else {
                    award_achievement($game['user_id'], 'bonus_game_win');
                }
            }
        }
    } else {
        // Penalty: reduce score by half (but not below 0)
        $game['score'] = max(0, floor($game['score'] / 2));
    }
    
    // Save updated game state
    update_game_state($session_id, $game);
    
    // Prepare response
    $response = [
        'success' => true,
        'score' => $game['score'],
        'lives' => $game['lives'],
        'points_reward' => $gave_points_reward,
        'game_type' => $game_type
    ];
    
    safe_json_output($response);
}

// Start the multiplayer bonus game at the end of a match
function handle_start_multiplayer_bonus_game() {
    global $response;
    
    // Check if game session exists
    if (!isset($_SESSION['game_session_id'])) {
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game data
    $session_id = $_SESSION['game_session_id'];
    $game = get_game_state($session_id, 'multiplayer');
    
    // Check if game exists
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    // Check if game is completed
    if ($game['completed'] != 1) {
        $response['message'] = 'Game is not yet completed';
        safe_json_output($response);
    }
    
    // Initialize bonus game
    $conn = get_db_connection();
    
    try {
        // Check if bonus game data already exists
        $stmt = $conn->prepare("SELECT * FROM multiplayer_bonus_games WHERE multiplayer_game_id = :game_id");
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $bonus_game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bonus_game) {
            // Create new bonus game
            $chest_values = [10, 20, 50, 100]; // Point values for each chest
            shuffle($chest_values); // Randomize the values
            
            $stmt = $conn->prepare("
                INSERT INTO multiplayer_bonus_games 
                (multiplayer_game_id, chest1_value, chest2_value, chest3_value, chest4_value, created_at)
                VALUES (:game_id, :chest1, :chest2, :chest3, :chest4, NOW())
            ");
            
            $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
            $stmt->bindParam(':chest1', $chest_values[0], PDO::PARAM_INT);
            $stmt->bindParam(':chest2', $chest_values[1], PDO::PARAM_INT);
            $stmt->bindParam(':chest3', $chest_values[2], PDO::PARAM_INT);
            $stmt->bindParam(':chest4', $chest_values[3], PDO::PARAM_INT);
            $stmt->execute();
            
            // Get the newly created bonus game
            $bonus_game_id = $conn->lastInsertId();
            
            $stmt = $conn->prepare("SELECT * FROM multiplayer_bonus_games WHERE id = :id");
            $stmt->bindParam(':id', $bonus_game_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $bonus_game = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Update game status to include bonus game
        $stmt = $conn->prepare("UPDATE multiplayer_games SET bonus_game_started = 1 WHERE id = :game_id");
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Return success
        $response = [
            'success' => true,
            'bonus_game_id' => $bonus_game['id'],
            'message' => 'Bonus game started successfully'
        ];
        
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
    
    safe_json_output($response);
}

// Handle chest selection in the multiplayer bonus game
function handle_multiplayer_chest_selection() {
    global $response;
    
    // Check if game session exists
    if (!isset($_SESSION['game_session_id'])) {
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game data
    $session_id = $_SESSION['game_session_id'];
    $game = get_game_state($session_id, 'multiplayer');
    
    // Check if game exists
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    // Check if chest selection was provided
    if (!isset($_POST['chest_index'])) {
        $response['message'] = 'No chest selected';
        safe_json_output($response);
    }
    
    // Get selected chest index (0-3)
    $chest_index = (int)$_POST['chest_index'];
    
    // Validate chest index
    if ($chest_index < 0 || $chest_index > 3) {
        $response['message'] = 'Invalid chest index';
        safe_json_output($response);
    }
    
    // Get current player
    $player_number = 0;
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    
    for ($i = 1; $i <= 4; $i++) {
        if (isset($game["player{$i}_id"]) && $game["player{$i}_id"] == $user_id) {
            $player_number = $i;
            break;
        } elseif (isset($game["player{$i}_name"]) && $game["player{$i}_name"] == $username) {
            $player_number = $i;
            break;
        }
    }
    
    if ($player_number == 0) {
        $response['message'] = 'Player not found in game';
        safe_json_output($response);
    }
    
    $conn = get_db_connection();
    
    try {
        // Get the bonus game
        $stmt = $conn->prepare("SELECT * FROM multiplayer_bonus_games WHERE multiplayer_game_id = :game_id");
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $bonus_game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bonus_game) {
            $response['message'] = 'Bonus game not found';
            safe_json_output($response);
        }
        
        // Check if player has already selected a chest
        $chest_selected_field = "player{$player_number}_chest_selected";
        if ($bonus_game[$chest_selected_field] != 0) {
            $response['message'] = 'You have already selected a chest';
            safe_json_output($response);
        }
        
        // Check if chest is already taken
        for ($i = 1; $i <= 4; $i++) {
            if ($bonus_game["player{$i}_chest_selected"] == $chest_index + 1) {
                $response['message'] = 'This chest has already been selected by another player';
                safe_json_output($response);
            }
        }
        
        // Record player's chest selection
        $stmt = $conn->prepare("
            UPDATE multiplayer_bonus_games 
            SET {$chest_selected_field} = :chest_num 
            WHERE id = :bonus_game_id
        ");
        
        $chest_num = $chest_index + 1; // Convert 0-based index to 1-based for DB
        $stmt->bindParam(':chest_num', $chest_num, PDO::PARAM_INT);
        $stmt->bindParam(':bonus_game_id', $bonus_game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Get the bonus value for the selected chest
        $chest_value_field = "chest" . $chest_num . "_value";
        $bonus_value = $bonus_game[$chest_value_field];
        
        // Add the bonus to the player's score
        $score_field = "player{$player_number}_score";
        $new_score = $game[$score_field] + $bonus_value;
        
        $stmt = $conn->prepare("
            UPDATE multiplayer_games 
            SET {$score_field} = :new_score 
            WHERE id = :game_id
        ");
        
        $stmt->bindParam(':new_score', $new_score, PDO::PARAM_INT);
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Get updated game state
        $game = get_game_state($session_id, 'multiplayer');
        
        // Prepare response
        $response = [
            'success' => true,
            'bonus_value' => $bonus_value,
            'new_score' => $new_score,
            'all_scores' => [
                'player1' => $game['player1_score'],
                'player2' => $game['player2_score'],
                'player3' => $game['player3_score'],
                'player4' => $game['player4_score']
            ],
            'message' => "You found a chest worth {$bonus_value} points!"
        ];
        
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
    
    safe_json_output($response);
}

// Get the status of the multiplayer bonus game
function handle_get_multiplayer_bonus_images() {
    global $response;
    
    // Check if game session exists
    if (!isset($_SESSION['game_session_id'])) {
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game data
    $session_id = $_SESSION['game_session_id'];
    $game = get_game_state($session_id, 'multiplayer');
    
    // Check if game exists
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    $conn = get_db_connection();
    
    try {
        // Get the bonus game
        $stmt = $conn->prepare("SELECT * FROM multiplayer_bonus_games WHERE multiplayer_game_id = :game_id");
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        $bonus_game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bonus_game) {
            $response['message'] = 'Bonus game not found';
            safe_json_output($response);
        }
        
        // Get current player
        $player_number = 0;
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
        
        for ($i = 1; $i <= 4; $i++) {
            if (isset($game["player{$i}_id"]) && $game["player{$i}_id"] == $user_id) {
                $player_number = $i;
                break;
            } elseif (isset($game["player{$i}_name"]) && $game["player{$i}_name"] == $username) {
                $player_number = $i;
                break;
            }
        }
        
        if ($player_number == 0) {
            $response['message'] = 'Player not found in game';
            safe_json_output($response);
        }
        
        // Check which chest the player has selected
        $chest_selected = $bonus_game["player{$player_number}_chest_selected"];
        
        // Get all player selections
        $player_selections = [];
        for ($i = 1; $i <= 4; $i++) {
            if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
                $player_selections[$i] = $bonus_game["player{$i}_chest_selected"];
            }
        }
        
        // Get chest values (only reveal if selected)
        $chest_values = [];
        for ($i = 1; $i <= 4; $i++) {
            $is_selected = false;
            $selected_by = 0;
            
            // Check if any player has selected this chest
            foreach ($player_selections as $player => $selection) {
                if ($selection == $i) {
                    $is_selected = true;
                    $selected_by = $player;
                    break;
                }
            }
            
            if ($is_selected) {
                $chest_values[$i - 1] = [
                    'value' => $bonus_game["chest{$i}_value"],
                    'selected_by' => $selected_by
                ];
            } else {
                $chest_values[$i - 1] = [
                    'value' => null,
                    'selected_by' => 0
                ];
            }
        }
        
        // Prepare response
        $response = [
            'success' => true,
            'player_number' => $player_number,
            'chest_selected' => $chest_selected,
            'chest_values' => $chest_values,
            'player_selections' => $player_selections,
            'all_scores' => [
                'player1' => $game['player1_score'],
                'player2' => $game['player2_score'],
                'player3' => $game['player3_score'],
                'player4' => $game['player4_score']
            ]
        ];
        
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
    
    safe_json_output($response);
}

// Handle the final result of the multiplayer bonus game
function handle_multiplayer_bonus_result() {
    global $response;
    
    // Check if game session exists
    if (!isset($_SESSION['game_session_id'])) {
        $response['message'] = 'No active game session';
        safe_json_output($response);
    }
    
    // Get game data
    $session_id = $_SESSION['game_session_id'];
    $game = get_game_state($session_id, 'multiplayer');
    
    // Check if game exists
    if (!$game) {
        $response['message'] = 'Game session not found';
        safe_json_output($response);
    }
    
    $conn = get_db_connection();
    
    try {
        // Mark the bonus game as completed
        $stmt = $conn->prepare("
            UPDATE multiplayer_games 
            SET bonus_game_completed = 1 
            WHERE id = :game_id
        ");
        
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Determine the winner
        $scores = [
            1 => $game['player1_score'],
            2 => $game['player2_score'],
            3 => $game['player3_score'],
            4 => $game['player4_score']
        ];
        
        arsort($scores); // Sort scores in descending order
        $winner_number = key($scores); // Get the player number with the highest score
        
        // Get winner name
        $winner_name = '';
        if ($winner_number > 0) {
            if (!empty($game["player{$winner_number}_id"])) {
                $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = :user_id");
                $user_stmt->bindParam(':user_id', $game["player{$winner_number}_id"], PDO::PARAM_INT);
                $user_stmt->execute();
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                $winner_name = $user ? $user['username'] : "Player {$winner_number}";
            } else {
                $winner_name = $game["player{$winner_number}_name"] ?: "Player {$winner_number}";
            }
        }
        
        // Update game with winner
        $stmt = $conn->prepare("
            UPDATE multiplayer_games 
            SET winner_player_num = :winner_num,
                winner_name = :winner_name,
                completed_at = NOW()
            WHERE id = :game_id
        ");
        
        $stmt->bindParam(':winner_num', $winner_number, PDO::PARAM_INT);
        $stmt->bindParam(':winner_name', $winner_name, PDO::PARAM_STR);
        $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $stmt->execute();
        
        // Prepare response
        $response = [
            'success' => true,
            'winner' => [
                'player_number' => $winner_number,
                'name' => $winner_name,
                'score' => $scores[$winner_number]
            ],
            'all_scores' => [
                'player1' => $game['player1_score'],
                'player2' => $game['player2_score'],
                'player3' => $game['player3_score'],
                'player4' => $game['player4_score']
            ],
            'message' => "{$winner_name} wins with {$scores[$winner_number]} points!"
        ];
        
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
        $response = [
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ];
    }
    
    safe_json_output($response);
}