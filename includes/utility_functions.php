<?php
/**
 * Utility Functions
 * General utility functions for the application
 */

/**
 * Redirect to a URL
 * 
 * @param string $url The URL to redirect to
 * @param int $status_code HTTP status code (default: 302)
 * @return void
 */
function redirect_to($url, $status_code = 302) {
    header('Location: ' . $url, true, $status_code);
    exit;
}

/**
 * Format a date string for display
 * 
 * @param string $date_string The date string to format
 * @param string $format The date format (default: 'F j, Y')
 * @return string Formatted date
 */
function format_date($date_string, $format = 'F j, Y') {
    $date = new DateTime($date_string);
    return $date->format($format);
}

/**
 * Truncate a string to a specified length
 * 
 * @param string $string The string to truncate
 * @param int $length The maximum length
 * @param string $append String to append if truncated (default: '...')
 * @return string Truncated string
 */
function truncate_string($string, $length, $append = '...') {
    if (strlen($string) > $length) {
        $string = substr($string, 0, $length - strlen($append)) . $append;
    }
    return $string;
}

/**
 * Sanitize output for HTML display
 * 
 * @param string $string The string to sanitize
 * @return string Sanitized string
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Check if a string starts with a specific substring
 * 
 * @param string $haystack The string to search in
 * @param string $needle The substring to search for
 * @return bool True if $haystack starts with $needle
 */
function starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Check if a string ends with a specific substring
 * 
 * @param string $haystack The string to search in
 * @param string $needle The substring to search for
 * @return bool True if $haystack ends with $needle
 */
function ends_with($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }
    return substr($haystack, -$length) === $needle;
}

/**
 * Get the current page URL
 * 
 * @param bool $include_query_string Whether to include the query string
 * @return string Current page URL
 */
function current_url($include_query_string = true) {
    $url = 'http';
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        $url .= 's';
    }
    $url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    if (!$include_query_string) {
        $url = strtok($url, '?');
    }
    
    return $url;
}

/**
 * Generate a random string
 * 
 * @param int $length Length of the string
 * @param string $keyspace Characters to use (default: alphanumeric)
 * @return string Random string
 */
function random_string($length = 10, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
    $pieces = [];
    $max = mb_strlen($keyspace, '8bit') - 1;
    
    for ($i = 0; $i < $length; ++$i) {
        $pieces[] = $keyspace[random_int(0, $max)];
    }
    
    return implode('', $pieces);
}

/**
 * Process the daily final round
 * This function handles the last round of the daily challenge
 * 
 * @param array $post_data Form post data
 * @param string $username Current username
 * @return array Result data
 */
function process_daily_final_round($post_data, $username) {
    if (empty($post_data) || empty($username)) {
        return [
            'success' => false,
            'message' => 'Invalid input data'
        ];
    }
    
    // Validate required fields
    $required_fields = ['real_image_choice', 'csrf_token', 'game_id'];
    foreach ($required_fields as $field) {
        if (!isset($post_data[$field])) {
            return [
                'success' => false,
                'message' => "Missing required field: $field"
            ];
        }
    }
    
    // Validate CSRF token
    if (!validate_csrf_token($post_data['csrf_token'])) {
        return [
            'success' => false,
            'message' => 'Invalid CSRF token'
        ];
    }
    
    // Process the choice
    $image_choice = intval($post_data['real_image_choice']);
    $game_id = intval($post_data['game_id']);
    
    // Get game data from session
    if (!isset($_SESSION['daily_games'][$game_id])) {
        return [
            'success' => false,
            'message' => 'Game not found'
        ];
    }
    
    $game = $_SESSION['daily_games'][$game_id];
    
    // Check if correct choice was made
    $correct_choice = 0; // First image (index 0) is always the real one in our implementation
    
    if ($image_choice === $correct_choice) {
        // Correct answer
        // Update game stats
        $game['is_completed'] = true;
        $game['is_victory'] = true;
        $game['completed_at'] = date('Y-m-d H:i:s');
        
        // Update session
        $_SESSION['daily_games'][$game_id] = $game;
        
        // Update daily challenge record
        update_daily_challenge_record($username, true);
        
        // Return success
        return [
            'success' => true,
            'is_correct' => true,
            'redirect' => '/controllers/daily_victory.php'
        ];
    } else {
        // Incorrect answer
        // Update game stats
        $game['is_completed'] = true;
        $game['is_victory'] = false;
        $game['completed_at'] = date('Y-m-d H:i:s');
        
        // Update session
        $_SESSION['daily_games'][$game_id] = $game;
        
        // Update daily challenge record
        update_daily_challenge_record($username, false);
        
        // Return failure
        return [
            'success' => true,
            'is_correct' => false,
            'redirect' => '/controllers/daily_game_over.php'
        ];
    }
}
?>