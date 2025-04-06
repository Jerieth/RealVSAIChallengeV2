<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for potential refreshes and cheating attempts
$page_visits = track_page_visits('game');

// Get the requested game mode from GET parameters
$requested_mode = isset($_GET['mode']) ? $_GET['mode'] : null;

// Get current session ID, considering mode-specific session management
$current_session_id = null;

// If there's an explicit session_id in the request, use that first
if (isset($_GET['session_id'])) {
    $current_session_id = $_GET['session_id'];
    error_log("Using session ID from GET parameter: $current_session_id");

    // Store this session ID in both mode-specific and legacy session variables
    // This ensures consistency between all game functionality
    if ($requested_mode) {
        $_SESSION['game_session_id_' . $requested_mode] = $current_session_id;
        error_log("Storing GET session ID in mode-specific session variable: game_session_id_$requested_mode");
    }
    $_SESSION['game_session_id'] = $current_session_id;
    error_log("Storing GET session ID in legacy session variable");
}
// Otherwise check for a session ID stored for this specific mode
else if ($requested_mode && isset($_SESSION['game_session_id_' . $requested_mode])) {
    $current_session_id = $_SESSION['game_session_id_' . $requested_mode];
    error_log("Using mode-specific session ID for $requested_mode: $current_session_id");

    // Also update the legacy session variable for compatibility
    $_SESSION['game_session_id'] = $current_session_id;
    error_log("Synchronizing mode-specific session ID to legacy variable");
}
// Legacy fallback to the old non-mode-specific session ID
else if (isset($_SESSION['game_session_id'])) {
    $current_session_id = $_SESSION['game_session_id'];
    error_log("Using legacy session ID: $current_session_id");

    // If we have a requested mode, also store in mode-specific variable
    if ($requested_mode) {
        $_SESSION['game_session_id_' . $requested_mode] = $current_session_id;
        error_log("Synchronizing legacy session ID to mode-specific variable: game_session_id_$requested_mode");
    }
}

// If we have a session ID, check if the game is completed
if ($current_session_id) {
    $game_state = get_game_state($current_session_id);

    // If game is complete, redirect to appropriate page
    if ($game_state && $game_state['completed'] == 1) {
        // Check if this is a daily challenge
        $is_daily_challenge = (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] === 'daily_challenge');
        
        if ($game_state['lives'] > 0) {
            // Player won - redirect to appropriate victory page
            if ($is_daily_challenge) {
                error_log("Daily Challenge completed successfully. Redirecting to daily_victory.php");
                secure_redirect('/controllers/daily_victory.php');
                exit;
            } else {
                error_log("Redirecting to: victory.php with session ID: " . session_id());
                secure_redirect('victory.php');
                exit;
            }
        } else {
            // Player lost - redirect to appropriate game over page
            if ($is_daily_challenge) {
                error_log("Daily Challenge failed. Redirecting to daily_game_over.php");
                secure_redirect('/controllers/daily_game_over.php');
                exit;
            } else {
                error_log("Redirecting to: game_over.php with session ID: " . session_id());
                secure_redirect('game_over.php');
                exit;
            }
        }
    }
}

// Apply time penalty for excessive refreshes
if ($page_visits > 2 && $current_session_id) {
    update_time_penalty_flag($current_session_id, true, isset($_GET['multiplayer']) ? 'multiplayer' : 'single');
    error_log("Applied time penalty for session ID: $current_session_id due to excessive refreshes ($page_visits)");
}

// Debug: Log session data in game.php
error_log("Game.php - Session ID: " . session_id());
error_log("Game.php - Session data: " . print_r($_SESSION, true));

