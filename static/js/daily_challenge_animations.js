/**
 * Daily Challenge Animation Functions
 * 
 * These functions handle animations for the daily challenge game mode
 * and provide AJAX functionality for submitting answers
 */

// Loading screen animation for daily challenge
function initDailyChallengeLoadingScreen() {
    console.log("Daily challenge loading screen initialized");
    
    // Create a temporary loading animation if needed
    const loadingAnimation = document.createElement('div');
    loadingAnimation.id = 'challenge-loading';
    loadingAnimation.innerHTML = `
        <div class="text-center p-5">
            <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4 class="mb-0">Loading Daily Challenge...</h4>
        </div>
    `;
    
    // Fade out loading after a short delay
    setTimeout(function() {
        const loader = document.getElementById('challenge-loading');
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(function() {
                if (loader.parentNode) {
                    loader.parentNode.removeChild(loader);
                }
            }, 500);
        }
    }, 1500);
    
    // Add loading animation to the page if a container exists
    const container = document.getElementById('challenge-container');
    if (container) {
        container.prepend(loadingAnimation);
    } else {
        document.body.prepend(loadingAnimation);
    }
}

// Initialize event listeners for the daily challenge
function initDailyChallengeListeners() {
    const answerButtons = document.querySelectorAll('.answer-btn');
    answerButtons.forEach(button => {
        button.addEventListener('click', submitDailyAnswer);
    });
    
    console.log("Daily challenge listeners initialized");
}

// Function to submit an answer in the daily challenge
function submitDailyAnswer(event) {
    if (event) {
        event.preventDefault();
    }
    
    // Disable all answer buttons to prevent multiple submissions
    document.querySelectorAll('.answer-btn').forEach(btn => {
        btn.disabled = true;
    });
    
    // Get the form data
    const form = document.getElementById('game-form');
    const formData = new FormData(form);
    
    // Get the answer from the clicked button
    let answer;
    if (event && event.target) {
        answer = event.target.getAttribute('data-answer');
    } else if (event && event.currentTarget) {
        answer = event.currentTarget.getAttribute('data-answer');
    }
    
    // If we still don't have an answer, this might be a direct call
    if (!answer && event && typeof event === 'string') {
        answer = event;
    }
    
    formData.append('answer', answer);
    
    // Show loading state
    const feedbackElement = document.getElementById('answer-feedback');
    if (feedbackElement) {
        feedbackElement.style.display = "block";
    }
    feedbackElement.innerHTML = `
        <div class="alert alert-info" role="alert">
            <div class="d-flex align-items-center">
                <div class="spinner-border spinner-border-sm me-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <span>Processing your answer...</span>
            </div>
        </div>
    `;
    
    // Send AJAX request
    fetch('/ajax_handlers/daily_challenge_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Answer response:', data);
        
        // Update the feedback message
        feedbackElement.innerHTML = '';
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert ${data.correct ? 'alert-success' : 'alert-danger'}`;
        alertDiv.role = 'alert';
        
        const alertTitle = document.createElement('h4');
        alertTitle.className = 'alert-heading';
        alertTitle.textContent = data.correct ? 'Correct!' : 'Incorrect!';
        
        const alertMessage = document.createElement('p');
        alertMessage.textContent = data.message;
        
        alertDiv.appendChild(alertTitle);
        alertDiv.appendChild(alertMessage);
        feedbackElement.appendChild(alertDiv);
        
        // Update the score display if correct
        if (data.correct && data.score !== undefined) {
            document.getElementById('currentScore').textContent = data.score;
        }
        
        // Update the lives display if incorrect
        if (!data.correct && data.lives !== undefined) {
            const livesContainer = document.querySelector('.game-status h3:last-child');
            if (livesContainer) {
                let livesHtml = 'Lives: ';
                for (let i = 0; i < data.lives; i++) {
                    livesHtml += '<i class="fas fa-heart text-danger"></i> ';
                }
                for (let i = data.lives; i < 3; i++) {
                    livesHtml += '<i class="far fa-heart text-danger"></i> ';
                }
                livesContainer.innerHTML = livesHtml;
            }
        }
        
        // Update round counter
        if (!data.game_over) {
            const currentRoundSpan = document.getElementById('currentRound');
            if (currentRoundSpan) {
                const nextRound = parseInt(currentRoundSpan.textContent) + 1;
                currentRoundSpan.textContent = nextRound;
            }
        }
        
        // Check if game is over
        if (data.game_over) {
            window.gameCompleted = true; // Set flag for navigation warning
            
            // Show redirect timer
            const timerElement = document.getElementById("next-round-timer");
            if (timerElement) {
                timerElement.style.display = "block";
            }
            
            // Start countdown for redirection
            let countdown = 3;
            const countdownElement = document.getElementById("countdown");
            if (countdownElement) {
                countdownElement.textContent = countdown;
                
                const countdownInterval = setInterval(() => {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                        
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            // Navigate to game over or victory page
                            window.location.href = data.correct ? '/controllers/daily_victory.php' : '/controllers/daily_final_round.php';
                        }
                    } else {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            } else {
                // If countdown element doesn't exist, just redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = data.correct ? '/controllers/daily_victory.php' : '/controllers/daily_final_round.php';
                }, 3000);
            }
        } else {
            // Show next round timer
            const timerElement = document.getElementById('next-round-timer');
            timerElement.style.display = 'block';
            
            // Start countdown for next round
            let countdown = 3;
            const countdownElement = document.getElementById('countdown');
            if (countdownElement) {
                countdownElement.textContent = countdown;
                
                const countdownInterval = setInterval(() => {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                        
                        if (countdown <= 0) {
                            clearInterval(countdownInterval);
                            // Reload page for next round
                            window.location.reload();
                        }
                    } else {
                        clearInterval(countdownInterval);
                    }
                }, 1000);
            } else {
                // If countdown element doesn't exist, just reload after 3 seconds
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
            }
        }
    })
    .catch(error => {
        console.error('Error submitting answer:', error);
        const feedbackElement = document.getElementById('answer-feedback');
        if (feedbackElement) {
            feedbackElement.innerHTML = `
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">Error!</h4>
                    <p>There was a problem submitting your answer. Please try again.</p>
                </div>
            `;
        }
        
        // Re-enable answer buttons
        document.querySelectorAll('.answer-btn').forEach(btn => {
            btn.disabled = false;
        });
    });
}

// Handle daily score display animations
function animateDailyScore(currentScore, newScore) {
    const scoreElement = document.getElementById('currentScore');
    if (!scoreElement) return;
    
    // Create a counting animation
    const duration = 1500; // milliseconds
    const frameDuration = 1000 / 60; // assume 60fps
    const totalFrames = Math.round(duration / frameDuration);
    const scoreIncrement = (newScore - currentScore) / totalFrames;
    
    let frame = 0;
    const counter = setInterval(() => {
        frame++;
        const progress = frame / totalFrames;
        const currentCount = Math.round(currentScore + scoreIncrement * frame);
        
        scoreElement.textContent = currentCount;
        
        if (frame === totalFrames) {
            clearInterval(counter);
            scoreElement.textContent = newScore;
        }
    }, frameDuration);
}

// Initialize everything when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initDailyChallengeListeners();
});
