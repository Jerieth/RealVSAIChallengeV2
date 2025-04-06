<?php
/**
 * Leaderboard model - PHP version
 */

require_once __DIR__ . '/../includes/database.php';

/**
 * Add a leaderboard entry
 * 
 * @param string $initials Player initials
 * @param int $score Score
 * @param string $game_mode Game mode ('single', 'endless')
 * @param string|null $difficulty Difficulty ('easy', 'medium', 'hard')
 * @param int|null $user_id User ID or null for anonymous play
 * @param string|null $email Email address for anonymous players
 * @return int|false Entry ID or false on failure
 */
if (!function_exists('add_leaderboard_entry_model')) {
function add_leaderboard_entry_model($initials, $score, $game_mode, $difficulty = null, $user_id = null, $email = null) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        INSERT INTO leaderboard_entries (
            user_id, 
            initials, 
            email, 
            score, 
            game_mode, 
            difficulty, 
            created_at
        ) VALUES (
            :user_id, 
            :initials, 
            :email, 
            :score, 
            :game_mode, 
            :difficulty, 
            :created_at
        )
    ");
    
    $now = date('Y-m-d H:i:s');
    
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':initials', $initials, PDO::PARAM_STR);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    $stmt->bindParam(':created_at', $now, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        return $db->lastInsertId();
    }
    
    return false;
}
}

/**
 * Get top leaderboard entries
 * 
 * @param string $game_mode Game mode ('single', 'endless')
 * @param string|null $difficulty Difficulty ('easy', 'medium', 'hard')
 * @param int $limit Maximum number of entries to return
 * @return array List of leaderboard entries
 */
