<?php
/**
 * Main entry point for Real vs AI application
 * 
 * This file handles the home page display and redirects to the appropriate
 * controllers based on user actions.
 */

// Include necessary files
require_once 'includes/functions.php'; // Contains flash message functions
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/compatibility.php'; // Add compatibility functions
require_once 'includes/auth_functions.php';
require_once 'includes/game_functions.php';

// Define a constant to prevent duplicate function definitions
if (!defined('FUNCTIONS_INCLUDED')) {
    define('FUNCTIONS_INCLUDED', true);
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Handle the new_game action to clear the game session
if (isset($_POST['action']) && $_POST['action'] === 'new_game') {
    // Clear all game session variables
    unset($_SESSION['game_session_id']);
    
    // Also clear mode-specific session variables
    foreach (['single', 'endless', 'multiplayer'] as $mode) {
        if (isset($_SESSION['game_session_id_' . $mode])) {
            unset($_SESSION['game_session_id_' . $mode]);
        }
    }
    
    // Log the session cleanup for debugging
    error_log("index.php - Cleared all game session variables due to new_game action");
    
    // Redirect to prevent form resubmission
    secure_redirect('index.php');
    exit;
}

// Check if user is logged in
$user = get_logged_in_user();

// Get recent game statistics
$recent_games = [];
$top_players = [];

try {
    // Get recent games (limit to 5)
    $recent_games_sql = "
        SELECT g.*, u.username 
        FROM games g
        JOIN users u ON g.user_id = u.id
        WHERE g.completed = TRUE AND u.is_admin = 0
        ORDER BY g.completed_at DESC
        LIMIT 5
    ";
    $recent_games = db_query($recent_games_sql);
    
    // Get top players (limit to 5)
    $top_players_sql = "
        SELECT u.id, u.username, MAX(g.score) as high_score
        FROM users u
        JOIN games g ON u.id = g.user_id
        WHERE g.completed = TRUE AND u.is_admin = 0 AND g.score > 0
        GROUP BY u.id, u.username
        ORDER BY high_score DESC
        LIMIT 5
    ";
    $top_players = db_query($top_players_sql);
} catch (Exception $e) {
    // Log error but continue - these are non-critical features
    error_log('Error fetching game statistics: ' . $e->getMessage());
}

// Page title
$page_title = "Real vs AI - Test Your Image Recognition Skills";

// Include header
require_once 'includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-12 text-center">
                <div class="hero-section">
                    <h1 class="hero-title mt-2 mb-4">Real Vs AI</h1>
                    <p class="hero-subtitle mb-4">Can you tell the difference between a real photo and an AI-generated image?</p>
                </div>
                
                <div class="mb-4 text-center">
                    <h2 class="section-title mb-5">Choose Game Mode</h2>
                        
                    <div class="row g-4 justify-content-center">
                        <!-- Single Player Mode -->
                        <div class="col-md-4">
                            <div class="game-mode-card h-100 p-4">
                                <h5 class="game-mode-title text-center">
                                    <i class="fas fa-user me-2"></i>
                                    Single Player
                                </h5>
                                <p class="game-mode-description text-center">
                                    Challenge yourself to identify real photos.
                                </p>
                                
                                <!-- Single Player mode no longer offers resume functionality -->
                                
                                <div class="d-flex justify-content-center" style="width: 100%;">
                                    <div style="width: 80%;">
                                        
                                        <form action="game.php" method="get" class="mt-auto">
                                            <input type="hidden" name="mode" value="single">
                                            
                                            <div class="form-group mb-1">
                                                <label for="difficulty" class="form-label">Select Difficulty:</label>
                                                <select class="form-select bg-dark text-light border-secondary" id="difficulty" name="difficulty" style="text-align-last: center; text-align: center;">
                                                    <option value="easy" style="text-align: center;">Easy</option>
                                                    <option value="medium" style="text-align: center;">Medium</option>
                                                    <option value="hard" style="text-align: center;">Hard</option>
                                                </select>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary mt-2" style="width: 100%;">
                                                Start Game
                                            </button>
                                        </form>
                                        
                                        <?php
                                        // Add Daily Challenge button for logged-in users
                                        if (isset($_SESSION['user_id'])):
                                        ?>
                                        <a href="daily-summary.php" class="btn btn-info mt-2" style="width: 100%;">
                                            <i class="fas fa-calendar-day me-1"></i> Daily Challenge
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Check if user has completed the tutorial
                                        $tutorial_completed = false;
                                        if (isset($_SESSION['user_id'])) {
                                            $user_id = $_SESSION['user_id'];
                                            $tutorial_query = "SELECT tutorial_completed FROM users WHERE id = ?";
                                            $tutorial_completed = (bool)db_query_value($tutorial_query, [$user_id]);
                                        }
                                        
                                        // Only show the tutorial button if the user hasn't completed it or isn't logged in
                                        if (!$tutorial_completed):
                                        ?>
                                        <a href="tutorial.php" class="btn btn-info mt-2" style="width: 100%;">
                                            <i class="fas fa-graduation-cap me-1"></i> Start Tutorial
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Play with Friends Mode -->
                        <div class="col-md-4">
                            <div class="game-mode-card h-100 p-4">
                                <h5 class="game-mode-title text-center">
                                    <i class="fas fa-users me-2"></i>
                                    Play with Friends
                                </h5>
                                <p class="game-mode-description text-center">
                                    Create a session and invite friends<br>
                                    to play together!
                                </p>
                                
                                <div class="d-flex justify-content-center mt-auto">
                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                        <!-- Show login button for non-logged in users -->
                                        <a href="login.php" class="btn btn-primary" style="width: 80%;">
                                            <i class="fas fa-sign-in-alt me-1"></i> Login to Play
                                        </a>
                                    <?php else: ?>
                                        <div class="d-grid gap-2" style="width: 80%;">
                                            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createGameModal">
                                                Create Game
                                            </button>
                                            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#joinGameModal">
                                                Join Game
                                            </button>
                                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickMatchModal">
                                                Quick Match
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Endless Mode (Ranked) -->
                        <div class="col-md-4">
                            <div class="game-mode-card h-100 p-4">
                                <h5 class="game-mode-title text-center">
                                    <i class="fas fa-infinity me-2"></i>
                                    Endless Mode
                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                    <?php endif; ?>
                                </h5>
                                <p class="game-mode-description text-center">
                                    One mistake and you're out!<br>
                                    How far can you go? Get on the leaderboard!
                                </p>
                                
                                <?php
                                // Check if the user has an unfinished endless game
                                $resumable_game = null;
                                if (isset($_SESSION['user_id'])) {
                                    $resumable_game = get_resumable_endless_game($_SESSION['user_id']);
                                }
                                ?>
                                
                                <div class="d-grid gap-2 mt-auto" style="width: 80%; margin: 0 auto;">
                                    <?php if (!isset($_SESSION['user_id'])): ?>
                                        <!-- Show login button for non-logged in users -->
                                        <a href="login.php" class="btn btn-primary" style="width: 100%;">
                                            <i class="fas fa-sign-in-alt me-1"></i> Login to Play
                                        </a>
                                    <?php else: ?>
                                        <?php if ($resumable_game): ?>
                                            <form action="game.php" method="get">
                                                <input type="hidden" name="resume" value="1">
                                                <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($resumable_game['session_id']); ?>">
                                                <button type="submit" class="btn btn-warning mb-2" style="width: 100%;">
                                                    <i class="fas fa-play-circle me-1"></i> Resume Game
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form action="game.php" method="get">
                                            <input type="hidden" name="mode" value="endless">
                                            <button type="submit" class="btn btn-danger" style="width: 100%;">
                                                <?php echo $resumable_game ? 'New Endless Game' : 'Play Endless'; ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- How to Play Section -->
    <div class="container-fluid mb-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-12 text-center">
                <h2 class="section-title mb-5">How to Play</h2>
                
                <div class="row g-4 justify-content-center">
                    <div class="col-md-4">
                        <div class="how-to-play-card text-center p-4">
                            <i class="fas fa-eye how-to-play-icon purple-icon"></i>
                            <h5 class="how-to-play-title">Observe</h5>
                            <p class="card-text">
                                You'll see two images - <br>
                                one real and one AI-generated.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="how-to-play-card text-center p-4">
                            <i class="fas fa-mouse-pointer how-to-play-icon purple-icon"></i>
                            <h5 class="how-to-play-title">Select</h5>
                            <p class="card-text">
                                Click on the image you think is real <br>
                                and submit your answer.
                            </p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="how-to-play-card text-center p-4">
                            <i class="fas fa-chart-line how-to-play-icon purple-icon"></i>
                            <h5 class="how-to-play-title">Score</h5>
                            <p class="card-text">
                                Each correct answer earns you points. <br>
                                Try to get on the leaderboard!
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Games and Top Players Section -->
    <?php if (isset($_SESSION['user_id']) && (!empty($recent_games) || !empty($top_players))): ?>
    <div class="container mb-5">
        <div class="row">
            <?php if (!empty($recent_games)): ?>
            <div class="col-md-6">
                <div class="card mb-4 border-0">
                    <div class="card-header bg-dark border-bottom border-secondary">
                        <h3 class="fs-4 fw-500 mb-0">Recent Games</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Player</th>
                                        <th>Mode</th>
                                        <th>Score</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_games as $game): ?>
                                        <?php if (!empty($game['completed_at'])): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($game['username']); ?></td>
                                            <td>
                                                <?php 
                                                    echo ucfirst($game['game_mode']);
                                                    if ($game['game_mode'] == 'single') {
                                                        echo ' (' . ucfirst($game['difficulty']) . ')';
                                                    }
                                                ?>
                                            </td>
                                            <td><?php echo $game['score']; ?></td>
                                            <td><?php echo date('M j, Y', strtotime($game['completed_at'])); ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($top_players)): ?>
            <div class="col-md-6">
                <div class="card border-0">
                    <div class="card-header bg-dark border-bottom border-secondary">
                        <h3 class="fs-4 fw-500 mb-0">Top Players</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Rank</th>
                                        <th>Player</th>
                                        <th>High Score</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $rank = 1; ?>
                                    <?php foreach ($top_players as $player): ?>
                                        <tr>
                                            <td><?php echo $rank++; ?></td>
                                            <td><?php echo htmlspecialchars($player['username']); ?></td>
                                            <td><?php echo $player['high_score']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add extra space before footer -->