// Handle GET parameters (form submissions from index.php)
// Check if we need to create or resume a game
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    error_log("Game.php - Handling GET request to create or resume game");

    // Check if we're resuming an existing endless mode game
    // Only endless mode games can be resumed, and only by logged-in users
    if (isset($_GET['resume']) && isset($_GET['session_id'])) {
        // Require login for endless mode
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            error_log("Game.php - Attempted to resume endless game without being logged in");
            flash_message('You must be logged in to play or resume Endless Mode', 'danger');
            secure_redirect('login.php');
            exit;
        }

        $resume_session_id = $_GET['session_id'];
        error_log("Game.php - Attempting to resume game with session ID: $resume_session_id");

        // Verify the game exists, belongs to the current user, and is an endless mode game
        $conn = get_db_connection();
        $stmt = $conn->prepare("
            SELECT * FROM games 
            WHERE session_id = :session_id 
            AND user_id = :user_id 
            AND game_mode = 'endless'
            AND completed = 0
        ");

        $user_id = $_SESSION['user_id']; // We know user is logged in at this point
        $stmt->bindParam(':session_id', $resume_session_id, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $game_to_resume = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($game_to_resume) {
            // Game exists, belongs to current user, and is an endless mode game
            // Set the session IDs accordingly
            $_SESSION['game_session_id'] = $resume_session_id;
            $_SESSION['game_session_id_endless'] = $resume_session_id;
            $current_session_id = $resume_session_id;

            // Set time penalty flag to true for resumed endless games (no time bonus)
            update_time_penalty_flag($resume_session_id, true, 'endless');

            error_log("Game.php - Successfully resumed endless game with session ID: $resume_session_id - Time penalty flag set");
        } else {
            // Game doesn't exist, doesn't belong to current user, or isn't an endless mode game
            error_log("Game.php - Failed to resume game with session ID: $resume_session_id - either not found, not an endless game, or doesn't belong to current user");
            set_flash_message('danger', 'Only endless mode games can be resumed.');
            secure_redirect('index.php');
            exit;
        }
    } else if (isset($_GET['mode'])) {
        // Create a new game
        $game_mode = $_GET['mode'];
        $difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'easy';
        $play_anonymous = isset($_GET['play_anonymous']) ? true : false;

        // Check if user is trying to access endless mode without being logged in
        if ($game_mode === 'endless' && (!isset($_SESSION['user_id']) || empty($_SESSION['user_id']))) {
            error_log("Game.php - Attempted to start endless game without being logged in");
            flash_message('You must be logged in to play Endless Mode', 'danger');
            secure_redirect('login.php');
            exit;
        }

        error_log("Game.php - Creating new game with mode: $game_mode, difficulty: $difficulty");
        $result = create_game($game_mode, $difficulty, $play_anonymous);

        if (!$result['success']) {
            error_log("Game.php - Failed to create game: " . $result['message']);
            set_flash_message('danger', $result['message']);
            secure_redirect('index.php');
            exit;
        }

        // Store both in the legacy variable and the mode-specific variable
        $new_session_id = $_SESSION['game_session_id']; // The session ID set by create_game()
        $_SESSION['game_session_id_' . $game_mode] = $new_session_id;
        $current_session_id = $new_session_id;

        error_log("Game.php - Game created successfully with mode-specific session ID: " . $_SESSION['game_session_id_' . $game_mode]);
    }
}

// Check if game session exists or creating new game (either in legacy or mode-specific variable)
if (!$current_session_id && !isset($_SESSION['game_session_id']) && !isset($_GET['mode'])) {
    error_log("Game.php - No game_session_id found in session after checks and no mode parameter");
    flash_message('No active game session', 'danger');
    secure_redirect('index.php');
    exit;
}

// Get the session ID to use (prefer the explicitly set one from earlier logic)
$session_id = $current_session_id ?? $_SESSION['game_session_id'];
$game = get_game_state($session_id);

// Check if game exists
if (!$game) {
    flash_message('Game session not found', 'danger');

    // Clean up all game session variables
    unset($_SESSION['game_session_id']);

    // Clear mode-specific session variables too
    foreach (['single', 'endless', 'multiplayer'] as $mode) {
        if (isset($_SESSION['game_session_id_' . $mode])) {
            unset($_SESSION['game_session_id_' . $mode]);
        }
    }

    secure_redirect('index.php');
    exit;
}

// Check if game is already completed
if ($game['completed'] == 1) {
    // Check if this is a daily challenge
    $is_daily_challenge = (isset($_SESSION['game_mode']) && $_SESSION['game_mode'] === 'daily_challenge');
    
    if ($game['lives'] > 0) {
        // Player won - redirect to appropriate victory page
        if ($is_daily_challenge) {
            error_log("Daily Challenge completed successfully. Redirecting to daily_victory.php");
            secure_redirect('/controllers/daily_victory.php');
        } else {
            secure_redirect('victory.php');
        }
    } else {
        // Player lost - redirect to appropriate game over page
        if ($is_daily_challenge) {
            error_log("Daily Challenge failed. Redirecting to daily_game_over.php");
            secure_redirect('/controllers/daily_game_over.php');
        } else {
            secure_redirect('game_over.php');
        }
    }
    exit;
}

