/**
 * Achievement Display System
 * 
 * Handles displaying achievement notifications in the application
 */

// Configuration
const achievementConfig = {
    displayDuration: 5000,      // How long to show notification (ms)
    animationDuration: 500,     // Animation duration (ms)
    maxQueuedNotifications: 3,  // Maximum notifications to show at once
    soundEnabled: true,         // Whether to play sounds
    storageKey: 'achievement_notifications' // Local storage key for tracking shown achievements
};

// Queue for notifications
let achievementQueue = [];
let isProcessingQueue = false;

// Achievement tracker to prevent duplicate notifications
window.achievementTracker = {
    // Get already notified achievements from local storage
    getNotifiedAchievements: function() {
        try {
            const storedData = localStorage.getItem(achievementConfig.storageKey);
            return storedData ? JSON.parse(storedData) : {};
        } catch (e) {
            console.error('Error retrieving achievement notification data:', e);
            return {};
        }
    },
    
    // Check if an achievement has been notified
    hasBeenNotified: function(achievementType) {
        if (!achievementType) return true; // Consider empty types as already notified
        
        const userId = window.currentUserId || 'guest';
        const notifiedAchievements = this.getNotifiedAchievements();
        
        // Check if this user has seen this achievement
        return notifiedAchievements[userId] && 
               notifiedAchievements[userId].includes(achievementType);
    },
    
    // Mark an achievement as notified
    markAsNotified: function(achievementType) {
        if (!achievementType) return;
        
        try {
            const userId = window.currentUserId || 'guest';
            const notifiedAchievements = this.getNotifiedAchievements();
            
            // Initialize user's array if not exists
            if (!notifiedAchievements[userId]) {
                notifiedAchievements[userId] = [];
            }
            
            // Add achievement to the user's notified list if not already there
            if (!notifiedAchievements[userId].includes(achievementType)) {
                notifiedAchievements[userId].push(achievementType);
                localStorage.setItem(
                    achievementConfig.storageKey, 
                    JSON.stringify(notifiedAchievements)
                );
            }
        } catch (e) {
            console.error('Error saving achievement notification data:', e);
        }
    }
};

/**
 * Show an achievement notification
 * 
 * @param {Object} achievement - Achievement data with title, description, and icon
 */
function showAchievement(achievement) {
    // Skip if this achievement has already been shown to this user
    if (!achievement || !achievement.type || 
        window.achievementTracker.hasBeenNotified(achievement.type)) {
        console.log('Achievement already shown or invalid:', achievement);
        return;
    }
    
    console.log('Showing achievement notification:', achievement);
    
    // Add to queue and process
    achievementQueue.push(achievement);
    
    // Start processing queue if not already in progress
    if (!isProcessingQueue) {
        processAchievementQueue();
    }
    
    // Mark as notified to prevent duplicate notifications
    window.achievementTracker.markAsNotified(achievement.type);
    
    // Mark achievement as viewed on server via AJAX
    markAchievementAsViewed(achievement.type);
}

/**
 * Process the queue of achievement notifications
 */
function processAchievementQueue() {
    // If queue is empty, stop processing
    if (achievementQueue.length === 0) {
        isProcessingQueue = false;
        return;
    }
    
    isProcessingQueue = true;
    
    // Limit the number of active notifications
    const currentNotifications = document.querySelectorAll('.achievement-popup');
    if (currentNotifications.length >= achievementConfig.maxQueuedNotifications) {
        // Try again in a moment
        setTimeout(processAchievementQueue, 1000);
        return;
    }
    
    // Get next achievement from queue
    const achievement = achievementQueue.shift();
    
    // Create and display notification
    displayAchievementNotification(achievement);
    
    // Continue processing queue
    setTimeout(processAchievementQueue, 500);
}

/**
 * Create and display an achievement notification
 * 
 * @param {Object} achievement - Achievement data with title, description, and icon
 */