<div class="mb-5 pb-3"></div>

<!-- Quick Match Modal -->
<div class="modal fade" id="quickMatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Join Quick Match</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Join a public multiplayer game with other players!</p>
                
                <form action="join_game.php" method="post" class="mb-3">
                    <input type="hidden" name="action" value="quick_match">
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="form-check form-switch mb-3 d-flex justify-content-center">
                            <input class="form-check-input me-2" type="checkbox" id="play-anon-quick" name="play_anonymous" value="1">
                            <label class="form-check-label" for="play-anon-quick">Play anonymously</label>
                        </div>
                    <?php endif; ?>
                    
                    <button type="submit" class="btn btn-primary btn-lg">Find Match</button>
                </form>
                
                <hr class="border-secondary my-4">
                
                <p class="text-muted small">Quick Match will connect you to an available public game or create a new one if none are available.</p>
            </div>
        </div>
    </div>
</div>

<!-- Create Game Modal -->
<div class="modal fade" id="createGameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Create Multiplayer Game</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Create a new multiplayer game and invite your friends!</p>
                
                <form action="start_game.php" method="post">
                    <input type="hidden" name="mode" value="multiplayer">
                    
                    <!-- Game visibility options -->
                    <div class="form-group mb-3">
                        <label class="form-label">Game Visibility:</label>
                        <div class="d-flex justify-content-center gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_public" id="publicGame" value="1" checked>
                                <label class="form-check-label" for="publicGame">
                                    <i class="fas fa-globe me-1"></i> Public
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="is_public" id="privateGame" value="0">
                                <label class="form-check-label" for="privateGame">
                                    <i class="fas fa-lock me-1"></i> Private
                                </label>
                            </div>
                        </div>
                        <div class="small text-muted text-center mt-1">
                            <span id="publicInfo">Public games can be joined by anyone</span>
                            <span id="privateInfo" style="display:none">Private games require a room code to join</span>
                        </div>
                    </div>
                    
                    <!-- Number of turns -->
                    <div class="form-group mb-3 d-flex justify-content-center">
                        <div style="width: 80%;">
                            <label for="totalTurns" class="form-label">Number of Turns:</label>
                            <select class="form-select bg-dark text-light border-secondary" id="totalTurns" name="total_turns">
                                <option value="5">5 Turns (Quick)</option>
                                <option value="10" selected>10 Turns (Normal)</option>
                                <option value="15">15 Turns (Extended)</option>
                                <option value="20">20 Turns (Long)</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Play anonymously option removed -->
                    
                    <div class="d-flex justify-content-center">
                        <button type="submit" class="btn btn-success" style="width: 80%;">Create Game</button>
                    </div>
                </form>
                
                <script>
                    // Toggle visibility info text based on selection
                    document.getElementById('publicGame').addEventListener('change', function() {
                        document.getElementById('publicInfo').style.display = 'block';
                        document.getElementById('privateInfo').style.display = 'none';
                    });
                    document.getElementById('privateGame').addEventListener('change', function() {
                        document.getElementById('publicInfo').style.display = 'none';
                        document.getElementById('privateInfo').style.display = 'block';
                    });
                </script>
            </div>
        </div>
    </div>