// Check if this is a fresh visit or a resume (page load/refresh)
$is_resuming_game = false;
$refresh_detected = false;
$current_images = null;

// If there are current game images stored in the database
if (!empty($game['current_real_image']) && !empty($game['current_ai_image'])) {
    $is_resuming_game = true;
    $real_image = $game['current_real_image'];
    $ai_image = $game['current_ai_image'];
    $left_is_real = $game['left_is_real'];

    // Check for valid image filenames
    $images_need_update = false;
    $reason = "";

    // Only check if images exist if we actually have filenames
    if (empty($real_image) || empty($ai_image)) {
        error_log("Game.php - One or both images are empty. Real: '$real_image', AI: '$ai_image'");
        $images_need_update = true;
        $reason = "Missing image filenames";
    }
    // If both images are the same, we need new ones
    else if ($real_image === $ai_image) {
        error_log("Game.php - Same image detected for both real and AI: '$real_image'");
        $images_need_update = true;
        $reason = "Same image detected for both real and AI";
    }
    // Check that images actually exist in the filesystem
    else {
        $real_image_info = verify_image_exists($real_image);
        $ai_image_info = verify_image_exists($ai_image);

        if (!$real_image_info['exists'] || !$ai_image_info['exists']) {
            error_log("Game.php - Image check: Real image exists: " . ($real_image_info['exists'] ? 'Yes' : 'No'));
            error_log("Game.php - Image check: AI image exists: " . ($ai_image_info['exists'] ? 'Yes' : 'No'));
            $images_need_update = true;
            $reason = "One or both images don't exist in filesystem";
        }
    }

    // If we need to update images
    if ($images_need_update) {
        error_log("Game.php - Image issue detected. Reason: " . $reason . ". Getting new images.");

        // Get new images for current turn
        $shown_images = !empty($game['shown_images']) ? explode(',', $game['shown_images']) : array();
        list($real_image, $ai_image) = get_random_image_pair($shown_images, $game['difficulty']);

        // Randomly determine the order (left or right)
        $left_is_real = random_int(0, 1) == 1;

        // Store the current image pair and order in the database
        update_current_images($session_id, $real_image, $ai_image, $left_is_real);

        error_log("Game.php - Updated to new images: Real: {$real_image}, AI: {$ai_image}, Left is real: " . ($left_is_real ? 'Yes' : 'No'));
    }

    // If time penalty flag is set, we know they've refreshed rather than navigated away and returned
    if (isset($game['time_penalty']) && $game['time_penalty'] == 1) {
        $refresh_detected = true;
        error_log("Game.php - Time penalty detected, maintaining same images (refresh)");
    } else {
        // Set time penalty flag for any subsequent refreshes on this turn
        try {
            update_time_penalty_flag($session_id, true);
        } catch (PDOException $e) {
            // If we get an error, it might be because the column doesn't exist yet
            error_log("Error updating time penalty: " . $e->getMessage());
        }
    }

    error_log("Game.php - Resuming game with existing images");
} else {
    // Get new images for current turn
    $shown_images = !empty($game['shown_images']) ? explode(',', $game['shown_images']) : array();
    list($real_image, $ai_image) = get_random_image_pair($shown_images, $game['difficulty']);

    // If we ran out of images
    if (!$real_image || !$ai_image) {
        flash_message('No more unique images available', 'danger');
        secure_redirect('index.php');
        exit;
    }

    // Randomly determine the order (left or right)
    $left_is_real = random_int(0, 1) == 1;

    // Store the current image pair and order in the database
    update_current_images($session_id, $real_image, $ai_image, $left_is_real);

    // Set time penalty flag to false for first view of this image pair
    update_time_penalty_flag($session_id, false);

    // Update shown images in database
    update_shown_images($session_id, $real_image, $ai_image);
}

// Set image variables for display
$image1 = $left_is_real ? $real_image : $ai_image;
$image2 = $left_is_real ? $ai_image : $real_image;

// Set page title based on game mode
$mode_name = ucfirst($game['game_mode']);
if ($game['game_mode'] == 'single') {
    $mode_name .= ' Player - ' . ucfirst($game['difficulty']);
} elseif ($game['game_mode'] == 'endless') {
    $mode_name .= ' Mode';
}

