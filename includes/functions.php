<?php
require_once 'database.php';
require_once 'achievements.php';
require_once 'compatibility.php';

/**
 * Anti-Cheating System Functions
 * These functions help prevent score tampering using hash-based verification,
 * timestamps, and encryption.
 */

// We've removed our duplicated encryption/decryption functions since they already exist elsewhere in the file.

// We've removed our duplicated function here since one already exists at line 3281

// Initialize global game configuration
global $game_config;
$game_config = [
    'difficulties' => [
        'easy' => EASY_DIFFICULTY,
        'medium' => MEDIUM_DIFFICULTY, 
        'hard' => HARD_DIFFICULTY,
        'endless' => defined('ENDLESS_SETTINGS') ? ENDLESS_SETTINGS : [
            'turns' => 0,
            'lives' => 1,
            'bonus_frequency' => 10
        ]
    ],
    'multiplayer' => MULTIPLAYER_SETTINGS,
    'max_players' => MULTIPLAYER_SETTINGS['max_players'],
    'min_players' => MULTIPLAYER_SETTINGS['min_players']
];

/**
 * Update the current images for a game session
 * @param string $session_id The session ID
 * @param string $real_image The path to the real image
 * @param string $ai_image The path to the AI-generated image
 * @param bool $left_is_real Whether the left image is the real one
 * @param string $game_mode The game mode (single or multiplayer)
 * @return bool Success status
 */
/**
 * Verify an image exists in either the real or AI folders
 * 
 * @param string $image_path The path to the image to verify
 * @return array Array with 'exists' (bool) and 'path' (string|null) keys
 */
// Function moved to line ~820 with improved implementation

/**
 * Get the shown images for a game session
 * 
 * @param string $session_id The session ID
 * @return array Array of image filenames that have been shown
 */
function get_shown_images_from_db($session_id) {
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT shown_images FROM games WHERE session_id = :session_id");
        $stmt->bindParam(':session_id', $session_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['shown_images'])) {
            return explode(',', $result['shown_images']);
        }
        return [];
    } catch (PDOException $e) {
        error_log("Error getting shown images: " . $e->getMessage());
        return [];
    }
}

function update_current_images($session_id, $real_image, $ai_image, $left_is_real, $game_mode = 'single') {
    $conn = get_db_connection();
    
    // Make sure we clean the paths to just store the relative filenames
    $real_image_basename = basename($real_image);
    $ai_image_basename = basename($ai_image);
    
    // For resumable games, verify the images exist and aren't duplicates
    if ($real_image_basename === $ai_image_basename) {
        error_log("Error: Same image for real and AI detected: {$real_image_basename}. Getting new images.");
        
        // Get shown images for this session
        $shown_images = get_shown_images_from_db($session_id);
        
        // Get new image pair
        list($new_real_image, $new_ai_image) = get_random_image_pair($shown_images);
        
        // Update the basenames
        $real_image_basename = basename($new_real_image);
        $ai_image_basename = basename($new_ai_image);
        
        // Generate new left_is_real
        $left_is_real = random_int(0, 1) == 1;
        
        error_log("Generated new image pair: Real: {$real_image_basename}, AI: {$ai_image_basename}, Left is real: " . ($left_is_real ? 'Yes' : 'No'));
    }
    
    // Verify each image actually exists
    $real_image_info = verify_image_exists_original($real_image_basename);
    $ai_image_info = verify_image_exists_original($ai_image_basename);
    
    // If either image doesn't exist, get new ones
    if (!$real_image_info['exists'] || !$ai_image_info['exists']) {
        error_log("Error: Image verification failed - Real image exists: " . ($real_image_info['exists'] ? 'Yes' : 'No') . 
                 ", AI image exists: " . ($ai_image_info['exists'] ? 'Yes' : 'No'));
        
        // Get shown images for this session
        $shown_images = get_shown_images_from_db($session_id);
        
        // Get new image pair
        list($new_real_image, $new_ai_image) = get_random_image_pair($shown_images);
        
        // Update the basenames
        $real_image_basename = basename($new_real_image);
        $ai_image_basename = basename($new_ai_image);
        
        // Generate new left_is_real
        $left_is_real = random_int(0, 1) == 1;
        
        error_log("Generated new image pair after verification failure: Real: {$real_image_basename}, AI: {$ai_image_basename}, Left is real: " . ($left_is_real ? 'Yes' : 'No'));
    }
    
    $left_is_real_int = $left_is_real ? 1 : 0;
    
    if ($game_mode === 'multiplayer') {
        $stmt = $conn->prepare("UPDATE multiplayer_games SET 
            current_real_image = :real_image, 
            current_ai_image = :ai_image, 
            left_is_real = :left_is_real 
            WHERE session_id = :session_id");
    } else {
        $stmt = $conn->prepare("UPDATE games SET 
            current_real_image = :real_image, 
            current_ai_image = :ai_image, 
            left_is_real = :left_is_real,
            last_modified = :last_modified
            WHERE session_id = :session_id");
    }
    
    $stmt->bindParam(':real_image', $real_image_basename, PDO::PARAM_STR);
    $stmt->bindParam(':ai_image', $ai_image_basename, PDO::PARAM_STR);
    $stmt->bindParam(':left_is_real', $left_is_real_int, PDO::PARAM_INT);
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    
    // Bind last_modified parameter if this is not a multiplayer game
    if ($game_mode !== 'multiplayer') {
        $current_time = date('Y-m-d H:i:s');
        $stmt->bindParam(':last_modified', $current_time, PDO::PARAM_STR);
    }
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Failed to update current images for session ID: " . $session_id);
        error_log("Error: " . print_r($stmt->errorInfo(), true));
    } else {
        error_log("Updated current images for session ID: " . $session_id);
    }
    
    return $result;
}

/**
 * Update the time penalty flag for a game session
 * @param string $session_id The session ID
 * @param bool $penalty_enabled Whether the time penalty is in effect
 * @param string $game_mode The game mode (single or multiplayer)
 * @return bool Success status
 */
function update_time_penalty_flag($session_id, $penalty_enabled, $game_mode = 'single') {
    $conn = get_db_connection();
    $penalty_value = $penalty_enabled ? 1 : 0;
    
    // First check if the time_penalty column exists
    $table = ($game_mode === 'multiplayer') ? 'multiplayer_games' : 'games';
    $column_exists = false;
    
    try {
        // SQLite approach to check if column exists
        $result = $conn->query("PRAGMA table_info($table)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        
        $column_exists = false;
        foreach ($columns as $column) {
            if ($column['name'] === 'time_penalty') {
                $column_exists = true;
                break;
            }
        }
        
        // If column doesn't exist, add it
        if (!$column_exists) {
            $conn->exec("ALTER TABLE $table ADD COLUMN time_penalty INTEGER DEFAULT 0");
            error_log("Added time_penalty column to $table table");
            $column_exists = true;
        }
    } catch (PDOException $e) {
        error_log("Error checking/adding time_penalty column: " . $e->getMessage());
        // Return without error so game can continue even without the time penalty feature
        return false;
    }
    
    // Only proceed if column exists or was successfully added
    if ($column_exists) {
        try {
            // For Endless Mode: When a refresh is detected, reset the time bonus by updating the appropriate fields
            if ($game_mode === 'endless' && $penalty_enabled) {
                error_log("Endless mode refresh detected - resetting time bonus to zero");
                
                // For endless mode, update both time_penalty and reset any streak as penalty
                $stmt = $conn->prepare("UPDATE games SET time_penalty = :time_penalty WHERE session_id = :session_id");
                $stmt->bindParam(':time_penalty', $penalty_value, PDO::PARAM_INT);
                $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
                $result = $stmt->execute();
                
                if ($result) {
                    error_log("Set time penalty for endless mode session: " . $session_id);
                }
            }
            // For all other modes, just update the time_penalty flag
            else {
                if ($game_mode === 'multiplayer') {
                    $stmt = $conn->prepare("UPDATE multiplayer_games SET time_penalty = :time_penalty WHERE session_id = :session_id");
                } else {
                    $stmt = $conn->prepare("UPDATE games SET time_penalty = :time_penalty WHERE session_id = :session_id");
                }
                
                $stmt->bindParam(':time_penalty', $penalty_value, PDO::PARAM_INT);
                $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
                
                $result = $stmt->execute();
            }
            
            if (!$result) {
                error_log("Failed to update time penalty flag for session ID: " . $session_id);
                error_log("Error: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("Updated time penalty flag to " . $penalty_value . " for session ID: " . $session_id);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error updating time penalty flag: " . $e->getMessage());
            return false;
        }
    }
    
    return false;
}

/**
 * Reset current image references and time penalty flag after submitting an answer
 * @param string $session_id The session ID
 * @param string $game_mode The game mode (single or multiplayer)
 * @return bool Success status
 */
function reset_current_images($session_id, $game_mode = 'single') {
    $conn = get_db_connection();
    
    try {
        // Get current game state to log the streak value before resetting images
        if ($game_mode === 'single') {
            $game = get_game_state($session_id);
            if ($game && isset($game['current_streak'])) {
                error_log("reset_current_images - IMPORTANT: Current streak BEFORE reset is: " . $game['current_streak']);
            }
        }
    
        // Instead of preserving the images, we will now clear them
        // This will force get_game_image_pair to generate new images
        // instead of reusing the same ones
        
        // Check if columns exist for this table using SQLite syntax
        $table = ($game_mode === 'multiplayer') ? 'multiplayer_games' : 'games';
        $result = $conn->query("PRAGMA table_info($table)");
        $columns = $result->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare column names for the update query
        $updateColumns = [];
        $hasTimeColumn = false;
        $hasImageColumns = false;
        
        foreach ($columns as $column) {
            // Check for time_penalty column
            if ($column['name'] === 'time_penalty') {
                $hasTimeColumn = true;
                $updateColumns[] = "time_penalty = 0";
            }
            
            // Check for image-related columns
            if ($column['name'] === 'current_real_image' || $column['name'] === 'current_ai_image') {
                $hasImageColumns = true;
                $updateColumns[] = "{$column['name']} = NULL";
            }
        }
        
        // If we have columns to update, proceed with the update
        if (!empty($updateColumns)) {
            $updateSQL = implode(', ', $updateColumns);
            
            // For non-multiplayer games, also update the last_modified timestamp
            if ($game_mode === 'multiplayer') {
                $stmt = $conn->prepare("UPDATE multiplayer_games SET $updateSQL WHERE session_id = :session_id");
            } else {
                // Add last_modified timestamp update
                $updateSQL .= ", last_modified = :last_modified";
                $stmt = $conn->prepare("UPDATE games SET $updateSQL WHERE session_id = :session_id");
            }
            
            $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
            
            // Bind last_modified parameter if this is not a multiplayer game
            if ($game_mode !== 'multiplayer') {
                $current_time = date('Y-m-d H:i:s');
                $stmt->bindParam(':last_modified', $current_time, PDO::PARAM_STR);
            }
            
            $result = $stmt->execute();
            
            if (!$result) {
                error_log("Failed to reset images and time penalty for session ID: " . $session_id);
                error_log("Error: " . print_r($stmt->errorInfo(), true));
            } else {
                error_log("Reset images and time penalty for session ID: " . $session_id);
                
                if ($hasTimeColumn) {
                    error_log("Reset time_penalty to 0");
                }
                
                if ($hasImageColumns) {
                    error_log("Cleared current_real_image and current_ai_image");
                }
            }
            
            return $result;
        } else {
            error_log("No columns to reset found for $table table, nothing to do");
            return true; // Consider it successful if there's nothing to do
        }
    } catch (PDOException $e) {
        error_log("Error in reset_current_images: " . $e->getMessage());
        return false;
    }
}

/**
 * Start a session if one hasn't been started yet
 */
if (!function_exists('session_start_if_not_started')) {
    function session_start_if_not_started() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}

if (!function_exists('check_if_bot_join_attempt')) {
    /**
     * Check if this is a bot join attempt based on various heuristics
     * 
     * @return bool True if this appears to be a bot join attempt
     */
    function check_if_bot_join_attempt() {
        // Check if the function is being called from check_and_add_bots function
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        foreach ($backtrace as $call) {
            if (isset($call['function']) && $call['function'] === 'check_and_add_bots') {
                error_log("Detected bot join attempt through function call stack: check_and_add_bots");
                return true;
            }
        }
        
        // Check if this is an automated process by checking the caller
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        if (empty($referrer) && isset($_SERVER['SCRIPT_NAME'])) {
            $scriptName = basename($_SERVER['SCRIPT_NAME']);
            if ($scriptName === 'enhanced_bot_scoring.php') {
                error_log("Detected bot join attempt: call from enhanced_bot_scoring.php");
                return true;
            }
        }
        
        // Check for special request headers or patterns that would indicate a bot
        $isBot = false;
        
        // Check user agent for bot signatures
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        if (strpos($userAgent, 'bot') !== false || 
            strpos($userAgent, 'spider') !== false || 
            strpos($userAgent, 'crawl') !== false) {
            $isBot = true;
        }
        
        // If this is called within check_and_add_bots (server-side bots)
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
            if ($scriptName === 'enhanced_bot_scoring.php' || 
                strpos($scriptName, 'bot') !== false ||
                strpos($scriptName, 'cron') !== false) {
                $isBot = true;
            }
        }
        
        return $isBot;
    }
}

// Authentication functions
if (!function_exists('register_user')) {
    function register_user($username, $email, $password) {
    $conn = get_db_connection();
    
    // Check if username already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    if ($stmt->rowCount() > 0) {
        return array('success' => false, 'message' => 'Username already exists');
    }
    
    // If email is provided, check if it already exists
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Email already exists');
        }
    }
    
    // Make sure username doesn't contain "Anonymous"
    if (stripos($username, 'Anonymous') !== false) {
        return array('success' => false, 'message' => 'Username cannot contain "Anonymous"');
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        // Set session variables
        $_SESSION['user_id'] = $conn->lastInsertId();
        $_SESSION['username'] = $username;
        $_SESSION['is_admin'] = 0;
        
        return array('success' => true, 'message' => 'Registration successful');
    } else {
        $errorInfo = $stmt->errorInfo();
        return array('success' => false, 'message' => 'Registration failed: ' . $errorInfo[2]);
    }
}

