<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_message('Invalid request method', 'danger');
    secure_redirect('index.php');
    exit;
}

// Check if user is logged in - only logged in users can submit scores
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    flash_message('You must be logged in to submit scores to the leaderboard', 'danger');
    secure_redirect('login.php');
    exit;
}

// Get form data
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$score = isset($_POST['score']) ? intval($_POST['score']) : 0;
$game_mode = isset($_POST['game_mode']) ? $_POST['game_mode'] : '';
$difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : '';
$skip_wait = isset($_POST['skip_wait']) ? (bool)$_POST['skip_wait'] : false;

// For endless mode, set the difficulty explicitly to 'endless'
if ($game_mode === 'endless') {
    $difficulty = 'endless';
    error_log("submit_score.php - This is an endless mode score, setting difficulty to 'endless'");
}

// For multiplayer mode, ensure mode is set correctly
if ($game_mode === 'multiplayer' || isset($_POST['mode']) && $_POST['mode'] === 'multiplayer') {
    $game_mode = 'multiplayer';
    error_log("submit_score.php - This is a multiplayer mode score");
    
    // If skip_wait is set, the user chose to submit their score early
    if ($skip_wait) {
        error_log("submit_score.php - User is skipping the wait period in multiplayer");
    }
}

// Now that only logged-in users can submit scores, we can use their username directly
$initials = $_SESSION['username'];

// ANTI-CHEAT: Verify the score hash
$score_hash = isset($_POST['score_hash']) ? $_POST['score_hash'] : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

// If a hash was provided, verify it
$score_verified = false;
$original_score = $score;

if (!empty($score_hash)) {
    error_log("submit_score.php - Verifying score hash for score $score");
    // Note the updated parameter order to match the existing function
    $hash_result = verify_score_hash($score_hash, $score, $user_id, 300);
    
    if ($hash_result['valid']) {
        $score_verified = true;
        // Use the score from the hash to prevent tampering
        $verified_score = $hash_result['score'];
        
        // If the submitted score doesn't match the verified score from the hash
        if ($score !== $verified_score) {
            error_log("submit_score.php - SCORE TAMPERING DETECTED: Form score ($score) does not match verified hash score ({$verified_score})");
            $score = $verified_score; // Override with verified score
        }
        
        error_log("submit_score.php - Score successfully verified via hash");
    } else {
        error_log("submit_score.php - Score hash verification failed: " . ($hash_result['reason'] ?? 'Unknown reason'));
        // We'll continue, but log this suspicious activity
        error_log("SUSPICIOUS ACTIVITY - Possible score tampering attempt for user: $user_id");
    }
} else {
    error_log("submit_score.php - No score hash provided for verification");
}

// Validate input
$errors = [];

// Validate username length (should always be valid since it comes from the database)
if (empty($initials) || strlen($initials) > 20) {
    $errors[] = 'Username must be 1-20 characters';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Please enter a valid email address';
}

if ($score <= 0) {
    $errors[] = 'Invalid score';
}

if (!in_array($game_mode, ['single', 'multiplayer', 'endless'])) {
    $errors[] = 'Invalid game mode';
}

// If there are errors, redirect back
if (!empty($errors)) {
    foreach ($errors as $error) {
        flash_message($error, 'danger');
    }
    secure_redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    exit;
}

// Add to leaderboard
$result = add_leaderboard_entry($initials, $email, $score, $game_mode, $difficulty);

