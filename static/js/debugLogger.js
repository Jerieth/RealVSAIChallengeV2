/**
 * Debug Logger for Real vs AI
 * This module provides centralized debug logging with debug mode control
 */

// Initialize debug mode as false until we fetch the correct setting from server
var debugMode = false;

// Function to fetch debug mode setting from server
function initializeDebugMode() {
    fetch('/controllers/debug_mode.php')
        .then(response => response.json())
        .then(data => {
            debugMode = data.debug_enabled;
            // Always log debug initialization status for troubleshooting
            console.log('Debug mode initialized:', debugMode);
        })
        .catch(error => {
            // Keep error logging regardless of debug mode
            console.error('Error fetching debug mode:', error);
            // Default to false if there's an error
            debugMode = false;
        });
}

// Call the initialization function
initializeDebugMode();

// Debug logging utility function
window.centralDebugLog = function() {
    if (debugMode) {
        console.log.apply(console, arguments);
    }
};

// Error logging utility - always show errors regardless of debug mode
window.centralErrorLog = function() {
    console.error.apply(console, arguments);
};

// Warning logging utility - always show warnings regardless of debug mode
window.centralWarnLog = function() {
    console.warn.apply(console, arguments);
};

// Info logging utility - only show in debug mode
window.centralInfoLog = function() {
    if (debugMode) {
        console.info.apply(console, arguments);
    }
};