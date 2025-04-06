<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if game session exists
if (!isset($_SESSION['game_session_id'])) {
    flash_message('No active game session', 'danger');
    secure_redirect('index.php');
    exit;
}

// Get game data
$session_id = $_SESSION['game_session_id'];
$game = get_game_state($session_id);

// Check if game exists
if (!$game) {
    flash_message('Game session not found', 'danger');
    unset($_SESSION['game_session_id']);
    secure_redirect('index.php');
    exit;
}

// For multiplayer games, redirect to the multiplayer victory page
if (isset($game['game_mode']) && $game['game_mode'] === 'multiplayer') {
    secure_redirect('multiplayer_victory.php');
    exit;
}

// Set page title
$page_title = 'Game Over - ' . SITE_TITLE;

// Include header
include 'includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="result-card">
                <h1 class="result-title text-danger">Game Over!</h1>
                
                <?php 
                // Check the reason for game over
                if (isset($_GET['out_of_images']) && $_GET['out_of_images'] == 1): ?>
                    <p class="lead mb-4">Wow! You've seen all the available images in this difficulty level. Impressive!</p>
                <?php elseif (isset($game['lives']) && $game['lives'] <= 0): ?>
                    <p class="lead mb-4">You ran out of lives. Better luck next time!</p>
                <?php elseif (isset($game['total_turns']) && isset($game['current_turn']) && $game['current_turn'] >= $game['total_turns']): ?>
                    <p class="lead mb-4">You've completed all <?= $game['total_turns'] ?> turns! Great job!</p>
                <?php else: ?>
                    <p class="lead mb-4">Game over! Thanks for playing.</p>
                <?php endif; ?>
                
                <div class="mb-4">
                    <h3>Your Score</h3>
                    <div class="score-display"><?= $game['score'] ?></div>
                </div>
                
                <div class="game-stats mb-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-dark">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Game Mode</h5>
                                    <p class="card-text"><?= ucfirst($game['game_mode']) ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-dark">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Difficulty</h5>
                                    <p class="card-text"><?= ucfirst($game['difficulty'] ?? 'Standard') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-dark">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Turns Completed</h5>
                                    <p class="card-text"><?= $game['current_turn'] - 1 ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Always show final score, but only allow submission if score > 0 -->
                <div class="submit-score-section mb-4">
                    <h3 class="mb-3">Submit to Leaderboard</h3>
                    
                    <?php if ($game['score'] > 0): ?>
                        <?php
                        // ANTI-CHEAT: Generate secure score hash to prevent tampering
                        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                        $timestamp = time();
                        $hash_data = generate_score_hash($game['score'], $user_id, $timestamp);
                        
                        // For endless mode, ensure difficulty is set to 'endless'
                        $displayed_difficulty = $game['difficulty'] ?? '';
                        if ($game['game_mode'] === 'endless') {
                            $displayed_difficulty = 'endless';
                        }
                        
                        // Check if user is logged in
                        $is_logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
                        ?>
                        
                        <?php if ($is_logged_in): ?>
                            <!-- Score submission form for logged-in users -->
                            <form id="submitScoreForm" action="submit_score.php" method="post">
                                <input type="hidden" name="score" value="<?= $game['score'] ?>">
                                <input type="hidden" name="game_mode" value="<?= $game['game_mode'] ?>">
                                <input type="hidden" name="difficulty" value="<?= $displayed_difficulty ?>">
                                <!-- ANTI-CHEAT: Include secure hash for score verification -->
                                <input type="hidden" name="score_hash" value="<?= $hash_data['encoded'] ?>">
                                <input type="hidden" name="score_timestamp" value="<?= $hash_data['timestamp'] ?>">
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <div class="form-floating mb-3">
                                            <input type="email" class="form-control" id="email" name="email" placeholder="name@example.com">
                                            <label for="email">Email (optional)</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">Submit Score</button>
                            </form>
                        <?php else: ?>
                            <!-- Message for non-logged in users -->
                            <div class="alert alert-info mb-4">
                                <i class="bi bi-info-circle"></i> <strong>Sign in required</strong>
                                <p>You need to be logged in to submit your score to the leaderboard.</p>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-primary">Sign In</a>
                                    <a href="register.php" class="btn btn-outline-primary ms-2">Register</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            You need to score at least 1 point to submit to the leaderboard.
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="index.php" class="btn btn-outline-primary btn-lg">Back to Home</a>
                    <a href="leaderboard.php" class="btn btn-outline-info btn-lg">View Leaderboard</a>
                    
                    <!-- Play Again button redirects to home page -->
                    <a href="index.php" class="btn btn-success btn-lg">Play Again</a>
                </div>
                
                <?php
                // Award achievements for completed games
                // Only for logged-in users
                if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
                    $user_id = $_SESSION['user_id'];
                    
                    // Check if this is a victory screen for a completed game
                    $isVictory = false;
                    if (isset($_GET['out_of_images']) && $_GET['out_of_images'] == 1) {
                        $isVictory = true;
                    } elseif (isset($game['total_turns']) && isset($game['current_turn']) && $game['current_turn'] >= $game['total_turns']) {
                        $isVictory = true;
                    }
                    
                    if ($isVictory) {
                        error_log("Victory screen detected for user $user_id");
                        
                        // 1. Check for "Beginner Detective" achievement (Easy difficulty completion)
                        if (isset($game['game_mode']) && $game['game_mode'] === 'single' && 
                            isset($game['difficulty']) && $game['difficulty'] === 'easy') {
                            require_once 'models/Achievement.php';
                            if (award_achievement_model($user_id, 'complete_easy')) {
                                error_log("Achievement awarded: complete_easy (Beginner Detective) from victory screen");
                            }
                        }
                        
                        // 2. Check for "Flawless Victory" achievement (no lives lost)
                        if (isset($game['lives']) && isset($game['starting_lives']) && 
                            $game['lives'] >= $game['starting_lives'] && $game['starting_lives'] > 0) {
                            require_once 'models/Achievement.php';
                            if (award_achievement_model($user_id, 'perfect_score')) {
                                error_log("Achievement awarded: perfect_score (Flawless Victory) from victory screen");
                            }
                        }
                        
                        // Mark game as completed in database
                        try {
                            $db = get_db_connection();
                            $stmt = $db->prepare("
                                UPDATE games 
                                SET completed = 1, 
                                    completed_at = CURRENT_TIMESTAMP 
                                WHERE session_id = ? AND user_id = ?
                            ");
                            $stmt->execute([$session_id, $user_id]);
                            error_log("Game marked as completed in database: session_id=$session_id, user_id=$user_id");
                        } catch (Exception $e) {
                            error_log("Error marking game as completed: " . $e->getMessage());
                        }
                    }
                }
                
                // Clear all game-related session IDs after showing the game over screen
                // This will allow starting a new game from the homepage
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
                ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>