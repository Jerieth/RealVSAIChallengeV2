<?php
/**
 * Leaderboard functions for Real vs AI application
 * Handles leaderboard queries and stats calculations
 */

// Include needed files
require_once 'database.php';

/**
 * Get the top players from the leaderboard
 * 
 * @param int $limit The number of results to return
 * @param string $game_mode Filter by game mode (single, endless, all)
 * @param string $difficulty Filter by difficulty (easy, medium, hard, all)
 * @param string $time_period Time period filter (all, week, month, year)
 * @return array The top players
 */
function get_top_players($limit = 10, $game_mode = 'all', $difficulty = 'all', $time_period = 'all') {
    $pdo = get_db_connection();
    
    // Build the SQL query
    $sql = "
        SELECT l.*, u.username
        FROM LeaderboardEntry l
        LEFT JOIN User u ON l.user_id = u.id
        WHERE 1=1
    ";
    $params = [];
    
    // Add game mode filter
    if ($game_mode !== 'all') {
        $sql .= " AND l.game_mode = ?";
        $params[] = $game_mode;
    }
    
    // Add difficulty filter
    if ($difficulty !== 'all') {
        $sql .= " AND l.difficulty = ?";
        $params[] = $difficulty;
    }
    
    // Add time period filter
    if ($time_period !== 'all') {
        $date = new DateTime();
        
        switch ($time_period) {
            case 'week':
                $date->modify('-1 week');
                break;
            case 'month':
                $date->modify('-1 month');
                break;
            case 'year':
                $date->modify('-1 year');
                break;
        }
        
        $sql .= " AND l.created_at >= ?";
        $params[] = $date->format('Y-m-d H:i:s');
    }
    
    // Add order and limit
    $sql .= " ORDER BY l.score DESC LIMIT ?";
    $params[] = (int) $limit;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching top players: ' . $e->getMessage());
        return [];
    }
}

/**
 * Get a user's highest score
 * 
 * @param int $user_id The user ID
 * @param string $game_mode Filter by game mode (single, endless, all)
 * @param string $difficulty Filter by difficulty (easy, medium, hard, all)
 * @return int The highest score
 */