function login_user($username, $password) {
    $conn = get_db_connection();
    
    // Get user by username
    $stmt = $conn->prepare("SELECT id, username, password_hash, is_admin FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            return array('success' => true, 'message' => 'Login successful');
        } else {
            return array('success' => false, 'message' => 'Invalid password');
        }
    } else {
        return array('success' => false, 'message' => 'Username not found');
    }
}

function logout_user() {
    // Clear user session variables
    unset($_SESSION['user_id']);
    unset($_SESSION['username']);
    unset($_SESSION['is_admin']);
    unset($_SESSION['is_anonymous']);
    unset($_SESSION['game_session_id']);
    
    // Clear all mode-specific game session variables
    foreach (['single', 'endless', 'multiplayer'] as $mode) {
        if (isset($_SESSION['game_session_id_' . $mode])) {
            unset($_SESSION['game_session_id_' . $mode]);
        }
    }
    
    // Optional: regenerate session ID for security
    session_regenerate_id(true);
    
    error_log("User logged out - All session variables cleared");
    
    return array('success' => true, 'message' => 'Logout successful');
}

// Game management functions
function generate_session_id($length = 16) {
    $chars = '0123456789abcdefghijklmnopqrstuvwxyz';
    $session_id = '';
    
    for ($i = 0; $i < $length; $i++) {
        $session_id .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $session_id;
}

function create_game($game_mode, $difficulty = null, $play_anonymous = false) {
    global $game_config;
    $conn = get_db_connection();
    
    // Debug: Log session information
    error_log("Creating game. Session ID: " . session_id());
    error_log("Session status: " . session_status());
    error_log("Session data: " . print_r($_SESSION, true));
    
    // Generate a unique session ID
    $session_id = generate_session_id();
    
    // Check if anonymous play is requested
    if ($play_anonymous && !isset($_SESSION['user_id'])) {
        // Generate a random anonymous username
        $anon_id = generate_session_id(8);
        $anon_username = "Anonymous" . $anon_id;
        
        // Set anonymous user in session
        $_SESSION['username'] = $anon_username;
        $_SESSION['is_anonymous'] = true;
        
        // No user_id for anonymous users
        $user_id = null;
    } else {
        // Get user ID from session
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
    
    // Set default game values
    $total_turns = 10;
    $lives = 3;
    
    // Set game parameters based on difficulty
    if ($game_mode != 'multiplayer' && $difficulty) {
        if (isset($game_config['difficulties'][$difficulty])) {
            $total_turns = $game_config['difficulties'][$difficulty]['turns'];
            $lives = $game_config['difficulties'][$difficulty]['lives'];
        }
    }
    
    // Set endless mode parameters
    if ($game_mode == 'endless') {
        // Use the endless settings from game_config
        $total_turns = $game_config['difficulties']['endless']['turns']; // Should be 0 (unlimited)
        $lives = $game_config['difficulties']['endless']['lives']; // Should be 1
        error_log("Endless mode configured with $lives lives and $total_turns turns");
    }
    
    // Create game based on mode
    if ($game_mode == 'multiplayer') {
        $stmt = $conn->prepare("INSERT INTO multiplayer_games (session_id, player1_id, player1_name, total_turns) VALUES (:session_id, :player1_id, :player1_name, :total_turns)");
        $player1_name = ($user_id === null) ? $_SESSION['username'] : null;
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->bindParam(':player1_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':player1_name', $player1_name, PDO::PARAM_STR);
        $stmt->bindParam(':total_turns', $total_turns, PDO::PARAM_INT);
    } else {
        // Create a new game 
        $initial_score = 0; // Always start with score of 0 for new games
        $current_turn = 1; // Start at turn 1
        
        $stmt = $conn->prepare("INSERT INTO games (
            session_id, 
            game_mode, 
            difficulty, 
            total_turns, 
            lives, 
            starting_lives, 
            score, 
            current_turn, 
            user_id,
            current_streak
        ) VALUES (
            :session_id, 
            :game_mode, 
            :difficulty, 
            :total_turns, 
            :lives, 
            :starting_lives, 
            :score, 
            :current_turn, 
            :user_id,
            0
        )");
        
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
        $stmt->bindParam(':total_turns', $total_turns, PDO::PARAM_INT);
        $stmt->bindParam(':lives', $lives, PDO::PARAM_INT);
        $stmt->bindParam(':starting_lives', $lives, PDO::PARAM_INT); // Store original lives count
        $stmt->bindParam(':score', $initial_score, PDO::PARAM_INT);
        $stmt->bindParam(':current_turn', $current_turn, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        error_log("Creating new game with initial score: $initial_score, turn: $current_turn");
    }
    
    if ($stmt->execute()) {
        // Store session ID in user session (both in legacy variable and mode-specific variable)
        $_SESSION['game_session_id'] = $session_id;
        $_SESSION['game_session_id_' . $game_mode] = $session_id;
        
        // Debug: Verify session data was saved
        error_log("Game created successfully. Session ID: " . $session_id);
        error_log("Mode-specific session variable set: game_session_id_" . $game_mode . " = " . $session_id);
        error_log("Updated session data: " . print_r($_SESSION, true));
        
        return array('success' => true, 'session_id' => $session_id);
    } else {
        $errorInfo = $stmt->errorInfo();
        error_log("Game creation failed: " . print_r($errorInfo, true));
        return array('success' => false, 'message' => 'Failed to create game: ' . $errorInfo[2]);
    }
}

function join_game($session_id, $play_anonymous = false) {
    global $game_config;
    $conn = get_db_connection();
    
    // Check if multiplayer game exists
    $stmt = $conn->prepare("SELECT * FROM multiplayer_games WHERE session_id = :session_id");
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    $stmt->execute();
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        return array('success' => false, 'message' => 'Game session not found!');
    }
    
    // Check if game is already in progress and this is a bot join attempt
    $is_bot = check_if_bot_join_attempt();
    $game_in_progress = $game['started_at'] !== null || $game['current_turn'] > 1 || $game['status'] === 'in_progress';
    
    if ($is_bot && $game_in_progress) {
        error_log("Blocking bot from joining in-progress game: " . $session_id);
        return array('success' => false, 'message' => 'This game is already in progress. Bots cannot join.');
    }
    
    // Count the number of players
    $player_count = 0;
    for ($i = 1; $i <= $game_config['max_players']; $i++) {
        if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
            $player_count++;
        }
    }
    
    // Check if the game is already full
    if ($player_count >= $game_config['max_players']) {
        return array('success' => false, 'message' => 'This game is already full!');
    }
    
    // Check if anonymous play is requested
    if ($play_anonymous && !isset($_SESSION['user_id'])) {
        // Generate a random anonymous username
        $anon_id = generate_session_id(8);
        $anon_username = "Anonymous" . $anon_id;
        
        // Set anonymous user in session
        $_SESSION['username'] = $anon_username;
        $_SESSION['is_anonymous'] = true;
        
        // No user_id for anonymous users
        $user_id = null;
        $player_name = $anon_username;
    } else {
        // Get user ID and name from session
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $player_name = isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
    
    // Find the next available player slot
    $next_slot = 0;
    for ($i = 1; $i <= $game_config['max_players']; $i++) {
        if (empty($game["player{$i}_id"]) && empty($game["player{$i}_name"])) {
            $next_slot = $i;
            break;
        }
    }
    
    if ($next_slot === 0) {
        return array('success' => false, 'message' => 'This game is already full!');
    }
    
    // Update the game with the new player
    if ($user_id !== null) {
        $stmt = $conn->prepare("UPDATE multiplayer_games SET player{$next_slot}_id = :player_id WHERE session_id = :session_id");
        $stmt->bindParam(':player_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    } else {
        $stmt = $conn->prepare("UPDATE multiplayer_games SET player{$next_slot}_name = :player_name WHERE session_id = :session_id");
        $stmt->bindParam(':player_name', $player_name, PDO::PARAM_STR);
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    }
    
    if ($stmt->execute()) {
        // Store session ID in user session (both in legacy variable and mode-specific variable)
        $_SESSION['game_session_id'] = $session_id;
        $_SESSION['game_session_id_multiplayer'] = $session_id;
        
        error_log("Joined multiplayer game. Mode-specific session variable set: game_session_id_multiplayer = " . $session_id);
        
        return array('success' => true, 'session_id' => $session_id);
    } else {
        $errorInfo = $stmt->errorInfo();
        return array('success' => false, 'message' => 'Failed to join game: ' . $errorInfo[2]);
    }
}

// Image management functions
function get_available_images($folder_type, $difficulty = null) {
    $dir = ($folder_type == 'real') ? REAL_IMAGES_DIR : AI_IMAGES_DIR;
    $images = array();
    
    // Debug info
    error_log("Checking for images in directory: $dir with difficulty: " . ($difficulty ?: 'any'));
    
    // Check if directory exists and is accessible
    if (!is_dir($dir)) {
        error_log("Directory does not exist: $dir");
        
        // Fallback to static/images
        $fallback_dir = ROOT_DIR . '/static/images/' . strtolower($folder_type) . '/';
        error_log("Trying fallback directory: $fallback_dir");
        
        if (is_dir($fallback_dir)) {
            $dir = $fallback_dir;
        } else {
            error_log("Fallback directory also does not exist: $fallback_dir");
            return $images; // Return empty array
        }
    }
    
    // Ensure the directory path ends with a slash
    if (substr($dir, -1) !== '/') {
        $dir .= '/';
    }
    
    // Get files with pattern matching
    $files = glob($dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    
    error_log("Found " . count($files) . " files in $dir");
    
    // If no difficulty is specified, return all images
    if ($difficulty === null) {
        foreach ($files as $file) {
            $images[] = $file;
        }
        return $images;
    }
    
    // Get images with specific difficulty
    $conn = get_db_connection();
    $all_images = array();
    
    // If difficulty is 'endless', we want to include all images
    $include_all_difficulties = ($difficulty === 'endless');
    
    foreach ($files as $file) {
        // Extract filename from full path
        $filename = basename($file);
        
        // Check image metadata in the database
        $stmt = $conn->prepare("SELECT * FROM images WHERE filename = :filename");
        $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
        $stmt->execute();
        $image_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Endless mode logic: Include all images regardless of difficulty
        if ($include_all_difficulties) {
            $images[] = $file;
            
            // If image not in database, add it with 'easy' difficulty as default
            if (!$image_data) {
                try {
                    $image_type = $folder_type; // 'real' or 'ai'
                    $category = 'uncategorized';
                    
                    $stmt = $conn->prepare("INSERT INTO images (filename, type, difficulty, category) 
                                           VALUES (:filename, :type, 'easy', :category)");
                    $filename = basename($file);
                    $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
                    $stmt->bindParam(':type', $image_type, PDO::PARAM_STR);
                    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    error_log("Added unrated image to database with 'easy' difficulty: $file");
                } catch (PDOException $e) {
                    error_log("Error adding unrated image to database: " . $e->getMessage());
                }
            }
            continue; // Skip the difficulty checks below for endless mode
        }
        
        // Regular game modes - filter by difficulty
        $image_difficulty = ($image_data && $image_data['difficulty']) ? $image_data['difficulty'] : 'easy';
        
        // Decide whether to include this image based on current difficulty:
        // - easy mode: only include 'easy' difficulty images
        // - medium mode: include 'easy' and 'medium' difficulty images
        // - hard mode: include all difficulty images
        $include_image = false;
        
        if ($difficulty === 'easy' && $image_difficulty === 'easy') {
            $include_image = true;
        } else if ($difficulty === 'medium' && in_array($image_difficulty, ['easy', 'medium'])) {
            $include_image = true;
        } else if ($difficulty === 'hard') {
            $include_image = true;
        }
        
        if ($include_image) {
            $images[] = $file;
            error_log("Including image: $file with difficulty: $image_difficulty for requested difficulty: $difficulty");
        }
        
        // If image is not in database or has no difficulty set, add it with 'easy' difficulty
        if (!$image_data || $image_data['difficulty'] === null || $image_data['difficulty'] === '') {
            try {
                $image_type = $folder_type; // 'real' or 'ai'
                $category = 'uncategorized';
                
                if (!$image_data) {
                    // Insert new record
                    $stmt = $conn->prepare("INSERT INTO images (filename, type, difficulty, category) 
                                           VALUES (:filename, :type, 'easy', :category)");
                    $filename = basename($file);
                    $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
                    $stmt->bindParam(':type', $image_type, PDO::PARAM_STR);
                    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    error_log("Added unrated image to database with 'easy' difficulty: $file");
                } else {
                    // Update existing record
                    $stmt = $conn->prepare("UPDATE images SET difficulty = 'easy' WHERE id = :id");
                    $stmt->bindParam(':id', $image_data['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    
                    error_log("Updated existing image to 'easy' difficulty: $file");
                }
            } catch (PDOException $e) {
                error_log("Error updating image difficulty: " . $e->getMessage());
            }
        }
        
        // Keep track of all images for potential fallback
        $all_images[] = $file;
    }
    
    // If no images with the specified difficulty were found, fall back to all images
    if (empty($images) && !empty($all_images)) {
        error_log("Warning: No images found with difficulty '$difficulty', using all available images instead");
        return $all_images;
    }
    
    return $images;
}

function get_image_metadata($filename) {
    $conn = get_db_connection();
    
    // Extract just the filename if a full path was given
    $basename = basename($filename);
    
    $stmt = $conn->prepare("SELECT * FROM images WHERE filename = :filename");
    $stmt->bindParam(':filename', $basename, PDO::PARAM_STR);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// This function is now handled by the implementation below
// See the function definition at line ~1710

function get_images_by_difficulty($difficulty, $image_type = null) {
    $conn = get_db_connection();
    
    if ($image_type !== null) {
        $stmt = $conn->prepare("SELECT * FROM images WHERE difficulty = :difficulty AND type = :type");
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
        $stmt->bindParam(':type', $image_type, PDO::PARAM_STR);
    } else {
        $stmt = $conn->prepare("SELECT * FROM images WHERE difficulty = :difficulty");
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_random_image_pair($shown_images = array(), $difficulty = 'easy') {
    // Debug info
    error_log("Getting random image pair. Difficulty: " . $difficulty);
    error_log("Shown images: " . print_r($shown_images, true));
    
    // Get available images based on difficulty
    $real_images = get_available_images('real', $difficulty);
    $ai_images = get_available_images('ai', $difficulty);
    
    error_log("Total real images with difficulty '$difficulty': " . count($real_images));
    error_log("Total AI images with difficulty '$difficulty': " . count($ai_images));
    
    // Filter out already shown images
    if (!empty($shown_images)) {
        $real_images = array_filter($real_images, function($image) use ($shown_images) {
            return !in_array(basename($image), $shown_images);
        });
        
        $ai_images = array_filter($ai_images, function($image) use ($shown_images) {
            return !in_array(basename($image), $shown_images);
        });
        
        // Re-index arrays
        $real_images = array_values($real_images);
        $ai_images = array_values($ai_images);
        
        error_log("After filtering shown images - Real images: " . count($real_images));
        error_log("After filtering shown images - AI images: " . count($ai_images));
    }
    
    // Check if we have enough images
    if (empty($real_images) || empty($ai_images)) {
        error_log("Not enough images after filtering shown images for difficulty '$difficulty'");
        // If we run out of unique images, reset shown_images and get all images again with same difficulty
        // but preserve the last shown images to prevent immediate duplicates
        $last_shown = array_slice($shown_images, -10); // Keep the 10 most recent images
        
        $real_images = get_available_images('real', $difficulty);
        $ai_images = get_available_images('ai', $difficulty);
        
        // Filter out the last shown images to ensure no immediate duplicates
        if (!empty($last_shown)) {
            $real_images = array_filter($real_images, function($image) use ($last_shown) {
                return !in_array(basename($image), $last_shown);
            });
            
            $ai_images = array_filter($ai_images, function($image) use ($last_shown) {
                return !in_array(basename($image), $last_shown);
            });
            
            // Re-index arrays
            $real_images = array_values($real_images);
            $ai_images = array_values($ai_images);
        }
        
        error_log("Reset shown images (keeping last shown) - Real images: " . count($real_images));
        error_log("Reset shown images (keeping last shown) - AI images: " . count($ai_images));
        
        // If still not enough images with this difficulty, try all images but still avoid most recent ones
        if (empty($real_images) || empty($ai_images)) {
            error_log("Still not enough $difficulty images, falling back to all images but avoiding recent");
            $real_images = get_available_images('real');
            $ai_images = get_available_images('ai');
            
            // Filter out the last shown images to ensure no immediate duplicates
            if (!empty($last_shown)) {
                $real_images = array_filter($real_images, function($image) use ($last_shown) {
                    return !in_array(basename($image), $last_shown);
                });
                
                $ai_images = array_filter($ai_images, function($image) use ($last_shown) {
                    return !in_array(basename($image), $last_shown);
                });
                
                // Re-index arrays
                $real_images = array_values($real_images);
                $ai_images = array_values($ai_images);
            }
            
            error_log("Fallback to all images (keeping last shown) - Real images: " . count($real_images));
            error_log("Fallback to all images (keeping last shown) - AI images: " . count($ai_images));
        }
        
        // Last resort: If still no images, use all available images
        if (empty($real_images) || empty($ai_images)) {
            $real_images = get_available_images('real');
            $ai_images = get_available_images('ai');
            error_log("Last resort: Using all images - Real images: " . count($real_images));
            error_log("Last resort: Using all images - AI images: " . count($ai_images));
        }
    }
    
    // Check again after reset
    if (empty($real_images) || empty($ai_images)) {
        error_log("Still not enough images after all fallbacks");
        return array(null, null);
    }
    
    // Get random images
    $real_index = random_int(0, count($real_images) - 1);
    $ai_index = random_int(0, count($ai_images) - 1);
    
    // Make sure the basenames are not identical (some images may have same filenames in different directories)
    $attempts = 0;
    $max_attempts = 10;
    $real_basename = basename($real_images[$real_index]);
    $ai_basename = basename($ai_images[$ai_index]);
    
    while ($real_basename === $ai_basename && $attempts < $max_attempts && count($ai_images) > 1) {
        error_log("Duplicate filename detected, selecting different AI image");
        $new_ai_index = random_int(0, count($ai_images) - 1);
        // Make sure we don't pick the same index again
        while ($new_ai_index === $ai_index && count($ai_images) > 1) {
            $new_ai_index = random_int(0, count($ai_images) - 1);
        }
        $ai_index = $new_ai_index;
        $ai_basename = basename($ai_images[$ai_index]);
        $attempts++;
    }
    
    // Validate that we have one real and one AI image by checking the database
    $selected_real_image = $real_images[$real_index];
    $selected_ai_image = $ai_images[$ai_index];
    
    // Double-check that the real image is actually from the real folder and the AI image is from the AI folder
    $real_image_info = verify_image_exists_original(basename($selected_real_image));
    $ai_image_info = verify_image_exists_original(basename($selected_ai_image));
    
    // If there's a mismatch in the types, log it and try to fix it
    if ($real_image_info['exists'] && $real_image_info['type'] !== 'real') {
        error_log("ERROR: Image selected as 'real' is actually of type '{$real_image_info['type']}': " . basename($selected_real_image));
        
        // Try to find a truly real image as a replacement
        foreach ($real_images as $potential_real) {
            $check = verify_image_exists_original(basename($potential_real));
            if ($check['exists'] && $check['type'] === 'real') {
                $selected_real_image = $potential_real;
                error_log("Fixed: Replaced mistyped real image with actual real image: " . basename($selected_real_image));
                break;
            }
        }
    }
    
    if ($ai_image_info['exists'] && $ai_image_info['type'] !== 'ai') {
        error_log("ERROR: Image selected as 'AI' is actually of type '{$ai_image_info['type']}': " . basename($selected_ai_image));
        
        // Try to find a truly AI image as a replacement
        foreach ($ai_images as $potential_ai) {
            $check = verify_image_exists_original(basename($potential_ai));
            if ($check['exists'] && $check['type'] === 'ai') {
                $selected_ai_image = $potential_ai;
                error_log("Fixed: Replaced mistyped AI image with actual AI image: " . basename($selected_ai_image));
                break;
            }
        }
    }
    
    // Final verification that we have one real and one AI image
    $final_real_check = verify_image_exists_original(basename($selected_real_image));
    $final_ai_check = verify_image_exists_original(basename($selected_ai_image));
    
    if (!$final_real_check['exists'] || $final_real_check['type'] !== 'real') {
        error_log("CRITICAL ERROR: Could not find a valid real image. Using first available real image.");
        // Emergency fallback: Get first file in real images directory
        $emergency_real = get_first_file_in_directory(REAL_IMAGES_DIR);
        if ($emergency_real) {
            $selected_real_image = $emergency_real;
            error_log("Emergency fallback to real image: " . $selected_real_image);
        }
    }
    
    if (!$final_ai_check['exists'] || $final_ai_check['type'] !== 'ai') {
        error_log("CRITICAL ERROR: Could not find a valid AI image. Using first available AI image.");
        // Emergency fallback: Get first file in AI images directory
        $emergency_ai = get_first_file_in_directory(AI_IMAGES_DIR);
        if ($emergency_ai) {
            $selected_ai_image = $emergency_ai;
            error_log("Emergency fallback to AI image: " . $selected_ai_image);
        }
    }
    
    error_log("Selected real image: " . $selected_real_image);
    error_log("Selected AI image: " . $selected_ai_image);
    
    return array($selected_real_image, $selected_ai_image);
}

/**
 * Get the first image file in a directory
 * 
 * @param string $directory The directory to search in
 * @return string|false Path to the first image file or false if none found
 */
function get_first_file_in_directory($directory) {
    if (!is_dir($directory)) {
        error_log("Directory does not exist: $directory");
        return false;
    }
    
    // Ensure the directory path ends with a slash
    if (substr($directory, -1) !== '/') {
        $directory .= '/';
    }
    
    $files = glob($directory . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    if (!empty($files)) {
        return $files[0];
    }
    
    return false;
}

// Leaderboard functions
/**
 * Verifies if an image exists in the filesystem and checks for duplicates
 * 
 * @param string $filename The image filename to verify
 * @return array Information about the image existence and path
 */
function verify_image_exists_original($filename) {
    if (empty($filename)) {
        return [
            'exists' => false,
            'path' => '',
            'message' => 'Empty filename'
        ];
    }
    
    // Try to find the image in real images directory
    $real_path = REAL_IMAGES_DIR . '/' . $filename;
    if (file_exists($real_path)) {
        return [
            'exists' => true,
            'path' => $real_path,
            'type' => 'real',
            'message' => 'Image found in real directory'
        ];
    }
    
    // Try to find the image in AI images directory
    $ai_path = AI_IMAGES_DIR . '/' . $filename;
    if (file_exists($ai_path)) {
        return [
            'exists' => true,
            'path' => $ai_path,
            'type' => 'ai',
            'message' => 'Image found in AI directory'
        ];
    }
    
    // Image not found in either directory
    return [
        'exists' => false,
        'path' => '',
        'message' => 'Image not found in any directory'
    ];
}

function get_top_leaderboard_entries($limit = 10) {
    $conn = get_db_connection();
    
    // Check if hide_username column exists
    $hide_username_exists = false;
    try {
        $check_column = $conn->query("PRAGMA table_info(users)");
        $columns = $check_column->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $column) {
            if ($column['name'] === 'hide_username') {
                $hide_username_exists = true;
                break;
            }
        }
    } catch (Exception $e) {
        error_log('Error checking for hide_username column: ' . $e->getMessage());
    }
    
    if ($hide_username_exists) {
        $stmt = $conn->prepare("
            SELECT l.*, u.username, u.country, u.avatar, u.hide_username
            FROM leaderboard l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.score > 0 AND (u.is_admin = 0 OR u.is_admin IS NULL)
            ORDER BY l.score DESC
            LIMIT :limit
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT l.*, u.username, u.country, u.avatar, 0 as hide_username
            FROM leaderboard l
            LEFT JOIN users u ON l.user_id = u.id
            WHERE l.score > 0 AND (u.is_admin = 0 OR u.is_admin IS NULL)
            ORDER BY l.score DESC
            LIMIT :limit
        ");
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function add_leaderboard_entry($initials, $email, $score, $game_mode, $difficulty) {
    // Don't add entries with 0 scores
    if ($score <= 0) {
        return array('success' => false, 'message' => 'Scores of 0 or less are not saved to the leaderboard');
    }
    
    $conn = get_db_connection();
    
    // Get current user ID if logged in
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    // Make sure initials is not empty, otherwise use a default
    if (empty($initials)) {
        $initials = "AAA";
    } else {
        // Ensure we have exactly 3 characters (or pad with spaces)
        $initials = substr(strtoupper(trim($initials) . "   "), 0, 3);
    }
    
    // Get current game session ID - first try mode-specific, then fall back to legacy
    $game_session_id = null;
    
    // Map game_mode parameter to session variable suffix
    $mode_map = [
        'single' => 'single',
        'endless' => 'endless',
        'multiplayer' => 'multiplayer'
    ];
    
    // Determine the appropriate session variable name based on game mode
    $session_var = isset($mode_map[$game_mode]) ? 'game_session_id_' . $mode_map[$game_mode] : null;
    
    if ($session_var && isset($_SESSION[$session_var])) {
        // Use mode-specific session ID if available
        $game_session_id = $_SESSION[$session_var];
        error_log("Using mode-specific session ID for leaderboard: $session_var = $game_session_id");
    } else {
        // Fall back to legacy session ID if mode-specific not available
        $game_session_id = isset($_SESSION['game_session_id']) ? $_SESSION['game_session_id'] : null;
        error_log("Using legacy session ID for leaderboard: game_session_id = $game_session_id");
    }
    
    // Debug the initials being saved
    error_log("Saving leaderboard entry with initials: '$initials'");
    
    $stmt = $conn->prepare("INSERT INTO leaderboard (user_id, username, score, game_mode, difficulty, game_session_id) VALUES (:user_id, :username, :score, :game_mode, :difficulty, :game_session_id)");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':username', $initials, PDO::PARAM_STR); // Store initials in username column
    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    $stmt->bindParam(':game_session_id', $game_session_id, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        return array('success' => true, 'message' => 'Score saved to leaderboard');
    } else {
        $errorInfo = $stmt->errorInfo();
        return array('success' => false, 'message' => 'Failed to save score: ' . $errorInfo[2]);
    }
}

/**
 * Get user by ID
 *
 * @param int $user_id User ID to retrieve
 * @return array|false User data if found, false otherwise
 */
function get_user_by_id($user_id) {
    $conn = get_db_connection();
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user ? $user : false;
}

// Admin user management functions
function get_all_users() {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array();
    }
    
    $conn = get_db_connection();
    $stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function delete_user($user_id) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    // Don't allow deletion of admin user
    if ($user_id == $_SESSION['user_id']) {
        return array('success' => false, 'message' => 'Cannot delete your own admin account!');
    }
    
    $conn = get_db_connection();
    
    // Start transaction
    $conn->beginTransaction();
    
    try {
        // Delete user's game data
        $stmt = $conn->prepare("DELETE FROM games WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete user's multiplayer games
        $stmt = $conn->prepare("DELETE FROM multiplayer_players WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete user's leaderboard entries
        $stmt = $conn->prepare("DELETE FROM leaderboard WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Delete user's achievements
        $stmt = $conn->prepare("DELETE FROM user_achievements WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Finally delete the user
        $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        return array('success' => true, 'message' => 'User deleted successfully');
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollBack();
        return array('success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage());
    }
}

function toggle_admin_status($user_id) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    // Don't allow changing own admin status
    if ($user_id == $_SESSION['user_id']) {
        return array('success' => false, 'message' => 'Cannot change your own admin status!');
    }
    
    $conn = get_db_connection();
    
    // Get current admin status
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return array('success' => false, 'message' => 'User not found');
    }
    
    // Toggle admin status
    $new_status = ($user['is_admin'] == 1) ? 0 : 1;
    $status_text = ($new_status == 1) ? 'granted' : 'revoked';
    
    $stmt = $conn->prepare("UPDATE users SET is_admin = :is_admin WHERE id = :user_id");
    $stmt->bindParam(':is_admin', $new_status, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return array('success' => true, 'message' => "Admin privileges {$status_text} successfully", 'is_admin' => $new_status);
    } else {
        return array('success' => false, 'message' => 'Failed to update admin status');
    }
}

/**
 * Toggle user active status (activate/deactivate)
 * @param int $user_id The user ID to toggle active status
 * @return array Result status with success flag and message
 */
function toggle_user_active_status($user_id) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    // Cannot deactivate self
    if ($_SESSION['user_id'] == $user_id) {
        return array('success' => false, 'message' => 'You cannot deactivate your own account!');
    }
    
    $conn = get_db_connection();
    
    // Get current status
    $stmt = $conn->prepare("SELECT active FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        return array('success' => false, 'message' => 'User not found');
    }
    
    // Toggle the status
    $new_status = ($user['active'] == 1) ? 0 : 1;
    $action = ($new_status == 1) ? 'activated' : 'deactivated';
    
    $stmt = $conn->prepare("UPDATE users SET active = :active WHERE id = :user_id");
    $stmt->bindParam(':active', $new_status, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return array('success' => true, 'message' => "User successfully $action");
    } else {
        return array('success' => false, 'message' => 'Failed to update user status');
    }
}

/**
 * Update a user's password
 * @param int $user_id The user ID to update password
 * @param string $new_password The new password
 * @return array Result status with success flag and message
 */
function update_user_password($user_id, $new_password) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    if (strlen($new_password) < 8) {
        return array('success' => false, 'message' => 'Password must be at least 8 characters long');
    }
    
    $conn = get_db_connection();
    
    // Hash the password
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
    $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return array('success' => true, 'message' => 'Password updated successfully');
    } else {
        return array('success' => false, 'message' => 'Failed to update password');
    }
}

// Admin leaderboard management
function delete_leaderboard_entry($entry_id) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    $conn = get_db_connection();
    $stmt = $conn->prepare("DELETE FROM leaderboard WHERE id = :entry_id");
    $stmt->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return array('success' => true, 'message' => 'Leaderboard entry deleted successfully');
    } else {
        return array('success' => false, 'message' => 'Failed to delete leaderboard entry');
    }
}

// Admin image functions
function get_image_counts_by_difficulty() {
    $conn = get_db_connection();
    
    $result = [
        'easy' => ['real' => 0, 'ai' => 0],
        'medium' => ['real' => 0, 'ai' => 0],
        'hard' => ['real' => 0, 'ai' => 0],
        'unrated' => ['real' => 0, 'ai' => 0],
        'total' => ['real' => 0, 'ai' => 0]
    ];
    
    // Query difficulty counts from database
    // For SQLite, the 'type' column contains 'real' or 'ai' values
    $stmt = $conn->query("
        SELECT 
            difficulty, 
            type, 
            COUNT(*) as count
        FROM images
        GROUP BY difficulty, type
    ");
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($rows as $row) {
        $difficulty = $row['difficulty'] ? $row['difficulty'] : 'unrated';
        $type = strtolower($row['type']); // Type column contains 'real' or 'ai' directly 
        $result[$difficulty][$type] = $row['count'];
        $result['total'][$type] += $row['count'];
    }
    
    // Count real images from filesystem that might not be in the database
    $real_images = get_available_images('real');
    $ai_images = get_available_images('ai');
    
    $real_count = count($real_images);
    $ai_count = count($ai_images);
    
    // If filesystem count is higher than database count, add the difference to unrated
    if ($real_count > $result['total']['real']) {
        $result['unrated']['real'] += ($real_count - $result['total']['real']);
        $result['total']['real'] = $real_count;
    }
    
    if ($ai_count > $result['total']['ai']) {
        $result['unrated']['ai'] += ($ai_count - $result['total']['ai']);
        $result['total']['ai'] = $ai_count;
    }
    
    return $result;
}

function upload_image($image_type, $file) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    // Validate image type
    if ($image_type !== 'real' && $image_type !== 'ai') {
        return array('success' => false, 'message' => 'Invalid image type!');
    }
    
    // Get target directory
    $target_dir = ($image_type === 'real') ? REAL_IMAGES_DIR : AI_IMAGES_DIR;
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Check file type
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
    
    if (!in_array($extension, $allowed_extensions)) {
        return array('success' => false, 'message' => 'Invalid file type! Only JPG, PNG, and GIF files are allowed.');
    }
    
    // Find the next available number
    $files = glob($target_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    $existing_numbers = array();
    
    foreach ($files as $existing_file) {
        $filename = basename($existing_file);
        $file_number = intval(pathinfo($filename, PATHINFO_FILENAME));
        if ($file_number > 0) {
            $existing_numbers[] = $file_number;
        }
    }
    
    $next_number = empty($existing_numbers) ? 1 : max($existing_numbers) + 1;
    $new_filename = $next_number . '.' . $extension;
    $target_file = $target_dir . $new_filename;
    
    // Upload the file
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        // Set default difficulty to easy for new images
        set_image_difficulty($target_file, 'easy');
        return array('success' => true, 'message' => 'Image uploaded successfully as ' . $new_filename);
    } else {
        return array('success' => false, 'message' => 'Failed to upload image');
    }
}

function upload_multiple_images($image_type, $files) {
    // Make sure user is admin
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        return array('success' => false, 'message' => 'Access denied!');
    }
    
    // Validate image type
    if ($image_type !== 'real' && $image_type !== 'ai') {
        return array('success' => false, 'message' => 'Invalid image type!');
    }
    
    // Get target directory
    $target_dir = ($image_type === 'real') ? REAL_IMAGES_DIR : AI_IMAGES_DIR;
    
    // Create directory if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }
    
    // Track statistics
    $uploaded_count = 0;
    $failed_count = 0;
    $invalid_count = 0;
    
    // Define allowed extensions
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif');
    
    // Find the next available number to start with
    $existing_files = glob($target_dir . '*.{jpg,jpeg,png,gif}', GLOB_BRACE);
    $existing_numbers = array();
    
    foreach ($existing_files as $existing_file) {
        $filename = basename($existing_file);
        $file_number = intval(pathinfo($filename, PATHINFO_FILENAME));
        if ($file_number > 0) {
            $existing_numbers[] = $file_number;
        }
    }
    
    $next_number = empty($existing_numbers) ? 1 : max($existing_numbers) + 1;
    
    // Process each file
    foreach ($files['name'] as $key => $name) {
        // Skip empty entries
        if (empty($name)) {
            continue;
        }
        
        // Check error code
        if ($files['error'][$key] !== UPLOAD_ERR_OK) {
            $failed_count++;
            continue;
        }
        
        // Check file type
        $file_info = pathinfo($name);
        $extension = strtolower($file_info['extension']);
        
        if (!in_array($extension, $allowed_extensions)) {
            $invalid_count++;
            continue;
        }
        
        // Create a new filename
        $new_filename = $next_number . '.' . $extension;
        $target_file = $target_dir . $new_filename;
        
        // Upload the file
        if (move_uploaded_file($files['tmp_name'][$key], $target_file)) {
            // Set default difficulty to easy for new images
            set_image_difficulty($target_file, 'easy');
            $uploaded_count++;
            $next_number++; // Increment for the next file
        } else {
            $failed_count++;
        }
    }
    
    // Generate result message
    $message = "{$uploaded_count} image" . ($uploaded_count != 1 ? "s" : "") . " uploaded successfully";
    if ($failed_count > 0) {
        $message .= ", {$failed_count} failed";
    }
    if ($invalid_count > 0) {
        $message .= ", {$invalid_count} invalid file type" . ($invalid_count != 1 ? "s" : "");
    }
    
    return array(
        'success' => ($uploaded_count > 0),
        'message' => $message,
        'uploaded' => $uploaded_count,
        'failed' => $failed_count,
        'invalid' => $invalid_count
    );
}

// Game state functions
function get_game_state($session_id, $game_mode = 'single') {
    error_log("get_game_state - Starting for session: " . $session_id . ", mode: " . $game_mode);
    
    try {
        $conn = get_db_connection();
        if (!$conn) {
            error_log("get_game_state - Failed to get database connection");
            return null;
        }
        
        // Validate session ID to prevent SQL injection (even though we're using prepared statements)
        if (!preg_match('/^[a-zA-Z0-9_]{4,64}$/', $session_id)) {
            error_log("get_game_state - Invalid session ID format: " . $session_id);
            return null;
        }
        
        $table = ($game_mode === 'multiplayer') ? 'multiplayer_games' : 'games';
        $sql = "SELECT * FROM $table WHERE session_id = :session_id";
        
        error_log("get_game_state - SQL: " . $sql . " with session_id: " . $session_id);
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("get_game_state - Failed to prepare statement: " . implode(" ", $conn->errorInfo()));
            return null;
        }
        
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        
        if (!$stmt->execute()) {
            error_log("get_game_state - Execute failed: " . implode(" ", $stmt->errorInfo()));
            return null;
        }
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            error_log("get_game_state - No results found for session ID: " . $session_id);
            return null;
        }
        
        error_log("get_game_state - Found game data for session ID: " . $session_id);
        return $result;
    } catch (Exception $e) {
        error_log("get_game_state - Exception: " . $e->getMessage());
        return null;
    }
}

function get_multiplayer_game_state($session_id) {
    return get_game_state($session_id, 'multiplayer');
}

function update_game_state($session_id, $game_data) {
    error_log("update_game_state - Starting for session: " . $session_id);
    
    // Special logging for streak updates
    if (isset($game_data['current_streak'])) {
        error_log("update_game_state - STREAK UPDATE: Session $session_id, setting streak to " . $game_data['current_streak']);
    }
    
    error_log("update_game_state - Game data: " . print_r($game_data, true));
    
    try {
        $conn = get_db_connection();
        if (!$conn) {
            error_log("update_game_state - Failed to get database connection");
            return false;
        }
        
        // Verify game exists first
        $check_stmt = $conn->prepare("SELECT COUNT(*) FROM games WHERE session_id = :session_id");
        $check_stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $check_stmt->execute();
        $game_exists = (int)$check_stmt->fetchColumn();
        
        if ($game_exists === 0) {
            error_log("update_game_state - Game not found for session: " . $session_id);
            return false;
        }
        
        error_log("update_game_state - Game exists, proceeding with update");
        
        // Prepare UPDATE statement based on game data
        $sql = "UPDATE games SET ";
        $placeholders = [];
        $params = [];
        
        // Add each field that exists in the game data
        foreach ($game_data as $key => $value) {
            if ($key !== 'id' && $key !== 'session_id') {
                $placeholders[] = "$key = :$key";
                $params[":$key"] = $value;
                error_log("update_game_state - Adding field: $key with value: " . (is_array($value) ? json_encode($value) : $value));
            }
        }
        
        // Check if we have fields to update
        if (empty($placeholders)) {
            error_log("update_game_state - No fields to update");
            return false;
        }
        
        // Combine placeholders
        $sql .= implode(", ", $placeholders);
        
        // Add WHERE clause
        $sql .= " WHERE session_id = :session_id";
        $params[":session_id"] = $session_id;
        
        // Log the SQL and parameters
        error_log("update_game_state - SQL: " . $sql);
        error_log("update_game_state - Params: " . print_r($params, true));
        
        // Prepare statement
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("update_game_state - Failed to prepare statement: " . implode(" ", $conn->errorInfo()));
            return false;
        }
        
        // Bind all parameters
        foreach ($params as $placeholder => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            }
            $stmt->bindValue($placeholder, $value, $type);
        }
        
        // Execute and check result
        $result = $stmt->execute();
        if (!$result) {
            error_log("update_game_state - Execute failed: " . implode(" ", $stmt->errorInfo()));
            return false;
        }
        
        error_log("update_game_state - Successfully updated game state");
        return true;
    } catch (Exception $e) {
        error_log("update_game_state - Exception: " . $e->getMessage());
        return false;
    }
}

function update_multiplayer_game_state($session_id, $game_data) {
    $conn = get_db_connection();
    
    // Prepare UPDATE statement based on game data
    $sql = "UPDATE multiplayer_games SET ";
    $placeholders = [];
    $params = [];
    
    // Add each field that exists in the game data
    foreach ($game_data as $key => $value) {
        if ($key !== 'id' && $key !== 'session_id') {
            $placeholders[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }
    
    // Combine placeholders
    $sql .= implode(", ", $placeholders);
    
    // Add WHERE clause
    $sql .= " WHERE session_id = :session_id";
    $params[":session_id"] = $session_id;
    
    // Prepare and execute statement
    $stmt = $conn->prepare($sql);
    
    // Bind all parameters
    foreach ($params as $placeholder => $value) {
        $type = PDO::PARAM_STR;
        if (is_int($value)) {
            $type = PDO::PARAM_INT;
        } elseif (is_bool($value)) {
            $type = PDO::PARAM_BOOL;
        } elseif (is_null($value)) {
            $type = PDO::PARAM_NULL;
        }
        $stmt->bindValue($placeholder, $value, $type);
    }
    
    return $stmt->execute();
}

function update_game_score($session_id, $is_correct, $game_mode = 'single', $player_number = 1) {
    $conn = get_db_connection();
    
    if ($game_mode === 'multiplayer') {
        // Get current game state
        $game = get_game_state($session_id, 'multiplayer');
        
        if (!$game) {
            return array('success' => false, 'message' => 'Game not found');
        }
        
        // Update player score
        if ($is_correct) {
            $stmt = $conn->prepare("UPDATE multiplayer_games SET player{$player_number}_score = player{$player_number}_score + 1 WHERE session_id = :session_id");
            $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
            $stmt->execute();
        }
        
        // If player 1 (host) advances the turn
        if ($player_number == 1) {
            $new_turn = $game['current_turn'] + 1;
            
            // Check if game is complete
            $completed = ($new_turn > $game['total_turns']) ? 1 : 0;
            
            $stmt = $conn->prepare("UPDATE multiplayer_games SET current_turn = :current_turn, completed = :completed WHERE session_id = :session_id");
            $stmt->bindParam(':current_turn', $new_turn, PDO::PARAM_INT);
            $stmt->bindParam(':completed', $completed, PDO::PARAM_INT);
            $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
            $stmt->execute();
        }
        
        // Get updated game state
        $updated_game = get_game_state($session_id, 'multiplayer');
        
        return array(
            'success' => true,
            'correct' => $is_correct,
            'scores' => array(
                'player1' => $updated_game['player1_score'],
                'player2' => $updated_game['player2_score'],
                'player3' => $updated_game['player3_score'],
                'player4' => $updated_game['player4_score']
            ),
            'turn' => $updated_game['current_turn'],
            'totalTurns' => $updated_game['total_turns'],
            'completed' => $updated_game['completed'] == 1,
            'playerNumber' => $player_number
        );
    } else {
        // Single player or endless mode
        $game = get_game_state($session_id);
        
        if (!$game) {
            return array('success' => false, 'message' => 'Game not found');
        }
        
        $new_score = $game['score'];
        $new_lives = $game['lives'];
        $new_turn = $game['current_turn'] + 1;
        $completed = 0;
        
        // Update score and lives
        if ($is_correct) {
            $new_score++;
            
            // If this is an endless mode game for a logged-in user, update user's total score in leaderboard
            if ($game['game_mode'] == 'endless' && isset($game['user_id']) && $game['user_id']) {
                // Check if user has an existing leaderboard entry
                $stmt = $conn->prepare("SELECT id, score FROM leaderboard WHERE user_id = :user_id AND difficulty = 'endless' LIMIT 1");
                $stmt->bindParam(':user_id', $game['user_id'], PDO::PARAM_INT);
                $stmt->execute();
                $leaderboard_entry = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($leaderboard_entry) {
                    // Update existing leaderboard entry
                    $stmt = $conn->prepare("UPDATE leaderboard SET score = score + 1 WHERE id = :id");
                    $stmt->bindParam(':id', $leaderboard_entry['id'], PDO::PARAM_INT);
                    $stmt->execute();
                    error_log("Updated endless mode score for user ID {$game['user_id']} in leaderboard, new total: " . ($leaderboard_entry['score'] + 1));
                } else {
                    // Create new leaderboard entry
                    $stmt = $conn->prepare("INSERT INTO leaderboard (user_id, username, score, difficulty, game_session_id, created_at) 
                                           VALUES (:user_id, :username, 1, 'endless', :session_id, :created_at)");
                    $stmt->bindParam(':user_id', $game['user_id'], PDO::PARAM_INT);
                    $stmt->bindParam(':username', $_SESSION['username'], PDO::PARAM_STR);
                    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
                    $stmt->bindParam(':created_at', date('Y-m-d H:i:s'), PDO::PARAM_STR);
                    $stmt->execute();
                    error_log("Created new endless mode leaderboard entry for user ID {$game['user_id']}");
                }
            }
        } else {
            // For wrong answers, reduce lives
            $new_lives--;
            
            // For endless mode specifically, maintain current lives count but don't go below 0
            if ($game['game_mode'] == 'endless') {
                $new_lives = max(0, $new_lives);
                error_log("Endless mode: Wrong answer. Lives reduced to $new_lives");
            }
        }
        
        // Check if game is over
        if ($new_lives <= 0) {
            $completed = 1;
            error_log("Game over: Lives depleted, setting completed=1");
            
            // If this is a completed endless mode game and the player has no lives left,
            // mark completion time
            if ($game['game_mode'] == 'endless') {
                $stmt = $conn->prepare("UPDATE games SET completed_at = :completed_at WHERE session_id = :session_id");
                $completed_at = date('Y-m-d H:i:s');
                $stmt->bindParam(':completed_at', $completed_at, PDO::PARAM_STR);
                $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
                $stmt->execute();
                error_log("Marked endless mode game as completed at $completed_at");
            }
        }
        
        // Check if game is complete based on turns
        if ($game['total_turns'] > 0 && $new_turn > $game['total_turns']) {
            $completed = 1;
        }
        
        // Update game and set last_modified timestamp
        $stmt = $conn->prepare("UPDATE games SET score = :score, lives = :lives, current_turn = :current_turn, completed = :completed, last_modified = :last_modified WHERE session_id = :session_id");
        $stmt->bindParam(':score', $new_score, PDO::PARAM_INT);
        $stmt->bindParam(':lives', $new_lives, PDO::PARAM_INT);
        $stmt->bindParam(':current_turn', $new_turn, PDO::PARAM_INT);
        $stmt->bindParam(':completed', $completed, PDO::PARAM_INT);
        $stmt->bindParam(':last_modified', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            return array(
                'success' => true,
                'correct' => $is_correct,
                'score' => $new_score,
                'lives' => $new_lives,
                'turn' => $new_turn,
                'totalTurns' => $game['total_turns'],
                'completed' => $completed == 1
            );
        } else {
            $errorInfo = $stmt->errorInfo();
            return array('success' => false, 'message' => 'Failed to update game: ' . $errorInfo[2]);
        }
    }
}

// Add new columns to the games table for image state tracking
function update_db_game_schema() {
    $conn = get_db_connection();
    
    // First, check if the columns already exist
    $checkColumns = $conn->query("PRAGMA table_info(games)");
    $columns = $checkColumns->fetchAll(PDO::FETCH_ASSOC);
    
    $columnNames = array_map(function($col) {
        return $col['name'];
    }, $columns);
    
    // Add current_real_image column if not exists
    if (!in_array('current_real_image', $columnNames)) {
        try {
            $conn->exec("ALTER TABLE games ADD COLUMN current_real_image TEXT");
            error_log("Added current_real_image column to games table");
        } catch (PDOException $e) {
            error_log("Error adding current_real_image column: " . $e->getMessage());
        }
    }
    
    // Add current_ai_image column if not exists
    if (!in_array('current_ai_image', $columnNames)) {
        try {
            $conn->exec("ALTER TABLE games ADD COLUMN current_ai_image TEXT");
            error_log("Added current_ai_image column to games table");
        } catch (PDOException $e) {
            error_log("Error adding current_ai_image column: " . $e->getMessage());
        }
    }
    
    // Add left_is_real column if not exists
    if (!in_array('left_is_real', $columnNames)) {
        try {
            $conn->exec("ALTER TABLE games ADD COLUMN left_is_real INTEGER DEFAULT 0");
            error_log("Added left_is_real column to games table");
        } catch (PDOException $e) {
            error_log("Error adding left_is_real column: " . $e->getMessage());
        }
    }
    
    // Add time_penalty column if not exists
    if (!in_array('time_penalty', $columnNames)) {
        try {
            $conn->exec("ALTER TABLE games ADD COLUMN time_penalty INTEGER DEFAULT 0");
            error_log("Added time_penalty column to games table");
        } catch (PDOException $e) {
            error_log("Error adding time_penalty column: " . $e->getMessage());
        }
    }
    
    // Also update multiplayer games table
    $checkColumns = $conn->query("PRAGMA table_info(multiplayer_games)");
    if ($checkColumns) {
        $columns = $checkColumns->fetchAll(PDO::FETCH_ASSOC);
        
        $columnNames = array_map(function($col) {
            return $col['name'];
        }, $columns);
        
        // Add same columns to multiplayer_games if needed
        if (!in_array('current_real_image', $columnNames)) {
            try {
                $conn->exec("ALTER TABLE multiplayer_games ADD COLUMN current_real_image TEXT");
                error_log("Added current_real_image column to multiplayer_games table");
            } catch (PDOException $e) {
                error_log("Error adding current_real_image column to multiplayer: " . $e->getMessage());
            }
        }
        
        if (!in_array('current_ai_image', $columnNames)) {
            try {
                $conn->exec("ALTER TABLE multiplayer_games ADD COLUMN current_ai_image TEXT");
                error_log("Added current_ai_image column to multiplayer_games table");
            } catch (PDOException $e) {
                error_log("Error adding current_ai_image column to multiplayer: " . $e->getMessage());
            }
        }
        
        if (!in_array('left_is_real', $columnNames)) {
            try {
                $conn->exec("ALTER TABLE multiplayer_games ADD COLUMN left_is_real INTEGER DEFAULT 0");
                error_log("Added left_is_real column to multiplayer_games table");
            } catch (PDOException $e) {
                error_log("Error adding left_is_real column to multiplayer: " . $e->getMessage());
            }
        }
        
        if (!in_array('time_penalty', $columnNames)) {
            try {
                $conn->exec("ALTER TABLE multiplayer_games ADD COLUMN time_penalty INTEGER DEFAULT 0");
                error_log("Added time_penalty column to multiplayer_games table");
            } catch (PDOException $e) {
                error_log("Error adding time_penalty column to multiplayer: " . $e->getMessage());
            }
        }
    }
    
    return true;
}

// Duplicate function declarations removed
// These functions (update_current_images, update_time_penalty_flag, reset_current_images)
// were already defined earlier in this file (lines 15-130)

function update_shown_images($session_id, $real_image, $ai_image, $game_mode = 'single') {
    $conn = get_db_connection();
    
    // Get current game state
    if ($game_mode === 'multiplayer') {
        $game = get_game_state($session_id, 'multiplayer');
    } else {
        $game = get_game_state($session_id);
    }
    
    if (!$game) {
        return false;
    }
    
    // Prepare new shown_images string
    $real_basename = basename($real_image);
    $ai_basename = basename($ai_image);
    
    // Check if these images are already in the shown_images list
    $already_shown = false;
    if (!empty($game['shown_images'])) {
        $shown_array = explode(',', $game['shown_images']);
        // Only add if either image is not already in the list
        if (in_array($real_basename, $shown_array) && in_array($ai_basename, $shown_array)) {
            $already_shown = true;
            error_log("update_shown_images - Images already in shown list, not adding duplicates: $real_basename, $ai_basename");
        }
    }
    
    // If images are not already shown, add them to the list
    if (!$already_shown) {
        if (empty($game['shown_images'])) {
            $new_shown_images = $real_basename . ',' . $ai_basename;
        } else {
            $new_shown_images = $game['shown_images'] . ',' . $real_basename . ',' . $ai_basename;
        }
        
        // Update game
        if ($game_mode === 'multiplayer') {
            $stmt = $conn->prepare("UPDATE multiplayer_games SET shown_images = :shown_images WHERE session_id = :session_id");
        } else {
            $stmt = $conn->prepare("UPDATE games SET shown_images = :shown_images, last_modified = :last_modified WHERE session_id = :session_id");
        }
        
        $stmt->bindParam(':shown_images', $new_shown_images, PDO::PARAM_STR);
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        
        // Bind last_modified parameter if this is not a multiplayer game
        if ($game_mode !== 'multiplayer') {
            $current_time = date('Y-m-d H:i:s');
            $stmt->bindParam(':last_modified', $current_time, PDO::PARAM_STR);
        }
        
        $result = $stmt->execute();
        error_log("update_shown_images - Updated shown images to: $new_shown_images");
        return $result;
    }
    
    return true; // Consider it successful if we didn't need to make changes
}

// Misc utility functions
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function secure_redirect($url) {
    // Ensure session data is saved before redirecting
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    // Debug redirect
    error_log("Redirecting to: " . $url . " with session ID: " . session_id());
    
    header("Location: " . $url);
    exit();
}

function flash_message($message, $type = 'info') {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = array();
    }
    
    $_SESSION['flash_messages'][] = array(
        'message' => $message,
        'type' => $type
    );
}

function get_flash_messages() {
    $messages = isset($_SESSION['flash_messages']) ? $_SESSION['flash_messages'] : array();
    
    // Clear messages after retrieving
    $_SESSION['flash_messages'] = array();
    
    return $messages;
}

// Social sharing functions - only define if not already defined
if (!function_exists('generate_social_share_links')) {
    function generate_social_share_links($score, $game_mode, $difficulty = '') {
        $title = urlencode("I scored $score points in $game_mode mode" . ($difficulty ? " on $difficulty difficulty" : "") . " on Real vs AI!");
        $url = urlencode((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
        
        // Generate sharing links
        $facebook_link = "https://www.facebook.com/sharer/sharer.php?u={$url}&quote={$title}";
        $twitter_link = "https://twitter.com/intent/tweet?text={$title}&url={$url}";
        $email_link = "mailto:?subject=" . urlencode("Check out my score on Real vs AI!") . "&body=" . urlencode("I scored $score points in $game_mode mode" . ($difficulty ? " on $difficulty difficulty" : "") . " on Real vs AI! Try to beat my score at: ") . $url;
        
        return [
            'facebook' => $facebook_link,
            'twitter' => $twitter_link,
            'email' => $email_link
        ];
    }
}

// User profile management functions
if (!function_exists('update_user_avatar')) {
    function update_user_avatar($user_id, $avatar) {
        if (!is_logged_in() || $_SESSION['user_id'] != $user_id) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        if (empty($avatar)) {
            return ['success' => false, 'message' => 'No avatar selected'];
        }
        
        $conn = get_db_connection();
        
        $stmt = $conn->prepare("UPDATE users SET avatar = :avatar WHERE id = :user_id");
        $stmt->bindParam(':avatar', $avatar, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            // Update session
            $_SESSION['avatar'] = $avatar;
            return ['success' => true, 'message' => 'Avatar updated successfully'];
        } else {
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => 'Failed to update avatar: ' . $errorInfo[2]];
        }
    }
}

if (!function_exists('update_user_email')) {
    function update_user_email($user_id, $new_email, $current_password) {
        if (!is_logged_in() || $_SESSION['user_id'] != $user_id) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        $conn = get_db_connection();
        
        // Validate email format
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Invalid email format'];
        }
        
        // Check if email is already in use
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
        $stmt->bindParam(':email', $new_email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['success' => false, 'message' => 'Email is already in use by another account'];
        }
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Update email
        $stmt = $conn->prepare("UPDATE users SET email = :email WHERE id = :user_id");
        $stmt->bindParam(':email', $new_email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Email updated successfully'];
        } else {
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => 'Failed to update email: ' . $errorInfo[2]];
        }
    }
}

if (!function_exists('update_user_password')) {
    function update_user_password($user_id, $current_password, $new_password) {
        if (!is_logged_in() || $_SESSION['user_id'] != $user_id) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        // Validate password strength
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'New password must be at least 8 characters long'];
        }
        
        $conn = get_db_connection();
        
        // Verify current password
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!password_verify($current_password, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash and update new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :user_id");
        $stmt->bindParam(':password_hash', $new_password_hash, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Password updated successfully'];
        } else {
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => 'Failed to update password: ' . $errorInfo[2]];
        }
    }
}
}

// Bonus mini-game functions

// Admin dashboard functions
if (!function_exists('clean_old_game_records')) {
    function clean_old_game_records($months_old = 1) {
        if (!is_admin()) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        $conn = get_db_connection();
        
        // Calculate the date threshold (1 month ago by default)
        $threshold_date = date('Y-m-d H:i:s', strtotime("-{$months_old} month"));
        
        try {
            // Begin transaction for data consistency
            $conn->beginTransaction();
            
            // Get count of records to be deleted (for reporting)
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM games WHERE last_modified < :threshold_date");
            $stmt->bindParam(':threshold_date', $threshold_date, PDO::PARAM_STR);
            $stmt->execute();
            $count_result = $stmt->fetch(PDO::FETCH_ASSOC);
            $records_count = $count_result['count'];
            
            // Delete old game records
            $stmt = $conn->prepare("DELETE FROM games WHERE last_modified < :threshold_date");
            $stmt->bindParam(':threshold_date', $threshold_date, PDO::PARAM_STR);
            $stmt->execute();
            
            // Commit the transaction
            $conn->commit();
            
            return [
                'success' => true, 
                'message' => "Successfully deleted {$records_count} game records older than {$months_old} month(s)",
                'records_deleted' => $records_count
            ];
        } catch (Exception $e) {
            // Rollback the transaction on error
            $conn->rollBack();
            return ['success' => false, 'message' => 'Failed to delete old game records: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('delete_leaderboard_entry')) {
    function delete_leaderboard_entry($entry_id) {
        if (!is_admin()) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        $conn = get_db_connection();
        
        // Verify the entry exists
        $stmt = $conn->prepare("SELECT id FROM leaderboard WHERE id = :entry_id");
        $stmt->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Leaderboard entry not found'];
        }
        
        // Delete the entry
        $stmt = $conn->prepare("DELETE FROM leaderboard WHERE id = :entry_id");
        $stmt->bindParam(':entry_id', $entry_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Leaderboard entry deleted successfully'];
        } else {
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => 'Failed to delete leaderboard entry: ' . $errorInfo[2]];
        }
    }
}

if (!function_exists('get_all_users')) {
    function get_all_users() {
        if (!is_admin()) {
            return [];
        }
        
        $conn = get_db_connection();
        
        $stmt = $conn->query("SELECT id, username, email, is_admin, created_at FROM users ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('delete_user')) {
    function delete_user($user_id) {
        if (!is_admin()) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        // Don't allow deleting current user
        if ($user_id == $_SESSION['user_id']) {
            return ['success' => false, 'message' => 'Cannot delete your own account while logged in'];
        }
        
        $conn = get_db_connection();
        
        // Verify the user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Start transaction
        $conn->beginTransaction();
        
        try {
            // Delete user's leaderboard entries
            $stmt = $conn->prepare("DELETE FROM leaderboard WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete user's game data
            $stmt = $conn->prepare("DELETE FROM games WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete user's multiplayer game data
            $stmt = $conn->prepare("DELETE FROM multiplayer_games WHERE creator_id = :user_id OR player_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Delete user's achievements
            $stmt = $conn->prepare("DELETE FROM user_achievements WHERE user_id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Finally, delete the user
            $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            return ['success' => true, 'message' => 'User and all associated data deleted successfully'];
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            return ['success' => false, 'message' => 'Failed to delete user: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('toggle_admin_status')) {
    function toggle_admin_status($user_id) {
        if (!is_admin()) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        // Don't allow changing admin status of current user
        if ($user_id == $_SESSION['user_id']) {
            return ['success' => false, 'message' => 'Cannot change your own admin status while logged in'];
        }
        
        $conn = get_db_connection();
        
        // Get current admin status
        $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $new_status = $user['is_admin'] ? 0 : 1;
        
        // Update admin status
        $stmt = $conn->prepare("UPDATE users SET is_admin = :is_admin WHERE id = :user_id");
        $stmt->bindParam(':is_admin', $new_status, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            $status_text = $new_status ? 'granted' : 'revoked';
            return ['success' => true, 'message' => "Admin privileges $status_text successfully"];
        } else {
            $errorInfo = $stmt->errorInfo();
            return ['success' => false, 'message' => 'Failed to update admin status: ' . $errorInfo[2]];
        }
    }
}

if (!function_exists('set_image_difficulty')) {
    function set_image_difficulty($image_path, $difficulty) {
        if (!is_admin()) {
            return ['success' => false, 'message' => 'Unauthorized access'];
        }
        
        if (!in_array($difficulty, ['easy', 'medium', 'hard'])) {
            return ['success' => false, 'message' => 'Invalid difficulty level'];
        }
        
        if (!file_exists($image_path)) {
            return ['success' => false, 'message' => 'Image file not found'];
        }
        
        // Make sure we're working with a standardized path
        $image_path = str_replace('\\', '/', $image_path);
        
        // Determine if image is real or AI based on path
        $image_type = strpos($image_path, '/real/') !== false ? 'real' : 'ai';
        
        // Get the category (can be enhanced later)
        $category = 'uncategorized';
        
        // Log details for debugging
        error_log("Setting difficulty for image: $image_path");
        error_log("Image type: " . $image_type);
        error_log("Difficulty: $difficulty");
        
        $conn = get_db_connection();
        
        try {
            // Extract filename from path
            $filename = basename($image_path);
            
            // Check if image already exists in database using filename
            $stmt = $conn->prepare("SELECT id FROM images WHERE filename = :filename");
            $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // Update existing record
                $image = $stmt->fetch(PDO::FETCH_ASSOC);
                // Check if image has a valid ID before using it
                if ($image && isset($image['id'])) {
                    $stmt = $conn->prepare("UPDATE images SET difficulty = :difficulty WHERE id = :id");
                    $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $image['id'], PDO::PARAM_INT);
                } else {
                    // Fall back to insert if we couldn't get a valid image ID
                    $stmt = $conn->prepare("INSERT INTO images (filename, type, difficulty, category) 
                                        VALUES (:filename, :type, :difficulty, :category)");
                    $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
                    $stmt->bindParam(':type', $image_type, PDO::PARAM_STR);
                    $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
                    $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                }
            } else {
                // Insert new record
                $stmt = $conn->prepare("INSERT INTO images (filename, type, difficulty, category) 
                                        VALUES (:filename, :type, :difficulty, :category)");
                $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
                $stmt->bindParam(':type', $image_type, PDO::PARAM_STR);
                $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, PDO::PARAM_STR);
            }
            
            if ($stmt->execute()) {
                return ['success' => true, 'message' => "Image difficulty set to $difficulty successfully"];
            } else {
                $errorInfo = $stmt->errorInfo();
                return ['success' => false, 'message' => 'Failed to set image difficulty: ' . $errorInfo[2]];
            }
        } catch (PDOException $e) {
            error_log("Database error in set_image_difficulty: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
}
function should_show_bonus_game($game) {
    // Don't show bonus game on hard difficulty
    if ($game['difficulty'] === 'hard') {
        return false;
    }
    
    // Show bonus game every 10 turns
    if ($game['current_turn'] % 10 !== 0) {
        return false;
    }
    
    // Only show if player has fewer than starting lives
    $starting_lives = get_starting_lives_for_difficulty($game['difficulty']);
    if ($game['lives'] >= $starting_lives) {
        return false;
    }
    
    return true;
}

function get_starting_lives_for_difficulty($difficulty) {
    global $game_config;
    
    if (isset($game_config['difficulties'][$difficulty])) {
        return $game_config['difficulties'][$difficulty]['lives'];
    }
    
    // Default to 3 lives if difficulty not found
    return 3;
}

function get_bonus_game_images() {
    // Get 3 AI images and 1 real image for the bonus game
    $real_images = get_available_images('real');
    $ai_images = get_available_images('ai');
    
    if (empty($real_images) || count($ai_images) < 3) {
        return null;
    }
    
    // Get random images
    $real_index = random_int(0, count($real_images) - 1);
    $selected_real = $real_images[$real_index];
    
    // Get 3 random AI images
    $selected_ai = [];
    $ai_count = count($ai_images);
    
    // Make sure we have at least 3 AI images
    if ($ai_count < 3) {
        return null;
    }
    
    // Randomly select 3 unique AI images
    $ai_indices = array_rand($ai_images, 3);
    if (!is_array($ai_indices)) {
        $ai_indices = [$ai_indices];
    }
    
    foreach ($ai_indices as $index) {
        $selected_ai[] = $ai_images[$index];
    }
    
    // Combine and shuffle
    $all_images = array_merge([$selected_real], $selected_ai);
    $correct_index = 0; // Real image is at index 0 before shuffling
    
    // Shuffle images and track which one is the real one
    $indices = range(0, 3);
    shuffle($indices);
    
    $shuffled_images = [];
    foreach ($indices as $i => $original_index) {
        $shuffled_images[$i] = $all_images[$original_index];
        if ($original_index === $correct_index) {
            $correct_index = $i; // Update correct index after shuffling
        }
    }
    
    return [
        'images' => $shuffled_images,
        'correct_index' => $correct_index
    ];
}

function process_bonus_game_answer($session_id, $selected_index, $correct_index) {
    $conn = get_db_connection();
    
    // Get current game state
    $game = get_game_state($session_id);
    
    if (!$game) {
        return ['success' => false, 'message' => 'Game not found'];
    }
    
    $is_correct = ($selected_index == $correct_index);
    $current_score = $game['score'];
    $current_lives = $game['lives'];
    
    if ($is_correct) {
        // Award an extra life
        $new_lives = $current_lives + 1;
        $message = "Correct! You earned an extra life!";
        
        $stmt = $conn->prepare("UPDATE games SET lives = :lives WHERE session_id = :session_id");
        $stmt->bindParam(':lives', $new_lives, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
    } else {
        // Penalty: lose half the score (rounded up)
        $penalty = ceil($current_score / 2);
        $new_score = max(0, $current_score - $penalty);
        $message = "Wrong! You lost " . $penalty . " points.";
        
        $stmt = $conn->prepare("UPDATE games SET score = :score WHERE session_id = :session_id");
        $stmt->bindParam(':score', $new_score, PDO::PARAM_INT);
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
    }
    
    // Get updated game state
    $updated_game = get_game_state($session_id);
    
    return [
        'success' => true,
        'correct' => $is_correct,
        'message' => $message,
        'score' => $updated_game['score'],
        'lives' => $updated_game['lives']
    ];
}

// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Track the count of times a user visits a specific route in a session
 * Useful for tracking if the user is refreshing a page repeatedly
 * 
 * @param string $route The route being accessed
 * @return int The number of times the user has visited this route in the current session
 */
function track_page_visits($route) {
    if (!isset($_SESSION['page_visits'])) {
        $_SESSION['page_visits'] = array();
    }
    
    if (!isset($_SESSION['page_visits'][$route])) {
        $_SESSION['page_visits'][$route] = 0;
    }
    
    $_SESSION['page_visits'][$route]++;
    
    return $_SESSION['page_visits'][$route];
}

/**
 * Check if the current user has any unfinished endless mode games they can resume
 * @param int $user_id The user ID to check, or null for anonymous users
 * @return array|null The game data or null if no resumable game
 */
function get_resumable_single_player_game($user_id, $difficulty = null) {
    // Skip for anonymous users
    if (!$user_id) {
        return null;
    }
    
    $conn = get_db_connection();
    
    // Prepare the base query
    $sql = "
        SELECT * FROM games 
        WHERE user_id = :user_id 
        AND game_mode = 'single' 
        AND completed = 0
    ";
    
    // Add difficulty filter if specified
    if ($difficulty) {
        $sql .= " AND difficulty = :difficulty";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($difficulty) {
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        error_log("No resumable single player game found for user $user_id" . ($difficulty ? " with difficulty $difficulty" : ""));
        return null;
    }
    
    // Check if the game was recently played (within the last 24 hours)
    // We don't want to resume very old games
    $last_updated = new DateTime($game['updated_at'] ?? $game['created_at']);
    $now = new DateTime();
    $interval = $now->diff($last_updated);
    
    // If the game is older than 24 hours, mark it as expired and return null
    if ($interval->days > 0) {
        // Mark old games as expired
        $mark_expired = $conn->prepare("
            UPDATE games 
            SET completed = 1, completed_at = CURRENT_TIMESTAMP, game_status = 'expired'
            WHERE id = :game_id
        ");
        $mark_expired->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $mark_expired->execute();
        
        error_log("Marked old single player game as expired: " . $game['id']);
        return null;
    }
    
    // Store the session ID in the mode-specific session variable
    if (isset($_SESSION)) {
        $_SESSION['game_session_id_single'] = $game['session_id'];
        error_log("Function get_resumable_single_player_game - Set mode-specific session ID: " . $game['session_id']);
    }
    
    return $game;
}

function get_resumable_endless_game($user_id) {
    // Skip for anonymous users
    if (!$user_id) {
        error_log("No user_id provided to get_resumable_endless_game, skipping");
        return null;
    }
    
    $conn = get_db_connection();
    
    // Look for unfinished endless mode games
    $stmt = $conn->prepare("
        SELECT * FROM games 
        WHERE user_id = :user_id 
        AND game_mode = 'endless' 
        AND completed = 0
        ORDER BY created_at DESC
        LIMIT 1
    ");
    
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$game) {
        error_log("No resumable endless game found for user {$user_id}");
        return null;
    }
    
    error_log("Found resumable endless game for user {$user_id}: " . print_r($game, true));
    
    // Check if the game was recently played (within the last 24 hours)
    // We don't want to resume very old games
    $last_updated = new DateTime($game['updated_at'] ?? $game['created_at']);
    $now = new DateTime();
    $interval = $now->diff($last_updated);
    
    // If the game is older than 24 hours, mark it as expired and return null
    if ($interval->days > 0) {
        error_log("Endless game {$game['session_id']} is too old ({$interval->days} days), marking as expired");
        
        // Mark old endless games as expired
        $mark_expired = $conn->prepare("
            UPDATE games 
            SET completed = 1, completed_at = CURRENT_TIMESTAMP, game_status = 'expired'
            WHERE id = :game_id
        ");
        $mark_expired->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
        $mark_expired->execute();
        
        return null;
    }
    
    // Store the session ID in the mode-specific session variable AND the legacy variable
    // This ensures both old and new code paths can find the session
    if (isset($_SESSION)) {
        $_SESSION['game_session_id_endless'] = $game['session_id'];
        $_SESSION['game_session_id'] = $game['session_id']; // Also set in legacy variable for compatibility
        error_log("Function get_resumable_endless_game - Set mode-specific session ID: " . $game['session_id']);
        error_log("Function get_resumable_endless_game - Also set legacy session ID: " . $game['session_id']);
    }
    
    return $game;
}

/**
 * Verify if an image exists in the filesystem
 * 
 * @param string $image_name The image filename to check
 * @return array Information about the image status
 */
function verify_image_exists($image_name) {
    // Check if the image name is valid
    if (empty($image_name) || !is_string($image_name)) {
        return [
            'exists' => false,
            'message' => 'Invalid image name',
            'path' => null
        ];
    }
    
    // Define image directory constants if not already defined
    if (!defined('REAL_IMAGES_DIR')) {
        define('REAL_IMAGES_DIR', ROOT_DIR . '/uploads/real');
    }
    if (!defined('AI_IMAGES_DIR')) {
        define('AI_IMAGES_DIR', ROOT_DIR . '/uploads/ai');
    }
    
    // Try to determine if this is a real or AI image based on file extension
    // This is not foolproof but serves as a simple heuristic
    $is_likely_real = (pathinfo($image_name, PATHINFO_EXTENSION) === 'jpg');
    
    // Check both directories
    $real_image_path = REAL_IMAGES_DIR . '/' . $image_name;
    $ai_image_path = AI_IMAGES_DIR . '/' . $image_name;
    
    // First check in the more likely directory
    if ($is_likely_real) {
        if (file_exists($real_image_path)) {
            return [
                'exists' => true,
                'message' => 'Real image exists',
                'path' => $real_image_path
            ];
        } else if (file_exists($ai_image_path)) {
            return [
                'exists' => true,
                'message' => 'Found in AI directory despite jpg extension',
                'path' => $ai_image_path
            ];
        }
    } else {
        if (file_exists($ai_image_path)) {
            return [
                'exists' => true,
                'message' => 'AI image exists',
                'path' => $ai_image_path
            ];
        } else if (file_exists($real_image_path)) {
            return [
                'exists' => true,
                'message' => 'Found in real directory despite png extension',
                'path' => $real_image_path
            ];
        }
    }
    
    // If not found, check fallback locations
    $fallback_real = ROOT_DIR . '/static/images/real/' . $image_name;
    $fallback_ai = ROOT_DIR . '/static/images/ai/' . $image_name;
    
    if (file_exists($fallback_real)) {
        return [
            'exists' => true,
            'message' => 'Real image exists in fallback location',
            'path' => $fallback_real
        ];
    } else if (file_exists($fallback_ai)) {
        return [
            'exists' => true,
            'message' => 'AI image exists in fallback location',
            'path' => $fallback_ai
        ];
    }
    
    // Image not found anywhere
    return [
        'exists' => false,
        'message' => 'Image not found',
        'path' => null
    ];
}

/**
 * Cleanup old game records from the games table
 * Removes games older than the specified number of days
 * 
 * @param int $days Number of days to keep (default 30)
 * @return array Results with success flag and stats
 */
function cleanup_old_game_records($days = 30) {
    try {
        $conn = get_db_connection();
        
        // Calculate the cutoff date (X days ago)
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Start a transaction
        $conn->beginTransaction();
        
        // Count records before deletion (for reporting)
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM games WHERE last_modified < :cutoff_date");
        $count_stmt->bindParam(':cutoff_date', $cutoff_date, PDO::PARAM_STR);
        $count_stmt->execute();
        $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
        $records_to_delete = $count_result['total'];
        
        // Execute deletion - only delete where last_modified is older than cutoff
        $delete_stmt = $conn->prepare("DELETE FROM games WHERE last_modified < :cutoff_date");
        $delete_stmt->bindParam(':cutoff_date', $cutoff_date, PDO::PARAM_STR);
        $delete_stmt->execute();
        
        // Get number of affected rows
        $deleted_count = $delete_stmt->rowCount();
        
        // Commit the transaction
        $conn->commit();
        
        return [
            'success' => true,
            'message' => "Successfully deleted {$deleted_count} game records older than {$days} days",
            'records_deleted' => $deleted_count,
            'cutoff_date' => $cutoff_date
        ];
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        error_log("Error cleaning up old game records: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => "Error deleting old game records: " . $e->getMessage(),
            'records_deleted' => 0
        ];
    }
}

/**
 * Get a setting from the settings table
 * 
 * @param string $name The name of the setting
 * @param mixed $default The default value to return if setting doesn't exist
 * @return mixed The value of the setting or the default
 */
function get_setting($name, $default = null) {
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT value FROM settings WHERE name = :name");
        $stmt->execute([':name' => $name]);
        $setting = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($setting) {
            return $setting['value'];
        }
    } catch (PDOException $e) {
        error_log("Error getting setting {$name}: " . $e->getMessage());
    }
    
    return $default;
}

/**
 * Update a setting in the settings table
 * 
 * @param string $name The name of the setting
 * @param mixed $value The new value for the setting
 * @return bool True if successful, false otherwise
 */
function update_setting($name, $value) {
    try {
        $conn = get_db_connection();
        
        // Get the proper parameter type for binding
        $param_type = PDO::PARAM_STR; // Default to string
        if (is_bool($value) || is_int($value) || $name === 'debug_mode') {
            $param_type = PDO::PARAM_INT;
            // Convert to integer explicitly for boolean values or debug_mode setting
            if (is_bool($value)) {
                $value = (int)$value;
            } else if ($name === 'debug_mode') {
                $value = (int)$value;
            }
        }
        
        error_log("Updating setting: {$name} with value: {$value} (type: " . gettype($value) . ", param_type: {$param_type})");
        
        // Check if setting exists
        $stmt = $conn->prepare("SELECT COUNT(*) FROM settings WHERE name = :name");
        $stmt->bindParam(':name', $name, PDO::PARAM_STR);
        $stmt->execute();
        $exists = (int)$stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Update existing setting
            $stmt = $conn->prepare("UPDATE settings SET value = :value, updated_at = CURRENT_TIMESTAMP WHERE name = :name");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':value', $value, $param_type);
            $stmt->execute();
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO settings (name, value) VALUES (:name, :value)");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':value', $value, $param_type);
            $stmt->execute();
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating setting {$name}: " . $e->getMessage());
        return false;
    }
}

/**
 * Anti-Cheat System - Score Security Functions
 * These functions implement Hash-Based Verification, Timestamps/Nonces, 
 * and Encryption to prevent score tampering.
 */

/**
 * Generate a secure hash for verifying score integrity
 * Uses the user's ID, score value, current timestamp, and a server secret
 * 
 * @param int $score The current score
 * @param int $user_id The user ID
 * @param int $timestamp The timestamp when the score was achieved (default: current time)
 * @return array An array with 'hash', 'timestamp', and 'encoded' values
 */
function generate_score_hash($score, $user_id, $timestamp = null) {
    // Use current time if no timestamp provided
    if ($timestamp === null) {
        $timestamp = time();
    }
    
    // Create a nonce that changes with each request to prevent replay attacks
    $nonce = bin2hex(random_bytes(8));
    
    // Get the secret key from environment variable (set in .htaccess)
    $secret_key = getenv('SCORE_SECRET_KEY');
    
    // Fallback if environment variable is not available
    if (empty($secret_key)) {
        $secret_key = defined('SERVER_SECRET') ? SERVER_SECRET : 'G7&^32@!03920AnRt9@!';
        error_log("Warning: Using fallback secret key - SCORE_SECRET_KEY environment variable not found");
    }
    
    // Combine all data for the hash
    $data_to_hash = $score . '|' . $user_id . '|' . $timestamp . '|' . $nonce . '|' . $secret_key;
    
    // Generate hash using SHA-256
    $hash = hash('sha256', $data_to_hash);
    
    // Create an encoded version that includes the data and hash
    // This is what we'll send to the client
    $encoded_data = base64_encode(json_encode([
        'score' => $score,
        'user_id' => $user_id,
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'hash' => $hash
    ]));
    
    return [
        'hash' => $hash,
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'encoded' => $encoded_data
    ];
}

/**
 * Verify a score hash to ensure it hasn't been tampered with
 * 
 * @param string $encoded_data The encoded data containing score info and hash
 * @param int $expected_score The expected score value (optional)
 * @param int $expected_user_id The expected user ID (optional)
 * @param int $max_age Maximum age of the hash in seconds (default: 300 - 5 minutes)
 * @return array Result with 'valid' status and score data if valid
 */
function verify_score_hash($encoded_data, $expected_score = null, $expected_user_id = null, $max_age = 300) {
    try {
        // Decode the data
        $decoded = json_decode(base64_decode($encoded_data), true);
        
        if (!$decoded || !isset($decoded['hash']) || !isset($decoded['score']) || 
            !isset($decoded['user_id']) || !isset($decoded['timestamp']) || !isset($decoded['nonce'])) {
            return ['valid' => false, 'reason' => 'Invalid hash format'];
        }
        
        // Extract data
        $score = $decoded['score'];
        $user_id = $decoded['user_id'];
        $timestamp = $decoded['timestamp'];
        $nonce = $decoded['nonce'];
        $received_hash = $decoded['hash'];
        
        // Check if hash is too old (prevents using old valid hashes)
        $current_time = time();
        if ($current_time - $timestamp > $max_age) {
            return ['valid' => false, 'reason' => 'Hash expired', 'age' => ($current_time - $timestamp)];
        }
        
        // Verify expected values if provided
        if ($expected_score !== null && $score !== $expected_score) {
            return ['valid' => false, 'reason' => 'Score mismatch'];
        }
        
        if ($expected_user_id !== null && $user_id !== $expected_user_id) {
            return ['valid' => false, 'reason' => 'User ID mismatch'];
        }
        
        // Get the secret key from environment variable (set in .htaccess)
        $secret_key = getenv('SCORE_SECRET_KEY');
        
        // Fallback if environment variable is not available
        if (empty($secret_key)) {
            $secret_key = defined('SERVER_SECRET') ? SERVER_SECRET : 'G7&^32@!03920AnRt9@!';
            error_log("Warning: Using fallback secret key in verify_score_hash - SCORE_SECRET_KEY environment variable not found");
        }
        
        // Recreate the hash using the same method
        $data_to_hash = $score . '|' . $user_id . '|' . $timestamp . '|' . $nonce . '|' . $secret_key;
        $calculated_hash = hash('sha256', $data_to_hash);
        
        // Compare the calculated hash with the received hash
        if ($calculated_hash !== $received_hash) {
            return ['valid' => false, 'reason' => 'Hash verification failed'];
        }
        
        // Hash is valid
        return [
            'valid' => true,
            'score' => $score,
            'user_id' => $user_id,
            'timestamp' => $timestamp,
            'age' => ($current_time - $timestamp)
        ];
    } catch (Exception $e) {
        return ['valid' => false, 'reason' => 'Exception: ' . $e->getMessage()];
    }
}

/**
 * Obfuscate the client-side score calculation logic
 * This makes it harder for cheaters to understand how scores are calculated
 * 
 * @param int $basePoints The base points for a correct answer
 * @param int $timeBonus Any time-based bonus points
 * @param int $streakBonus Any streak-based bonus points
 * @return string Obfuscated JavaScript code for score calculation
 */
function get_obfuscated_score_logic($basePoints = 10, $timeBonus = 0, $streakBonus = 0) {
    // Create obfuscated variable names
    $vars = [
        'basePoints' => '_' . bin2hex(random_bytes(3)),
        'timeBonus' => '_' . bin2hex(random_bytes(3)),
        'streakBonus' => '_' . bin2hex(random_bytes(3)),
        'totalScore' => '_' . bin2hex(random_bytes(3))
    ];
    
    // Generate a random operation sequence that results in the same calculation
    // but makes it harder to understand at a glance
    $operations = [
        // Complex calculation that equals basePoints
        "{$vars['basePoints']} = " . ($basePoints + 5) . " - " . 5 . ";",
        
        // Complex calculation that equals timeBonus
        "{$vars['timeBonus']} = " . ($timeBonus * 2) . " / " . 2 . ";",
        
        // Complex calculation that equals streakBonus
        "{$vars['streakBonus']} = " . ($streakBonus + 3) . " - " . 3 . ";",
        
        // Final calculation with extra steps
        "{$vars['totalScore']} = {$vars['basePoints']} + ({$vars['timeBonus']} * 1) + " .
        "Math.floor({$vars['streakBonus']} + 0.5);"
    ];
    
    // Shuffle the operations (except the last one)
    $firstThree = array_slice($operations, 0, 3);
    shuffle($firstThree);
    $operations = array_merge($firstThree, [end($operations)]);
    
    // Add dummy calculations that don't affect the result
    $dummy1 = '_' . bin2hex(random_bytes(3));
    $dummy2 = '_' . bin2hex(random_bytes(3));
    array_splice($operations, mt_rand(0, 2), 0, [
        "$dummy1 = (new Date()).getTime() % 1000;",
        "$dummy2 = $dummy1 > 500 ? 1 : 0;"
    ]);
    
    // Return the obfuscated code
    return implode("\n    ", $operations) . "\n    return {$vars['totalScore']};";
}

/**
 * Encrypt data for secure transmission
 * 
 * @param mixed $data The data to encrypt (will be JSON encoded)
 * @param string $key Encryption key (optional, will use a default if not provided)
 * @return string Encrypted and base64 encoded string
 */
function encrypt_game_data($data, $key = null) {
    // Use provided key or generate a default one based on the date
    if ($key === null) {
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'RealVsAI_Encryption_' . date('Ymd');
    }
    
    // Convert data to JSON
    $json = json_encode($data);
    
    // Generate a random IV (Initialization Vector)
    $iv_size = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_size);
    
    // Encrypt the data
    $encrypted = openssl_encrypt($json, 'aes-256-cbc', $key, 0, $iv);
    
    // Combine IV and encrypted data and encode for safe transmission
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt data received from the client
 * 
 * @param string $encrypted_data The encrypted and base64 encoded string
 * @param string $key Encryption key (optional, will use a default if not provided)
 * @return mixed The decrypted data, or false on failure
 */
function decrypt_game_data($encrypted_data, $key = null) {
    // Use provided key or generate a default one based on the date
    if ($key === null) {
        $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : 'RealVsAI_Encryption_' . date('Ymd');
    }
    
    try {
        // Decode the base64 data
        $decoded = base64_decode($encrypted_data);
        
        // Extract the IV (first part of the decoded data)
        $iv_size = openssl_cipher_iv_length('aes-256-cbc');
        $iv = substr($decoded, 0, $iv_size);
        
        // Extract the encrypted data (remaining part)
        $encrypted = substr($decoded, $iv_size);
        
        // Decrypt the data
        $decrypted = openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        
        // Convert from JSON back to original data structure
        return json_decode($decrypted, true);
    } catch (Exception $e) {
        error_log("Error decrypting data: " . $e->getMessage());
        return false;
    }
}

/**
 * Update just the streak counter for a game
 * 
 * @param string $session_id The game session ID
 * @param int $new_streak_value The new streak value to set
 * @return bool Success status
 */
function update_streak($session_id, $new_streak_value) {
    error_log("update_streak - Setting streak to $new_streak_value for session $session_id");
    
    try {
        $conn = get_db_connection();
        if (!$conn) {
            error_log("update_streak - Failed to get database connection");
            return false;
        }
        
        // Directly update just the streak value
        $stmt = $conn->prepare("UPDATE games SET current_streak = :current_streak WHERE session_id = :session_id");
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->bindValue(':current_streak', intval($new_streak_value), PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("update_streak - Failed to update streak for session ID: " . $session_id);
            error_log("update_streak - Error: " . print_r($stmt->errorInfo(), true));
            return false;
        }
        
        error_log("update_streak - Successfully updated streak to: " . $new_streak_value);
        return true;
    } catch (PDOException $e) {
        error_log("update_streak - Error: " . $e->getMessage());
        return false;
    }
}

?>