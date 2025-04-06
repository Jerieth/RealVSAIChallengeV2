<?php
/**
 * Game model - PHP version
 */

require_once __DIR__ . '/../includes/database.php';

// Prevent function redeclaration conflicts with functions.php
// Do not declare create_game() here - it already exists in functions.php

/**
 * Create a new game entry in the database directly
 * 
 * @param string $session_id Session ID
 * @param string $game_mode Game mode ('single', 'multiplayer', 'endless')
 * @param string $difficulty Difficulty ('easy', 'medium', 'hard')
 * @param int $total_turns Total number of turns
 * @param int $lives Number of lives
 * @param int|null $user_id User ID or null for anonymous play
 * @return array|false Game data or false on failure
 */
function create_game_db_entry($session_id, $game_mode, $difficulty, $total_turns, $lives, $user_id = null) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        INSERT INTO games (
            session_id, 
            game_mode, 
            difficulty, 
            current_turn, 
            total_turns, 
            score, 
            lives, 
            original_lives,
            completed, 
            created_at, 
            user_id, 
            shown_images
        ) VALUES (
            :session_id, 
            :game_mode, 
            :difficulty, 
            1, 
            :total_turns, 
            0, 
            :lives, 
            :original_lives,
            FALSE, 
            :created_at, 
            :user_id, 
            ''
        )
    ");
    
    $now = date('Y-m-d H:i:s');
    
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    $stmt->bindParam(':game_mode', $game_mode, PDO::PARAM_STR);
    $stmt->bindParam(':difficulty', $difficulty, PDO::PARAM_STR);
    $stmt->bindParam(':total_turns', $total_turns, PDO::PARAM_INT);
    $stmt->bindParam(':lives', $lives, PDO::PARAM_INT);
    $stmt->bindParam(':original_lives', $lives, PDO::PARAM_INT);
    $stmt->bindParam(':created_at', $now, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return get_game_by_session_id($session_id);
    }
    
    return false;
}

/**
 * Get game by ID
 * 
 * @param int $id Game ID
 * @return array|null Game data or null if not found
 */
function get_game_by_id($id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT * FROM games WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    return $game ?: null;
}

/**
 * Get game by session ID
 * 
 * @param string $session_id Session ID
 * @return array|null Game data or null if not found
 */
function get_game_by_session_id($session_id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("SELECT * FROM games WHERE session_id = :session_id");
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    $stmt->execute();
    
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    return $game ?: null;
}

/**
 * Update game data in the database directly
 * 
 * Note: This is distinct from update_game_state() in functions.php
 * 
 * @param int $id Game ID
 * @param array $data Associative array of fields to update
 * @return bool True on success, false on failure
 */
function db_update_game($id, $data) {
    $db = get_db_connection();
    
    // Build the SQL query dynamically based on provided data
    $sql = "UPDATE games SET ";
    $updates = [];
    $params = [':id' => $id];
    
    // Add each field to the query
    foreach ($data as $field => $value) {
        if ($field == 'id' || $field == 'session_id') continue; // Skip ID fields
        
        $updates[] = "$field = :$field";
        $params[":$field"] = $value;
    }
    
    // If no valid fields were provided, return false
    if (empty($updates)) {
        return false;
    }
    
    $sql .= implode(', ', $updates);
    $sql .= " WHERE id = :id";
    
    $stmt = $db->prepare($sql);
    
    foreach ($params as $param => $value) {
        if ($param == ':id' || $param == ':user_id' || $param == ':current_turn' || 
            $param == ':total_turns' || $param == ':score' || $param == ':lives' || 
            $param == ':original_lives') {
            $stmt->bindValue($param, $value, PDO::PARAM_INT);
        } elseif ($param == ':completed') {
            $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
        } else {
            $stmt->bindValue($param, $value, PDO::PARAM_STR);
        }
    }
    
    return $stmt->execute();
}

