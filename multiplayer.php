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

// Get session ID from URL or session (check for mode-specific multiplayer session ID first)
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : 
              (isset($_SESSION['game_session_id_multiplayer']) ? $_SESSION['game_session_id_multiplayer'] : 
              (isset($_SESSION['game_session_id']) ? $_SESSION['game_session_id'] : null));

// Check if session ID exists
if (!$session_id) {
    flash_message('No game session specified', 'danger');
    secure_redirect('index.php');
    exit;
}

// Store session ID in user session (both in mode-specific variable and legacy variable)
$_SESSION['game_session_id_multiplayer'] = $session_id;
$_SESSION['game_session_id'] = $session_id;
error_log("multiplayer.php - Set mode-specific session ID for multiplayer: $session_id");

// Get game data
$game = get_game_state($session_id, 'multiplayer');

// Check if game exists
if (!$game) {
    flash_message('Game session not found', 'danger');
    // Clean up both mode-specific and legacy session variables
    unset($_SESSION['game_session_id_multiplayer']);
    unset($_SESSION['game_session_id']);
    error_log("multiplayer.php - Game not found, session variables cleared");
    secure_redirect('index.php');
    exit;
}

// Get current player number first
$player_number = 0;
$current_user = isset($_SESSION['username']) ? $_SESSION['username'] : null;

if ($current_user) {
    for ($i = 1; $i <= 4; $i++) {
        if (
            (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $game["player{$i}_id"]) ||
            (!isset($_SESSION['user_id']) && $current_user == $game["player{$i}_name"])
        ) {
            $player_number = $i;
            break;
        }
    }
}

// Check if bots should be added (this automatically adds bots after timeout if needed)
if ($player_number == 1) { // Host checks for bots
    $bot_result = check_and_add_bots($session_id);

    // If bots were added, refresh the game data
    if ($bot_result['success'] && $bot_result['bots_added']) {
        $game = get_game_state($session_id, 'multiplayer');
    }
}

// Check if game is waiting for players
$player_count = 0;
for ($i = 1; $i <= 4; $i++) {
    if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
        $player_count++;
    }
}

// Get player names and avatars
$player_names = array();
$player_avatars = array();
for ($i = 1; $i <= 4; $i++) {
    if (!empty($game["player{$i}_id"])) {
        // Get username and avatar from database
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT username, avatar FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $game["player{$i}_id"], PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $player_names[$i] = $user ? $user['username'] : "Player {$i}";
        $player_avatars[$i] = $user && !empty($user['avatar']) ? $user['avatar'] : "";
    } elseif (!empty($game["player{$i}_name"])) {
        $player_names[$i] = $game["player{$i}_name"];
        $player_avatars[$i] = "";
    } else {
        $player_names[$i] = "Waiting...";
        $player_avatars[$i] = "";
    }
}

// Check if game is already completed
if ($game['completed'] == 1) {
    secure_redirect('leaderboard.php');
    exit;
}

