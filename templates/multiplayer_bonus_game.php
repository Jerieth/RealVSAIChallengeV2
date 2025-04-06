<?php
/**
 * Multiplayer Bonus Game Template
 * 
 * This template displays the multiplayer bonus game interface where players can
 * select treasure chests to earn additional points at the end of a multiplayer game.
 */
?>

<div id="multiplayerBonusGameContainer" class="mt-4">
    <input type="hidden" id="gameSessionId" value="<?= $session_id ?>">
    
    <div class="card border-0 mb-4">
        <div class="card-header bg-primary">
            <h3 class="card-title mb-0 text-white">Bonus Challenge!</h3>
        </div>
        <div class="card-body">
            <!-- Introduction section -->
            <div class="text-center mb-4">
                <h4>Congratulations on completing the game!</h4>
                <p class="lead">Now it's time for a bonus challenge to earn extra points.</p>
                <p>Each player can select one treasure chest containing bonus points. Choose wisely!</p>
            </div>
            
            <!-- Initialization -->
            <div id="bonusGameInitSection" class="text-center mb-4">
                <button id="startBonusGameBtn" class="btn btn-success btn-lg">
                    <i class="fas fa-play-circle me-2"></i>Start Bonus Challenge
                </button>
                <div id="bonusGameLoading" class="mt-3" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Preparing bonus challenge...</p>
                </div>
            </div>
            
            <!-- Chest selection section -->
            <div id="chestContainer" class="text-center mb-4" style="display: none;">
                <div id="bonusGameInstructions" class="alert alert-info mb-4" style="display: none;">
                    <p class="mb-0"><strong>Instructions:</strong> Select one treasure chest to earn bonus points. Each chest contains a different point value (10-50).</p>
                </div>
                
                <div class="d-flex justify-content-center flex-wrap">
                    <?php for ($i = 0; $i < 5; $i++): ?>
                    <div class="bonus-chest" data-index="<?= $i ?>">
                        <i class="fas fa-treasure-chest"></i>
                    </div>
                    <?php endfor; ?>
                </div>
                
                <div id="bonusMessageContainer" class="mt-3" style="display: none;"></div>
            </div>
            
            <!-- Score display -->
            <div class="card bg-dark mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Current Scores</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <?php if (!empty($game["player{$i}_name"])): ?>
                            <div class="col-md-3 col-6 mb-3">
                                <div class="card bg-secondary">
                                    <div class="card-body text-center p-2">
                                        <h6 class="card-title mb-1"><?= htmlspecialchars($game["player{$i}_name"]) ?></h6>
                                        <p class="card-text mb-0 fs-4" id="player<?= $i ?>FinalScore"><?= $game["player{$i}_score"] ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            
            <!-- Game completion section -->
            <div id="bonusGameResultsContainer" class="text-center" style="display: none;">
                <div id="completingGameMessage" class="mb-3" style="display: none;">
                    <p>Finalizing results...</p>
                </div>
                
                <div id="winnerAnnouncement" class="alert alert-success mb-3" style="display: none;"></div>
                
                <div id="youWonMessage" class="mb-4" style="display: none;">
                    <h2 class="text-success mb-3">Congratulations! You won!</h2>
                    <div class="confetti-container">
                        <img src="static/images/trophy.png" alt="Trophy" class="img-fluid mb-3" style="max-width: 150px;">
                    </div>
                </div>
                
                <div id="returnToHomeMessage" class="mb-4" style="display: none;">
                    <p>The game is now complete. Thank you for playing!</p>
                </div>
                
                <a id="returnHomeBtn" href="index.php" class="btn btn-primary btn-lg" style="display: none;">
                    <i class="fas fa-home me-2"></i>Return to Home
                </a>
            </div>
            
            <!-- Error display -->
            <div id="bonusGameError" class="alert alert-danger" style="display: none;"></div>
        </div>
    </div>
</div>