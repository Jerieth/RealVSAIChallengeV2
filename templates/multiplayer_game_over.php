<?php
/**
 * Multiplayer Game Over Template
 * 
 * This template displays the multiplayer game over screen, showing final scores
 * and allowing players to participate in a bonus challenge.
 */
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="result-card">
                <h1 class="result-title text-primary">Game Complete!</h1>
                
                <p class="lead mb-4">The multiplayer match has ended. Here are the final results:</p>
                
                <!-- Player scores section -->
                <div class="mb-4">
                    <h3 class="mb-3">Final Scores</h3>
                    <div class="row">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <?php if (!empty($game["player{$i}_name"])): ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card <?= $current_player === $i ? 'bg-primary' : 'bg-dark' ?>">
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?= htmlspecialchars($game["player{$i}_name"]) ?></h5>
                                        <p class="card-text fs-2 fw-bold"><?= $game["player{$i}_score"] ?></p>
                                        <?php if ($game["winner_player_num"] === $i): ?>
                                        <div class="winner-badge">
                                            <i class="fas fa-crown text-warning me-1"></i> Winner
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <!-- Game statistics section -->
                <div class="game-stats mb-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="card bg-dark">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Game Mode</h5>
                                    <p class="card-text">Multiplayer</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-dark">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Turns Completed</h5>
                                    <p class="card-text"><?= $game['total_turns'] ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="card bg-dark">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Players</h5>
                                    <p class="card-text"><?= $player_count ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Include bonus game template if not yet started -->
                <?php if (!$game['bonus_game_started']): ?>
                    <?php include 'templates/multiplayer_bonus_game.php'; ?>
                <?php else: ?>
                    <div class="text-center mb-4">
                        <p>The bonus challenge has already been played.</p>
                        <a href="index.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-home me-2"></i>Return to Home
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Action buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                    <a href="index.php" class="btn btn-outline-primary btn-lg">Back to Home</a>
                    <a href="leaderboard.php" class="btn btn-outline-info btn-lg">View Leaderboard</a>
                    
                    <!-- Play Again button redirects to home page -->
                    <a href="index.php" class="btn btn-success btn-lg">Play Again</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include the multiplayer bonus game JavaScript -->
<script src="static/js/multiplayer_bonus_game.js"></script>
<script>
// Initialize JSConfetti for celebrations
const jsConfetti = new JSConfetti();
</script>