<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';
require_once 'includes/multiplayer_functions.php';

// Initialize database connection
$db = get_db_connection();

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_message('Invalid request method', 'danger');
    secure_redirect('index.php');
    exit;
}

// Debug: Log session status
error_log("Session status before start_game: " . print_r(session_status(), true));
error_log("Session ID: " . session_id());

// Get game parameters
$game_mode = isset($_POST['mode']) ? $_POST['mode'] : '';
$difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : null;
// We're no longer allowing anonymous play
$play_anonymous = false;
$is_public = isset($_POST['is_public']) ? (bool)$_POST['is_public'] : true;
$total_turns = isset($_POST['total_turns']) ? (int)$_POST['total_turns'] : 10;

// Debug log
error_log("start_game.php received: mode=$game_mode, difficulty=$difficulty, anonymous=" . ($play_anonymous ? "true" : "false") . 
          ", is_public=" . ($is_public ? "true" : "false") . ", total_turns=$total_turns");

// Validate game mode
if (!in_array($game_mode, ['single', 'multiplayer', 'endless'])) {
    flash_message('Invalid game mode', 'danger');
    secure_redirect('index.php');
    exit;
}

// Require login for endless mode and multiplayer mode
if (($game_mode === 'endless' || $game_mode === 'multiplayer') && (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']))) {
    flash_message('You must be logged in to play ' . ($game_mode === 'endless' ? 'Endless Mode' : 'Multiplayer'), 'danger');
    secure_redirect('login.php');
    exit;
}

// Validate difficulty for single player
if ($game_mode === 'single' && !in_array($difficulty, ['easy', 'medium', 'hard'])) {
    flash_message('Invalid difficulty level', 'danger');
    secure_redirect('index.php');
    exit;
}

// Set endless mode parameters
if ($game_mode === 'endless') {
    $difficulty = 'endless';
}

// Special handling for multiplayer mode
if ($game_mode === 'multiplayer') {
    $result = create_multiplayer_game($is_public, $total_turns, $play_anonymous);
    
    if (!$result['success']) {
        flash_message($result['message'], 'danger');
        secure_redirect('index.php');
        exit;
    }
    
    secure_redirect('multiplayer.php?session_id=' . $result['session_id']);
    exit;
} else {
    // Create regular single player or endless game
    $result = create_game($game_mode, $difficulty, $play_anonymous);
    
    if (!$result['success']) {
        flash_message($result['message'], 'danger');
        secure_redirect('index.php');
        exit;
    }
    
    // Use mode parameter to ensure game.php uses correct session variable
    secure_redirect('game.php?mode=' . $game_mode);
}
?>