if (!function_exists('get_top_leaderboard_entries')) {
function get_top_leaderboard_entries($game_mode, $difficulty = null, $limit = 10) {
    $db = get_db_connection();
    
    $sql = "
        SELECT le.*, u.username 
        FROM leaderboard_entries le
        LEFT JOIN users u ON le.user_id = u.id
        WHERE le.game_mode = :game_mode
    ";
    
    if ($difficulty !== null) {
        $sql .= " AND le.difficulty = :difficulty";
    }
    
    $sql .= " ORDER BY le.score DESC LIMIT :limit";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    
    if ($difficulty !== null) {
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

/**
 * Get user's rank on leaderboard
 * 
 * @param int $user_id User ID
 * @param string $game_mode Game mode ('single', 'endless')
 * @param string|null $difficulty Difficulty ('easy', 'medium', 'hard')
 * @return int Rank position (1-based) or 0 if not ranked
 */
if (!function_exists('get_user_leaderboard_rank')) {
function get_user_leaderboard_rank($user_id, $game_mode, $difficulty = null) {
    $db = get_db_connection();
    
    // Get user's highest score
    $sql = "
        SELECT MAX(score) as highest_score 
        FROM leaderboard_entries 
        WHERE user_id = :user_id 
        AND game_mode = :game_mode
    ";
    
    if ($difficulty !== null) {
        $sql .= " AND difficulty = :difficulty";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    
    if ($difficulty !== null) {
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || $result['highest_score'] === null) {
        return 0; // Not ranked
    }
    
    $highest_score = $result['highest_score'];
    
    // Count how many scores are higher than the user's highest score
    $sql = "
        SELECT COUNT(DISTINCT score) as rank_count 
        FROM leaderboard_entries 
        WHERE score > :score 
        AND game_mode = :game_mode
    ";
    
    if ($difficulty !== null) {
        $sql .= " AND difficulty = :difficulty";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':score', $highest_score, PDO::PARAM_INT);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    
    if ($difficulty !== null) {
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Rank is 1-based (rank 1 is the highest)
    return $result['rank_count'] + 1;
}
}

/**
 * Get user's position in leaderboard relative to others
 * 
 * @param int $user_id User ID
 * @param string $game_mode Game mode ('single', 'endless')
 * @param string|null $difficulty Difficulty ('easy', 'medium', 'hard')
 * @param int $range Number of players to include before and after user
 * @return array List of leaderboard entries around the user's position
 */
if (!function_exists('get_user_leaderboard_context')) {
function get_user_leaderboard_context($user_id, $game_mode, $difficulty = null, $range = 2) {
    $db = get_db_connection();
    
    // Get user's highest score
    $sql = "
        SELECT MAX(score) as highest_score 
        FROM leaderboard_entries 
        WHERE user_id = :user_id 
        AND game_mode = :game_mode
    ";
    
    if ($difficulty !== null) {
        $sql .= " AND difficulty = :difficulty";
    }
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    
    if ($difficulty !== null) {
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result || $result['highest_score'] === null) {
        return []; // Not ranked
    }
    
    $highest_score = $result['highest_score'];
    
    // Get entries around user's score
    $sql = "
        SELECT le.*, u.username, 
            (SELECT COUNT(DISTINCT score) FROM leaderboard_entries 
            WHERE score > le.score AND game_mode = :game_mode " . 
            ($difficulty !== null ? "AND difficulty = :difficulty" : "") . ") + 1 as rank
        FROM leaderboard_entries le
        LEFT JOIN users u ON le.user_id = u.id
        WHERE le.game_mode = :game_mode
    ";
    
    if ($difficulty !== null) {
        $sql .= " AND le.difficulty = :difficulty";
    }
    
    $sql .= "
        ORDER BY le.score DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    
    if ($difficulty !== null) {
        $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $all_entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Find user's position
    $user_position = -1;
    foreach ($all_entries as $index => $entry) {
        if ($entry['user_id'] == $user_id && $entry['score'] == $highest_score) {
            $user_position = $index;
            break;
        }
    }
    
    if ($user_position === -1) {
        return []; // Not found
    }
    
    // Get entries around user's position
    $start = max(0, $user_position - $range);
    $end = min(count($all_entries) - 1, $user_position + $range);
    
    return array_slice($all_entries, $start, $end - $start + 1);
}
}

/**
 * Get recent leaderboard entries
 * 
 * @param int $limit Maximum number of entries to return
 * @return array List of recent leaderboard entries
 */
if (!function_exists('get_recent_leaderboard_entries')) {
function get_recent_leaderboard_entries($limit = 10) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT le.*, u.username 
        FROM leaderboard_entries le
        LEFT JOIN users u ON le.user_id = u.id
        ORDER BY le.created_at DESC 
        LIMIT :limit
    ");
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
}

/**
 * Count total leaderboard entries
 * 
 * @return int Total number of leaderboard entries
 */
if (!function_exists('count_total_leaderboard_entries')) {
function count_total_leaderboard_entries() {
    $db = get_db_connection();
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM leaderboard_entries");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ?: 0;
}
}

/**
 * Format leaderboard entry for display
 * 
 * @param array $entry Leaderboard entry
 * @return array Formatted entry
 */
if (!function_exists('format_leaderboard_entry')) {
function format_leaderboard_entry($entry) {
    // If entry has a username, use it; otherwise use initials
    $display_name = !empty($entry['username']) ? $entry['username'] : $entry['initials'];
    
    // Format date for display
    $date = new DateTime($entry['created_at']);
    $formatted_date = $date->format('M j, Y');
    
    return [
        'id' => $entry['id'],
        'display_name' => $display_name,
        'score' => $entry['score'],
        'game_mode' => $entry['game_mode'],
        'difficulty' => $entry['difficulty'],
        'date' => $formatted_date,
        'rank' => isset($entry['rank']) ? $entry['rank'] : null,
        'is_user' => isset($entry['is_user']) ? $entry['is_user'] : false
    ];
}
}

/**
 * Delete all leaderboard entries
 * 
 * @return array Array with success status and message
 */
if (!function_exists('clear_all_leaderboard_entries')) {
function clear_all_leaderboard_entries() {
    $db = get_db_connection();
    
    try {
        $stmt = $db->prepare("DELETE FROM leaderboard");
        $stmt->execute();
        
        $count = $stmt->rowCount();
        
        return [
            'success' => true,
            'message' => "Successfully deleted {$count} leaderboard entries"
        ];
    } catch (PDOException $e) {
        error_log("Error clearing leaderboard entries: " . $e->getMessage());
        return [
            'success' => false,
            'message' => "Error clearing leaderboard entries: " . $e->getMessage()
        ];
    }
}
}