$page_title = $mode_name . ' - ' . SITE_TITLE;

// Set body data attributes for game settings
$body_data_attributes = [
    'difficulty' => $game['difficulty'] ?? 'easy',
    'game-mode' => $game['game_mode'] ?? 'single'
];

// Add time penalty attribute if applicable
if (isset($game['time_penalty']) && $game['time_penalty'] == 1) {
    $body_data_attributes['time-penalty'] = 'true';
}

// Add resuming game attribute if applicable
if ($is_resuming_game) {
    $body_data_attributes['is-resuming-game'] = 'true';
}

// Include header
include 'includes/header.php';
?>

<!-- Add Medium Zoom library for image zoom -->
<script src="https://unpkg.com/medium-zoom@1.0.6/dist/medium-zoom.min.js"></script>
<script src="static/js/image-zoom.js"></script>

<div class="container py-4">
    <!-- Game Header -->
    <div class="game-header p-3 mb-4 rounded">
        <div class="row align-items-center">
            <div class="col-md-3">
                <div class="turn-counter">
                    Turn: <span id="turn"><?= $game['current_turn'] ?><?= $game['total_turns'] > 0 ? '/' . $game['total_turns'] : '' ?></span>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="score-counter">
                    Score: <span id="score"><?= $game['score'] ?></span>
                </div>
            </div>
            <div class="col-md-3 text-center">
                <div class="lives-counter">
                    Lives: <span id="lives"><?= $game['lives'] ?></span>
                </div>
            </div>
            <div class="col-md-3 text-end">
                <div class="controls">
                    <button id="toggleSound" class="btn btn-sm btn-outline-secondary" aria-label="Toggle Sound">
                        <i id="soundIcon" class="fas fa-volume-up"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <h1 class="text-center mb-4">Which image is REAL?</h1>

    <!-- Time indicator - only shown for hard difficulty, multiplayer and endless modes -->
    <?php 
    // Get the logged-in user's timer preference
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $hide_timer = false;

    // Check if the user is logged in and has chosen to hide the timer
    if ($user_id) {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT hide_timer FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user_pref = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_pref && isset($user_pref['hide_timer'])) {
            $hide_timer = (bool)$user_pref['hide_timer'];
        }
    }

    // Show timer for Multiplayer and Endless modes
    $show_time_bonus = ($game['game_mode'] == 'multiplayer' || $game['game_mode'] == 'endless');
    $has_time_penalty = isset($game['time_penalty']) && $game['time_penalty'] == 1;

    // Only hide timer if there's a time penalty
    if ($has_time_penalty) {
        $show_time_bonus = false;
    }

    $should_display_timer = $show_time_bonus && (!$user_id || !$hide_timer);

    // Always add a hidden field with the timer state for JavaScript to use
    echo '<input type="hidden" id="timerEnabled" value="' . ($show_time_bonus && !$has_time_penalty ? '1' : '0') . '">';
    echo '<input type="hidden" id="timerVisible" value="' . ($should_display_timer ? '1' : '0') . '">';

    if ($should_display_timer): 
    ?>
    <div class="text-center mb-3">
        <div class="time-bonus-indicator">
            <div class="progress">
                <div id="timeBar" class="progress-bar bg-success" role="progressbar" style="width: 100%;" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <div class="text-muted small mt-1">Time Bonus: <span id="timeBonusValue">+20</span> points</div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Feedback message (hidden initially) -->
    <div id="feedback" class="feedback-message" style="display: none;"></div>

    <!-- Images -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="image-container" data-index="0">
                <div class="zoom-controls">
                    <div class="magnifier-icon" title="Magnify Image">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </div>
                <div class="img-magnifier-container">
                    <div class="magnifier-glass"></div>
                    <img src="static/images/game-image.php?id=<?= urlencode(base64_encode($image1)) ?>" alt="Image A" class="img-fluid game-image">
                </div>
                <div class="image-label mt-2 text-center">Image A</div>
                <!-- Info box for image descriptions - shown after answering -->
                <div class="image-info-box" id="image-info-0"></div>
            </div>
            <div class="d-flex justify-content-center mt-2">
                <?php if (!($game['game_mode'] == 'single' && $game['difficulty'] == 'hard') && $game['game_mode'] != 'endless'): ?>
                <button class="btn btn-sm btn-info view-in-new-tab-btn me-2" style="width: auto;" onclick="window.open('static/images/game-image.php?id=<?= urlencode(base64_encode($image1)) ?>', '_blank')">
                    <i class="fas fa-external-link-alt me-1"></i> New Tab 
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-secondary" style="width: auto; height: 35px" onclick="showFullSizeImage(document.querySelector('[data-index=\'0\'] img'), 0)">
                    <i class="fas fa-expand me-1"></i> Enlarge Image
                </button>
            </div>
        </div>
        <div class="col-md-6 mb-4">
            <div class="image-container" data-index="1">
                <div class="zoom-controls">
                    <div class="magnifier-icon" title="Magnify Image">
                        <i class="fas fa-search-plus"></i>
                    </div>
                </div>
                <div class="img-magnifier-container">
                    <div class="magnifier-glass"></div>
                    <img src="static/images/game-image.php?id=<?= urlencode(base64_encode($image2)) ?>" alt="Image B" class="img-fluid game-image">
                </div>
                <div class="image-label mt-2 text-center">Image B</div>
                <!-- Info box for image descriptions - shown after answering -->
                <div class="image-info-box" id="image-info-1"></div>
            </div>
            <div class="d-flex justify-content-center mt-2">
                <?php if (!($game['game_mode'] == 'single' && $game['difficulty'] == 'hard') && $game['game_mode'] != 'endless'): ?>
                <button class="btn btn-sm btn-info view-in-new-tab-btn me-2" style="width: auto;" onclick="window.open('static/images/game-image.php?id=<?= urlencode(base64_encode($image2)) ?>', '_blank')">
                    <i class="fas fa-external-link-alt me-1"></i> New Tab
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-secondary" style="width: auto;" onclick="showFullSizeImage(document.querySelector('[data-index=\'1\'] img'), 1)">
                    <i class="fas fa-expand me-1"></i> Enlarge Image
                </button>
            </div>
        </div>
    </div>

    <!-- Hidden data -->
    <input type="hidden" id="leftIsReal" value="<?= $left_is_real ? '1' : '0' ?>">
    <input type="hidden" id="gameSessionId" value="<?= htmlspecialchars($session_id) ?>">
    <input type="hidden" id="game-mode" value="<?= htmlspecialchars($game['game_mode']) ?>">

    <!-- Controls -->
    <div class="text-center mt-3">
        <!-- Compare Images button REMOVED -->

        <div class="d-flex justify-content-center">
            <button id="gameActionButton" class="btn btn-primary btn-lg px-5" style="min-width: 200px;" disabled>Submit Answer</button>
        </div>
        <div class="my-2"></div>
    </div>

    <!-- Bonus Game Intro Modal -->
    <div class="modal fade" id="bonusIntroModal" tabindex="-1" aria-labelledby="bonusIntroModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="bonusIntroModalLabel">Bonus Challenge Available!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="alert alert-info">
                        <p><strong>BONUS CHALLENGE:</strong> Find the REAL image among four images.</p>
                        <p id="bonusRewardText">If you guess correctly, you'll earn an extra life! If you guess wrong, you'll lose half your score.</p>
                        <p>Would you like to play the bonus game?</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Skip (No Penalty)</button>
                    <button type="button" class="btn btn-primary" id="playBonusGameBtn">Play Bonus Game</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bonus Mini-Game Modal -->
    <div class="modal fade" id="bonusGameModal" tabindex="-1" aria-labelledby="bonusGameModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="bonusGameModalLabel">Bonus Mini-Game!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <div class="alert alert-info">
                            <p><strong>BONUS CHALLENGE:</strong> Find the REAL image among these four images.</p>
                            <p id="bonusGameRewardText">If you guess correctly, you'll earn an extra life! If you guess wrong, you'll lose half your score.</p>
                        </div>
                    </div>

                    <div id="bonusImagesContainer" class="row">
                        <!-- Images will be inserted here via JavaScript -->
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Skip (No Penalty)</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bonus Result Modal -->
    <div class="modal fade" id="bonusResultModal" tabindex="-1" aria-labelledby="bonusResultModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark text-light">
                <div class="modal-header">
                    <h5 class="modal-title" id="bonusResultModalLabel">Bonus Result</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="bonusResultMessage" class="alert">
                        <!-- Result message will go here -->
                    </div>
                    <div class="mt-3">
                        <div class="stats-row">
                            <strong>Score:</strong> <span id="bonusResultScore"></span>
                        </div>
                        <div class="stats-row">
                            <strong>Lives:</strong> <span id="bonusResultLives"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Continue Game</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Game Info (Session ID and Total Score) -->
    <div class="mt-5 text-center">
        <div class="session-info">
            Session ID: <?= $session_id ?><br>
            Total Score: <span id="total-score">0</span>
        </div>
    </div>
