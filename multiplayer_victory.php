<?php
/**
 * Multiplayer Victory Page
 * 
 * This page is shown when a player finishes a multiplayer game.
 * It shows the current standings and waits for other players to finish.
 */

// Include bootstrap
require_once 'bootstrap.php';

// Check if game session exists
if (!isset($_SESSION['game_session_id_multiplayer'])) {
    // Redirect to game select page if no active game
    header('Location: multiplayer.php');
    exit;
}

// Get the multiplayer game
$session_id = $_SESSION['game_session_id_multiplayer'];
$game = get_multiplayer_game_state($session_id);

// Ensure game exists
if (!$game) {
    flash_message("The multiplayer game session could not be found.", "danger");
    secure_redirect('multiplayer.php');
    exit;
}

// Get current user ID and username
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';

// Determine which player slot the current user occupies
$player_slot = 0;
for ($i = 1; $i <= 4; $i++) {
    if (
        ($user_id !== null && $game["player{$i}_id"] == $user_id) ||
        ($username !== 'Guest' && $game["player{$i}_name"] == $username)
    ) {
        $player_slot = $i;
        break;
    }
}

// If user is not a player in this game, redirect to home
if ($player_slot === 0) {
    secure_redirect('index.php');
    exit;
}

// Get game difficulty based on number of turns
$difficulty = 'Easy';
if ($game['total_turns'] >= 20) {
    $difficulty = 'Hard';
} elseif ($game['total_turns'] >= 15) {
    $difficulty = 'Medium';
}

// Get all player scores and sort by score descending
$players = [];
for ($i = 1; $i <= 4; $i++) {
    if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
        $player_id = $game["player{$i}_id"];
        $player_name = !empty($game["player{$i}_name"]) ? $game["player{$i}_name"] : "Player $i";
        $player_score = isset($game["player{$i}_score"]) ? (int)$game["player{$i}_score"] : 0;
        $is_bot = empty($player_id) && !empty($player_name);
        $has_finished = isset($game["player{$i}_finished"]) && $game["player{$i}_finished"] == 1;
        
        $players[] = [
            'id' => $player_id,
            'name' => $player_name,
            'score' => $player_score,
            'is_bot' => $is_bot,
            'slot' => $i,
            'has_finished' => $has_finished,
            'is_current_user' => ($i == $player_slot)
        ];
    }
}

// Sort players by score (highest first)
usort($players, function($a, $b) {
    return $b['score'] - $a['score'];
});

// Check if all players have finished
$all_finished = true;
foreach ($players as $player) {
    if (!$player['has_finished'] && !$player['is_current_user']) {
        $all_finished = false;
        break;
    }
}

// Check if current player has already been marked as finished
$current_player_finished = isset($game["player{$player_slot}_finished"]) && $game["player{$player_slot}_finished"] == 1;

// If the current player hasn't been marked as finished yet, update the database
if (!$current_player_finished) {
    $conn = get_db_connection();
    $stmt = $conn->prepare("UPDATE multiplayer_games SET player{$player_slot}_finished = 1, player{$player_slot}_finished_at = NOW() WHERE session_id = ?");
    $stmt->execute([$session_id]);
    
    // Refresh game state
    $game = get_multiplayer_game_state($session_id);
}

// Calculate wait time (10 minutes maximum)
$wait_start_time = isset($game["player{$player_slot}_finished_at"]) ? strtotime($game["player{$player_slot}_finished_at"]) : time();
$current_time = time();
$elapsed_wait_time = $current_time - $wait_start_time;
$max_wait_time = 10 * 60; // 10 minutes in seconds
$remaining_wait_time = max(0, $max_wait_time - $elapsed_wait_time);

// Determine if the current player is the winner, loser, or in a draw
$current_player_score = 0;
$highest_score = 0;
$winner_name = '';
$is_winner = false;
$is_tie = false;

foreach ($players as $index => $player) {
    if ($player['is_current_user']) {
        $current_player_score = $player['score'];
    }
    
    if ($index == 0) {
        $highest_score = $player['score'];
        $winner_name = $player['name'];
    }
}

// Check win/tie status only if all players finished or wait time expired
if ($all_finished || $remaining_wait_time <= 0) {
    // Count how many players have the highest score
    $players_with_highest_score = 0;
    foreach ($players as $player) {
        if ($player['score'] == $highest_score) {
            $players_with_highest_score++;
        }
    }
    
    // Determine if player won, tied, or lost
    if ($current_player_score == $highest_score) {
        $is_winner = true;
        if ($players_with_highest_score > 1) {
            $is_tie = true;
        }
    }
}

