<?php
/**
 * MultiplayerGame model - PHP version
 */

require_once __DIR__ . '/../includes/database.php';

/**
 * Create a new multiplayer game
 * 
 * @param string $session_id Session ID
 * @param int $host_id Host user ID
 * @param string $host_name Host username
 * @param int $total_turns Total number of turns
 * @return array|false Game data or false on failure
 */
function create_multiplayer_game($session_id, $host_id, $host_name, $total_turns = 10) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO multiplayer_games (
            session_id, 
            player1_id, 
            player1_name, 
            current_turn, 
            total_turns, 
            completed, 
            created_at, 
            player1_score,
            shown_images
        ) VALUES (
            :session_id, 
            :host_id, 
            :host_name, 
            1, 
            :total_turns, 
            FALSE, 
            :created_at, 
            0,
            ''
        )
    ");
    
    $now = date('Y-m-d H:i:s');
    
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    $stmt->bindParam(':host_id', $host_id, PDO::PARAM_INT);
    $stmt->bindParam(':host_name', $host_name, PDO::PARAM_STR);
    $stmt->bindParam(':total_turns', $total_turns, PDO::PARAM_INT);
    $stmt->bindParam(':created_at', $now, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        return get_multiplayer_game_by_session_id($session_id);
    }
    
    return false;
}

/**
 * Get multiplayer game by ID
 * 
 * @param int $id Game ID
 * @return array|null Game data or null if not found
 */
function get_multiplayer_game_by_id($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM multiplayer_games WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    return $game ?: null;
}

/**
 * Get multiplayer game by session ID
 * 
 * @param string $session_id Session ID
 * @return array|null Game data or null if not found
 */
function get_multiplayer_game_by_session_id($session_id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM multiplayer_games WHERE session_id = :session_id");
    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
    $stmt->execute();
    
    $game = $stmt->fetch(PDO::FETCH_ASSOC);
    return $game ?: null;
}

/**
 * Update multiplayer game data
 * 
 * @param int $id Game ID
 * @param array $data Associative array of fields to update
 * @return bool True on success, false on failure
 */
