/**
 * Profile Page JavaScript
 * Handles dynamic functionality for user profile pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Toggle achievement visibility
    const expandAchievementsBtn = document.getElementById('expandAchievements');
    if (expandAchievementsBtn) {
        expandAchievementsBtn.addEventListener('click', function() {
            const hiddenAchievements = document.querySelectorAll('.achievement-hidden');
            
            // Show all hidden achievements
            hiddenAchievements.forEach(achievement => {
                achievement.classList.remove('achievement-hidden');
                // Add a fade-in animation
                achievement.style.animation = 'fadeInUp 0.4s ease forwards';
            });
            
            // Disable the button after showing all achievements
            this.disabled = true;
            this.classList.add('disabled');
            this.textContent = 'All Achievements Shown';
        });
    }
    
    // Avatar selection functionality
    const avatarButtons = document.querySelectorAll('.avatar-option');
    const selectedAvatarInput = document.getElementById('selectedAvatar');
    
    avatarButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons
            avatarButtons.forEach(btn => btn.classList.remove('active', 'selected'));
            
            // Add active class to clicked button
            this.classList.add('active', 'selected');
            
            // Update the hidden input
            const avatar = this.getAttribute('data-avatar');
            if (selectedAvatarInput) {
                selectedAvatarInput.value = avatar;
                
                // Also update the display if it exists
                const displayElem = document.querySelector('.selected-avatar-display');
                if (displayElem) {
                    displayElem.textContent = avatar;
                }
            } else {
                console.error('Could not find selectedAvatar input element');
            }
        });
    });
    
    // Country selection functionality
    const countrySelect = document.getElementById('country');
    if (countrySelect) {
        countrySelect.addEventListener('change', function() {
            const flagPreview = document.getElementById('flag-preview');
            if (flagPreview && this.value) {
                flagPreview.innerHTML = `<span class="flag-icon flag-icon-${this.value.toLowerCase()}"></span>`;
                flagPreview.style.display = 'inline-block';
            } else if (flagPreview) {
                flagPreview.style.display = 'none';
            }
        });
    }
});