</div>

<!-- Join Game Modal -->
<div class="modal fade" id="joinGameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-light">
            <div class="modal-header border-secondary">
                <h5 class="modal-title">Join Multiplayer Game</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="joinGameTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="session-tab" data-bs-toggle="tab" data-bs-target="#session" type="button" role="tab">Session ID</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="room-tab" data-bs-toggle="tab" data-bs-target="#room" type="button" role="tab">Room Code</button>
                    </li>
                </ul>
                
                <div class="tab-content mt-3" id="joinGameTabContent">
                    <!-- Session ID Tab -->
                    <div class="tab-pane fade show active" id="session" role="tabpanel">
                        <form action="join_game.php" method="post">
                            <input type="hidden" name="join_type" value="session">
                            <div class="form-group mb-3">
                                <label for="sessionId" class="form-label">Session ID:</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="sessionId" name="session_id" required placeholder="Enter the session ID shared by your friend">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-info">Join Game</button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Room Code Tab -->
                    <div class="tab-pane fade" id="room" role="tabpanel">
                        <form action="join_game.php" method="post">
                            <input type="hidden" name="join_type" value="room">
                            <div class="form-group mb-3">
                                <label for="roomCode" class="form-label">Room Code:</label>
                                <input type="text" class="form-control bg-dark text-light border-secondary" id="roomCode" name="room_code" required placeholder="Enter the room code (e.g. ABC-123)">
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-info">Join Game</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
            </div>
        </div>
    </div>
</div>



<?php require_once 'includes/footer.php'; ?>