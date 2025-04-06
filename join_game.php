<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/multiplayer_functions.php';

// Require login for multiplayer games
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    flash_message('You must be logged in to play Multiplayer', 'danger');
    secure_redirect('login.php');
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash_message('Invalid request method', 'danger');
    secure_redirect('index.php');
    exit;
}

// Get join parameters
$join_type = isset($_POST['join_type']) ? trim($_POST['join_type']) : '';
$session_id = isset($_POST['session_id']) ? trim($_POST['session_id']) : '';
$room_code = isset($_POST['room_code']) ? trim($_POST['room_code']) : '';
$play_anonymous = isset($_POST['anonymous']) || isset($_POST['play_anonymous']) ? true : false;
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

// Handle quick match action
if ($action === 'quick_match') {
    // Use the quick_match function from multiplayer_functions.php
    $result = quick_match($play_anonymous);
} 
// Handle different join types
elseif ($join_type === 'room' && !empty($room_code)) {
    // Join by room code
    $result = join_private_game($room_code, $play_anonymous);
} elseif ($join_type === 'session' && !empty($session_id)) {
    // Join by session ID
    $result = join_game($session_id, $play_anonymous);
} else {
    flash_message('Invalid join parameters', 'danger');
    secure_redirect('index.php');
    exit;
}

if (!$result['success']) {
    flash_message($result['message'], 'danger');
    secure_redirect('index.php');
    exit;
}

secure_redirect('multiplayer.php?session_id=' . $result['session_id']);
exit;
?>