<?php
require_once __DIR__ . '/functions.php';

function is_offensive_username($username) {
    $pdo = get_db_connection();
    
    // Check if username validation is disabled
    if (function_exists('get_setting') && get_setting('disable_username_validation', 0) == 1) {
        return false;
    }
    
    // Convert username to lowercase for checking
    $username_lower = strtolower($username);
    
    // Prevent 'Anonymous' or similar variations
    if (preg_match('/^an+on+[yi]+m+ou*s$/i', $username)) {
        return true;
    }
    
    // Also block exact match for 'Anonymous' in any capitalization
    if (strtolower($username) === 'anonymous') {
        return true;
    }
    
    // Get all banned words
    $stmt = $pdo->query("SELECT word FROM banned_words");
    $banned_words = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($banned_words as $word) {
        // Use word boundaries for matching
        $pattern = '/\b' . preg_quote(strtolower($word), '/') . '\b/';
        if (preg_match($pattern, $username_lower)) {
            return true;
        }
    }
    
    return false;
}