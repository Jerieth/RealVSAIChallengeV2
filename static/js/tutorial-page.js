/**
 * Tutorial Page JavaScript
 * Handles interactive tutorial functionality and achievement tracking
 * 
 * This is separate from the SimpleTutorialBubble class in tutorial.js
 * which handles the basic tutorial speech bubble functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    // Initialize achievement sound if browser supports audio
    try {
        window.achievementSound = new Audio('/static/sounds/achievement.mp3');
    } catch (e) {
        console.log('Audio not supported or achievement sound not found');
    }
    // Navigation between tutorial sections
    const tutorialNav = document.querySelectorAll('.tutorial-next, .tutorial-prev');
    tutorialNav.forEach(button => {
        button.addEventListener('click', function() {
            const currentSection = this.closest('.tutorial-section');
            let targetSection;
            
            if (this.classList.contains('tutorial-next')) {
                targetSection = document.getElementById(this.dataset.next);
            } else {
                targetSection = document.getElementById(this.dataset.prev);
            }
            
            if (currentSection && targetSection) {
                currentSection.style.display = 'none';
                targetSection.style.display = 'block';
                
                // Scroll to top of new section
                window.scrollTo({
                    top: targetSection.offsetTop - 100,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Handle example guesses
    const exampleButtons = document.querySelectorAll('.example-guess');
    exampleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const exampleNum = this.dataset.example;
            const isCorrect = this.dataset.correct === 'true';
            const feedback = document.querySelector(`.feedback-${exampleNum}`);
            
            // Display feedback
            if (feedback) {
                feedback.style.display = 'block';
            }
            
            // Visual feedback on buttons
            const allButtons = document.querySelectorAll(`[data-example="${exampleNum}"]`);
            allButtons.forEach(btn => {
                btn.disabled = true;
                
                if (btn === this) {
                    if (isCorrect) {
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-success');
                    } else {
                        btn.classList.remove('btn-primary');
                        btn.classList.add('btn-danger');
                    }
                }
            });
            
            // Show correct answer if wrong
            if (!isCorrect) {
                const correctButton = document.querySelector(`[data-example="${exampleNum}"][data-correct="true"]`);
                if (correctButton) {
                    correctButton.classList.remove('btn-primary');
                    correctButton.classList.add('btn-success');
                }
            }
            
            // Scroll to feedback
            setTimeout(() => {
                if (feedback) {
                    feedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }, 300);
        });
    });
    
    // Practice section functionality
    const practiceImages = document.querySelectorAll('.practice-image');
    const practiceSubmit = document.getElementById('practice-submit');
    const practiceFeedback = document.getElementById('practice-feedback');
    
    let selectedImage = null;
    
    practiceImages.forEach(image => {
        image.addEventListener('click', function() {
            // Remove selection from all images
            practiceImages.forEach(img => {
                img.closest('.card').classList.remove('selected-image');
            });
            
            // Select this image
            this.closest('.card').classList.add('selected-image');
            selectedImage = this.dataset.type;
            
            // Enable submit button
            if (practiceSubmit) {
                practiceSubmit.disabled = false;
            }
        });
    });
    
    if (practiceSubmit) {
        practiceSubmit.addEventListener('click', function() {
            if (!selectedImage) return;
            
            const isCorrect = selectedImage === 'real';
            
            // Show appropriate feedback
            if (practiceFeedback) {
                practiceFeedback.style.display = 'block';
                
                if (isCorrect) {
                    practiceFeedback.innerHTML = `
                        <div class="alert alert-success">
                            <h4 class="alert-heading">Correct!</h4>
                            <p>Great job! You identified the real image correctly.</p>
                            <p>Continue practicing to improve your skills!</p>
                        </div>
                    `;
                } else {
                    practiceFeedback.innerHTML = `
                        <div class="alert alert-danger">
                            <h4 class="alert-heading">Not quite right</h4>
                            <p>That was actually the AI-generated image. Look for the telltale signs we discussed.</p>
                            <p>Don't worry, it takes practice to get good at this!</p>
                        </div>
                    `;
                }
                
                // Scroll to feedback
                practiceFeedback.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Disable buttons after submission
            this.disabled = true;
            
            // Highlight correct answer
            practiceImages.forEach(img => {
                const card = img.closest('.card');
                const imageType = img.dataset.type;
                
                if (imageType === 'real') {
                    card.classList.add('correct-image');
                }
            });
        });
    }
    
    // Feature detail toggles
    const featureToggles = document.querySelectorAll('.feature-toggle');
    featureToggles.forEach(toggle => {
        toggle.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const targetElement = document.getElementById(targetId);
            
            if (targetElement) {
                const isVisible = targetElement.style.display !== 'none';
                
                // Toggle visibility
                targetElement.style.display = isVisible ? 'none' : 'block';
                
                // Update toggle icon
                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-plus');
                    icon.classList.toggle('fa-minus');
                }
                
                // Scroll into view if opening
                if (!isVisible) {
                    setTimeout(() => {
                        targetElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }, 100);
                }
            }
        });
    });
});