<?php
/**
 * Configuration file for Real vs AI application
 * Contains global settings, database configuration, and constants
 */

// Error reporting settings
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Site settings
if (!defined('SITE_TITLE')) {
    define('SITE_TITLE', 'Real vs AI');
}

if (!defined('APP_NAME')) {
    define('APP_NAME', 'Real vs AI');
}

// Path definitions
if (!defined('ROOT_DIR')) {
    define('ROOT_DIR', dirname(__DIR__));
}
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', ROOT_DIR . '/uploads');
}
if (!defined('IMAGE_DIR')) {
    define('IMAGE_DIR', ROOT_DIR . '/static/images');
}
if (!defined('REAL_IMAGES_DIR')) {
    define('REAL_IMAGES_DIR', ROOT_DIR . '/static/images/real/');
}
if (!defined('AI_IMAGES_DIR')) {
    define('AI_IMAGES_DIR', ROOT_DIR . '/static/images/ai/');
}
if (!defined('REAL_IMAGES_URL')) {
    define('REAL_IMAGES_URL', '/static/images/real');
}
if (!defined('AI_IMAGES_URL')) {
    define('AI_IMAGES_URL', '/static/images/ai');
}
if (!defined('DATA_DIR')) {
    define('DATA_DIR', ROOT_DIR . '/data');
}

// Ensure data directory exists with proper permissions
if (!file_exists(DATA_DIR)) {
    if (!mkdir(DATA_DIR, 0777, true)) {
        error_log('Failed to create data directory: ' . DATA_DIR);
        die('Failed to create data directory. Check permissions.');
    }
} else {
    // Ensure directory is writable
    chmod(DATA_DIR, 0777);
}

// Ensure image directories exist
if (!file_exists(REAL_IMAGES_DIR)) {
    if (!mkdir(REAL_IMAGES_DIR, 0777, true)) {
        error_log('Failed to create real images directory: ' . REAL_IMAGES_DIR);
    }
}

if (!file_exists(AI_IMAGES_DIR)) {
    if (!mkdir(AI_IMAGES_DIR, 0777, true)) {
        error_log('Failed to create AI images directory: ' . AI_IMAGES_DIR);
    }
}

// Database configuration
if (!defined('DB_TYPE')) {
    define('DB_TYPE', 'sqlite');
}
if (!defined('DB_PATH')) {
    define('DB_PATH', DATA_DIR . '/realvsai.db');
}

// Ensure database file is writable if it exists
if (file_exists(DB_PATH)) {
    chmod(DB_PATH, 0666);
    if (!is_writable(DB_PATH)) {
        error_log('Database file is not writable: ' . DB_PATH);
    }
}

// Game settings
if (!defined('EASY_DIFFICULTY')) {
    define('EASY_DIFFICULTY', [
        'turns' => 20,
        'lives' => 5,
        'bonus_frequency' => 10 // Show bonus game every 10 turns
    ]);
}

if (!defined('MEDIUM_DIFFICULTY')) {
    define('MEDIUM_DIFFICULTY', [
        'turns' => 50,
        'lives' => 3,
        'bonus_frequency' => 10
    ]);
}

if (!defined('HARD_DIFFICULTY')) {
    define('HARD_DIFFICULTY', [
        'turns' => 100,
        'lives' => 1,
        'bonus_frequency' => 0 // No bonus games in hard mode
    ]);
}

if (!defined('MULTIPLAYER_SETTINGS')) {
    define('MULTIPLAYER_SETTINGS', [
        'min_players' => 2,
        'max_players' => 4,
        'turns' => 10
    ]);
}

if (!defined('ENDLESS_SETTINGS')) {
    define('ENDLESS_SETTINGS', [
        'turns' => 0, // Unlimited turns
        'lives' => 1, // Only 1 life in endless mode
        'bonus_frequency' => 10
    ]);
}

// Session configuration was moved to router.php
// to ensure it happens before session_start()