function displayAchievementNotification(achievement) {
    // Create container
    const notification = document.createElement('div');
    notification.className = 'achievement-popup';
    
    // Set content
    notification.innerHTML = `
        <div class="achievement-content">
            <div class="achievement-icon">
                <i class="${achievement.icon}"></i>
            </div>
            <div class="achievement-text">
                <h3>${achievement.title}</h3>
                <p>${achievement.description}</p>
            </div>
            <button class="achievement-close" aria-label="Close">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    
    // Add to document
    document.body.appendChild(notification);
    
    // Add event listener for close button
    const closeButton = notification.querySelector('.achievement-close');
    if (closeButton) {
        closeButton.addEventListener('click', function() {
            removeAchievementNotification(notification);
        });
    }
    
    // Play sound if available
    if (achievementConfig.soundEnabled && typeof playSound === 'function') {
        playSound('achievement');
    }
    
    // Set timeout to remove notification
    setTimeout(function() {
        removeAchievementNotification(notification);
    }, achievementConfig.displayDuration);
}

/**
 * Remove an achievement notification with animation
 * 
 * @param {HTMLElement} notification - The notification element to remove
 */
function removeAchievementNotification(notification) {
    // Add class for animation
    notification.classList.add('achievement-hiding');
    
    // Remove after animation completes
    setTimeout(function() {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, achievementConfig.animationDuration);
}

/**
 * Mark achievement as viewed on server
 * 
 * @param {string} achievementType - The achievement type
 */
function markAchievementAsViewed(achievementType) {
    // Only attempt if user is logged in
    if (typeof window.currentUserId === 'undefined' || !window.currentUserId) {
        return;
    }
    
    // Create a form data object for the POST request
    const formData = new FormData();
    formData.append('action', 'mark_viewed');
    formData.append('user_id', window.currentUserId);
    formData.append('achievements', JSON.stringify([achievementType]));
    
    // Make an AJAX request
    fetch('controllers/achievements.php', {
        method: 'POST',
        body: formData
    })
    .catch(error => {
        console.error('Error marking achievement as viewed:', error);
    });
}

/**
 * Process multiple new achievements
 * 
 * @param {Array} achievements - Array of achievement objects
 */
function processNewAchievements(achievements) {
    if (!achievements || !Array.isArray(achievements) || achievements.length === 0) {
        return;
    }
    
    // Process each achievement
    achievements.forEach(achievement => {
        // Skip if no type is provided
        if (!achievement || !achievement.type) return;
        
        // Show the achievement notification
        showAchievement(achievement);
    });
}

// Initialize once DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle the "Show More/Less Achievements" toggle button on profile page
    const expandButton = document.getElementById('expandAchievements');
    if (expandButton) {
        let expanded = false;
        
        expandButton.addEventListener('click', function() {
            expanded = !expanded;
            
            // Get all achievement items
            const hiddenAchievements = document.querySelectorAll('.achievement-hidden');
            
            if (expanded) {
                // Show all hidden achievements
                hiddenAchievements.forEach(function(achievement) {
                    // First remove the hidden class to make it display:block
                    achievement.classList.remove('achievement-hidden');
                    
                    // Set initial state for animation
                    achievement.style.opacity = '0';
                    achievement.style.transform = 'translateY(20px)';
                    
                    // Force browser to acknowledge the change
                    void achievement.offsetWidth;
                    
                    // Animate in
                    achievement.style.opacity = '1';
                    achievement.style.transform = 'translateY(0)';
                    achievement.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                });
                
                expandButton.textContent = 'Show Less Achievements';
            } else {
                // Hide achievements beyond initial count
                hiddenAchievements.forEach(function(achievement) {
                    // Fade out first
                    achievement.style.opacity = '0';
                    achievement.style.transform = 'translateY(20px)';
                    
                    // After fade completes, hide the element completely
                    setTimeout(function() {
                        achievement.classList.add('achievement-hidden');
                    }, 400);
                });
                
                expandButton.textContent = 'Show More Achievements';
            }
        });
    }
});
