// game.js - Client-side game logic for Real vs AI
// FINAL FIXED VERSION with duplicate functions removed

// The debug functionality is now handled by debugLogger.js
// We keep these variables for backward compatibility
var debugMode = false;

// This function is kept for backward compatibility but is empty
// The actual initialization happens in debugLogger.js
function initializeDebugMode() {
    // Implementation moved to debugLogger.js
    // This function is kept empty for backward compatibility
}

// This function is kept for backward compatibility
// It just calls the centralized debugLog function from debugLogger.js
function debugLog() {
    // Forward to the global debugLog function from debugLogger.js
    if (typeof window.centralDebugLog === 'function') {
        window.centralDebugLog.apply(window, arguments);
    } else if (debugMode) {
        // Fallback if the global function is not available
        console.log.apply(console, arguments);
    }
}

// Score tracking globals with clearer initialization
// Use var instead of let to avoid redeclaration issues when loaded multiple times
var currentScore = 0;
var currentStreak = 0;
var currentTurn = 1;
var totalTurns = 0;
var selectedImage = null;
var gameState = 'select';
var startTime = new Date();
var leftImg = null;
var rightImg = null;
var imagesLoaded = 0;
var totalImages = 2;
var timeInterval = null;
var timePenalty = false;
var timerEnabled = true;
var soundMuted = localStorage.getItem('soundMuted') === 'true';

// JSConfetti completely removed as requested

// Add debugging
debugLog('GAME.JS LOADED - This is the FINAL FIXED VERSION with duplicate functions removed');
debugLog('INITIAL SCORE DEBUG - Score value:', currentScore);

// Helper function to play sound effects (only if sound is not muted)
function playSound(type) {
    if (soundMuted) {
        debugLog('Sound muted, not playing sound of type:', type);
        return;
    }
    
    try {
        // Sound file paths for different sound types
        const soundFiles = {
            'correct': '/static/sounds/correct-6033.mp3',
            'wrong': '/static/sounds/wrong-47985.mp3',
            'wrong_single': '/static/sounds/wrong-47985.mp3',  // Specific wrong sound for Single Player mode
            'streak': '/static/sounds/the-correct-answer-33-183620.mp3',
            'player_joined': '/static/sounds/wrong-answer-129254.mp3',
            'multiplayer_start': '/static/sounds/new-information-153314.mp3',
            'game_over': '/static/sounds/fail-144746.mp3',
            'out_of_images': '/static/sounds/wow-171498.mp3'
        };
        
        let soundFile = soundFiles[type];
        
        if (soundFile) {
            // Create an audio element
            const audio = new Audio(soundFile);
            audio.volume = 0.5; // Set volume to 50%
            audio.play()
                .catch(e => {
                    console.warn('Error playing sound:', e);
                    // Fallback to the old method if needed
                    playFallbackSound(type);
                });
        } else {
            // For unknown sound types or as fallback, use the oscillator method
            playFallbackSound(type);
        }
    } catch (e) {
        console.warn('Could not play sound:', e);
    }
}

