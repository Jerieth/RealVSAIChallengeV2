/**
 * View Profile Page JavaScript
 * Handles achievement showing/hiding
 */

document.addEventListener('DOMContentLoaded', function() {
    // Only handle "Show More Achievements" functionality
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
});