// Get page title
$page_title = "Multiplayer Game Results";

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-dark text-white">
                    <h2>Multiplayer Game Results</h2>
                </div>
                <div class="card-body">
                    <?php if ($all_finished || $remaining_wait_time <= 0): ?>
                        <!-- All players finished or wait time expired -->
                        <div class="alert <?php echo $is_winner ? ($is_tie ? 'alert-warning' : 'alert-success') : 'alert-info'; ?> text-center mb-4">
                            <?php if ($is_winner): ?>
                                <?php if ($is_tie): ?>
                                    <h3><i class="fas fa-handshake"></i> It's a tie!</h3>
                                    <p>You tied for first place with a score of <?php echo $current_player_score; ?>.</p>
                                <?php else: ?>
                                    <h3><i class="fas fa-trophy"></i> You Won!</h3>
                                    <p>Congratulations! You won with a score of <?php echo $current_player_score; ?>.</p>
                                <?php endif; ?>
                            <?php else: ?>
                                <h3><i class="fas fa-medal"></i> Game Complete</h3>
                                <p><?php echo htmlspecialchars($winner_name); ?> won with a score of <?php echo $highest_score; ?>.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Waiting for other players -->
                        <div class="alert alert-warning text-center mb-4">
                            <h3><i class="fas fa-hourglass-half"></i> Waiting for other players...</h3>
                            <p>We're waiting for the other players to finish. You'll automatically see the final results when everyone completes the game.</p>
                            <div class="mt-3">
                                <h4>Time remaining: <span id="countdown"></span></h4>
                                <div class="progress">
                                    <div id="countdown-progress" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Current Standings -->
                    <h3 class="mb-3">Current Standings</h3>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Rank</th>
                                    <th>Player</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $index => $player): ?>
                                <tr class="<?php echo $player['is_current_user'] ? 'table-primary' : ''; ?>">
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($player['name']); ?>
                                        <?php if ($player['is_bot']): ?>
                                            <span class="badge bg-secondary">BOT</span>
                                        <?php endif; ?>
                                        <?php if ($player['is_current_user']): ?>
                                            <span class="badge bg-primary">YOU</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $player['score']; ?></td>
                                    <td>
                                        <?php if ($player['has_finished']): ?>
                                            <span class="badge bg-success">Finished</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Playing</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Game Details -->
                    <div class="card mt-4">
                        <div class="card-header bg-secondary text-white">
                            <h3>Game Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Game Mode:</strong> Multiplayer</p>
                                    <p><strong>Difficulty:</strong> <?php echo $difficulty; ?></p>
                                    <p><strong>Total Turns:</strong> <?php echo $game['total_turns']; ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Players:</strong> <?php echo count($players); ?></p>
                                    <p><strong>Your Score:</strong> <?php echo $current_player_score; ?></p>
                                    <p><strong>Session ID:</strong> <?php echo substr($session_id, 0, 8) . '...'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Submit Score Button -->
                    <?php if ($current_player_score > 0): ?>
                    <div class="text-center mt-4">
                        <?php if (!$all_finished && $remaining_wait_time > 0): ?>
                            <form action="submit_score.php" method="post">
                                <input type="hidden" name="score" value="<?php echo $current_player_score; ?>">
                                <input type="hidden" name="game_mode" value="multiplayer">
                                <input type="hidden" name="difficulty" value="<?php echo strtolower($difficulty); ?>">
                                <input type="hidden" name="skip_wait" value="1">
                                <button type="submit" class="btn btn-gold btn-lg">
                                    <i class="fas fa-clock"></i> Skip Wait and Submit Score
                                </button>
                            </form>
                        <?php else: ?>
                            <form action="submit_score.php" method="post">
                                <input type="hidden" name="score" value="<?php echo $current_player_score; ?>">
                                <input type="hidden" name="game_mode" value="multiplayer">
                                <input type="hidden" name="difficulty" value="<?php echo strtolower($difficulty); ?>">
                                <button type="submit" class="btn btn-gold btn-lg">
                                    <i class="fas fa-trophy"></i> Submit Score to Leaderboard
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info text-center mt-4">
                        <p>You need to score at least 1 point to submit your score to the leaderboard.</p>
                        <a href="multiplayer.php" class="btn btn-primary mt-2">
                            <i class="fas fa-users"></i> Play Another Multiplayer Game
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Navigation Buttons -->
                    <div class="text-center mt-3">
                        <a href="multiplayer.php" class="btn btn-secondary">
                            <i class="fas fa-users"></i> New Multiplayer Game
                        </a>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-home"></i> Home
                        </a>
                        <a href="leaderboard.php" class="btn btn-info">
                            <i class="fas fa-list-ol"></i> View Leaderboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for countdown timer -->
<script>
    // Only initialize countdown if waiting for players
    <?php if (!$all_finished && $remaining_wait_time > 0): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Initial remaining wait time in seconds
        let remainingTime = <?php echo $remaining_wait_time; ?>;
        const totalWaitTime = <?php echo $max_wait_time; ?>;
        const countdownEl = document.getElementById('countdown');
        const progressBar = document.getElementById('countdown-progress');
        
        // Function to format time as mm:ss
        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
        }
        
        // Update countdown every second
        const countdownInterval = setInterval(function() {
            remainingTime--;
            
            // Update display
            countdownEl.textContent = formatTime(remainingTime);
            
            // Update progress bar
            const progressPercent = (remainingTime / totalWaitTime) * 100;
            progressBar.style.width = `${progressPercent}%`;
            
            // Change progress bar color as time decreases
            if (progressPercent <= 25) {
                progressBar.classList.remove('bg-warning');
                progressBar.classList.add('bg-danger');
            } else if (progressPercent <= 50) {
                progressBar.classList.remove('bg-info');
                progressBar.classList.add('bg-warning');
            }
            
            // When countdown completes
            if (remainingTime <= 0) {
                clearInterval(countdownInterval);
                // Reload the page to show final results
                location.reload();
            }
        }, 1000);
        
        // Initialize first display
        countdownEl.textContent = formatTime(remainingTime);
    });
    <?php endif; ?>
    
    // Auto-refresh page to check if all players have finished
    <?php if (!$all_finished && $remaining_wait_time > 0): ?>
    setTimeout(function() {
        location.reload();
    }, 30000); // Refresh every 30 seconds
    <?php endif; ?>
</script>

<?php
// Include footer
include 'includes/footer.php';
?>