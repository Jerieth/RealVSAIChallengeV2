/**
 * Game Achievement Notification System
 * 
 * This file handles achievement tracking and notifications during gameplay.
 * It uses AJAX to check for new achievements without page refreshes.
 * It also integrates with the localStorage-based achievement tracker from achievements.js
 * 
 * Notifications are now silenced during gameplay but achievements are still tracked.
 */

// Make sure we use the global achievementTracker from achievements.js
// Fallback to a local version if not available
if (typeof window.achievementTracker === 'undefined') {
    console.warn('Global achievement tracker not found. Using local fallback (non-persistent).');
    
    // Set up a local achievement tracker as fallback
    window.achievementTracker = {
        notifiedAchievements: [],
        
        // Check if achievement has already been notified
        hasBeenNotified: function(achievementType) {
            return this.notifiedAchievements.includes(achievementType);
        },
        
        // Mark achievement as notified
        markAsNotified: function(achievementType) {
            if (!this.hasBeenNotified(achievementType)) {
                this.notifiedAchievements.push(achievementType);
            }
        },
        
        // Reset the tracker (e.g., on new game)
        reset: function() {
            this.notifiedAchievements = [];
        }
    };
} else {
    console.log('Using global localStorage-based achievement tracker');
}

/**
 * Initialize achievement system when document is loaded
 */
document.addEventListener('DOMContentLoaded', function() {
    // Check for user ID in window.currentUserId
    if (typeof window.currentUserId !== 'undefined' && window.currentUserId > 0) {
        setupAchievementAjaxListener();
        
        // Initialize check for achievements
        // Give time for game to initialize first
        setTimeout(function() {
            checkForNewAchievements(window.currentUserId);
        }, 2000);
    }
});

/**
 * Listen for achievements in AJAX responses
 */
function setupAchievementAjaxListener() {
    // Create a proxy for the native XMLHttpRequest object
    const originalXHR = window.XMLHttpRequest;
    
    // Override the XMLHttpRequest to intercept responses
    window.XMLHttpRequest = function() {
        const xhr = new originalXHR();
        let originalOnReadyStateChange = xhr.onreadystatechange;
        
        // Override the onreadystatechange property
        Object.defineProperty(xhr, 'onreadystatechange', {
            get: function() {
                return originalOnReadyStateChange;
            },
            set: function(newCallback) {
                // Store the new callback
                originalOnReadyStateChange = newCallback;
                
                // Replace with our wrapper function
                return function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            
                            // Check if the response contains achievement data
                            if (response && response.achievements && Array.isArray(response.achievements)) {
                                processNewAchievements(response.achievements);
                            }
                        } catch (e) {
                            // Not JSON or error in parsing - ignore
                            console.log("Not JSON response or parsing error", e);
                        }
                    }
                    
                    // Call the original callback
                    if (originalOnReadyStateChange) {
                        originalOnReadyStateChange.apply(this, arguments);
                    }
                };
            }
        });
        
        return xhr;
    };
}

/**
 * Retrieve and track (but not display) any new achievements
 * 
 * @param {number} userId - The user's ID
 */
function checkForNewAchievements(userId) {
    if (!userId) return;
    
    // Create a form data object for the POST request
    const formData = new FormData();
    formData.append('action', 'check_achievements');
    formData.append('user_id', userId);
    
    // Make an AJAX request to check for achievements
    fetch('controllers/achievements.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.achievements && data.achievements.length > 0) {
            processNewAchievements(data.achievements);
        }
    })
    .catch(error => {
        console.error('Error checking for achievements:', error);
    });
}

/**
 * Process newly earned achievements but don't display them
 * Instead, just mark them as seen and track them
 * 
 * @param {Array} achievements - Array of achievement objects to process
 */
function processNewAchievements(achievements) {
    if (!achievements || achievements.length === 0) return;
    
    console.log('Processing achievements (without displaying):', achievements);
    
    // Process each achievement
    achievements.forEach(achievement => {
        // Skip invalid achievements
        if (!achievement || !achievement.type) {
            console.log('Invalid achievement found, skipping');
            return;
        }
        
        // Avoid duplicate notifications using window.achievementTracker
        // This tracker is persistent across page loads
        if (window.achievementTracker.hasBeenNotified(achievement.type)) {
            console.log('Achievement already notified, skipping:', achievement.type);
            return;
        }
        
        console.log('Achievement earned but notification hidden during gameplay:', achievement.type);
        
        // Mark achievement as notified so it's tracked but not shown
        window.achievementTracker.markAsNotified(achievement.type);
        
        // Mark the achievement as viewed on the server
        markAchievementAsViewed(achievement.type);
    });
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