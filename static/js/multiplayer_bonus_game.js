// multiplayer_bonus_game.js - Client-side logic for multiplayer end-game bonus challenge

document.addEventListener('DOMContentLoaded', function() {
    // Check if we're on a multiplayer game completed page with bonus game
    const multiplayerBonusGameContainer = document.getElementById('multiplayerBonusGameContainer');
    if (!multiplayerBonusGameContainer) return;
    
    // Elements
    const startBonusGameBtn = document.getElementById('startBonusGameBtn');
    const chestContainer = document.getElementById('chestContainer');
    const bonusGameResultsContainer = document.getElementById('bonusGameResultsContainer');
    
    // Game state
    let gameSessionId = document.getElementById('gameSessionId')?.value;
    let playerNumber = 0;
    let selectedChest = null;
    let gameCompleted = false;
    
    // Initialize bonus game
    if (startBonusGameBtn) {
        startBonusGameBtn.addEventListener('click', startBonusGame);
    }
    
    // Initialize chest selection
    const chests = document.querySelectorAll('.bonus-chest');
    chests.forEach((chest, index) => {
        chest.addEventListener('click', () => selectChest(index));
    });
    
    // Start the bonus game
    function startBonusGame() {
        // Hide start button
        startBonusGameBtn.style.display = 'none';
        
        // Show loading indicator
        document.getElementById('bonusGameLoading').style.display = 'block';
        
        // Send AJAX request to start the bonus game
        fetch('game_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=start_multiplayer_bonus_game'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show chest selection
                document.getElementById('bonusGameLoading').style.display = 'none';
                chestContainer.style.display = 'block';
                
                // Show instructions
                document.getElementById('bonusGameInstructions').style.display = 'block';
                
                // Start polling for game status
                pollBonusGameStatus();
            } else {
                showError(data.message || 'Failed to start bonus game');
            }
        })
        .catch(error => {
            showError('Network error: ' + error.message);
        });
    }
    
    // Select chest
    function selectChest(index) {
        // Prevent selecting if already selected
        if (selectedChest !== null || gameCompleted) return;
        
        // Highlight the selected chest
        chests.forEach(c => c.classList.remove('selected'));
        chests[index].classList.add('selected');
        
        // Confirm selection
        if (confirm('Are you sure you want to select this chest?')) {
            selectedChest = index;
            
            // Disable further selection
            chests.forEach(c => c.classList.add('disabled'));
            
            // Show loading indicator
            document.getElementById('bonusGameLoading').style.display = 'block';
            
            // Send AJAX request to record selection
            fetch('game_actions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=multiplayer_chest_selection&chest_index=${index}`
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('bonusGameLoading').style.display = 'none';
                
                if (data.success) {
                    // Show selected chest value
                    const chestValueElement = document.createElement('div');
                    chestValueElement.className = 'chest-value';
                    chestValueElement.textContent = `+${data.bonus_value}`;
                    chests[index].appendChild(chestValueElement);
                    
                    // Show success message
                    const messageElement = document.getElementById('bonusMessageContainer');
                    messageElement.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                    messageElement.style.display = 'block';
                    
                    // Update scores in the UI
                    updateScores(data.all_scores);
                    
                    // Confetti temporarily disabled for troubleshooting
                    // if (window.jsConfetti) {
                    //     jsConfetti.addConfetti({
                    //         confettiRadius: 6,
                    //         confettiNumber: 100,
                    //     });
                    // }
                    
                    // Continue polling game status
                    pollBonusGameStatus();
                } else {
                    showError(data.message || 'Failed to select chest');
                    // Reset selection
                    selectedChest = null;
                    chests.forEach(c => c.classList.remove('disabled'));
                }
            })
            .catch(error => {
                document.getElementById('bonusGameLoading').style.display = 'none';
                showError('Network error: ' + error.message);
                // Reset selection
                selectedChest = null;
                chests.forEach(c => c.classList.remove('disabled'));
            });
        } else {
            // Reset selection if canceled
            chests[index].classList.remove('selected');
        }
    }
    
    // Poll for bonus game status
    function pollBonusGameStatus() {
        if (gameCompleted) return;
        
        fetch('game_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_multiplayer_bonus_images'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update player number
                if (data.player_number) {
                    playerNumber = data.player_number;
                }
                
                // Update chest values and selections
                updateChests(data.chest_values, data.player_selections);
                
                // Update scores in the UI
                updateScores(data.all_scores);
                
                // Check if we need to keep polling
                if (hasAllPlayersSelected(data.player_selections)) {
                    // Show game completion
                    showGameCompletion();
                } else {
                    // Continue polling
                    setTimeout(pollBonusGameStatus, 3000);
                }
            } else {
                // Error handling
                showError(data.message || 'Failed to get bonus game status');
                
                // Retry after a delay
                setTimeout(pollBonusGameStatus, 5000);
            }
        })
        .catch(error => {
            showError('Network error: ' + error.message);
            // Retry after a delay
            setTimeout(pollBonusGameStatus, 5000);
        });
    }
    
    // Update chest UI based on current selections
    function updateChests(chestValues, playerSelections) {
        if (!chestValues || !playerSelections) return;
        
        // Update each chest
        chestValues.forEach((chestData, index) => {
            const chest = chests[index];
            
            // If this chest has been selected by any player
            if (chestData.selected_by > 0) {
                // Add player badge
                let playerBadge = chest.querySelector('.player-badge');
                if (!playerBadge) {
                    playerBadge = document.createElement('div');
                    playerBadge.className = 'player-badge';
                    chest.appendChild(playerBadge);
                }
                
                // Set player badge color based on whether it's the current player
                if (chestData.selected_by === playerNumber) {
                    playerBadge.className = 'player-badge bg-primary';
                    playerBadge.textContent = 'YOU';
                } else {
                    playerBadge.className = 'player-badge bg-secondary';
                    playerBadge.textContent = `P${chestData.selected_by}`;
                }
                
                // Show chest value if revealed
                if (chestData.value !== null) {
                    let chestValue = chest.querySelector('.chest-value');
                    if (!chestValue) {
                        chestValue = document.createElement('div');
                        chestValue.className = 'chest-value';
                        chest.appendChild(chestValue);
                    }
                    chestValue.textContent = `+${chestData.value}`;
                }
                
                // Disable chest for selection
                chest.classList.add('disabled');
            }
        });
        
        // If current player has already selected a chest, update selectedChest variable
        if (playerSelections[playerNumber] > 0) {
            selectedChest = playerSelections[playerNumber] - 1; // Convert from 1-based to 0-based
        }
    }
    
    // Check if all players have selected chests
    function hasAllPlayersSelected(playerSelections) {
        // Count active players (we'll consider a player active if they have a non-zero selection value)
        let activePlayers = 0;
        let selectedPlayers = 0;
        
        Object.keys(playerSelections).forEach(player => {
            if (player > 0 && player <= 4) {
                activePlayers++;
                if (playerSelections[player] > 0) {
                    selectedPlayers++;
                }
            }
        });
        
        // Return true if all active players have selected
        return activePlayers > 0 && selectedPlayers === activePlayers;
    }
    
    // Show game completion and final results
    function showGameCompletion() {
        if (gameCompleted) return;
        gameCompleted = true;
        
        // Show final loading
        document.getElementById('bonusGameLoading').style.display = 'block';
        document.getElementById('completingGameMessage').style.display = 'block';
        
        // Send final result request
        fetch('game_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=handle_multiplayer_bonus_result'
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('bonusGameLoading').style.display = 'none';
            
            if (data.success) {
                // Show winner celebration
                const winnerElement = document.getElementById('winnerAnnouncement');
                winnerElement.textContent = data.message;
                winnerElement.style.display = 'block';
                
                // Show different message if the current player is the winner
                if (data.winner.player_number === playerNumber) {
                    document.getElementById('youWonMessage').style.display = 'block';
                    
                    // Confetti temporarily disabled for troubleshooting
                    // if (window.jsConfetti) {
                    //     jsConfetti.addConfetti({
                    //         confettiRadius: 6,
                    //         confettiNumber: 300,
                    //     });
                    // }
                } else {
                    document.getElementById('returnToHomeMessage').style.display = 'block';
                }
                
                // Show return to home button
                document.getElementById('returnHomeBtn').style.display = 'block';
                
                // Update final scores
                updateScores(data.all_scores);
            } else {
                showError(data.message || 'Failed to complete bonus game');
                
                // Show return to home button anyway
                document.getElementById('returnHomeBtn').style.display = 'block';
            }
        })
        .catch(error => {
            document.getElementById('bonusGameLoading').style.display = 'none';
            showError('Network error: ' + error.message);
            
            // Show return to home button anyway
            document.getElementById('returnHomeBtn').style.display = 'block';
        });
    }
    
    // Update scores in the UI
    function updateScores(scores) {
        if (!scores) return;
        
        // Update each player's score
        for (let i = 1; i <= 4; i++) {
            const scoreElement = document.getElementById(`player${i}FinalScore`);
            if (scoreElement && scores[`player${i}`] !== undefined) {
                scoreElement.textContent = scores[`player${i}`];
                
                // Highlight current player's score
                if (i === playerNumber) {
                    scoreElement.parentElement.classList.add('bg-primary');
                }
            }
        }
    }
    
    // Show error message
    function showError(message) {
        const errorElement = document.getElementById('bonusGameError');
        errorElement.textContent = message;
        errorElement.style.display = 'block';
        
        // Hide after 5 seconds
        setTimeout(() => {
            errorElement.style.display = 'none';
        }, 5000);
    }
});