/**
 * Update game score in the database
 * 
 * Note: This function is different from update_game_score() in functions.php
 * which updates the score based on session ID and correctness.
 * 
 * @param int $id Game ID
 * @param int $score New score
 * @return bool True on success, false on failure
 */
function db_update_game_score($id, $score) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE games SET score = :score WHERE id = :id");
    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Update game lives in the database
 * 
 * @param int $id Game ID
 * @param int $lives New lives count
 * @return bool True on success, false on failure
 */
function db_update_game_lives($id, $lives) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE games SET lives = :lives WHERE id = :id");
    $stmt->bindParam(':lives', $lives, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Update game turn in the database
 * 
 * @param int $id Game ID
 * @param int $turn New turn number
 * @return bool True on success, false on failure
 */
function db_update_game_turn($id, $turn) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE games SET current_turn = :turn WHERE id = :id");
    $stmt->bindParam(':turn', $turn, PDO::PARAM_INT);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Mark game as completed in the database
 * 
 * @param int $id Game ID
 * @return bool True on success, false on failure
 */
function db_complete_game($id) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("UPDATE games SET completed = TRUE WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Check if game is over
 * 
 * @param array $game Game data
 * @return bool True if game is over, false otherwise
 */
function is_game_over($game) {
    // Game is over if the player ran out of lives
    return $game['lives'] <= 0;
}

/**
 * Check if game is complete
 * 
 * @param array $game Game data
 * @return bool True if game is complete, false otherwise
 */
function is_game_complete($game) {
    // Game is complete if the player reached the last turn and still has lives
    return $game['current_turn'] > $game['total_turns'] && $game['lives'] > 0;
}

/**
 * Get game shown images
 * 
 * @param array $game Game data
 * @return array Array of shown image paths
 */
function get_game_shown_images($game) {
    if (empty($game['shown_images'])) {
        return [];
    }
    
    return explode(',', $game['shown_images']);
}

/**
 * Add shown image to game in the database
 * 
 * @param int $id Game ID
 * @param string $image_path Image path to add
 * @return bool True on success, false on failure
 */
function db_add_game_shown_image($id, $image_path) {
    $db = get_db_connection();
    
    // Get current shown images
    $game = get_game_by_id($id);
    if (!$game) {
        return false;
    }
    
    $shown_images = get_game_shown_images($game);
    
    // Add new image
    $shown_images[] = $image_path;
    
    // Update game
    $new_shown_images = implode(',', $shown_images);
    
    $stmt = $db->prepare("UPDATE games SET shown_images = :shown_images WHERE id = :id");
    $stmt->bindParam(':shown_images', $new_shown_images, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Get difficulty settings
 * 
 * @param string $difficulty Difficulty ('easy', 'medium', 'hard')
 * @return array Difficulty settings
 */
function get_difficulty_settings($difficulty) {
    $settings = [
        'easy' => [
            'total_turns' => 20,
            'lives' => 5,
            'points_per_correct' => 5
        ],
        'medium' => [
            'total_turns' => 50,
            'lives' => 3,
            'points_per_correct' => 10
        ],
        'hard' => [
            'total_turns' => 100,
            'lives' => 1,
            'points_per_correct' => 20
        ]
    ];
    
    if (isset($settings[$difficulty])) {
        return $settings[$difficulty];
    }
    
    // Default to easy if difficulty not found
    return $settings['easy'];
}

/**
 * Count total games in the database
 * 
 * @return int Total number of games
 */
function db_count_total_games() {
    $db = get_db_connection();
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM games");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ?: 0;
}

/**
 * Get recent games from the database
 * 
 * @param int $limit Number of games to retrieve
 * @return array List of recent games
 */
function db_get_recent_games($limit = 10) {
    $db = get_db_connection();
    
    $stmt = $db->prepare("
        SELECT g.*, u.username 
        FROM games g
        LEFT JOIN users u ON g.user_id = u.id
        ORDER BY g.created_at DESC 
        LIMIT :limit
    ");
    
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}