// Check if a bot should make an answer (only if game has started and has bots)
if (isset($game['has_bots']) && $game['has_bots'] == 1 && $game['status'] == 'in_progress' && $player_number == 1) { // Host handles bot turns
    $conn = get_db_connection();

    // Check if there are any bots that haven't answered the current turn
    $stmt = $conn->prepare("
        SELECT mb.* FROM multiplayer_bots mb
        WHERE mb.multiplayer_game_id = :game_id
        AND NOT EXISTS (
            SELECT 1 FROM multiplayer_answers ma 
            WHERE ma.multiplayer_game_id = :game_id 
            AND ma.turn_number = :turn_number
            AND ma.user_id IS NULL
            AND ma.user_name = mb.bot_name
        )
    ");

    $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
    $stmt->bindParam(':turn_number', $game['current_turn'], PDO::PARAM_INT);
    $stmt->execute();

    $bots_to_answer = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Have each bot submit an answer with a random delay
    foreach ($bots_to_answer as $bot) {
        $bot_result = simulate_bot_answer($game['id'], $bot['player_slot'], $game['current_turn']);

        // This would normally be a delayed action, but for simplicity we just do it immediately
        if ($bot_result['success']) {
            // We would add a message about the bot answering here if we had a chat system
        }
    }
}

// Get images for current turn if game has started
$images_ready = false;
$image1 = $image2 = null;
$left_is_real = false;

if ($player_count >= 2) {
    // Game can start with at least 2 players
    $shown_images = !empty($game['shown_images']) ? explode(',', $game['shown_images']) : array();
    list($real_image, $ai_image) = get_random_image_pair($shown_images);

    // If we ran out of images
    if (!$real_image || !$ai_image) {
        flash_message('No more unique images available', 'danger');
        secure_redirect('index.php');
        exit;
    }

    // Update shown images in database (only if player 1/host)
    if ($player_number == 1) {
        update_shown_images($session_id, $real_image, $ai_image, 'multiplayer');
    }

    // Randomly determine the order (left or right)
    $left_is_real = random_int(0, 1) == 1;
    $image1 = $left_is_real ? $real_image : $ai_image;
    $image2 = $left_is_real ? $ai_image : $real_image;

    $images_ready = true;
}

// Set page title
$page_title = 'Multiplayer Game - ' . SITE_TITLE;

// Get game config for max players
// Make sure the global game config is accessible
global $game_config;

// Set body data attributes for game settings
$body_data_attributes = [
    'difficulty' => 'medium', // Multiplayer always uses medium difficulty
    'game-mode' => 'multiplayer'
];

// Add time penalty attribute if applicable
if (isset($game['time_penalty']) && $game['time_penalty'] == 1) {
    $body_data_attributes['time-penalty'] = 'true';
}

// Include header
include 'includes/header.php';
?>

<div class="container py-4">
    <?php if ($player_count < 2): ?>
        <!-- Waiting for players -->
        <div class="text-center py-5">
            <h1 class="mb-4">Waiting for Players</h1>

            <?php if (isset($game['is_public']) && $game['is_public'] == 0 && !empty($game['room_code'])): ?>
                <!-- Private Game - Show Room Code -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-lock me-2"></i> This is a <strong>private</strong> game. Share this room code with your friends:
                </div>

                <div class="session-info mb-4" onclick="copyToClipboard('<?= $game['room_code'] ?>', 'Room code copied to clipboard!')">
                    <i class="fas fa-copy me-2"></i> Room Code: <span id="roomCode" class="fs-3 fw-bold"><?= $game['room_code'] ?></span>
                    <div class="mt-1 small text-muted">Click to copy</div>
                </div>
            <?php else: ?>
                <!-- Public Game - Show Session ID -->
                <div class="alert alert-info mb-4">
                    <i class="fas fa-globe me-2"></i> This is a <strong>public</strong> game. Share this session ID with your friends to join:
                </div>
            <?php endif; ?>

            <div class="session-info mb-4" onclick="copyToClipboard('<?= $session_id ?>', 'Session ID copied to clipboard!')">
                <i class="fas fa-copy me-2"></i> Session ID: <span id="sessionId"><?= $session_id ?></span>
                <div class="mt-1 small text-muted">Click to copy</div>
            </div>

            <div class="mb-4">
                <h4>Current Players (<?= $player_count ?>/<?= MULTIPLAYER_SETTINGS['max_players'] ?>):</h4>
                <div class="row justify-content-center mt-3">
                    <?php for ($i = 1; $i <= MULTIPLAYER_SETTINGS['max_players']; $i++): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card <?= (empty($game["player{$i}_id"]) && empty($game["player{$i}_name"])) ? 'border-dashed' : '' ?>">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Player <?= $i ?></h5>
                                    <p class="card-text">
                                        <?php if (!empty($player_names[$i]) && $player_names[$i] != "Waiting..."): ?>
                                            <span class="badge bg-success">
                                                <?php if (!empty($player_avatars[$i])): ?>
                                                    <?= $player_avatars[$i] ?> 
                                                <?php endif; ?>
                                                <?= htmlspecialchars($player_names[$i]) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Waiting...</span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="mb-4">
                <button id="refreshBtn" class="btn btn-primary" onclick="location.reload()">Refresh</button>
                <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>

            <script>
                // Auto refresh every 10 seconds while waiting for players
                function startAutoRefresh() {
                    return setInterval(() => {
                        location.reload();
                    }, 10000);
                }
                
                // Start auto-refresh when waiting for players
                if (<?= $player_count ?> < 2) {
                    startAutoRefresh();
                }
            </script>

            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i> Game will start automatically when at least 2 players have joined.
            </div>

            <!-- Bot alert removed -->
        </div>
    <?php else: ?>
        <!-- Game in progress -->
        <div class="game-header p-3 mb-4 rounded">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="turn-counter">
                        Turn: <span id="multiplayerTurn"><?= $game['current_turn'] ?>/<?= $game['total_turns'] ?></span>
                    </div>
                </div>
                <div class="col-md-8 text-end">
                    <div class="player-scores">
                        <?php for ($i = 1; $i <= MULTIPLAYER_SETTINGS['max_players']; $i++): ?>
                            <?php if (isset($player_names[$i]) && $player_names[$i] != "Waiting..."): ?>
                                <span class="badge <?= ($player_number == $i) ? 'bg-primary' : 'bg-secondary' ?> me-2" style="font-size: 125%; padding: 8px 12px;">
                                    <?php if (!empty($player_avatars[$i])): ?>
                                        <?= $player_avatars[$i] ?> 
                                    <?php endif; ?>
                                    <?= htmlspecialchars($player_names[$i] ?? '') ?>: 
                                    <span id="player<?= $i ?>Score"><?= isset($game["player{$i}_score"]) ? (int)$game["player{$i}_score"] : 0 ?></span>
                                </span>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <h1 class="text-center mb-4">Which image is REAL?</h1>

        <!-- Feedback message (hidden initially) -->
        <div id="feedback" class="feedback-message" style="display: none;"></div>

        <?php if ($images_ready): ?>
            <!-- Images -->
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="image-container" data-index="0">
                        <img src="static/images/game-image.php?id=<?= urlencode(base64_encode($image1)) ?>" alt="Image A" class="img-fluid">
                        <div class="image-label mt-2 text-center">Image A</div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="image-container" data-index="1">
                        <img src="static/images/game-image.php?id=<?= urlencode(base64_encode($image2)) ?>" alt="Image B" class="img-fluid">
                        <div class="image-label mt-2 text-center">Image B</div>
                    </div>
                </div>
            </div>

            <!-- Hidden data -->
            <input type="hidden" id="leftIsReal" value="<?= $left_is_real ? '1' : '0' ?>">

            <!-- Controls -->
            <div class="text-center mt-3">
                <button id="submitMultiplayerAnswer" class="btn btn-primary btn-lg px-5" disabled>Submit Answer</button>
            </div>

            <!-- Player info -->
            <div class="text-center mt-4">
                <div class="alert bg-dark">
                    You are <span class="badge bg-dark me-2" style="font-size: 125%; padding: 8px 12px;">
                        <?php if (!empty($player_avatars[$player_number])): ?>
                            <?= $player_avatars[$player_number] ?> 
                        <?php endif; ?>
                        <?= htmlspecialchars($player_names[$player_number] ?? '') ?>
                    </span>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-danger text-center">
                Error loading game images. Please refresh the page.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Confetti completely removed as requested -->

<!-- Now include game JS -->
<script src="static/js/game.js"></script>

<?php
// Include footer
include 'includes/footer.php';
?>