</div>

<!-- Resume Game Modal -->
<div class="modal fade" id="resumeConfirmModal" tabindex="-1" aria-labelledby="resumeConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header">
                <h5 class="modal-title" id="resumeConfirmModalLabel">Resume Game?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="alert alert-info">
                    <p>You have an Endless Mode game in progress. Would you like to resume it or start a new game?</p>
                    <p class="text-warning small">Note: Only Endless Mode games can be resumed</p>
                </div>
                <div class="game-stats mt-3">
                    <div><strong>Game Mode:</strong> <?= ucfirst($game['game_mode']) ?> <?= $game['game_mode'] == 'single' ? '(' . ucfirst($game['difficulty']) . ')' : '' ?></div>
                    <div><strong>Current Score:</strong> <?= $game['score'] ?></div>
                    <div><strong>Current Turn:</strong> <?= $game['current_turn'] ?><?= $game['total_turns'] > 0 ? '/' . $game['total_turns'] : '' ?></div>
                    <div><strong>Lives Remaining:</strong> <?= $game['lives'] ?></div>
                </div>
            </div>
            <div class="modal-footer justify-content-center">
                <form action="index.php" method="post">
                    <input type="hidden" name="action" value="new_game">
                    <button type="submit" class="btn btn-danger">Start New Game</button>
                </form>
                <button type="button" class="btn btn-success" data-bs-dismiss="modal">Resume Game</button>
            </div>
        </div>
    </div>