function get_user_highest_score($user_id, $game_mode = 'all', $difficulty = 'all') {
    if (!$user_id) {
        return 0;
    }
    
    $pdo = get_db_connection();
    
    // Build the SQL query
    $sql = "
        SELECT MAX(score) as highest_score
        FROM LeaderboardEntry
        WHERE user_id = ?
    ";
    $params = [$user_id];
    
    // Add game mode filter
    if ($game_mode !== 'all') {
        $sql .= " AND game_mode = ?";
        $params[] = $game_mode;
    }
    
    // Add difficulty filter
    if ($difficulty !== 'all') {
        $sql .= " AND difficulty = ?";
        $params[] = $difficulty;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['highest_score'] ?? 0;
    } catch (PDOException $e) {
        error_log('Error fetching user highest score: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get a user's rank on the leaderboard
 * 
 * @param int $user_id The user ID
 * @param string $game_mode Filter by game mode (single, endless, all)
 * @param string $difficulty Filter by difficulty (easy, medium, hard, all)
 * @return int The user's rank (1-based)
 */
function get_user_rank($user_id, $game_mode = 'all', $difficulty = 'all') {
    if (!$user_id) {
        return 0;
    }
    
    $pdo = get_db_connection();
    
    // Get the user's highest score
    $highest_score = get_user_highest_score($user_id, $game_mode, $difficulty);
    
    if ($highest_score <= 0) {
        return 0; // No score recorded
    }
    
    // Build the SQL query to count players with higher scores
    $sql = "
        SELECT COUNT(DISTINCT user_id) as higher_scores
        FROM LeaderboardEntry
        WHERE score > ?
    ";
    $params = [$highest_score];
    
    // Add game mode filter
    if ($game_mode !== 'all') {
        $sql .= " AND game_mode = ?";
        $params[] = $game_mode;
    }
    
    // Add difficulty filter
    if ($difficulty !== 'all') {
        $sql .= " AND difficulty = ?";
        $params[] = $difficulty;
    }
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Rank is the number of players with higher scores plus 1
        return ($result['higher_scores'] ?? 0) + 1;
    } catch (PDOException $e) {
        error_log('Error fetching user rank: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get global game statistics
 * 
 * @return array The global statistics
 */
function get_global_stats() {
    $pdo = get_db_connection();
    
    $stats = [
        'total_games' => 0,
        'total_players' => 0,
        'average_accuracy' => 0,
        'highest_score' => 0
    ];
    
    try {
        // Total games
        $stmt = $pdo->query("SELECT COUNT(*) FROM Game WHERE completed = 1");
        $stats['total_games'] = (int) $stmt->fetchColumn();
        
        // Total unique players
        $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM Game WHERE user_id IS NOT NULL");
        $registered_users = (int) $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM LeaderboardEntry WHERE user_id IS NOT NULL");
        $leaderboard_users = (int) $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM LeaderboardEntry WHERE user_id IS NULL");
        $anonymous_players = (int) $stmt->fetchColumn();
        
        $stats['total_players'] = $registered_users + $anonymous_players;
        
        // Average accuracy
        $stmt = $pdo->query("
            SELECT 
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                COUNT(*) as total_answers
            FROM GameAnswer
        ");
        $accuracy_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($accuracy_data && $accuracy_data['total_answers'] > 0) {
            $stats['average_accuracy'] = round(($accuracy_data['correct_answers'] / $accuracy_data['total_answers']) * 100);
        }
        
        // Highest score
        $stmt = $pdo->query("SELECT MAX(score) FROM LeaderboardEntry");
        $stats['highest_score'] = (int) $stmt->fetchColumn();
        
        return $stats;
    } catch (PDOException $e) {
        error_log('Error fetching global stats: ' . $e->getMessage());
        return $stats;
    }
}

/**
 * Get a leaderboard entry by ID
 * 
 * @param int $entry_id The leaderboard entry ID
 * @return array|bool The leaderboard entry or false on failure
 */
function get_leaderboard_entry($entry_id) {
    if (!$entry_id) {
        return false;
    }
    
    $pdo = get_db_connection();
    
    try {
        $stmt = $pdo->prepare("
            SELECT l.*, u.username
            FROM LeaderboardEntry l
            LEFT JOIN User u ON l.user_id = u.id
            WHERE l.id = ?
        ");
        
        $stmt->execute([$entry_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching leaderboard entry: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get a user's leaderboard entries
 * 
 * @param int $user_id The user ID
 * @param int $limit The number of results to return
 * @param int $offset The offset for pagination
 * @return array The user's leaderboard entries
 */
function get_user_leaderboard_entries($user_id, $limit = 10, $offset = 0) {
    if (!$user_id) {
        return [];
    }
    
    $pdo = get_db_connection();
    
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM LeaderboardEntry
            WHERE user_id = ?
            ORDER BY score DESC, created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching user leaderboard entries: ' . $e->getMessage());
        return [];
    }
}

/**
 * Count a user's leaderboard entries
 * 
 * @param int $user_id The user ID
 * @return int The number of entries
 */
function count_user_leaderboard_entries($user_id) {
    if (!$user_id) {
        return 0;
    }
    
    $pdo = get_db_connection();
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM LeaderboardEntry
            WHERE user_id = ?
        ");
        
        $stmt->execute([$user_id]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Error counting user leaderboard entries: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Submit a score to the leaderboard
 * 
 * @param array $data Score data
 * @return int|bool The inserted ID or false on failure
 */
function submit_leaderboard_score($data) {
    if (empty($data)) {
        return false;
    }
    
    // Required fields
    $required = ['score', 'game_mode'];
    
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            return false;
        }
    }
    
    // Ensure initials are valid (3 characters) if provided
    if (isset($data['initials']) && !empty($data['initials'])) {
        $data['initials'] = substr(trim($data['initials']), 0, 3);
    } else {
        // Default initials if not provided
        $data['initials'] = 'AAA';
    }
    
    // Ensure score is valid
    $data['score'] = (int) $data['score'];
    
    if ($data['score'] <= 0) {
        return false;
    }
    
    // Ensure game mode is valid
    if (!in_array($data['game_mode'], ['single', 'endless'])) {
        return false;
    }
    
    // Validate difficulty if present
    if (isset($data['difficulty']) && !in_array($data['difficulty'], ['easy', 'medium', 'hard'])) {
        unset($data['difficulty']);
    }
    
    // Add created_at timestamp
    $data['created_at'] = date('Y-m-d H:i:s');
    
    $pdo = get_db_connection();
    
    try {
        return db_insert('LeaderboardEntry', $data);
    } catch (PDOException $e) {
        error_log('Error submitting leaderboard score: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get paginated leaderboard results
 * 
 * @param int $page The current page (1-based)
 * @param int $per_page Items per page
 * @param string $game_mode Filter by game mode (single, endless, all)
 * @param string $difficulty Filter by difficulty (easy, medium, hard, all)
 * @param string $time_period Time period filter (all, week, month, year)
 * @return array The paginated results and metadata
 */
function get_paginated_leaderboard($page = 1, $per_page = 10, $game_mode = 'all', $difficulty = 'all', $time_period = 'all') {
    $pdo = get_db_connection();
    
    // Ensure valid page number
    $page = max(1, (int) $page);
    $per_page = (int) $per_page;
    $offset = ($page - 1) * $per_page;
    
    // Build the SQL query for count
    $count_sql = "
        SELECT COUNT(*)
        FROM LeaderboardEntry l
        WHERE 1=1
    ";
    $params = [];
    
    // Add game mode filter
    if ($game_mode !== 'all') {
        $count_sql .= " AND l.game_mode = ?";
        $params[] = $game_mode;
    }
    
    // Add difficulty filter
    if ($difficulty !== 'all') {
        $count_sql .= " AND l.difficulty = ?";
        $params[] = $difficulty;
    }
    
    // Add time period filter
    if ($time_period !== 'all') {
        $date = new DateTime();
        
        switch ($time_period) {
            case 'week':
                $date->modify('-1 week');
                break;
            case 'month':
                $date->modify('-1 month');
                break;
            case 'year':
                $date->modify('-1 year');
                break;
        }
        
        $count_sql .= " AND l.created_at >= ?";
        $params[] = $date->format('Y-m-d H:i:s');
    }
    
    // Get total count for pagination
    try {
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_items = (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log('Error counting leaderboard entries: ' . $e->getMessage());
        $total_items = 0;
    }
    
    // Calculate total pages
    $total_pages = ceil($total_items / $per_page);
    
    // Build the SQL query for data
    $data_sql = "
        SELECT l.*, u.username
        FROM LeaderboardEntry l
        LEFT JOIN User u ON l.user_id = u.id
        WHERE 1=1
    ";
    
    // Add game mode filter
    if ($game_mode !== 'all') {
        $data_sql .= " AND l.game_mode = ?";
    }
    
    // Add difficulty filter
    if ($difficulty !== 'all') {
        $data_sql .= " AND l.difficulty = ?";
    }
    
    // Add time period filter
    if ($time_period !== 'all') {
        $data_sql .= " AND l.created_at >= ?";
    }
    
    // Add order and limit
    $data_sql .= " ORDER BY l.score DESC LIMIT ? OFFSET ?";
    $params[] = $per_page;
    $params[] = $offset;
    
    // Get paginated data
    try {
        $stmt = $pdo->prepare($data_sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching paginated leaderboard: ' . $e->getMessage());
        $items = [];
    }
    
    return [
        'items' => $items,
        'pagination' => [
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'per_page' => $per_page
        ]
    ];
}