if ($result['success']) {
    flash_message($result['message'], 'success');
    
    // Check and award achievements (all users are logged in due to our earlier check)
    $user_id = $_SESSION['user_id'];
    
    // Get the session_id that was just used before being cleared
    $game_session_id = isset($_SESSION['game_session_id_' . $game_mode]) ? 
                       $_SESSION['game_session_id_' . $game_mode] : 
                       (isset($_SESSION['game_session_id']) ? $_SESSION['game_session_id'] : null);
                       
    // If we have the game session ID, get the actual game state to get accurate lives remaining
    $lives_remaining = 0;
    $starting_lives = 0;
    $game_lives_data = null;
    
    if ($game_session_id) {
        error_log("submit_score.php - Getting actual game data for achievements using session ID: $game_session_id");
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT lives, starting_lives FROM games WHERE session_id = ? LIMIT 1");
            $stmt->execute([$game_session_id]);
            $game_lives_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($game_lives_data) {
                $lives_remaining = $game_lives_data['lives'];
                $starting_lives = $game_lives_data['starting_lives'];
                error_log("submit_score.php - Found game data with lives remaining: $lives_remaining out of $starting_lives starting lives");
            } else {
                error_log("submit_score.php - Could not find game data for session ID: $game_session_id");
            }
        } catch (Exception $e) {
            error_log("submit_score.php - Error getting game data: " . $e->getMessage());
        }
    } else {
        // If session ID not available, use the most recent completed game for this user
        error_log("submit_score.php - No session ID available, using most recent completed game");
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("
                SELECT lives, starting_lives 
                FROM games 
                WHERE user_id = ? AND completed = 1 
                ORDER BY completed_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $game_lives_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($game_lives_data) {
                $lives_remaining = $game_lives_data['lives'];
                $starting_lives = $game_lives_data['starting_lives'];
                error_log("submit_score.php - Found most recent completed game with lives: $lives_remaining of $starting_lives");
            }
        } catch (Exception $e) {
            error_log("submit_score.php - Error getting recent game: " . $e->getMessage());
        }
    }
    
    // Include complete game data for achievement checks
    $game_data = [
        'score' => $score,
        'game_mode' => $game_mode,
        'difficulty' => $difficulty,
        'completed' => true,
        'lives_remaining' => $lives_remaining,
        'lives_total' => $starting_lives,
        'session_id' => $game_session_id
    ];
    
    // Directly check for specific achievements based on game completion

    // 1. Check for "Beginner Detective" - if this is a completed single player easy game
    if ($game_mode === 'single' && $difficulty === 'easy') {
        error_log("submit_score.php - Checking for Beginner Detective achievement (complete_easy)");
        if (award_achievement_model($user_id, 'complete_easy')) {
            error_log("submit_score.php - Achievement awarded: complete_easy (Beginner Detective)");
        }
        
        // Also update the database to mark the game as completed
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("
                UPDATE games 
                SET completed = 1, 
                    completed_at = CURRENT_TIMESTAMP 
                WHERE user_id = ? AND game_mode = 'single' AND difficulty = 'easy'
                AND session_id = ?
            ");
            if (!empty($game_session_id)) {
                $stmt->execute([$user_id, $game_session_id]);
                error_log("submit_score.php - Marked single player easy game as completed in database");
            }
        } catch (Exception $e) {
            error_log("submit_score.php - Error marking game as completed: " . $e->getMessage());
        }
    }
    
    // 2. Check for "Flawless Victory" - if lives_remaining equals lives_total
    if ($lives_remaining == $starting_lives && $starting_lives > 0) {
        error_log("submit_score.php - Checking for Flawless Victory achievement (perfect_score): $lives_remaining of $starting_lives lives");
        if (award_achievement_model($user_id, 'perfect_score')) {
            error_log("submit_score.php - Achievement awarded: perfect_score (Flawless Victory)");
        }
    }
    
    // Award other achievements through the regular function
    $achievements_awarded = check_and_award_achievements($user_id, $game_data);
    
    // Also check for the Frog Explorer avatar reward if this is a single player game
    if ($game_mode === 'single') {
        try {
            require_once 'includes/daily_rewards_functions.php';
            $db = get_db_connection();
            $unlocked_rewards = [];
            $frog_result = check_and_award_frog_explorer_reward($db, $user_id, $unlocked_rewards);
            
            if ($frog_result && !empty($unlocked_rewards)) {
                error_log("submit_score.php - Awarded Frog Explorer avatar for playing all difficulties");
                // Store the unlocked avatar in session for display
                $_SESSION['unlocked_avatars'] = $unlocked_rewards;
            }
        } catch (Exception $e) {
            error_log("submit_score.php - Error checking for Frog Explorer avatar: " . $e->getMessage());
        }
    }
    
    // Store in session for display after redirect
    if (!empty($achievements_awarded)) {
        $_SESSION['new_achievements'] = $achievements_awarded;
    }
} else {
    flash_message($result['message'], 'danger');
}

// Clear all game-related session variables now that we're done with the game
unset($_SESSION['game_session_id']);
unset($_SESSION['game_session_id_single']);
unset($_SESSION['game_session_id_endless']);
unset($_SESSION['game_session_id_multiplayer']);

// Also clear game-specific session variables
$game_keys = [
    'difficulty', 'game_mode', 'current_turn', 'score',
    'shown_images', 'current_real_image', 'current_ai_image',
    'left_is_real', 'completed', 'lives', 'current_streak'
];

foreach ($game_keys as $key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

// Redirect to leaderboard
secure_redirect('leaderboard.php');
?>