// Fallback sound method using oscillator
function playFallbackSound(type) {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        let oscillator1, oscillator2, gainNode;
        
        // Create oscillators and settings based on sound type
        switch(type) {
            case 'streak':
                // Celebratory streak sound (higher pitched, exciting)
                oscillator1 = audioContext.createOscillator();
                oscillator1.type = 'sine';
                oscillator1.frequency.setValueAtTime(500, audioContext.currentTime);
                oscillator1.frequency.exponentialRampToValueAtTime(900, audioContext.currentTime + 0.1);
                
                oscillator2 = audioContext.createOscillator();
                oscillator2.type = 'triangle';
                oscillator2.frequency.setValueAtTime(750, audioContext.currentTime + 0.1);
                oscillator2.frequency.exponentialRampToValueAtTime(1200, audioContext.currentTime + 0.2);
                
                gainNode = audioContext.createGain();
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator1.connect(gainNode);
                oscillator2.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator1.start();
                oscillator1.stop(audioContext.currentTime + 0.3);
                oscillator2.start(audioContext.currentTime + 0.1);
                oscillator2.stop(audioContext.currentTime + 0.4);
                break;
                
            case 'correct':
                // Positive affirmation sound (pleasant upward tone)
                oscillator1 = audioContext.createOscillator();
                oscillator1.type = 'sine';
                oscillator1.frequency.setValueAtTime(400, audioContext.currentTime);
                oscillator1.frequency.exponentialRampToValueAtTime(600, audioContext.currentTime + 0.2);
                
                gainNode = audioContext.createGain();
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator1.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator1.start();
                oscillator1.stop(audioContext.currentTime + 0.3);
                break;
                
            case 'wrong':
            case 'player_joined':
            case 'game_over':
            case 'wrong_single':  // Added wrong_single to the fallback sound
                // Negative "wrong answer" sound (lower pitched, descending)
                oscillator1 = audioContext.createOscillator();
                oscillator1.type = 'sine';
                oscillator1.frequency.setValueAtTime(300, audioContext.currentTime);
                oscillator1.frequency.exponentialRampToValueAtTime(150, audioContext.currentTime + 0.2);
                
                gainNode = audioContext.createGain();
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                
                oscillator1.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator1.start();
                oscillator1.stop(audioContext.currentTime + 0.3);
                break;
                
            case 'multiplayer_start':
                // Celebratory sound for multiplayer game start
                oscillator1 = audioContext.createOscillator();
                oscillator1.type = 'sine';
                oscillator1.frequency.setValueAtTime(400, audioContext.currentTime);
                oscillator1.frequency.exponentialRampToValueAtTime(800, audioContext.currentTime + 0.3);
                
                gainNode = audioContext.createGain();
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);
                
                oscillator1.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator1.start();
                oscillator1.stop(audioContext.currentTime + 0.4);
                break;
                
            case 'out_of_images':
                // Special sound for running out of images - fun surprise sound
                oscillator1 = audioContext.createOscillator();
                oscillator1.type = 'sine';
                oscillator1.frequency.setValueAtTime(700, audioContext.currentTime);
                oscillator1.frequency.exponentialRampToValueAtTime(1200, audioContext.currentTime + 0.2);
                
                oscillator2 = audioContext.createOscillator();
                oscillator2.type = 'triangle';
                oscillator2.frequency.setValueAtTime(900, audioContext.currentTime + 0.1);
                oscillator2.frequency.exponentialRampToValueAtTime(600, audioContext.currentTime + 0.3);
                
                gainNode = audioContext.createGain();
                gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.4);
                
                oscillator1.connect(gainNode);
                oscillator2.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                oscillator1.start();
                oscillator1.stop(audioContext.currentTime + 0.3);
                oscillator2.start(audioContext.currentTime + 0.1);
                oscillator2.stop(audioContext.currentTime + 0.4);
                break;
                
            default:
                console.warn('Unknown sound type:', type);
                return;
        }
    } catch (e) {
        console.warn('Could not play fallback sound:', e);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    // Bot scoring timer 
    let botScoreTimer = null;
    let botScoreTimerEnabled = false;
    
    // Function to start the bot scoring timer
    function startBotScoreTimer() {
        // Only start if we're in a multiplayer game
        const gameMode = document.body.getAttribute('data-game-mode');
        if (gameMode !== 'multiplayer') return;
        
        // Get session ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const sessionId = urlParams.get('session_id');
        if (!sessionId) return;
        
        // Start the timer to simulate bot answering
        botScoreTimerEnabled = true;
        
        function performBotScoring() {
            if (!botScoreTimerEnabled) return;
            
            // Set a random delay between 3 to 8 seconds
            const delay = Math.floor(Math.random() * 5000) + 3000;
            
            // Send AJAX request to enhance bot scoring
            const formData = new FormData();
            formData.append('session_id', sessionId);
            
            fetch('enhanced_bot_scoring.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Bot scoring update:', data);
                
                // Update bot scores in the UI if there are any
                if (data.success && data.bots) {
                    data.bots.forEach(bot => {
                        // Find the player slot from the bot name
                        for (let i = 1; i <= 4; i++) {
                            const playerNameElement = document.querySelector('.player-scores .badge:nth-child(' + i + ')');
                            if (playerNameElement && playerNameElement.textContent.includes(bot.bot_name)) {
                                // Get the score element and update it
                                const scoreElement = document.getElementById('player' + i + 'Score');
                                if (scoreElement) {
                                    // Get current score
                                    let currentScore = parseInt(scoreElement.textContent || '0');
                                    
                                    // Update if needed
                                    if (bot.points > 0) {
                                        scoreElement.textContent = currentScore + bot.points;
                                    }
                                }
                            }
                        }
                    });
                }
                
                // Check if all bots are done
                if (data.success && data.all_bots_done && data.only_bots_left) {
                    // All bots have finished and there's only 1 human player, redirect to victory page
                    botScoreTimerEnabled = false;
                    window.location.href = 'multiplayer_victory.php?session_id=' + sessionId;
                } else if (botScoreTimerEnabled) {
                    // Schedule the next update
                    setTimeout(performBotScoring, delay);
                }
            })
            .catch(error => {
                console.error('Error updating bot scores:', error);
                // Try again after a delay
                if (botScoreTimerEnabled) {
                    setTimeout(performBotScoring, 10000);
                }
            });
        }
        
        // Start the bot scoring process
        performBotScoring();
    }
    
    // Start the bot scoring timer if we're in a multiplayer game
    startBotScoreTimer();
    debugLog('DOM content loaded - FINAL FIXED VERSION');
    
    // Handle sound toggle button
    const toggleSoundButton = document.getElementById('toggleSound');
    const soundIcon = document.getElementById('soundIcon');
    
    // Update sound icon based on stored preference
    if (toggleSoundButton && soundIcon) {
        // Set initial state based on stored preference
        if (soundMuted) {
            soundIcon.classList.remove('fa-volume-up');
            soundIcon.classList.add('fa-volume-mute');
        } else {
            soundIcon.classList.remove('fa-volume-mute');
            soundIcon.classList.add('fa-volume-up');
        }
        
        // Add click event to toggle sound
        toggleSoundButton.addEventListener('click', function() {
            // Toggle sound muted state
            soundMuted = !soundMuted;
            
            // Store preference in localStorage
            localStorage.setItem('soundMuted', soundMuted);
            
            // Update icon
            if (soundMuted) {
                soundIcon.classList.remove('fa-volume-up');
                soundIcon.classList.add('fa-volume-mute');
            } else {
                soundIcon.classList.remove('fa-volume-mute');
                soundIcon.classList.add('fa-volume-up');
            }
            
            debugLog('Sound muted:', soundMuted);
        });
    }
    
    // Initialize magnifier functionality
    initImageZoom();
    
    // Initialize game (this needs to run to set up the game properly)
    initGame();

    // Global variables for timer and game state
    var timeBar = document.getElementById('timeBar');
    var timeBonusValueElement = document.getElementById('timeBonusValue');
    // Not redeclaring timeInterval, imagesLoaded, etc. as they're already declared above
    var responseTime = 0;
    startTime = new Date(); // Reusing startTime declared above
    const timerInterval = null;
    var timeRemaining = 30; // Default time in seconds
    // Not redeclaring gameState as it's declared above
    var timerEnabled = document.getElementById('timerEnabled')?.value === '1';
    var gameMode = document.getElementById('game-mode')?.value || 'single';
    
    // Bonus game elements - don't redeclare image elements
    const bonusGameModal = document.getElementById('bonusGameModal');
    const bonusImagesContainer = document.getElementById('bonusImagesContainer');
    const bonusRealImageIndex = 0;

    // Don't redeclare selectedImage as it's already declared above
    const submitButton = document.getElementById('submitAnswer');
    const feedbackElement = document.getElementById('feedback');
    const scoreElement = document.getElementById('score');
    const livesElement = document.getElementById('lives');
    const turnElement = document.getElementById('turn');
    const nextButton = document.getElementById('nextButton');

    // Function to show full size image
    window.showFullSizeImage = function(img, index) {
        if (!img) return;
        
        // Use the local showFullSizeImage function
        showFullSizeImage(img, index);
    };

    // Function to show feedback messages
    function showFeedback(message, isCorrect) {
        if (!feedbackElement) return;
        
        // Check if this is a streak message
        const isStreakMilestone = message.includes('Streak x');
        
        // Create alert container if it doesn't exist
        let alertContainer = document.getElementById('feedback-alert');
        if (!alertContainer) {
            alertContainer = document.createElement('div');
            alertContainer.id = 'feedback-alert';
            alertContainer.className = 'alert';
            feedbackElement.appendChild(alertContainer);
        }
        
        // Set message content
        feedbackElement.innerHTML = '';
        
        // Create a more visible streak notification
        if (isStreakMilestone) {
            // Create a special visual streakBox for streak milestones
            const streakBox = document.createElement('div');
            streakBox.className = 'streak-milestone-box';
            streakBox.innerHTML = `
                <div class="streak-icon"><i class="fas fa-fire-alt"></i></div>
                <div class="streak-text">${message}</div>
            `;
            streakBox.style.cssText = 'background-color: #f8d7da; border: 2px solid #f5c6cb; border-radius: 10px; padding: 15px; text-align: center; font-weight: bold; font-size: 1.2em; color: #721c24; margin-bottom: 15px; display: flex; align-items: center; justify-content: center; flex-direction: column;';
            
            // Add the streak icon styling
            const streakIcon = streakBox.querySelector('.streak-icon');
            if (streakIcon) {
                streakIcon.style.cssText = 'font-size: 2em; color: #ff6b6b; margin-bottom: 8px;';
            }
            
            feedbackElement.appendChild(streakBox);
            
            // Confetti removed as requested
        } else {
            // Regular feedback message
            const messageElement = document.createElement('div');
            messageElement.innerHTML = message;
            messageElement.className = 'feedback-message';
            messageElement.classList.add(isCorrect ? 'feedback-correct' : 'feedback-wrong');
            feedbackElement.appendChild(messageElement);
            
            // Confetti removed as requested
        }
        
        feedbackElement.style.display = 'block';
        
        // Log the feedback message for debugging
        debugLog('FEEDBACK - Showing feedback:', message, isCorrect ? '(correct)' : '(wrong)');
    }
    
    /**
     * Shows a visually prominent notification for streak bonuses
     * @param {number} bonusPoints - The number of streak bonus points awarded
     * @param {number} streakCount - The current streak count
     */
    function showStreakBonusNotification(bonusPoints, streakCount) {
        // Check if there's already a streak notification, remove it if exists
        const existingNotification = document.getElementById('streak-bonus-notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        // Create streak bonus notification
        const notification = document.createElement('div');
        notification.id = 'streak-bonus-notification';
        notification.innerHTML = `
            <div class="streak-bonus-content">
                <span class="streak-count">🔥 ${streakCount} IN A ROW!</span>
                <span class="streak-bonus">+${bonusPoints} BONUS POINTS!</span>
            </div>
        `;
        
        // Add styles for the notification
        notification.style.position = 'fixed';
        notification.style.top = '20%';
        notification.style.left = '50%';
        notification.style.transform = 'translate(-50%, -50%)';
        notification.style.zIndex = '1000';
        notification.style.backgroundColor = 'rgba(255, 215, 0, 0.9)';
        notification.style.color = '#8B0000';
        notification.style.padding = '15px 25px';
        notification.style.borderRadius = '10px';
        notification.style.boxShadow = '0 0 20px rgba(255, 215, 0, 0.8)';
        notification.style.textAlign = 'center';
        notification.style.fontSize = '1.5rem';
        notification.style.fontWeight = 'bold';
        notification.style.animation = 'streak-pulse 0.5s infinite alternate';
        
        // Add animation style
        const style = document.createElement('style');
        style.textContent = `
            @keyframes streak-pulse {
                from { transform: translate(-50%, -50%) scale(1); }
                to { transform: translate(-50%, -50%) scale(1.1); }
            }
            .streak-bonus-content {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            .streak-count {
                font-size: 1.4rem;
            }
            .streak-bonus {
                font-size: 1.8rem;
                color: #8B0000;
            }
        `;
        document.head.appendChild(style);
        
        // Add to document and remove after animation
        document.body.appendChild(notification);
        
        // Play streak sound
        playSound('streak');
        
        // Remove after 2.5 seconds
        setTimeout(() => {
            notification.style.transition = 'opacity 0.5s ease-out';
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 500);
        }, 2500);
    }
    
    // Only one enhanced goToNextTurn function defined later
    function _unused_goToNextTurn() {
        // Reset the timer for the next turn
        startTime = new Date();

        // Disable the game action button while loading
        const gameActionButton = document.getElementById('gameActionButton');
        if (gameActionButton) {
            gameActionButton.disabled = true;
            gameActionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        }

        // Clear feedback message from previous turn
        if (feedbackElement) {
            feedbackElement.style.display = 'none';
            feedbackElement.textContent = '';
            feedbackElement.className = 'feedback-message';
        }

        // Reset selection state
        selectedImage = null;
        debugLog('Reset selectedImage to null');

        // Clear any selected class from image containers
        document.querySelectorAll('.image-container').forEach(container => {
            container.classList.remove('selected');
        });
        debugLog('Cleared selected classes from all containers');

        // Don't manually increment the turn counter - let the server handle it
        // We will receive the updated turn from the server response
        debugLog('Letting server handle turn increment');

        // Use AJAX to get the next turn
        const formData = new FormData();
        formData.append('action', 'get_next_turn');
        formData.append('increment_turn', 1); // Tell the server we're also incrementing the turn

        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('game_mode', gameMode.value);
        }

        // Add difficulty parameter if available
        const difficulty = document.getElementById('difficulty') || document.querySelector('[name="difficulty"]');
        if (difficulty && difficulty.value) {
            formData.append('difficulty', difficulty.value);
        }

        // Helper function to update turn counter and reload if needed
        const updateTurnCounterAndReload = () => {
            // If this is the last turn, redirect to game over
            if (totalTurns > 0 && currentTurn > totalTurns) {
                debugLog('This was the last turn, redirecting to game_over.php');
                
                // Clear any game in progress flag
                sessionStorage.removeItem("gameInProgress");
                
                // Play game over sound
                playSound('game_over');
                
                // Redirect to game over page with a slight delay to allow the sound to play
                setTimeout(() => {
                    window.location.href = 'game_over.php';
                }, 500);
                return; // Exit function early
            }
            
            // Otherwise, just reload the page to get the next turn
            debugLog('Going to next turn via page reload');
            window.location.reload();
        };

        // Show loading state
        const loadingOverlay = document.createElement('div');
        loadingOverlay.className = 'loading-overlay';
        loadingOverlay.innerHTML = '<div class="spinner"></div><p>Loading next turn...</p>';
        document.body.appendChild(loadingOverlay);

        // Add a timer to reload the page if the AJAX request takes too long
        const reloadTimeout = setTimeout(() => {
            debugLog('AJAX request timed out, reloading page');
            updateTurnCounterAndReload();
        }, 5000);

        fetch('game_actions.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                clearTimeout(reloadTimeout);
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                debugLog('AJAX response for next turn:', data);
                
                // Check if there are no more images available
                if (data.no_more_images) {
                    debugLog('No more images available, ending game');
                    
                    // Clear any game in progress flag
                    sessionStorage.removeItem("gameInProgress");
                    
                    // Play special "out of images" sound
                    playSound('out_of_images');
                    
                    // Display a message to the user
                    if (feedbackElement) {
                        feedbackElement.innerHTML = '<div class="alert alert-info">Congratulations! You have seen all available images!</div>';
                        feedbackElement.style.display = 'block';
                    }
                    
                    // Redirect to game over page with a slight delay to allow the sound to play
                    setTimeout(() => {
                        window.location.href = 'game_over.php?out_of_images=1';
                    }, 2000);
                    return; // Exit function early
                }
                
                if (data.next_turn) {
                    currentTurn = parseInt(data.next_turn);
                    debugLog('Updated current turn from server:', currentTurn);
                    
                    // Update turn counter on the page if it exists
                    const turnElement = document.getElementById('turn');
                    if (turnElement) {
                        turnElement.textContent = currentTurn;
                    }
                    
                    // Check if we need to reload
                    if (data.reload) {
                        updateTurnCounterAndReload();
                    }
                } else {
                    // Fallback if server doesn't send next_turn
                    debugLog('Server did not return next_turn, reloading page');
                    updateTurnCounterAndReload();
                }
            })
            .catch(error => {
                clearTimeout(reloadTimeout);
                console.error('Error during next turn fetch:', error);
                
                // If there's an error, just reload the page to get the next turn
                updateTurnCounterAndReload();
            });
    }
    
    // Function to preload images
    function preloadImages(urls, callback) {
        let loadedCount = 0;
        const totalImages = urls.length;
        const preloadedImgs = [];

        const timeoutId = setTimeout(() => {
            debugLog('Image preload timed out');
            callback(preloadedImgs);
        }, 5000);

        urls.forEach((url, index) => {
            const img = new Image();
            img.onload = () => {
                loadedCount++;
                preloadedImgs[index] = img;
                debugLog(`Image ${index + 1} loaded successfully`);
                if (loadedCount === totalImages) {
                    clearTimeout(timeoutId);
                    callback(preloadedImgs);
                }
            };

            img.onerror = () => {
                console.error('Failed to load image:', url);
                loadedCount++;
                if (loadedCount === totalImages) {
                    clearTimeout(timeoutId);
                    callback(preloadedImgs);
                }
            };

            // Add cache-busting parameter
            const cacheBuster = Date.now() + Math.random().toString(36).substring(2, 10);
            const separator = url.includes('?') ? '&' : '?';
            img.src = `${url}${separator}_cb=${cacheBuster}`;
        });
    }

    // Function to update image buttons
    function updateImageButtons(leftUrl, rightUrl) {
        const buttons = document.querySelectorAll('.view-in-new-tab-btn, .btn-secondary');
        buttons.forEach(button => {
            const isLeftButton = button.closest('.image-container[data-index="0"]') !== null;
            button.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                const url = isLeftButton ? leftUrl : rightUrl;
                if (button.classList.contains('view-in-new-tab-btn')) {
                    window.open(url, '_blank');
                } else {
                    // Handle enlarge functionality
                    const img = isLeftButton ? leftImg : rightImg;
                    if (img) {
                        img.classList.toggle('enlarged');
                    }
                }
            };
        });
    }
    
    // ENHANCED submitAnswer function 
    function submitAnswer() {
        // Check for selected image container
        const selectedContainer = document.querySelector('.image-container.selected');

        if (selectedContainer) {
            // Get the image selection data from the container
            const selectedIndex = parseInt(selectedContainer.getAttribute('data-index'));
            const leftIsReal = document.getElementById('leftIsReal').value === '1';

            // Determine if the selection is correct (user selected the real image)
            const isCorrect = (selectedIndex === 0 && leftIsReal) || (selectedIndex === 1 && !leftIsReal);
            selectedImage = isCorrect ? 'real' : 'ai';
            console.log('Selected image from container:', selectedImage, 'index:', selectedIndex, 'correct:', isCorrect);
        }

        // Still check if we have a selection
        if (!selectedImage) {
            console.error('No image selected');
            alert('Please select an image before submitting');
            return;
        }

        debugLog('Submit answer function called');

        // Disable game action button while processing
        const gameActionButton = document.getElementById('gameActionButton');
        if (gameActionButton) {
            gameActionButton.disabled = true;
            debugLog('Game action button disabled');
        }

        // Calculate response time in milliseconds
        const endTime = new Date();
        responseTime = endTime - startTime;
        debugLog('Response time calculated:', responseTime);

        // Stop the time interval
        if (timeInterval) {
            clearInterval(timeInterval);
            debugLog('Time interval cleared');
        }

        // Prepare data to send to server
        const formData = new FormData();
        formData.append('action', 'submit_answer');
        formData.append('selected', selectedImage);
        formData.append('response_time', responseTime);
        
        // ANTI-CHEAT: Include any previously received score hash for verification
        const existingScoreHash = sessionStorage.getItem('scoreHash');
        if (existingScoreHash) {
            formData.append('score_hash', existingScoreHash);
            debugLog('Included score verification hash in request');
        }

        // Get time bonus percentage from the time bar if it exists
        const timeBar = document.getElementById('timeBar');
        const timeBonusPercentage = timeBar ? parseInt(timeBar.style.width) : 0;
        formData.append('time_bonus_percentage', timeBonusPercentage);

        // If there's a time penalty in effect (page refreshed), we don't award a time bonus
        formData.append('time_penalty', timePenalty ? 1 : 0);

        // Check if this is a bonus round
        const isBonusRound = document.querySelector('body').hasAttribute('data-bonus-round');
        formData.append('is_bonus_round', isBonusRound ? 1 : 0);

        // Get the game mode (single, endless, multiplayer)
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('game_mode', gameMode.value);
        }

        // Submit answer to server
        fetch('game_actions.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    debugLog('Raw response from server:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                debugLog('Server response (parsed):', data);
                
                // ANTI-CHEAT: Store the score hash in session storage if provided
                if (data.score_hash) {
                    sessionStorage.setItem('scoreHash', data.score_hash);
                    sessionStorage.setItem('scoreVerification', data.score_verification || '');
                    debugLog('Saved score verification hash for next request');
                }
                
                // ANTI-CHEAT: Evaluate any obfuscated score calculations if provided
                if (data.obfuscated_calc) {
                    try {
                        // This evaluates the server-provided obfuscated calculation code
                        // which makes it harder for cheaters to predict how scores work
                        const obfuscationResult = Function('"use strict";return (' + data.obfuscated_calc + ')')();
                        debugLog('Processed obfuscated calculation: ' + obfuscationResult);
                    } catch (e) {
                        debugLog('Error in obfuscated calculation: ' + e);
                    }
                }
                
                // Update UI based on response
                if (data.success) {
                    // Update score display
                    if (data.correct) {
                        // Only increment score on correct answers
                        // Add 10 points for each correct answer (default points)
                        currentScore += 10;
                        
                        // Add any time or streak bonuses if provided by server
                        const timeBonus = data.time_bonus || 0;
                        const streakBonus = data.streak_bonus || 0;
                        currentScore += timeBonus + streakBonus;
                        
                        // Show streak bonus notification if applicable
                        if (streakBonus > 0) {
                            // Create and show a streak bonus notification
                            showStreakBonusNotification(streakBonus, data.current_streak);
                        }
                        
                        debugLog('SCORE DEBUG - Added 10 points for correct answer');
                        debugLog('SCORE DEBUG - Added bonuses: time=' + timeBonus + ', streak=' + streakBonus);
                    } else {
                        // Wrong answer - update lives display immediately
                        const livesElement = document.getElementById('lives');
                        if (livesElement && data.lives !== undefined) {
                            livesElement.textContent = data.lives;
                            debugLog('LIVES DEBUG - Updated lives to: ' + data.lives + ' after wrong answer');
                        }
                    }
                    
                    // Update score element on page
                    const scoreElement = document.getElementById('score');
                    if (scoreElement) {
                        scoreElement.textContent = currentScore;
                        debugLog('SCORE DEBUG - Updated score display to: ' + currentScore);
                    }
                    
                    // Update total score element as well
                    const totalScoreElement = document.getElementById('total-score');
                    if (totalScoreElement) {
                        totalScoreElement.textContent = currentScore;
                        debugLog('SCORE DEBUG - Updated total score display to: ' + currentScore);
                    }
                    
                    // Update streak display
                    if (data.streak !== undefined) {
                        currentStreak = data.streak;
                        const streakElement = document.getElementById('streak');
                        if (streakElement) {
                            streakElement.textContent = currentStreak;
                        }
                    }
                    
                    // Update game state
                    gameState = 'next';
                    
                    // Show correct/incorrect feedback
                    // Use selectedImage since we already know what was selected
                    let feedbackMessage = data.correct 
                        ? 'Correct! That was the ' + selectedImage + ' image.' 
                        : 'Incorrect. That was actually the AI image.';
                    
                    // Add image description for incorrect answers if available
                    // Show for ALL single player modes (Easy, Medium, Hard) and Endless mode
                    const gameMode = document.getElementById('game-mode')?.value || 'single';
                    const difficulty = document.getElementById('difficulty')?.value || 'easy';
                    
                    debugLog('Game Mode:', gameMode, 'Difficulty:', difficulty, 'Correct:', data.correct, 'Has Description:', !!data.image_description);
                    
                    // Determine which image is the real one (that we want to show the description for)
                    // The server sends image_description for the correct answer when user chooses wrong
                    const leftIsReal = document.getElementById('leftIsReal').value === '1';
                    const userSelectedCorrectly = data.correct;
                    const showRealImageDescription = !userSelectedCorrectly && data.image_description;
                    
                    // Get image info boxes
                    const leftInfoBox = document.getElementById('image-info-0');
                    const rightInfoBox = document.getElementById('image-info-1');
                    
                    // Only show description for real image when user gets the answer wrong
                    // and hide description for AI-generated images
                    if (leftInfoBox && rightInfoBox) {
                        // Reset both boxes
                        leftInfoBox.textContent = '';
                        rightInfoBox.textContent = '';
                        leftInfoBox.classList.remove('show');
                        rightInfoBox.classList.remove('show');
                        
                        // Only show description for the real image when user gets the answer wrong
                        if (showRealImageDescription) {
                            if (leftIsReal) {
                                leftInfoBox.textContent = data.image_description;
                                leftInfoBox.classList.add('show');
                            } else {
                                rightInfoBox.textContent = data.image_description;
                                rightInfoBox.classList.add('show');
                            }
                            debugLog('Showing description for the real image:', data.image_description);
                        }
                    }
                    
                    // For feedback message - show description for incorrect answers in ALL modes
                    // This includes all single player modes (easy, medium, hard), endless mode, and multiplayer
                    const shouldShowDescription = !data.correct && 
                                                (data.image_description || document.getElementById('real_image_description')?.value);
                    
                    // Get the correct description, either from the response or from the stored value
                    let useDescription = data.image_description;
                    if (!useDescription && shouldShowDescription) {
                        useDescription = document.getElementById('real_image_description')?.value;
                        debugLog('Using stored description:', useDescription);
                    }
                    
                    debugLog('Should Show Description:', shouldShowDescription, 'Game Mode:', gameMode, 'Description:', useDescription);
                    
                    // We no longer add the description to the feedback message
                    // The description is still shown in the image info boxes
                    
                    // Play sound effect for correct/incorrect answer
                    // Use special wrong sound for Single Player mode when answer is incorrect
                    if (!data.correct && gameMode === 'single') {
                        playSound('wrong_single');
                    } else {
                        playSound(data.correct ? 'correct' : 'wrong');
                    }
                    
                    // Add streak information to the feedback message if there's a streak
                    if (data.streak_bonus && data.streak_bonus > 0) {
                        feedbackMessage = 'Streak x' + data.current_streak + ' - ' + feedbackMessage + ' (Bonus points: ' + data.streak_bonus + ')';
                    }
                    
                    // Show feedback
                    showFeedback(feedbackMessage, data.correct);
                    
                    // No confetti as requested
                    
                    // Update button to go to next turn - but ONLY if game is not over
                    if (gameActionButton && !(data.is_final_turn || data.completed || data.lives <= 0)) {
                        gameActionButton.textContent = 'Next Image';
                        gameActionButton.disabled = false; 
                        gameActionButton.classList.remove('btn-primary');
                        gameActionButton.classList.add('btn-success');
                    } else if (gameActionButton && (data.is_final_turn || data.completed || data.lives <= 0)) {
                        // If game is over, hide or disable the button
                        gameActionButton.style.display = 'none'; // Hide the button completely
                        debugLog('Game over detected - hiding the Next Image button');
                    }
                    
                    // Check if game is completed (final turn reached OR no more lives)
                    if (data.is_final_turn || data.completed || data.lives <= 0) {
                        // Clear game in progress flag
                        sessionStorage.removeItem("gameInProgress");
                        
                        // Update game state one last time before leaving
                        gameState = 'complete';
                        
                        // Play game over sound
                        playSound('game_over');
                        
                        // Log the reason for game over
                        if (data.lives <= 0) {
                            debugLog('Game over: No more lives left');
                        } else if (data.is_final_turn) {
                            debugLog('Game over: Final turn reached');
                        } else if (data.completed) {
                            debugLog('Game over: Game marked as completed');
                        }
                        
                        // Schedule redirect to game over page after 2 seconds
                        setTimeout(() => {
                            window.location.href = 'game_over.php';
                        }, 2000);
                    }
                } else {
                    // Handle error
                    console.error('Error submitting answer:', data.message);
                    alert('Error: ' + data.message);
                    
                    // Re-enable the button
                    if (gameActionButton) {
                        gameActionButton.disabled = false;
                        gameActionButton.textContent = 'Submit Answer';
                        gameActionButton.classList.remove('btn-success');
                        gameActionButton.classList.add('btn-primary');
                        console.log('Game action button re-enabled after error');
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
                
                // Re-enable the button
                if (gameActionButton) {
                    gameActionButton.disabled = false;
                    gameActionButton.textContent = 'Submit Answer';
                    gameActionButton.classList.remove('btn-success');
                    gameActionButton.classList.add('btn-primary');
                    console.log('Game action button re-enabled after error');
                }
            });
    }

    // Main game initialization function - COMBINED from both previous versions
    function initGame() {
        // Check for refresh detection via sessionStorage
        const wasRefreshed = sessionStorage.getItem("gameInProgress") === "true";
        const gameMode = document.getElementById('game-mode')?.value || 'single';
        // Define isResumingGame variable - default to false if not otherwise set
        const isResumingGame = false;
        
        // Initialize the total score with the current score
        const scoreElement = document.getElementById('score');
        const totalScoreElement = document.getElementById('total-score');
        if (scoreElement && totalScoreElement) {
            // Update the global currentScore variable with the current score on the page
            currentScore = parseInt(scoreElement.textContent) || 0;
            totalScoreElement.textContent = currentScore;
            debugLog('SCORE DEBUG - Initialized total score from page:', currentScore);
        } else {
            debugLog('SCORE DEBUG - Score elements not found on page');
        }

        // Set gameInProgress flag for future refresh detection
        if (gameMode && gameMode !== '') {
            sessionStorage.setItem("gameInProgress", "true");
            debugLog('Game in progress flag set for refresh detection');
        }

        // Check if time penalty is in effect (page was refreshed and PHP set the flag)
        if (document.querySelector('body').hasAttribute('data-time-penalty') || isResumingGame || wasRefreshed) {
            // Always set time penalty for any refresh (as requested)
            timePenalty = true;
            console.log('Time penalty in effect - no time bonus will be awarded for this turn due to page refresh');

            // If detected via sessionStorage but not via PHP, we'll still apply the penalty
            if (wasRefreshed) {
                console.log('Refresh detected via sessionStorage');

                // Reset the time bonus when a refresh is detected (especially important for Endless Mode)
                if (timeBar) {
                    timeBar.style.width = '0%';
                }
                if (timeBonusValueElement) {
                    timeBonusValueElement.textContent = '+0';
                }

                // Clear any existing time interval
                if (timeInterval) {
                    clearInterval(timeInterval);
                }
            }

            // Remove the notification message about refresh as requested
            // Just silently disable the time bonus without showing any messages
        }

        // Fix image selection functionality
        // Get all image containers after DOM is loaded (they might not be available immediately)
        const allImageContainers = document.querySelectorAll('.image-container');
        debugLog('Found image containers:', allImageContainers.length);


        // Function to handle when an image is loaded
        function imageLoaded() {
            imagesLoaded++;
            if (imagesLoaded >= totalImages) {
                // All images loaded, remove loading overlays
                document.querySelectorAll('.loading-overlay').forEach(overlay => {
                    overlay.style.opacity = '0';
                    setTimeout(() => overlay.remove(), 300); // Remove after fade out
                });

                // Get the current game mode from the hidden field
                const gameModeField = document.getElementById('game-mode');
                const currentGameMode = gameModeField ? gameModeField.value : '';
                
                // Start timer immediately for Endless mode
                if (currentGameMode === 'endless') {
                    console.log('Endless mode detected in imageLoaded function - starting timer');
                    
                    // We need to call startTimeBonus directly for endless mode
                    // Small delay to ensure DOM elements are fully loaded
                    setTimeout(() => {
                        startTimeBonus();
                    }, 100);
                }
            }
        }

        // Get the image containers
        const leftImgContainer = document.querySelector('.image-container[data-index="0"]');
        const rightImgContainer = document.querySelector('.image-container[data-index="1"]');
        // Use the global variables instead of redeclaring
        leftImg = leftImgContainer ? leftImgContainer.querySelector('img') : null;
        rightImg = rightImgContainer ? rightImgContainer.querySelector('img') : null;

        if (leftImg && rightImg) {
            // Make images selectable
            [leftImgContainer, rightImgContainer].forEach(container => {
                if (container) {
                    container.addEventListener('click', function(e) {
                        // Don't handle clicks on buttons within the container
                        if (e.target.classList.contains('btn') || e.target.closest('.btn')) {
                            return;
                        }
                        
                        // Remove selected class from all containers
                        document.querySelectorAll('.image-container').forEach(c => {
                            c.classList.remove('selected');
                        });
                        
                        // Add selected class to clicked container
                        this.classList.add('selected');
                        
                        // Get the selected index
                        const selectedIndex = parseInt(this.dataset.index);
                        const leftIsReal = document.getElementById('leftIsReal').value === '1';
                        
                        // Determine if selection is correct (user selected the real image)
                        selectedImage = (selectedIndex === 0 && leftIsReal) || (selectedIndex === 1 && !leftIsReal) ? 'real' : 'ai';
                        debugLog('Selected:', selectedImage, 'index:', selectedIndex);
                        
                        // Enable the submit button
                        const submitButton = document.getElementById('gameActionButton');
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                        
                        // Also enable multiplayer submit button if it exists
                        const multiplayerSubmitBtn = document.getElementById('submitMultiplayerAnswer');
                        if (multiplayerSubmitBtn) {
                            multiplayerSubmitBtn.disabled = false;
                            console.log('Enabled multiplayer submit button');
                        }
                    });
                }
            });

            // Add loading state
            leftImg.classList.add('loading');
            rightImg.classList.add('loading');

            // Get current image URLs
            const leftImageUrl = leftImg.getAttribute('src');
            const rightImageUrl = rightImg.getAttribute('src');

            // Preload images
            preloadImages([leftImageUrl, rightImageUrl], (preloadedImgs) => {
                if (preloadedImgs.length === 2) {
                    leftImg.src = preloadedImgs[0].src;
                    rightImg.src = preloadedImgs[1].src;
                }

                // Remove loading states
                leftImg.classList.remove('loading');
                rightImg.classList.remove('loading');
                leftImgContainer.classList.remove('loading');
                rightImgContainer.classList.remove('loading');

                // Update image buttons
                updateImageButtons(leftImageUrl, rightImageUrl);
                imageLoaded();
            });
        }

        // Add dynamic button event listener based on game state
        const gameActionButton = document.getElementById('gameActionButton');
        if (gameActionButton) {
            gameActionButton.addEventListener('click', function() {
                if (gameState === 'select') {
                    // In select state, the button submits an answer
                    submitAnswer();
                } else if (gameState === 'next') {
                    // In next state, the button goes to the next turn
                    goToNextTurn();
                }
            });
        }

        // Get difficulty and check if time penalty is in effect
        const difficulty = document.body.hasAttribute('data-difficulty') ?
            document.body.getAttribute('data-difficulty') : 'easy';

        // Check if time penalty is in effect
        const hasTimePenalty = document.querySelector('body').hasAttribute('data-time-penalty');

        // Get if timer is necessary for this mode (only multiplayer and endless)
        const isTimerCriticalMode = (gameMode === 'multiplayer' || gameMode === 'endless');

        // Add debugging for timer functionality (only for hard/endless modes)
        if (isTimerCriticalMode) {
            console.log('Timer Debug - Game mode:', gameMode, 'Difficulty:', difficulty, 'Time penalty:', hasTimePenalty);
            console.log('Timer Debug - Time elements exist:', !!timeBar, !!timeBonusValueElement);
        }

        // Get timer visibility setting
        const timerVisible = document.getElementById('timerVisible')?.value === '1';
        debugLog('Timer status - Enabled:', timerEnabled, 'Visible:', timerVisible);

        // Initialize time bonus animation only for multiplayer or endless mode
        // AND only if no time penalty is in effect
        if (timeBar && timeBonusValueElement && isTimerCriticalMode && timerEnabled) {
            console.log('Initializing time bonus for ' + (gameMode === 'multiplayer' ? 'multiplayer mode' : 'endless mode'));

            // Make sure time bonus elements are visible if they should be
            const timeBarContainer = timeBar.closest('.time-bonus-indicator');
            if (timeBarContainer) {
                timeBarContainer.style.display = timerVisible ? 'block' : 'none';
                console.log('Timer Debug - Time bar container visibility set to:', timerVisible ? 'visible' : 'hidden');
            } else {
                console.warn('Timer Debug - Could not find time bar container');
            }

            // Clear any existing interval
            if (timeInterval) {
                clearInterval(timeInterval);
                console.log('Timer Debug - Cleared existing time interval');
            }

            // Set initial values
            timeBar.style.width = '100%';
            timeBonusValueElement.textContent = '+20';

            // Start the time bonus countdown only if the timer is enabled
            if (timerEnabled) {
                startTimeBonus();
                console.log('Timer Debug - Started time bonus countdown');
            }
        } else {
            // Only look for time bar container in multiplayer/endless modes to avoid warnings
            if (isTimerCriticalMode) {
                // Hide the time bonus if not enabled or visible
                const timeBarContainer = timeBar?.closest('.time-bonus-indicator');
                if (timeBarContainer) {
                    timeBarContainer.style.display = 'none';
                    console.log('Timer Debug - Time bar container hidden because:',
                        !timeBar ? 'timeBar missing' :
                            !timeBonusValueElement ? 'timeBonusValue missing' :
                                !timerEnabled ? 'timer disabled' :
                                    !timerVisible ? 'timer not visible' : 'unknown reason');
                } else {
                    console.warn('Timer Debug - Could not find time bar container to hide');
                }

                if (hasTimePenalty) {
                    console.log('Time penalty in effect - time bonus animation disabled');
                }
            } else {
                // Other modes don't need timer, so we don't look for the element
                // and don't generate warnings for these modes
                debugLog('Timer Debug - Mode detected that does not use timer');
            }
        }
    }

    // ENHANCED submitAnswer function from game.new.js - NOT USED
    function _duplicate_submitAnswer() {
        // Check for selected image container
        const selectedContainer = document.querySelector('.image-container.selected');

        if (selectedContainer) {
            // Get the image selection data from the container
            const selectedIndex = parseInt(selectedContainer.getAttribute('data-index'));
            const leftIsReal = document.getElementById('leftIsReal').value === '1';

            // Determine if the selection is correct (user selected the real image)
            const isCorrect = (selectedIndex === 0 && leftIsReal) || (selectedIndex === 1 && !leftIsReal);
            selectedImage = isCorrect ? 'real' : 'ai';
            console.log('Selected image from container:', selectedImage, 'index:', selectedIndex, 'correct:', isCorrect);
        }

        // Still check if we have a selection
        if (!selectedImage) {
            console.error('No image selected');
            alert('Please select an image before submitting');
            return;
        }

        console.log('Submit answer function called');

        // Disable game action button while processing
        const gameActionButton = document.getElementById('gameActionButton'); // Moved declaration here
        if (gameActionButton) {
            gameActionButton.disabled = true;
            console.log('Game action button disabled');
        }

        // Calculate response time in milliseconds
        const endTime = new Date();
        responseTime = endTime - startTime;
        console.log('Response time calculated:', responseTime);

        // Stop the time interval
        if (timeInterval) {
            clearInterval(timeInterval);
            console.log('Time interval cleared');
        }

        // Check if time penalty is in effect (from page refresh)
        const hasTimePenalty = document.querySelector('body').hasAttribute('data-time-penalty');
        if (hasTimePenalty) {
            console.log('Time penalty in effect - no time bonus will be awarded');
            timePenalty = true;
        }

        // Get current session ID from session element if available
        const sessionElement = document.getElementById('gameSessionId');
        const sessionId = sessionElement ? sessionElement.value : '';
        console.log('Session ID from element:', sessionId);

        // Additional session ID fallback from cookies if missing from element
        if (!sessionId) {
            const cookies = document.cookie.split(';');
            for (let i = 0; i < cookies.length; i++) {
                const cookie = cookies[i].trim();
                if (cookie.indexOf('game_session_id=') === 0) {
                    const cookieSessionId = cookie.substring('game_session_id='.length, cookie.length);
                    console.log('Found session ID in cookie:', cookieSessionId);
                    sessionId = cookieSessionId;
                    break;
                }
            }
        }

        // Get the form data
        const formData = new FormData();
        formData.append('selected', selectedImage);
        formData.append('action', 'submit_answer');
        formData.append('response_time', responseTime);

        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('mode', gameMode.value);
            console.log('Sending game mode:', gameMode.value);
        }

        // Always include session_id if available
        if (sessionId) {
            formData.append('session_id', sessionId);
            console.log('Added session_id to form data:', sessionId);
        } else {
            console.warn('No session ID available');
        }

        // Include CSRF token if available
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if (csrfToken) {
            formData.append('csrf_token', csrfToken);
            console.log('Added CSRF token to form data');
        }

        // Log data being sent to debug
        console.log('Sending data to server:', {
            selected: selectedImage,
            action: 'submit_answer',
            response_time: responseTime,
            session_id: sessionId
        });

        // Send AJAX request with improved error handling
        console.log('Sending fetch request to game_actions.php');
        fetch('game_actions.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin' // Include cookies
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    console.log('Raw response from server (submit answer):', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error (submit answer):', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });

                // Original code removed:
                // console.log('Received response:', response.status, response.statusText);
                // Log all response headers for debugging
                const headers = {};
                response.headers.forEach((value, name) => {
                    headers[name] = value;
                });
                console.log('Response headers:', headers);

                if (!response.ok) {
                    console.error('Server response was not OK:', response.status, response.statusText);
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }

                // Get the raw text first for debugging
                return response.text().then(text => {
                    console.log('Raw response text:', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                console.log('Data received from server:', data);

                // Handle response
                if (!data || !data.success) {
                    const errorMessage = data && data.message ? data.message : 'Unknown error occurred';
                    console.error('Error in response:', errorMessage);
                    showFeedback(errorMessage, false);
                    if (gameActionButton) {
                        gameActionButton.disabled = false;
                        gameState = 'select';
                    }
                    return;
                }

                // Check if we have a streak bonus
                let feedbackMessage = data.correct ? 'Correct!' : 'Wrong!';
                
                // Play sound effect for correct/incorrect answer
                // Use special wrong sound for Single Player mode when answer is incorrect
                if (!data.correct && gameMode === 'single') {
                    playSound('wrong_single');
                } else {
                    playSound(data.correct ? 'correct' : 'wrong');
                }

                // Add streak bonus information if available
                if (data.correct && data.streak_bonus > 0) {
                    feedbackMessage = `Correct! +${data.streak_bonus} streak bonus!`;

                    // Show streak milestone
                    const streakMilestone = Math.floor(data.current_streak / 5) * 5;
                    if (streakMilestone > 0) {
                        // Add streak notification
                        const streakElement = document.createElement('div');
                        streakElement.className = 'streak-notification';
                        streakElement.innerHTML = `<span class="streak-count">${streakMilestone}</span> correct in a row!`;
                        document.body.appendChild(streakElement);

                        // Animate and remove after delay
                        setTimeout(() => {
                            streakElement.classList.add('show');
                            setTimeout(() => {
                                streakElement.classList.remove('show');
                                setTimeout(() => {
                                    document.body.removeChild(streakElement);
                                }, 500);
                            }, 2000);
                        }, 10);
                    }
                }

                // Show feedback
                showFeedback(feedbackMessage, data.correct);

                // Update game state with enhanced score debugging
                if (scoreElement) {
                    // Store the current score temporarily
                    // Update the global currentScore variable
                    currentScore = parseInt(scoreElement.textContent) || 0;
                    console.log('SCORE DEBUG - Current displayed score before update:', currentScore);
                    console.log('SCORE DEBUG - Server response score:', data.score);
                    console.log('SCORE DEBUG - Full server response:', data);

                    // Check if we have streak information
                    if (data.correct && data.streak_bonus > 0) {
                        console.log('SCORE DEBUG - Streak bonus detected:', data.streak_bonus);
                        console.log('SCORE DEBUG - Current streak:', data.current_streak);
                    }

                    // Update the score from the server's response regardless of right/wrong
                    // The server only increments score on correct answers anyway
                    scoreElement.textContent = data.score;
                    
                    // Update the total score display
                    const totalScoreElement = document.getElementById('total-score');
                    if (totalScoreElement) {
                        totalScoreElement.textContent = data.score;
                    }
                    
                    // Log score change details
                    if (currentScore !== data.score) {
                        console.log('SCORE DEBUG - Score changed from', currentScore, 'to', data.score);
                        console.log('SCORE DEBUG - Increase amount:', (data.score - currentScore));
                        
                        // Check if score increase matches what we'd expect
                        if (data.correct) {
                            const basePoints = 10;
                            const timeBonus = data.time_bonus || 0;
                            const streakBonus = data.streak_bonus || 0;
                            const expectedIncrease = basePoints + timeBonus + streakBonus;
                            const actualIncrease = data.score - currentScore;
                            
                            console.log('SCORE DEBUG - Expected points:', expectedIncrease, 
                                       '(Base: 10, Time: ' + timeBonus + ', Streak: ' + streakBonus + ')');
                            console.log('SCORE DEBUG - Actual increase:', actualIncrease);
                            
                            if (expectedIncrease !== actualIncrease) {
                                console.warn('SCORE DEBUG - Mismatch between expected and actual score increase!');
                            }
                        }
                    } else {
                        console.log('SCORE DEBUG - Score remained the same:', data.score);
                        if (data.correct) {
                            console.warn('SCORE DEBUG - Score did not increase despite correct answer!');
                        }
                    }
                }

                // Always update lives from server response
                if (livesElement && data.lives !== undefined) {
                    livesElement.textContent = data.lives;
                }

                // Show next button or redirect
                if (data.completed) {
                    setTimeout(() => {
                        window.location.href = data.correct ? 'victory.php' : 'game_over.php';
                    }, 1500);
                } else {
                    // Update gameActionButton to be a Next button
                    if (gameActionButton) {
                        gameActionButton.disabled = false;
                        gameActionButton.textContent = 'Next Image';
                        gameActionButton.classList.remove('btn-primary');
                        gameActionButton.classList.add('btn-success');
                        gameState = 'next';
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                // Create a detailed error message for debugging
                const errorDetail = error.toString();
                showFeedback(`Error: ${errorDetail}. Please try again.`, false);

                // Log additional information
                console.error('Error stack:', error.stack);
                console.error('Browser info:', navigator.userAgent);
                console.error('Session info:', {
                    'session_element_exists': !!document.getElementById('gameSessionId'),
                    'cookies_enabled': navigator.cookieEnabled,
                    'document.cookie length': document.cookie.length
                });

                // Re-enable the game action button
                if (gameActionButton) {
                    gameActionButton.disabled = false;
                    gameState = 'select';
                    gameActionButton.textContent = 'Submit Answer';
                    gameActionButton.classList.remove('btn-success');
                    gameActionButton.classList.add('btn-primary');
                    console.log('Game action button re-enabled after error');
                }
            });
    }

    // Using enhanced showFeedback from earlier in the file - not redeclaring

    // Main goToNextTurn function that will be called by the game
    function goToNextTurn() {
        centralDebugLog('goToNextTurn function called');
        
        // Get game mode for logging/debugging
        const gameMode = document.getElementById('game-mode')?.value || 'unknown';
        centralDebugLog('Game mode in goToNextTurn:', gameMode);
        
        // Reset the timer for the next turn
        startTime = new Date();

        // Disable the game action button while loading
        const gameActionButton = document.getElementById('gameActionButton'); // Moved declaration here
        if (gameActionButton) {
            gameActionButton.disabled = true;
            gameActionButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
        }

        // Clear feedback message from previous turn
        if (feedbackElement) {
            feedbackElement.style.display = 'none';
            feedbackElement.textContent = '';
            feedbackElement.className = 'feedback-message';
        }

        // Reset selection state
        selectedImage = null;
        centralDebugLog('Reset selectedImage to null');

        // Clear any selected class from image containers
        document.querySelectorAll('.image-container').forEach(container => {
            container.classList.remove('selected');
        });
        centralDebugLog('Cleared selected classes from all containers');
        
        // Hide image info boxes
        document.querySelectorAll('.image-info-box').forEach(infoBox => {
            infoBox.textContent = '';
            infoBox.classList.remove('show');
        });
        centralDebugLog('Cleared and hidden image info boxes');

        // Don't manually increment the turn counter - let the server handle it
        // We will receive the updated turn from the server response
        centralDebugLog('Letting server handle turn increment');

        // Use AJAX to get the next turn
        const formData = new FormData();
        formData.append('action', 'get_next_turn');
        formData.append('increment_turn', 1); // Tell the server we're also incrementing the turn

        // Add game mode parameter if available
        // We already have gameMode variable declared above, so we'll use it here
        // For AJAX request, we need the full element for its properties
        const gameModeElement = document.getElementById('game-mode');
        if (gameModeElement && gameModeElement.value) {
            formData.append('mode', gameModeElement.value);
            centralDebugLog('Sending game mode for AJAX request:', gameModeElement.value);
        }

        fetch('game_actions.php', {
            method: 'POST',
            body: formData,
            credentials: 'same-origin' // Include cookies
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    centralDebugLog('Raw response from server (next turn):', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error (next turn):', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    centralDebugLog('Successfully loaded next turn:', data);

                    // Update turn counter with value from server
                    if (turnElement && data.turn) {
                        // Format turn counter based on total turns
                        const totalTurnsDisplay = data.totalTurns ? '/' + data.totalTurns : '';
                        turnElement.textContent = `${data.turn}${totalTurnsDisplay}`;
                        console.log('Updated turn counter to:', data.turn, 'of', data.totalTurns || 'endless');

                        // Check if game is over (reached max turns)
                        if (data.totalTurns && data.turn > data.totalTurns) {
                            console.log('Game completed - reached max turns');
                            window.location.href = 'victory.php';
                            return;
                        }
                    }
                    
                    // We'll maintain the score completely client-side since the server
                    // seems to be consistently returning 0 for score
                    if (scoreElement) {
                        // IMPORTANT: DO NOT reset the score based on server response
                        // We'll keep the accumulated score in the currentScore variable
                        
                        debugLog('SCORE DEBUG - Keeping accumulated score:', currentScore);
                        
                        // Always update the display with our accumulated score
                        scoreElement.textContent = currentScore;
                        
                        // Also update total score if it exists
                        const totalScoreElement = document.getElementById('total-score');
                        if (totalScoreElement) {
                            totalScoreElement.textContent = currentScore;
                            debugLog('SCORE DEBUG - Updated total score element to:', currentScore);
                        }
                    }
                    
                    // Update the streak counter from server response
                    if (typeof data.current_streak !== 'undefined') {
                        currentStreak = data.current_streak;
                        debugLog('STREAK DEBUG - Updated currentStreak from server to:', currentStreak);
                        
                        // Update streak display if it exists
                        const streakElement = document.getElementById('streak');
                        if (streakElement) {
                            streakElement.textContent = currentStreak;
                        }
                    }

                    // Update the images (server already adds cache-busting)
                    const leftImgContainer = document.querySelector('.image-container[data-index="0"]');
                    const rightImgContainer = document.querySelector('.image-container[data-index="1"]');
                    const leftImg = leftImgContainer.querySelector('img');
                    const rightImg = rightImgContainer.querySelector('img');

                    // Add loading class to containers for better visual feedback
                    leftImgContainer.classList.add('loading');
                    rightImgContainer.classList.add('loading');

                    // Create cache-busted URLs
                    const cacheBuster = new Date().getTime();
                    const leftImageUrl = data.leftImage + (data.leftImage.includes('?') ? '&' : '?') + '_cb=' + cacheBuster;
                    const rightImageUrl = data.rightImage + (data.rightImage.includes('?') ? '&' : '?') + '_cb=' + cacheBuster;

                    // Preload both images before setting them
                    preloadImages([leftImageUrl, rightImageUrl], (preloadedImgs) => {
                        console.log('Both images preloaded successfully, now updating src attributes');

                        // Use the preloaded images if available, otherwise fall back to URLs
                        if (preloadedImgs && preloadedImgs.length >= 2 && preloadedImgs[0] && preloadedImgs[1]) {
                            console.log('Using preloaded Image objects directly');
                            // Using the src from preloaded images to ensure they're fully loaded
                            leftImg.src = preloadedImgs[0].src;
                            rightImg.src = preloadedImgs[1].src;
                        } else {
                            console.log('Falling back to original URLs');
                            // Set the image sources with the cache-busted URLs as fallback
                            leftImg.src = leftImageUrl;
                            rightImg.src = rightImageUrl;
                        }

                        // Update the "Full Size" and "New Tab" buttons for both images
                        updateImageButtons(leftImageUrl, rightImageUrl);

                        // Set up onload handlers for the actual image elements
                        leftImg.onload = function() {
                            leftImg.classList.remove('loading');
                            leftImgContainer.classList.remove('loading');
                            console.log('Left image loaded in DOM');
                        };

                        rightImg.onload = function() {
                            rightImg.classList.remove('loading');
                            rightImgContainer.classList.remove('loading');
                            console.log('Right image loaded in DOM');
                        };

                        // Fallback in case onload doesn't trigger
                        setTimeout(() => {
                            leftImg.classList.remove('loading');
                            rightImg.classList.remove('loading');
                            leftImgContainer.classList.remove('loading');
                            rightImgContainer.classList.remove('loading');
                            console.log('Removed loading classes (timeout fallback)');
                        }, 2000);
                    });

                    // Update the hidden value
                    const leftIsRealInput = document.getElementById('leftIsReal');
                    if (leftIsRealInput) leftIsRealInput.value = data.leftIsReal ? '1' : '0';
                    
                    // Store real image description for later use when user answers incorrectly
                    if (data.real_image_description) {
                        // Store the description in a hidden input or data attribute for later use
                        let descriptionInput = document.getElementById('real_image_description');
                        if (!descriptionInput) {
                            descriptionInput = document.createElement('input');
                            descriptionInput.type = 'hidden';
                            descriptionInput.id = 'real_image_description';
                            document.body.appendChild(descriptionInput);
                        }
                        descriptionInput.value = data.real_image_description;
                        console.log('Stored real image description:', data.real_image_description);
                    }

                    // Check if this is a resumed turn
                    const isResumedTurn = data.isResumedTurn || false;

                    // Reset UI state
                    document.querySelectorAll('.image-container').forEach(c => c.classList.remove('selected'));

                    // Reset gameActionButton to Submit Answer state
                    if (gameActionButton) {
                        gameActionButton.disabled = false;
                        gameActionButton.textContent = 'Submit Answer';
                        gameActionButton.classList.remove('btn-success');
                        gameActionButton.classList.add('btn-primary');
                        gameState = 'select';
                    }

                    // Handle feedback display based on whether it's a resumed turn
                    if (isResumedTurn) {
                        console.log('This is a resumed turn - displaying previous images');

                        // Display a resume notification to the user
                        if (feedbackElement) {
                            feedbackElement.innerHTML = '<div class="alert alert-info">Resuming previous game state.</div>';
                            feedbackElement.style.display = 'block';
                        }

                        // Make sure the button is enabled for resumed turns
                        if (gameActionButton) {
                            gameActionButton.disabled = false;
                            console.log('Re-enabling game action button for resumed turn');
                        }
                    } else {
                        // Only hide feedback if it's not a resumed turn
                        if (feedbackElement) feedbackElement.style.display = 'none';
                    }

                    // Reset selected image
                    selectedImage = null;
                    console.log('Reset selectedImage to null again in data success handler');

                    // Reset time bonus animation if applicable
                    // Don't check if timeBar and timeBonusValue exist - startTimeBonus will handle this
                    if (timeInterval) {
                        console.log('Clearing existing time interval before starting new one');
                        clearInterval(timeInterval);
                    }
                    
                    // Start the timer for the next turn
                    console.log('Starting time bonus for next turn');
                    startTimeBonus();
                } else {
                    console.error('Error getting next turn:', data.message);
                    alert('Error loading next images. Please try again.');

                    // Re-enable the game action button as Next button
                    if (gameActionButton) {
                        gameActionButton.disabled = false;
                        gameActionButton.textContent = 'Next Image';
                        gameActionButton.classList.remove('btn-primary');
                        gameActionButton.classList.add('btn-success');
                        gameState = 'next';
                    }
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Network error. Please try again.');

                // Re-enable the game action button as Next button
                if (gameActionButton) {
                    gameActionButton.disabled = false;
                    gameActionButton.textContent = 'Next Image';
                    gameActionButton.classList.remove('btn-primary');
                    gameActionButton.classList.add('btn-success');
                    gameState = 'next';
                }
            });
    }

    /**
     * Updates the "Full Size" and "New Tab" buttons to use the current image URLs
     * @param {string} leftImageUrl - The URL of the left image
     * @param {string} rightImageUrl - The URL of the right image
     */
    function updateImageButtons(leftImageUrl, rightImageUrl) {
        // Update the "New Tab" buttons
        const leftNewTabBtn = document.querySelector('.col-md-6:nth-child(1) .view-in-new-tab-btn');
        const rightNewTabBtn = document.querySelector('.col-md-6:nth-child(2) .view-in-new-tab-btn');

        if (leftNewTabBtn) {
            leftNewTabBtn.onclick = function() {
                window.open(leftImageUrl, '_blank');
            };
        }

        if (rightNewTabBtn) {
            rightNewTabBtn.onclick = function() {
                window.open(rightImageUrl, '_blank');
            };
        }

        // Update the "Full Size" buttons by removing and recreating them
        const leftContainer = document.querySelector('.image-container[data-index="0"]');
        const rightContainer = document.querySelector('.image-container[data-index="1"]');
        const leftImg = leftContainer ? leftContainer.querySelector('img') : null;
        const rightImg = rightContainer ? rightContainer.querySelector('img') : null;

        // Update or recreate the full-size buttons
        if (leftContainer && leftImg) {
            let leftFullSizeBtn = document.querySelector('.col-md-6:nth-child(1) .full-size-btn');
            if (leftFullSizeBtn) {
                leftFullSizeBtn.onclick = function(e) {
                    e.preventDefault();
                    showFullSizeImage(leftImg, 0);
                };
            }
        }

        if (rightContainer && rightImg) {
            let rightFullSizeBtn = document.querySelector('.col-md-6:nth-child(2) .full-size-btn');
            if (rightFullSizeBtn) {
                rightFullSizeBtn.onclick = function(e) {
                    e.preventDefault();
                    showFullSizeImage(rightImg, 1);
                };
            }
        }

        console.log('Updated "Full Size" and "New Tab" buttons with current image URLs');
    }

    // Helper function for starting time bonus countdown
    function startTimeBonus() {
        // Get timer control elements
        const timerEnabled = document.getElementById('timerEnabled');
        const timerVisible = document.getElementById('timerVisible');
        const timeBarContainer = document.querySelector('.time-bonus-indicator');

        // Log once at the beginning of the function with more details
        debugLog('startTimeBonus function called', {
            timerEnabled: timerEnabled?.value,
            timerVisible: timerVisible?.value,
            timeBarContainerExists: !!timeBarContainer,
            gameMode: document.getElementById('game-mode')?.value || 'unknown'
        });

        // Try to locate the time bar elements - log whether they are found
        const timeBarElement = document.getElementById('timeBar');
        const timeBonusValueElementFound = document.getElementById('timeBonusValue');
        
        debugLog('Timer elements found:', {
            timeBar: !!timeBarElement,
            timeBonusValue: !!timeBonusValueElementFound,
            timeBarContainer: !!timeBarContainer
        });

        // Make sure we have the timer bar and value elements
        if (!timeBar) {
            timeBar = timeBarElement;
            debugLog('Setting timeBar global variable:', !!timeBar);
        }

        if (!timeBonusValueElement) {
            timeBonusValueElement = timeBonusValueElementFound;
            debugLog('Setting timeBonusValueElement global variable:', !!timeBonusValueElement);
        }
        
        // Get the current game mode
        const gameModeField = document.getElementById('game-mode');
        const gameMode = gameModeField ? gameModeField.value : '';
        
        // Force timer enabled for endless mode
        let isTimerEnabled = false;
        if (gameMode === 'endless') {
            isTimerEnabled = true;
            debugLog('Forcing timer enabled for Endless mode');
        } else {
            // Check if timer should be enabled (from PHP hidden fields)
            isTimerEnabled = timerEnabled && timerEnabled.value === '1';
        }
        
        // If timer is not enabled, hide it and return
        if (!isTimerEnabled) {
            if (timeBarContainer) {
                timeBarContainer.style.display = 'none';
            }
            debugLog('Timer disabled, not starting');
            return;
        }

        // For logged-in users, check if timer should be visible (from PHP hidden fields)
        const shouldShowTimer = !timerVisible || timerVisible.value === '1';

        // Only show timer in multiplayer or endless mode
        const isTimerCriticalMode = (gameMode === 'multiplayer' || gameMode === 'endless');

        // Set visibility of timer container based on preferences
        if (timeBarContainer) {
            // Show timer only if it's a critical mode AND user hasn't hidden it
            // Always show timer in endless mode regardless of user preferences
            if ((shouldShowTimer && isTimerCriticalMode) || gameMode === 'endless') {
                timeBarContainer.style.display = 'block';
            } else {
                timeBarContainer.style.display = 'none';
            }
        }

        // Clear any existing interval first
        if (timeInterval) {
            clearInterval(timeInterval);
        }

        // Reset timer bar visuals
        if (timeBar) {
            timeBar.style.width = '100%';
            timeBar.className = 'progress-bar bg-success';
        }

        // Reset time bonus value display
        if (timeBonusValueElement) {
            timeBonusValueElement.textContent = '+20';
        }

        // Timer variables
        let elapsedMs = 0;
        const totalTime = 30000; // 30 seconds total
        const updateInterval = 100; // Update every 100ms

        // Start the timer interval
        timeInterval = setInterval(() => {
            elapsedMs += updateInterval;

            if (elapsedMs >= totalTime) {
                // Max time reached, stop the interval
                clearInterval(timeInterval);

                // Update visual elements if they exist
                if (timeBar) {
                    timeBar.style.width = '0%';
                }
                if (timeBonusValueElement) {
                    timeBonusValueElement.textContent = '+0';
                }

                // For endless mode, restart the timer after it expires
                const currentGameMode = document.getElementById('game-mode')?.value;
                if (currentGameMode === 'endless') {
                    // Reset elapsed time and restart timer
                    elapsedMs = 0;
                    
                    // Reset time bar appearance
                    if (timeBar) {
                        timeBar.style.width = '100%';
                        timeBar.className = 'progress-bar bg-success';
                    }
                    
                    // Reset bonus value text
                    if (timeBonusValueElement) {
                        timeBonusValueElement.textContent = '+20';
                    }
                }
            } else {
                // Calculate remaining percentage and time bonus
                const remainingPercent = 100 - (elapsedMs / totalTime * 100);

                // Update progress bar width
                if (timeBar) {
                    timeBar.style.width = remainingPercent + '%';

                    // Change color as time decreases
                    if (remainingPercent > 60) {
                        timeBar.className = 'progress-bar bg-success';
                    } else if (remainingPercent > 30) {
                        timeBar.className = 'progress-bar bg-warning';
                    } else {
                        timeBar.className = 'progress-bar bg-danger';
                    }
                }

                // Calculate time bonus (20 points at start, 0 at 30 seconds)
                let timeBonus = 0;
                if (elapsedMs < 1000) {
                    timeBonus = 20; // First second gets full bonus
                } else {
                    // Then decrease by 1 point roughly every 1.5 seconds
                    timeBonus = Math.max(0, 20 - Math.floor((elapsedMs - 1000) / 1500));
                }

                // Update time bonus text
                if (timeBonusValueElement) {
                    timeBonusValueElement.textContent = '+' + timeBonus;
                }
            }
        }, updateInterval);

        debugLog('Time bonus interval started successfully');
    }

    // Bonus mini-game functions
    function showBonusGameIntro() {
        const bonusIntroModal = document.getElementById('bonusIntroModal');
        if (!bonusIntroModal) return;

        // Check game mode first - don't show bonus games in endless or multiplayer modes
        const gameMode = document.getElementById('game-mode');
        if (gameMode && (gameMode.value === 'endless' || gameMode.value === 'multiplayer')) {
            debugLog('Skipping bonus game in ' + gameMode.value + ' mode');
            return;
        }

        // Get current lives and max lives for this difficulty level
        const currentLives = parseInt(livesElement ? livesElement.textContent : "0");
        let maxLives = 3; // Default max lives

        // Determine max lives based on difficulty
        const difficulty = document.body.hasAttribute('data-difficulty') ?
            document.body.getAttribute('data-difficulty') : 'easy';

        if (difficulty === 'easy') maxLives = 5;
        else if (difficulty === 'medium') maxLives = 3;
        else if (difficulty === 'hard') maxLives = 1;

        // Check if player has max lives
        const hasMaxLives = currentLives >= maxLives;

        // Update reward text based on lives
        const bonusRewardText = document.getElementById('bonusRewardText');
        if (bonusRewardText) {
            if (hasMaxLives) {
                bonusRewardText.textContent = 'You already have maximum lives! If you guess correctly, you\'ll earn 50 bonus points instead! If you guess wrong, you\'ll lose half your score.';
            } else {
                bonusRewardText.textContent = 'If you guess correctly, you\'ll earn an extra life! If you guess wrong, you\'ll lose half your score.';
            }
        }

        // Add event listener for "Play Bonus Game" button
        const playBonusBtn = document.getElementById('playBonusGameBtn');
        if (playBonusBtn) {
            // Remove any existing event listeners
            const newPlayBonusBtn = playBonusBtn.cloneNode(true);
            playBonusBtn.parentNode.replaceChild(newPlayBonusBtn, playBonusBtn);

            // Add new event listener
            newPlayBonusBtn.addEventListener('click', () => {
                // Hide intro modal
                const bsIntroModal = bootstrap.Modal.getInstance(bonusIntroModal);
                if (bsIntroModal) bsIntroModal.hide();

                // Show the actual bonus game
                setTimeout(() => {
                    showBonusGame(hasMaxLives);
                }, 500);
            });
        }

        // Show the modal
        const bsModal = new bootstrap.Modal(bonusIntroModal);
        bsModal.show();
    }

    function showBonusGame(hasMaxLives = false) {
        if (!bonusGameModal || !bonusImagesContainer) return;

        // Skip bonus game if score is 1 or less
        // Update global currentScore instead of creating a local variable
        currentScore = parseInt(scoreElement ? scoreElement.textContent : "0");
        if (currentScore <= 1) {
            debugLog('Skipping bonus game because score is 1 or less');
            return;
        }

        // Check if player has only one life remaining
        const currentLives = parseInt(livesElement ? livesElement.textContent : "0");
        const isLastLife = currentLives === 1;

        // Get the game difficulty
        const difficulty = document.body.hasAttribute('data-difficulty') ?
            document.body.getAttribute('data-difficulty') : 'easy';

        // Clear previous content
        bonusImagesContainer.innerHTML = '';

        // Get bonus game images
        const bonusFormData = new FormData();
        bonusFormData.append('action', 'get_bonus_images');

        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            bonusFormData.append('mode', gameMode.value);
            debugLog('Sending game mode for bonus images:', gameMode.value);
        }

        fetch('game_actions.php', {
            method: 'POST',
            body: bonusFormData
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    debugLog('Raw response from server (bonus):', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error (bonus):', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                if (!data.success) {
                    console.error('Error loading bonus game:', data.message);
                    return;
                }

                // Add warning message if on last life
                if (isLastLife) {
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'alert alert-danger mb-3';
                    warningDiv.textContent = 'Warning: You have only one life remaining! If you choose incorrectly, your score will be cut in half!';
                    bonusImagesContainer.appendChild(warningDiv);
                }

                // Determine which game type we're showing
                const gameType = data.gameType || 'original';

                // ORIGINAL GAME MODE (4 images - 1 real, 3 AI)
                if (gameType === 'original') {
                    // Store real image index
                    bonusRealImageIndex = data.realImageIndex;

                    // Add images to container
                    data.images.forEach((image, index) => {
                        const col = document.createElement('div');
                        col.className = 'col-md-6 mb-3';

                        const imgContainer = document.createElement('div');
                        imgContainer.className = 'bonus-image-container';
                        imgContainer.dataset.index = index;

                        // Add zoom controls to bonus images
                        const zoomControls = document.createElement('div');
                        zoomControls.className = 'zoom-controls';

                        const zoomIcon = document.createElement('div');
                        zoomIcon.className = 'zoom-icon';
                        zoomIcon.title = 'Zoom Image';
                        zoomIcon.innerHTML = '<i class="fas fa-search-plus"></i>';

                        const magnifierIcon = document.createElement('div');
                        magnifierIcon.className = 'magnifier-icon';
                        magnifierIcon.title = 'Magnify Image';
                        magnifierIcon.innerHTML = '<i class="fas fa-search"></i>';
                        
                        // Get the game mode and difficulty
                        const gameMode = document.getElementById('game-mode')?.value || 'single';
                        const difficulty = document.getElementById('difficulty')?.value || 'easy';
                        
                        // Hide magnifier in Hard mode and Endless mode
                        if ((gameMode === 'single' && difficulty === 'hard') || gameMode === 'endless') {
                            magnifierIcon.style.display = 'none';
                        }

                        zoomControls.appendChild(zoomIcon);
                        zoomControls.appendChild(magnifierIcon);

                        // Add magnifier container
                        const magnifierContainer = document.createElement('div');
                        magnifierContainer.className = 'img-magnifier-container';

                        const magnifierGlass = document.createElement('div');
                        magnifierGlass.className = 'magnifier-glass';

                        const img = document.createElement('img');
                        img.src = image;
                        img.className = 'img-fluid game-image cursor-pointer';
                        img.alt = `Bonus Image ${index + 1}`;

                        magnifierContainer.appendChild(magnifierGlass);
                        magnifierContainer.appendChild(img);

                        imgContainer.appendChild(zoomControls);
                        imgContainer.appendChild(magnifierContainer);
                        col.appendChild(imgContainer);

                        // Add View Full Image button under the image container
                        const viewBtn = document.createElement('button');
                        viewBtn.className = 'view-in-new-tab-btn mt-2';
                        viewBtn.innerHTML = '<i class="fas fa-external-link-alt"></i> View Full Image';
                        viewBtn.onclick = function(e) {
                            e.stopPropagation(); // Prevent triggering image selection
                            window.open(image, '_blank');
                        };
                        col.appendChild(viewBtn);

                        bonusImagesContainer.appendChild(col);

                        // Add click handler with zoom control handling
                        imgContainer.addEventListener('click', (e) => {
                            // Check if the click target is zoom controls or their children
                            let isZoomControl = false;
                            let el = e.target;

                            // Check if clicked element or any parent is a zoom control
                            while (el && el !== imgContainer) {
                                if (el.classList && (
                                    el.classList.contains('zoom-controls') ||
                                    el.classList.contains('zoom-icon') ||
                                    el.classList.contains('magnifier-icon') ||
                                    el.classList.contains('magnifier-glass')
                                )) {
                                    isZoomControl = true;
                                    break;
                                }
                                el = el.parentElement;
                            }

                            // Don't process selection if a zoom control was clicked
                            if (isZoomControl) {
                                return;
                            }

                            // Check if any magnifier is active on this or other images
                            const activeMagnifier = document.querySelector('.magnifier-icon.active');
                            if (activeMagnifier) {
                                // Check if magnifier is on this image
                                if (imgContainer.contains(activeMagnifier)) {
                                    console.log('In magnifier mode, not selecting image');
                                    return;
                                } else {
                                    // If magnifier is active on another image, deactivate it
                                    const glass = document.querySelector('.magnifier-glass[style*="visibility: visible"]');
                                    const img = glass ? glass.closest('.image-container').querySelector('.game-image') : null;
                                    const icon = activeMagnifier;

                                    if (glass && img && icon) {
                                        // Call deactivateMagnifier function from image-zoom.js
                                        deactivateMagnifier(img, glass, icon);
                                        // Change back icon
                                        const iconElement = icon.querySelector('i');
                                        if (iconElement) {
                                            iconElement.className = 'fas fa-search-plus';
                                        }
                                        // Remove active class
                                        icon.classList.remove('active');
                                    }
                                }
                            }

                            handleBonusImageClick(index, 'original');
                        });
                    });

                    // Set up modal title and instructions
                    const bonusTitle = document.getElementById('bonusGameTitle');
                    if (bonusTitle) {
                        bonusTitle.textContent = 'Bonus Mini-Game: Find the Real Photo';
                    }

                    const bonusInstructions = document.getElementById('bonusGameInstructions');
                    if (bonusInstructions) {
                        bonusInstructions.textContent = 'Select the REAL photo to earn a bonus. Choose wisely!';
                    }
                }
                // SINGLE IMAGE GAME MODE (1 image - either real or AI)
                else if (gameType === 'single') {
                    // Store if the image is real
                    bonusSingleIsReal = data.isReal;

                    // Create container for the single image
                    const singleContainer = document.createElement('div');
                    singleContainer.className = 'bonus-single-container text-center mb-4';

                    // Add the image
                    const imgContainer = document.createElement('div');
                    imgContainer.className = 'bonus-image-container mx-auto';
                    imgContainer.style.maxWidth = '500px';

                    // Add zoom controls to bonus image
                    const zoomControls = document.createElement('div');
                    zoomControls.className = 'zoom-controls';

                    const zoomIcon = document.createElement('div');
                    zoomIcon.className = 'zoom-icon';
                    zoomIcon.title = 'Zoom Image';
                    zoomIcon.innerHTML = '<i class="fas fa-search-plus"></i>';

                    const magnifierIcon = document.createElement('div');
                    magnifierIcon.className = 'magnifier-icon';
                    magnifierIcon.title = 'Magnify Image';
                    magnifierIcon.innerHTML = '<i class="fas fa-search"></i>';
                    
                    // Get the game mode and difficulty
                    const gameMode = document.getElementById('game-mode')?.value || 'single';
                    const difficulty = document.getElementById('difficulty')?.value || 'easy';
                    
                    // Hide magnifier in Hard mode and Endless mode
                    if ((gameMode === 'single' && difficulty === 'hard') || gameMode === 'endless') {
                        magnifierIcon.style.display = 'none';
                    }

                    zoomControls.appendChild(zoomIcon);
                    zoomControls.appendChild(magnifierIcon);

                    // Add magnifier container
                    const magnifierContainer = document.createElement('div');
                    magnifierContainer.className = 'img-magnifier-container';

                    const magnifierGlass = document.createElement('div');
                    magnifierGlass.className = 'magnifier-glass';

                    const img = document.createElement('img');
                    img.src = data.image;
                    img.className = 'img-fluid game-image';
                    img.alt = 'Bonus Image';

                    magnifierContainer.appendChild(magnifierGlass);
                    magnifierContainer.appendChild(img);

                    imgContainer.appendChild(zoomControls);
                    imgContainer.appendChild(magnifierContainer);
                    singleContainer.appendChild(imgContainer);

                    // Add View Full Image button under the image container
                    const viewBtn = document.createElement('button');
                    viewBtn.className = 'view-in-new-tab-btn mt-3 mx-auto';
                    viewBtn.style.display = 'block';
                    viewBtn.innerHTML = '<i class="fas fa-external-link-alt"></i> View Full Image';
                    viewBtn.onclick = function() {
                        window.open(data.image, '_blank');
                    };
                    singleContainer.appendChild(viewBtn);

                    bonusImagesContainer.appendChild(singleContainer);

                    // Add the buttons for real or AI selection
                    const buttonsContainer = document.createElement('div');
                    buttonsContainer.className = 'bonus-buttons-container mt-4 d-flex justify-content-center gap-3';

                    const realButton = document.createElement('button');
                    realButton.type = 'button';
                    realButton.className = 'btn btn-lg btn-primary';
                    realButton.textContent = 'This is a REAL Photo';
                    realButton.addEventListener('click', () => handleSingleBonusChoice(true, 'single'));

                    const aiButton = document.createElement('button');
                    aiButton.type = 'button';
                    aiButton.className = 'btn btn-lg btn-danger';
                    aiButton.textContent = 'This is an AI Photo';
                    aiButton.addEventListener('click', () => handleSingleBonusChoice(false, 'single'));

                    buttonsContainer.appendChild(realButton);
                    buttonsContainer.appendChild(aiButton);
                    bonusImagesContainer.appendChild(buttonsContainer);

                    // Set up modal title and instructions
                    const bonusTitle = document.getElementById('bonusGameTitle');
                    if (bonusTitle) {
                        bonusTitle.textContent = 'Bonus Challenge: Real or AI?';
                    }

                    const bonusInstructions = document.getElementById('bonusGameInstructions');
                    if (bonusInstructions) {
                        bonusInstructions.textContent = 'Is this a real photo or an AI-generated image? Choose wisely!';
                    }
                }

                // Show the modal
                const bsModal = new bootstrap.Modal(bonusGameModal);
                bsModal.show();

                // Add zoom functionality to the bonus game images
                initImageZoom('.bonus-image-container .game-image');
            })
            .catch(error => {
                console.error('Error in bonus game:', error);
            });
    }

    function handleBonusImageClick(selectedIndex, gameType = 'original') {
        if (bonusRealImageIndex === null) return;

        // Hide bonus game modal
        const bsModal = bootstrap.Modal.getInstance(bonusGameModal);
        if (bsModal) bsModal.hide();

        // Check if correct
        const isCorrect = selectedIndex === bonusRealImageIndex;

        // Update game state
        const formData = new FormData();
        formData.append('action', 'handle_bonus_result');
        formData.append('correct', isCorrect ? '1' : '0');
        formData.append('game_type', gameType);

        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('mode', gameMode.value);
            console.log('Sending game mode for bonus game:', gameMode.value);
        }

        fetch('game_actions.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    console.log('Raw response from server (bonus result):', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error (bonus result):', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                if (!data.success) {
                    console.error('Error handling bonus result:', data.message);
                    return;
                }

                // Show result modal
                if (bonusResultMessage) {
                    const currentLives = parseInt(livesElement ? livesElement.textContent : "0");
                    const wasLastLife = currentLives === 1 && !isCorrect;

                    // Check if player had maximum lives (and got points instead)
                    let resultMessage = '';

                    if (isCorrect) {
                        // Check if score increased by 50 (instead of lives increasing) by comparing with previous score
                        const previousScore = parseInt(scoreElement ? scoreElement.getAttribute('data-previous-score') || scoreElement.textContent : "0");
                        currentScore = parseInt(data.score);

                        if (currentScore - previousScore === 50) {
                            resultMessage = 'Great job! You already had max lives, so you earned 50 bonus points instead!';
                        } else {
                            resultMessage = 'Great job! You earned an extra life!';
                        }
                    } else {
                        resultMessage = wasLastLife ?
                            'Sorry, that wasn\'t the real image. Your score has been cut in half!' :
                            'Sorry, that wasn\'t the real image. Your score has been reduced.';
                    }

                    bonusResultMessage.textContent = resultMessage;
                    bonusResultMessage.className = 'alert';
                    bonusResultMessage.classList.add(isCorrect ? 'alert-success' : 'alert-danger');
                }

                if (bonusResultScore) {
                    bonusResultScore.textContent = data.score;
                }

                if (bonusResultLives) {
                    bonusResultLives.textContent = data.lives;
                }

                // Update main game display
                if (scoreElement) {
                    // Store previous score for comparison
                    const currentScore = scoreElement.textContent;
                    scoreElement.setAttribute('data-previous-score', currentScore);
                    scoreElement.textContent = data.score;
                }
                if (livesElement) livesElement.textContent = data.lives;

                // Confetti temporarily disabled for troubleshooting
                // if (isCorrect) {
                //     // Confetti effect would go here
                // }

                // Show result modal
                const resultModal = new bootstrap.Modal(bonusResultModal);
                resultModal.show();
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Handler for the single-image bonus game
    function handleSingleBonusChoice(selectedIsReal, gameType = 'single') {
        if (bonusSingleIsReal === null) return;

        // Hide bonus game modal
        const bsModal = bootstrap.Modal.getInstance(bonusGameModal);
        if (bsModal) bsModal.hide();

        // Check if correct choice was made
        const isCorrect = selectedIsReal === bonusSingleIsReal;

        // Update game state
        const formData = new FormData();
        formData.append('action', 'handle_bonus_result');
        formData.append('correct', isCorrect ? '1' : '0');
        formData.append('game_type', gameType);

        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('mode', gameMode.value);
            console.log('Sending game mode for single bonus game:', gameMode.value);
        }

        fetch('game_actions.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    console.log('Raw response from server (single bonus):', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error (single bonus):', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                if (!data.success) {
                    console.error('Error handling single bonus result:', data.message);
                    return;
                }

                // Show result modal
                if (bonusResultMessage) {
                    const currentLives = parseInt(livesElement ? livesElement.textContent : "0");
                    const wasLastLife = currentLives === 1 && !isCorrect;

                    // Check if player had maximum lives (and got points instead)
                    let resultMessage = '';

                    if (isCorrect) {
                        // Check if score increased by 50 (instead of lives increasing) by comparing with previous score
                        const previousScore = parseInt(scoreElement ? scoreElement.getAttribute('data-previous-score') || scoreElement.textContent : "0");
                        const currentScore = parseInt(data.score);

                        if (currentScore - previousScore === 50) {
                            resultMessage = 'Great job! You already had max lives, so you earned 50 bonus points instead!';
                        } else {
                            resultMessage = 'Great job! You earned an extra life!';
                        }

                        // Add what the actual answer was
                        resultMessage += ' The image was ' + (bonusSingleIsReal ? 'REAL.' : 'AI-GENERATED.');
                    } else {
                        resultMessage = wasLastLife ?
                            'Sorry, that wasn\'t correct. Your score has been cut in half!' :
                            'Sorry, that wasn\'t correct. Your score has been reduced.';

                        // Add what the actual answer was
                        resultMessage += ' The image was ' + (bonusSingleIsReal ? 'REAL.' : 'AI-GENERATED.');
                    }

                    bonusResultMessage.textContent = resultMessage;
                    bonusResultMessage.className = 'alert';
                    bonusResultMessage.classList.add(isCorrect ? 'alert-success' : 'alert-danger');
                }

                if (bonusResultScore) {
                    bonusResultScore.textContent = data.score;
                }

                if (bonusResultLives) {
                    bonusResultLives.textContent = data.lives;
                }

                // Update main game display
                if (scoreElement) {
                    // Store previous score for comparison
                    const currentScore = scoreElement.textContent;
                    scoreElement.setAttribute('data-previous-score', currentScore);
                    scoreElement.textContent = data.score;
                }
                if (livesElement) livesElement.textContent = data.lives;

                // Show result modal
                const resultModal = new bootstrap.Modal(bonusResultModal);
                resultModal.show();
            })
            .catch(error => {
                console.error('Error in single bonus game:', error);
            });
    }

    // Multiplayer game functions
    const multiplayerSubmitButton = document.getElementById('submitMultiplayerAnswer');

    if (multiplayerSubmitButton) {
        multiplayerSubmitButton.addEventListener('click', submitMultiplayerAnswer);
    }

    function submitMultiplayerAnswer() {
        if (!selectedImage) return;

        // Disable submit button while processing
        multiplayerSubmitButton.disabled = true;
        
        // Pause bot score timer during submission
        if (typeof botScoreTimerEnabled !== 'undefined') {
            botScoreTimerEnabled = false;
        }

        // Get the form data
        const formData = new FormData();
        formData.append('selected', selectedImage);
        formData.append('action', 'submit_multiplayer_answer');

        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('mode', gameMode.value);
            console.log('Sending game mode for multiplayer answer:', gameMode.value);
        }

        // Send AJAX request with improved error handling
        console.log('Submitting multiplayer answer:', selectedImage);
        
        fetch('game_actions.php', {
            method: 'POST',
            body: formData
        })
            .then(response => {
                // Get the raw text first for debugging
                return response.text().then(text => {
                    console.log('Raw response from server (multiplayer):', text);
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error (multiplayer):', e, 'Response was:', text);
                        throw new Error('Failed to parse server response');
                    }
                });
            })
            .then(data => {
                console.log('Server response (multiplayer parsed):', data);
                
                // Handle response
                if (!data || !data.success) {
                    // Add more detailed error message based on the response
                    const errorMsg = data && data.message ? data.message : 'Unknown error occurred';
                    console.error('Multiplayer answer submission failed:', errorMsg);
                    showFeedback(errorMsg, false);
                    
                    // Re-enable the button so the user can try again
                    multiplayerSubmitButton.disabled = false;
                    
                    return;
                }

                // Check if we have a streak bonus
                let feedbackMessage = data.correct ? 'Correct!' : 'Wrong!';
                
                // Play sound effect for correct/incorrect answer
                // Use special wrong sound for Single Player mode when answer is incorrect
                if (!data.correct && gameMode === 'single') {
                    playSound('wrong_single');
                } else {
                    playSound(data.correct ? 'correct' : 'wrong');
                }
                
                // Update image info boxes for wrong answers in the background
                if (!data.correct && data.image_description) {
                    console.log('Adding image description to info boxes for wrong answer');
                    
                    // Update image info boxes if present (similar to single-player mode)
                    const leftInfoBox = document.getElementById('image-info-0');
                    const rightInfoBox = document.getElementById('image-info-1');
                    
                    if (leftInfoBox && rightInfoBox) {
                        // Reset both boxes
                        leftInfoBox.textContent = '';
                        rightInfoBox.textContent = '';
                        leftInfoBox.classList.remove('show');
                        rightInfoBox.classList.remove('show');
                        
                        // Only show description for the real image
                        const leftIsReal = document.getElementById('leftIsReal')?.value === '1';
                        
                        if (leftIsReal) {
                            leftInfoBox.textContent = data.image_description;
                            leftInfoBox.classList.add('show');
                        } else {
                            rightInfoBox.textContent = data.image_description;
                            rightInfoBox.classList.add('show');
                        }
                    }
                }

                // Add streak bonus information if available
                if (data.correct && data.streak_bonus > 0) {
                    feedbackMessage = `Correct! +${data.streak_bonus} streak bonus!`;

                    // Show streak milestone
                    const streakMilestone = Math.floor(data.current_streak / 5) * 5;
                    if (streakMilestone > 0) {
                        // Add streak notification
                        const streakElement = document.createElement('div');
                        streakElement.className = 'streak-notification';
                        streakElement.innerHTML = `<span class="streak-count">${streakMilestone}</span> correct in a row!`;
                        document.body.appendChild(streakElement);

                        // Animate and remove after delay
                        setTimeout(() => {
                            streakElement.classList.add('show');
                            setTimeout(() => {
                                streakElement.classList.remove('show');
                                setTimeout(() => {
                                    document.body.removeChild(streakElement);
                                }, 500);
                            }, 2000);
                        }, 10);
                    }
                }

                // Show feedback
                showFeedback(feedbackMessage, data.correct);

                // Update game state
                const multiplayerTurnElement = document.getElementById('multiplayerTurn');

                // Update player scores
                if (data.scores) {
                    // Update the individual player scores
                    for (let i = 1; i <= 4; i++) {
                        const scoreElement = document.getElementById(`player${i}Score`);
                        const scoreDisplayElement = document.getElementById(`player${i}ScoreDisplay`);

                        if (scoreElement && data.scores[`player${i}`] !== undefined) {
                            scoreElement.textContent = data.scores[`player${i}`];
                        }

                        if (scoreDisplayElement && data.scores[`player${i}`] !== undefined) {
                            scoreDisplayElement.textContent = data.scores[`player${i}`];
                        }
                    }
                }

                // Update turn counter
                if (multiplayerTurnElement) {
                    multiplayerTurnElement.textContent = `${data.turn}/${data.totalTurns}`;
                }

                // Wait for other player or redirect
                if (data.completed) {
                    setTimeout(() => {
                        window.location.href = 'leaderboard.php';
                    }, 1500);
                } else {
                    showWaitingMessage();

                    // Poll for game state updates
                    pollGameState();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFeedback('An error occurred. Please try again.', false);
                multiplayerSubmitButton.disabled = false;
            });
    }

    function showWaitingMessage() {
        if (!feedbackElement) return;

        feedbackElement.textContent = 'Waiting for other players...';
        feedbackElement.className= 'feedback-message';
        feedbackElement.style.backgroundColor = 'var(--bs-info)';
        feedbackElement.style.color = 'white';
        feedbackElement.style.display = 'block';
    }

function pollGameState() {
    // Instead of simply reloading, make an AJAX call to get_next_turn
    // to ensure turn is properly incremented before reload
    setTimeout(() => {
        debugLog('Polling for next turn...');
        
        // Create form data with game mode
        const formData = new FormData();
        formData.append('action', 'get_next_turn');
        
        // Add game mode parameter if available
        const gameMode = document.getElementById('game-mode');
        if (gameMode && gameMode.value) {
            formData.append('mode', gameMode.value);
            debugLog('Sending game mode for next turn:', gameMode.value);
        }
        
        // Fetch the next turn to properly increment counter in the DB
        // Route through game_actions.php instead of directly to handle_get_next_turn.php
        fetch('game_actions.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            debugLog('Next turn response received:', data);
            
            // Update the turn counter if the next_turn value is provided
            if (data && data.next_turn) {
                currentTurn = parseInt(data.next_turn);
                debugLog('Updated current turn from server:', currentTurn);
                
                // Update turn counter on the page if it exists
                const turnElement = document.getElementById('turn');
                if (turnElement) {
                    turnElement.textContent = currentTurn;
                }
            }
            
            // Reload the page
            window.location.reload();
        })
        .catch(error => {
            console.error('Error getting next turn:', error);
            // Still reload even if there was an error
            window.location.reload();
        });
    }, 3000);
}

    // Copy to clipboard function
    window.copyToClipboard = function(text, message) {
        navigator.clipboard.writeText(text).then(() => {
            // Create a toast notification instead of an alert
            const toast = document.createElement('div');
            toast.className= 'copy-toast';
            toast.textContent = message || 'Copied to clipboard!';
            document.body.appendChild(toast);

            // Show the toast
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);

            // Hide and remove the toast after 2 seconds
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 2000);
        });
    };

    // Legacy function for backward compatibility
    window.copySessionId = function(sessionId) {
        copyToClipboard(sessionId, 'Session ID copied to clipboard!');
    };

    // Prevent right-clicking on images
    document.addEventListener('contextmenu', function(e) {
        if (e.target.tagName === 'IMG') {
            e.preventDefault();
            return false;
        }
    });

    /**
     * Sets up protection against page refreshes during gameplay
     */
    function setupRefreshPrevention() {
        // Get game mode from the hidden field
        const gameMode = document.getElementById('game-mode')?.value || '';
        const isGamePage = document.querySelector('.game-container') !== null;

        // Only apply these protections if we're on a game page
        if (!isGamePage) {
            debugLog('Not a game page, skipping refresh prevention setup');

            // Clear the game in progress flag when on non-game pages
            sessionStorage.removeItem("gameInProgress");
            return;
        }

        // 1. Add beforeunload event to warn before refresh/navigation
        window.addEventListener('beforeunload', function(e) {
            // Only warn if we're in an active game (not on victory/game over screens)
            const isActiveTurn = document.querySelector('.image-containers-wrapper') !== null;
            if (isActiveTurn && gameMode) {
                // Modern browsers standardize the message; we can't customize it
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        // 2. Block specific keyboard shortcuts to prevent refreshes
        document.addEventListener('keydown', function(e) {
            // Block F5 and Ctrl+R, but only during active gameplay
            const isActiveTurn = document.querySelector('.image-containers-wrapper') !== null;
            if (isActiveTurn && gameMode && (e.key === 'F5' || (e.ctrlKey && e.key ==='r'))) {
                console.log('Refresh shortcut blocked during active gameplay');
                e.preventDefault();

                // Show a notification to the user about why refresh is blocked
                if (feedbackElement) {
                    feedbackElement.innerHTML = '<div class="alert alert-warning">Please use the game controls instead of refreshing the page.</div>';
                    feedbackElement.style.display = 'block';

                    // Hide the notification after 3 seconds
                    setTimeout(() => {
                        feedbackElement.style.display = 'none';
                    }, 3000);
                }

                return false;
            }
        });

        // 3. Handle game completion - clear sessionStorage to prevent penalties after game over
        const isGameOver = document.querySelector('.game-over-container') !== null ||
                              document.querySelector('.victory-container') !== null;

        if (isGameOver) {
            console.log('Game is complete, clearing gameInProgress flag');
            sessionStorage.removeItem("gameInProgress");
        }
    }

    //Added zoom functionality (incomplete - requires server-side image data and CSS styling)
    function initImageZoom(selector = '.game-image') {
        const images = document.querySelectorAll(selector);
        images.forEach(img => {
            img.addEventListener('mouseover', (e) => {
                //Simulate zoom effect - replace with actual zoom logic
                e.target.style.cursor = 'zoom-in';
                //Add a visual zoom circle (CSS styling required)
            });
            img.addEventListener('mouseout', (e) => {
                e.target.style.cursor = 'default';
            });
        });
    }

    // Placeholder for deactivateMagnifier (requires implementation in image-zoom.js)
    function deactivateMagnifier(img, glass, icon) {
        //Implement actual magnifier deactivation logic here
        console.log('deactivateMagnifier called');
        glass.style.visibility = 'hidden';
    }


    function showFullSizeImage(img, index) {
        console.log('showFullSizeImage called for index:', index);
        
        if (!img) return;
        
        const url = img.src;
        console.log('Opening image in modal:', url);
        
        // Check if the modal already exists
        let imageModal = document.getElementById('fullSizeImageModal');
        
        // Create modal if it doesn't exist
        if (!imageModal) {
            const modalHtml = `
                <div class="modal fade" id="fullSizeImageModal" tabindex="-1" aria-labelledby="fullSizeImageModalLabel">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="fullSizeImageModalLabel">Full Size Image</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body text-center">
                                <img id="modalImage" src="" alt="Full size image" style="max-width: 100%; max-height: 80vh;">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" id="closeFullSizeImageBtn" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Append the modal to the body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            imageModal = document.getElementById('fullSizeImageModal');
            
            // Add manual close event listeners
            const closeBtn = imageModal.querySelector('.btn-close');
            const closeFooterBtn = imageModal.querySelector('#closeFullSizeImageBtn');
            
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    const modalInstance = bootstrap.Modal.getInstance(imageModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });
            }
            
            if (closeFooterBtn) {
                closeFooterBtn.addEventListener('click', function() {
                    const modalInstance = bootstrap.Modal.getInstance(imageModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                });
            }
            
            // Also close when clicking outside the modal
            imageModal.addEventListener('click', function(event) {
                if (event.target === imageModal) {
                    const modalInstance = bootstrap.Modal.getInstance(imageModal);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }
            });
        }
        
        // Set the image source
        const modalImage = document.getElementById('modalImage');
        modalImage.src = url;
        
        // Initialize and show the modal
        const modal = new bootstrap.Modal(imageModal);
        modal.show();
    }

    // Initialize timer if enabled (but not for Endless mode - that will start after images load)
    if (timerEnabled && gameMode !== 'endless') {
        setTimeout(() => {
            startTimer();
        }, 5000);
    }

    function startTimer() {
        console.log('startTimer function called - using startTimeBonus instead for better consistency');
        
        // Forward to startTimeBonus which is the modern implementation
        startTimeBonus();
        
        // Old implementation below is no longer used
        /*
        // Clear any existing interval
        if (timerInterval) {
            clearInterval(timerInterval);
        }

        // Reset time remaining to max
        timeRemaining = 20;
        */
        
        // Update UI
        if (timeBar) {
            timeBar.style.width = '100%';
        }
        if (timeBonusValueElement) {
            timeBonusValueElement.textContent = '+20';
        }

        // Start the timer
        timerInterval = setInterval(() => {
            timeRemaining--;
            if (timeRemaining <= 0) {
                clearInterval(timerInterval);
                if (timeBar) {
                    timeBar.style.width = '0%';
                }
                if (timeBonusValueElement) {
                    timeBonusValueElement.textContent = '+0';
                }
            } else {
                if (timeBar) {
                    timeBar.style.width = (timeRemaining * 5) + '%';
                }
                if (timeBonusValueElement) {
                    timeBonusValueElement.textContent = '+' + timeRemaining;
                }
            }
        }, 1000);
    }

    setupRefreshPrevention();
});