function update_multiplayer_game($id, $data) {
    global $db;
    
    // Build the SQL query dynamically based on provided data
    $sql = "UPDATE multiplayer_games SET ";
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
        if (substr($param, -3) == '_id' || substr($param, -6) == '_score' || 
            $param == ':current_turn' || $param == ':total_turns') {
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
 * Add player to multiplayer game
 * 
 * @param int $game_id Game ID
 * @param int $player_id Player user ID
 * @param string $player_name Player username
 * @return bool True on success, false on failure
 */
function add_player_to_multiplayer_game($game_id, $player_id, $player_name) {
    global $db;
    
    $game = get_multiplayer_game_by_id($game_id);
    if (!$game) {
        return false;
    }
    
    // Check if player is already in the game
    if ($game['player1_id'] == $player_id || 
        $game['player2_id'] == $player_id || 
        $game['player3_id'] == $player_id || 
        $game['player4_id'] == $player_id) {
        return true; // Player already in game
    }
    
    // Determine which player slot to use
    $player_slot = null;
    $player_name_field = null;
    
    if (empty($game['player2_id'])) {
        $player_slot = 'player2_id';
        $player_name_field = 'player2_name';
    } elseif (empty($game['player3_id'])) {
        $player_slot = 'player3_id';
        $player_name_field = 'player3_name';
    } elseif (empty($game['player4_id'])) {
        $player_slot = 'player4_id';
        $player_name_field = 'player4_name';
    } else {
        return false; // Game is full
    }
    
    // Add player to game
    $stmt = $db->prepare("
        UPDATE multiplayer_games SET 
        $player_slot = :player_id,
        $player_name_field = :player_name,
        {$player_slot}_score = 0
        WHERE id = :game_id
    ");
    
    $stmt->bindParam(':player_id', $player_id, PDO::PARAM_INT);
    $stmt->bindParam(':player_name', $player_name, PDO::PARAM_STR);
    $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Get host user ID for multiplayer game
 * 
 * @param array $game Game data
 * @return int|null Host user ID or null if not found
 */
function get_multiplayer_game_host_id($game) {
    return $game['player1_id'];
}

/**
 * Get host player name for multiplayer game
 * 
 * @param array $game Game data
 * @return string|null Host player name or null if not found
 */
function get_multiplayer_game_host_name($game) {
    return $game['player1_name'];
}

/**
 * Get player count for multiplayer game
 * 
 * @param array $game Game data
 * @return int Number of players in the game
 */
function get_multiplayer_game_player_count($game) {
    $count = 0;
    
    if (!empty($game['player1_id'])) $count++;
    if (!empty($game['player2_id'])) $count++;
    if (!empty($game['player3_id'])) $count++;
    if (!empty($game['player4_id'])) $count++;
    
    return $count;
}

/**
 * Get player IDs for multiplayer game
 * 
 * @param array $game Game data
 * @return array Array of player IDs
 */
function get_multiplayer_game_player_ids($game) {
    $player_ids = [];
    
    if (!empty($game['player1_id'])) $player_ids[] = $game['player1_id'];
    if (!empty($game['player2_id'])) $player_ids[] = $game['player2_id'];
    if (!empty($game['player3_id'])) $player_ids[] = $game['player3_id'];
    if (!empty($game['player4_id'])) $player_ids[] = $game['player4_id'];
    
    return $player_ids;
}

/**
 * Get player names for multiplayer game
 * 
 * @param array $game Game data
 * @return array Array of player names
 */
function get_multiplayer_game_player_names($game) {
    $player_names = [];
    
    if (!empty($game['player1_name'])) $player_names[] = $game['player1_name'];
    if (!empty($game['player2_name'])) $player_names[] = $game['player2_name'];
    if (!empty($game['player3_name'])) $player_names[] = $game['player3_name'];
    if (!empty($game['player4_name'])) $player_names[] = $game['player4_name'];
    
    return $player_names;
}

/**
 * Get player scores for multiplayer game
 * 
 * @param array $game Game data
 * @return array Array of player scores
 */
function get_multiplayer_game_player_scores($game) {
    $player_scores = [];
    
    if (!empty($game['player1_id'])) {
        $player_scores[$game['player1_id']] = $game['player1_score'];
    }
    
    if (!empty($game['player2_id'])) {
        $player_scores[$game['player2_id']] = $game['player2_score'];
    }
    
    if (!empty($game['player3_id'])) {
        $player_scores[$game['player3_id']] = $game['player3_score'];
    }
    
    if (!empty($game['player4_id'])) {
        $player_scores[$game['player4_id']] = $game['player4_score'];
    }
    
    return $player_scores;
}

/**
 * Update player score in multiplayer game
 * 
 * @param int $game_id Game ID
 * @param int $player_id Player user ID
 * @param int $score New score
 * @return bool True on success, false on failure
 */
function update_multiplayer_game_player_score($game_id, $player_id, $score) {
    global $db;
    
    $game = get_multiplayer_game_by_id($game_id);
    if (!$game) {
        return false;
    }
    
    // Determine which player slot to update
    $score_field = null;
    
    if ($game['player1_id'] == $player_id) {
        $score_field = 'player1_score';
    } elseif ($game['player2_id'] == $player_id) {
        $score_field = 'player2_score';
    } elseif ($game['player3_id'] == $player_id) {
        $score_field = 'player3_score';
    } elseif ($game['player4_id'] == $player_id) {
        $score_field = 'player4_score';
    } else {
        return false; // Player not in game
    }
    
    // Update player score
    $stmt = $db->prepare("
        UPDATE multiplayer_games SET 
        $score_field = :score
        WHERE id = :game_id
    ");
    
    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Mark multiplayer game as completed
 * 
 * @param int $id Game ID
 * @return bool True on success, false on failure
 */
function complete_multiplayer_game($id) {
    global $db;
    
    $stmt = $db->prepare("UPDATE multiplayer_games SET completed = TRUE WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Get multiplayer game shown images
 * 
 * @param array $game Game data
 * @return array Array of shown image paths
 */
function get_multiplayer_game_shown_images($game) {
    if (empty($game['shown_images'])) {
        return [];
    }
    
    return explode(',', $game['shown_images']);
}

/**
 * Add shown image to multiplayer game
 * 
 * @param int $id Game ID
 * @param string $image_path Image path to add
 * @return bool True on success, false on failure
 */
function add_multiplayer_game_shown_image($id, $image_path) {
    global $db;
    
    // Get current shown images
    $game = get_multiplayer_game_by_id($id);
    if (!$game) {
        return false;
    }
    
    $shown_images = get_multiplayer_game_shown_images($game);
    
    // Add new image
    $shown_images[] = $image_path;
    
    // Update game
    $new_shown_images = implode(',', $shown_images);
    
    $stmt = $db->prepare("UPDATE multiplayer_games SET shown_images = :shown_images WHERE id = :id");
    $stmt->bindParam(':shown_images', $new_shown_images, PDO::PARAM_STR);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    return $stmt->execute();
}

/**
 * Get player position in multiplayer game
 * 
 * @param array $game Game data
 * @param int $player_id Player user ID
 * @return int Player position (1-4) or 0 if not found
 */
function get_player_position_in_multiplayer_game($game, $player_id) {
    if ($game['player1_id'] == $player_id) return 1;
    if ($game['player2_id'] == $player_id) return 2;
    if ($game['player3_id'] == $player_id) return 3;
    if ($game['player4_id'] == $player_id) return 4;
    
    return 0; // Player not in game
}

/**
 * Get all active multiplayer games
 * 
 * @return array List of active multiplayer games
 */
function get_active_multiplayer_games() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT * 
        FROM multiplayer_games 
        WHERE completed = FALSE
        ORDER BY created_at DESC
    ");
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Count total multiplayer games
 * 
 * @return int Total number of multiplayer games
 */
function count_total_multiplayer_games() {
    global $db;
    
    $stmt = $db->query("SELECT COUNT(*) as total FROM multiplayer_games");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['total'] ?: 0;
}