<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/multiplayer_functions.php';

// Check if game session exists
if (!isset($_SESSION['game_session_id'])) {
    flash_message('No active game session', 'danger');
    secure_redirect('index.php');
    exit;
}

// Get game data
$session_id = $_SESSION['game_session_id'];
$game = get_multiplayer_game($session_id);

// Check if game exists
if (!$game) {
    flash_message('Game session not found', 'danger');
    unset($_SESSION['game_session_id']);
    secure_redirect('index.php');
    exit;
}

// Check if the game is completed
if ($game['completed'] != 1) {
    flash_message('Game is not yet completed', 'danger');
    secure_redirect('multiplayer.php');
    exit;
}

// Determine the current player's position
$current_player = 0;
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? null;

for ($i = 1; $i <= 4; $i++) {
    if (
        ($user_id !== null && $game["player{$i}_id"] == $user_id) ||
        ($username !== null && $game["player{$i}_name"] == $username)
    ) {
        $current_player = $i;
        break;
    }
}

// Count players
$player_count = 0;
for ($i = 1; $i <= 4; $i++) {
    if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
        $player_count++;
    }
}

// Set page title
$page_title = 'Multiplayer Game Complete - ' . SITE_TITLE;

// Include header
include 'includes/header.php';

// Include the multiplayer game over template
include 'templates/multiplayer_game_over.php';

// Include footer
include 'includes/footer.php';
?>