</div>

<!-- Confetti JS removed as requested -->

<!-- Hidden form for submitting answers -->
<form id="answer-form" method="post" action="game_actions.php" style="display: none;">
    <input type="hidden" name="action" value="submit_answer">
    <input type="hidden" name="session_id" value="<?= $session_id ?>">
    <input type="hidden" name="answer" id="selected-answer" value="">
    <?php if (function_exists('create_csrf_token')): ?>
    <input type="hidden" name="csrf_token" value="<?= create_csrf_token() ?>">
    <?php endif; ?>
</form>

<!-- JSConfetti initialization removed as requested -->

<!-- Add score animation CSS -->
<style>
.score-updated {
    animation: score-pulse 0.5s ease-in-out;
    color: #28a745;
    font-weight: bold;
}

@keyframes score-pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.3); }
    100% { transform: scale(1); }
}

.image-description {
    display: block;
    margin-top: 10px;
    padding: 10px;
    background-color: #f8f9fa;
    border-left: 3px solid #007bff;
    border-radius: 4px;
    font-style: italic;
    line-height: 1.5;
}

.feedback-message strong {
    color: #343a40;
}

/* Add better spacing for the button row when the 'New Tab' button is hidden */
.d-flex.justify-content-center.mt-2 .btn-secondary {
    min-width: 150px;
}
</style>

<!-- Game JS will be included via footer.php -->
<script>
    // Set a flag to indicate the script has been loaded (to prevent double initialization)
    window.gameScriptLoaded = true;
    
    <?php if (isset($_SESSION['user_id'])): ?>
    // Set user ID for achievement tracking
    window.currentUserId = <?= $_SESSION['user_id'] ?>;
    <?php endif; ?>
</script>

<!-- Include game achievements functionality -->
<script src="/static/js/game-achievements.js"></script>

<?php
// Silently handle refresh by keeping same images and reset time bonus
if ($refresh_detected) {
    echo "<script>
        document.body.setAttribute('data-time-penalty', 'true');
    </script>";
}

// Template for Medium Zoom
echo '
<template id="zoom-template">
    <div class="zoom-template">
        <div class="zoom-image-container"></div>
        <button class="zoom-close-btn" aria-label="Close zoom view">
            <i class="fas fa-times"></i>
        </button>
    </div>
</template>
';

// Include footer
include 'includes/footer.php';
?>