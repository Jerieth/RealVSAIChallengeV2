<?php
/**
 * Enhanced Multiplayer Functions
 * 
 * This file contains functions for the enhanced multiplayer system including:
 * - Public/private room options
 * - Room codes for private rooms
 * - Public matchmaking
 * - Bot players
 * - End-game bonus challenge
 * - Quick match functionality
 */

// Include game functions to avoid redefinition errors
require_once __DIR__ . '/functions.php';

// Include bot functions
require_once __DIR__ . '/bot_functions.php';

if (!function_exists('quick_match')) {
    /**
     * Find an available public multiplayer game or create a new one
     * 
     * @param bool $play_anonymous Whether to join as an anonymous user
     * @return array Result with success status and session ID
     */
    function quick_match($play_anonymous = false) {
        global $game_config;
        $conn = get_db_connection();
        session_start_if_not_started();

        // Get user ID from session
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

        // Try to find an open public game with available slots
        $stmt = $conn->prepare("
            SELECT * FROM multiplayer_games 
            WHERE is_public = 1 
            AND started_at IS NULL
            AND completed = 0
            AND current_turn = 1
            AND (
                player1_id IS NULL OR player1_id != :user_id
            ) 
            AND (
                player2_id IS NULL OR player2_id != :user_id
            )
            AND (
                player3_id IS NULL OR player3_id != :user_id
            )
            AND (
                player4_id IS NULL OR player4_id != :user_id
            )
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($game) {
            // Found an open game, check if there's room
            $player_count = 0;
            for ($i = 1; $i <= $game_config['max_players']; $i++) {
                if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
                    $player_count++;
                }
            }

            // If there's room, join this game
            if ($player_count < $game_config['max_players']) {
                error_log("Found existing game with session ID: " . $game['session_id'] . " - Joining it");
                return join_game($game['session_id'], $play_anonymous);
            }
        }

        // No suitable game found, create a new one
        error_log("No suitable existing game found - Creating a new one");
        $result = create_multiplayer_game(true, 10, $play_anonymous);

        if ($result['success']) {
            // Game is already set as public when created with create_multiplayer_game(true, ...)
            error_log("Created new multiplayer game with session ID: " . $result['session_id']);
        }

        return $result;
    }
}

if (!function_exists('unlock_achievement')) {
    /**
     * Unlock an achievement for a user
     * 
     * @param int $user_id User ID
     * @param string $achievement_slug Achievement identifier
     * @return bool Success or failure
     */
    function unlock_achievement($user_id, $achievement_slug) {
        if (empty($user_id)) {
            return false; // Can't unlock achievements for anonymous users
        }

        $conn = get_db_connection();

        // Check if achievement exists
        $stmt = $conn->prepare("SELECT id FROM achievements WHERE slug = :slug");
        $stmt->bindParam(':slug', $achievement_slug, PDO::PARAM_STR);
        $stmt->execute();
        $achievement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$achievement) {
            return false; // Achievement doesn't exist
        }

        // Check if user already has this achievement
        $stmt = $conn->prepare("
            SELECT id FROM user_achievements 
            WHERE user_id = :user_id AND achievement_id = :achievement_id
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':achievement_id', $achievement['id'], PDO::PARAM_INT);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return true; // Already unlocked
        }

        // Unlock the achievement
        $stmt = $conn->prepare("
            INSERT INTO user_achievements 
            (user_id, achievement_id, unlocked_at) 
            VALUES (:user_id, :achievement_id, :unlocked_at)
        ");

        $now = date('Y-m-d H:i:s');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':achievement_id', $achievement['id'], PDO::PARAM_INT);
        $stmt->bindParam(':unlocked_at', $now, PDO::PARAM_STR);

        return $stmt->execute();
    }
}

if (!function_exists('generate_room_code')) {
    /**
     * Generate a random, easy-to-read room code
     * 
     * @param int $length Length of the code
     * @return string Room code
     */
    function generate_room_code($length = 6) {
        // Use only uppercase letters and numbers, exclude confusing characters like 0, O, 1, I
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Insert a dash in the middle for readability
        if ($length >= 4) {
            $middle = floor($length / 2);
            $code = substr($code, 0, $middle) . '-' . substr($code, $middle);
        }

        return $code;
    }
}

/**
 * Get a multiplayer game by session ID
 *
 * NOTE: This function is already defined in game_functions.php
 * We are skipping its definition here to avoid conflicts
 *
 * @param string $session_id The game session ID
 * @return array|false The game data or false if not found
 */
// Function is defined in game_functions.php

/**
 * Create a new multiplayer game with enhanced options
 * 
 * NOTE: This function is already defined in game_functions.php
 * We are skipping its definition here to avoid conflicts
 * 
 * @param bool $is_public Whether the game is public or private
 * @param int $total_turns Total number of turns in the game
 * @param bool $play_anonymous Whether to play anonymously
 * @return array Result of the operation
 */
// Function is defined in game_functions.php

if (!function_exists('join_public_match')) {
    /**
     * Join a public match or create one if none available
     * 
     * @param bool $play_anonymous Whether to play anonymously
     * @return array Result of the operation
     */
    function join_public_match($play_anonymous = false) {
        global $game_config;
        $conn = get_db_connection();

        // Check if anonymous play is requested
        if ($play_anonymous && !isset($_SESSION['user_id'])) {
            // Generate a random anonymous username
            $anon_id = generate_session_id(8);
            $anon_username = "Anonymous" . $anon_id;

            // Set anonymous user in session
            $_SESSION['username'] = $anon_username;
            $_SESSION['is_anonymous'] = true;

            // No user_id for anonymous users
            $user_id = null;
            $player_name = $anon_username;
        } else {
            // Get user ID and name from session
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            $player_name = isset($_SESSION['username']) ? $_SESSION['username'] : null;
        }

        // First, check if there are any available public games with space
        $stmt = $conn->prepare("
            SELECT * FROM multiplayer_games 
            WHERE is_public = 1 
            AND completed = 0 
            AND status = 'waiting'
            ORDER BY created_at ASC
            LIMIT 1
        ");
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($game) {
            // Found an available game, join it
            $result = join_game($game['session_id'], $play_anonymous);

            if ($result['success']) {
                return array(
                    'success' => true,
                    'session_id' => $game['session_id'],
                    'is_new' => false
                );
            }
        }

        // No available games or failed to join, create a new one
        $result = create_multiplayer_game(true, 10, $play_anonymous);

        if ($result['success']) {
            $result['is_new'] = true;
            return $result;
        } else {
            return array('success' => false, 'message' => 'Failed to create a public match');
        }
    }
}

if (!function_exists('join_private_game')) {
    /**
     * Join a private game using the room code
     * 
     * @param string $room_code Room code for the private game
     * @param bool $play_anonymous Whether to play anonymously
     * @return array Result of the operation
     */
    function join_private_game($room_code, $play_anonymous = false) {
        $conn = get_db_connection();

        // Find the game with this room code
        $stmt = $conn->prepare("
            SELECT * FROM multiplayer_games 
            WHERE room_code = :room_code 
            AND completed = 0
        ");
        $stmt->bindParam(':room_code', $room_code, PDO::PARAM_STR);
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return array('success' => false, 'message' => 'Room not found with the provided code.');
        }

        // Join the game using its session ID
        $result = join_game($game['session_id'], $play_anonymous);

        return $result;
    }
}

// Function is now defined in bot_functions.php

if (!function_exists('check_and_add_bots')) {
    /**
     * Check if bots should be added to a game and add them if needed
     * 
     * @param string $session_id The game session ID
     * @return array Result of the operation
     */
    function check_and_add_bots($session_id) {
        global $game_config;
        $conn = get_db_connection();

        // Get the game state
        $stmt = $conn->prepare("
            SELECT * FROM multiplayer_games 
            WHERE session_id = :session_id 
            AND completed = 0
        ");
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return array('success' => false, 'message' => 'Game not found');
        }

        // Check if wait timeout has passed
        $current_time = date('Y-m-d H:i:s');
        if (strtotime($current_time) < strtotime($game['wait_timeout'])) {
            return array('success' => true, 'message' => 'Wait timeout not reached yet', 'bots_added' => false);
        }

        // Check if the game is already in progress (with stricter criteria)
        $game_in_progress = $game['has_bots'] == 1 || 
                          $game['started_at'] !== null || 
                          $game['status'] === 'in_progress' ||
                          $game['current_turn'] > 1;
        
        if ($game_in_progress) {
            error_log("Bot addition skipped: Game {$session_id} is already in progress");
            return array('success' => true, 'message' => 'Game already has bots or has started', 'bots_added' => false);
        }

        // Count the number of players
        $player_count = 0;
        for ($i = 1; $i <= $game_config['max_players']; $i++) {
            if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
                $player_count++;
            }
        }

        // If there's already at least one player and timeout has passed, add a bot
        if ($player_count > 0) {
            // Generate bot player(s) to fill empty slots
            // We only add one bot if at least one human player is present
            $slots_needed = $game_config['min_players'] - $player_count;
            $slots_needed = max(1, $slots_needed); // At least one bot

            // Find the next available player slot
            $bots_added = 0;
            for ($i = 1; $i <= $game_config['max_players']; $i++) {
                if (empty($game["player{$i}_id"]) && empty($game["player{$i}_name"]) && $bots_added < $slots_needed) {
                    // Generate a bot name
                    $bot_name = generate_bot_name();

                    // Add the bot to the game
                    $stmt = $conn->prepare("
                        UPDATE multiplayer_games 
                        SET player{$i}_name = :bot_name,
                            has_bots = 1 
                        WHERE session_id = :session_id
                    ");
                    $stmt->bindParam(':bot_name', $bot_name, PDO::PARAM_STR);
                    $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
                    $stmt->execute();

                    // Record the bot in the multiplayer_bots table
                    $stmt = $conn->prepare("
                        INSERT INTO multiplayer_bots 
                        (multiplayer_game_id, bot_name, player_slot) 
                        VALUES (:game_id, :bot_name, :player_slot)
                    ");
                    $stmt->bindParam(':game_id', $game['id'], PDO::PARAM_INT);
                    $stmt->bindParam(':bot_name', $bot_name, PDO::PARAM_STR);
                    $stmt->bindParam(':player_slot', $i, PDO::PARAM_INT);
                    $stmt->execute();

                    $bots_added++;
                }
            }

            // If we added bots, update the game to start
            if ($bots_added > 0) {
                $stmt = $conn->prepare("
                    UPDATE multiplayer_games 
                    SET status = 'in_progress',
                        started_at = :started_at
                    WHERE session_id = :session_id
                ");
                $stmt->bindParam(':started_at', $current_time, PDO::PARAM_STR);
                $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
                $stmt->execute();

                return array('success' => true, 'message' => 'Bots added successfully', 'bots_added' => true, 'count' => $bots_added);
            }
        }

        return array('success' => true, 'message' => 'No bots needed at this time', 'bots_added' => false);
    }
}

if (!function_exists('generate_bot_name')) {
    /**
     * Generate a random bot name
     * 
     * - 50% chance of using a full predefined bot username from bot_usernames table
     * - 50% chance of using combination of adjective + noun from the bots table
     * - When using adjective+noun, 50% chance of not adding a number to the end
     * 
     * @return string Bot name
     */
    function generate_bot_name() {
        $conn = get_db_connection();
        
        // Make sure the bot_usernames table exists
        // This is from bot_functions.php, but we include it here as a fallback in case there's an issue with imports
        if (function_exists('ensure_bot_tables_exist')) {
            ensure_bot_tables_exist($conn);
        } else {
            error_log("Function ensure_bot_tables_exist not found, defining fallback version");
            // Define a fallback version to handle potential errors
            function ensure_bot_tables_exist($conn = null) {
                if ($conn === null) {
                    $conn = get_db_connection();
                }
                
                try {
                    // Check if bot_usernames table exists
                    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bot_usernames'");
                    $bot_usernames_exists = ($result && $result->fetchColumn()) ? true : false;
                    
                    if (!$bot_usernames_exists) {
                        error_log("Creating bot_usernames table");
                        
                        // Create the bot_usernames table
                        $conn->exec("CREATE TABLE bot_usernames (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            bot_username TEXT NOT NULL UNIQUE,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        
                        // Insert initial bot usernames
                        $initial_usernames = [
                            'PixelPhantomX', 'ShadowByte77', 'TurboGlitcher', 'QuantumRogue', 'NebulaHackz',
                            'Zephyr', 'Icarus', 'Cassian', 'Juno', 'Elio'
                        ];
                        
                        $stmt = $conn->prepare("INSERT INTO bot_usernames (bot_username) VALUES (?)");
                        foreach ($initial_usernames as $username) {
                            $stmt->execute([$username]);
                        }
                    }
                    
                    // Check if bots table exists
                    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bots'");
                    $bots_exists = ($result && $result->fetchColumn()) ? true : false;
                    
                    if (!$bots_exists) {
                        error_log("Creating bots table");
                        
                        // Create the bots table
                        $conn->exec("CREATE TABLE bots (
                            id INTEGER PRIMARY KEY AUTOINCREMENT,
                            adjective TEXT NOT NULL,
                            noun TEXT NOT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        
                        // Initial adjectives and nouns
                        $adjectives = [
                            'Swift', 'Quick', 'Fast', 'Rapid', 'Speedy',
                            'Smart', 'Clever', 'Wise', 'Bright', 'Sharp',
                            'Brave', 'Bold', 'Daring', 'Mighty', 'Strong'
                        ];
                        
                        $nouns = [
                            'Player', 'Gamer', 'Challenger', 'Competitor', 'Contender',
                            'Wizard', 'Ninja', 'Master', 'Champion', 'Warrior'
                        ];
                        
                        // Insert all combinations of adjectives and nouns
                        $stmt = $conn->prepare("INSERT INTO bots (adjective, noun) VALUES (?, ?)");
                        foreach ($adjectives as $adjective) {
                            foreach ($nouns as $noun) {
                                $stmt->execute([$adjective, $noun]);
                            }
                        }
                    }
                    
                    return true;
                } catch (Exception $e) {
                    error_log("Error ensuring bot tables exist: " . $e->getMessage());
                    return false;
                }
            }
            
            // Now call the newly defined function
            ensure_bot_tables_exist($conn);
        }

        // Decide whether to use a predefined username (50% chance) or generate one (50% chance)
        $use_predefined = (random_int(1, 100) <= 50);

        if ($use_predefined) {
            try {
                // Get a random predefined bot username
                $stmt = $conn->query("SELECT bot_username FROM bot_usernames ORDER BY RANDOM() LIMIT 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && !empty($result['bot_username'])) {
                    return $result['bot_username'];
                }
            } catch (PDOException $e) {
                error_log("Error selecting from bot_usernames: " . $e->getMessage());
                // Continue with fallback method if there's an error
            }
        }
        
        // If no predefined username or we're generating one with adjective+noun
        // Get a random adjective and noun from database
        $stmt = $conn->query("SELECT adjective, noun FROM bots ORDER BY RANDOM() LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $adjective = $result['adjective'];
            $noun = $result['noun'];
            
            // Decide whether to add a number (50% chance)
            $add_number = (random_int(1, 100) <= 50);
            
            if ($add_number) {
                // Generate a random 2-digit number
                $number = random_int(10, 99);
                return $adjective . $noun . $number;
            } else {
                return $adjective . $noun;
            }
        }
        
        // Fallback to hardcoded values if no database entries exist
        $adjectives = [
            'Swift', 'Quick', 'Fast', 'Rapid', 'Speedy',
            'Smart', 'Clever', 'Wise', 'Bright', 'Sharp'
        ];
        $nouns = [
            'Player', 'Gamer', 'Challenger', 'Competitor', 'Contender'
        ];
        
        // Randomly select an adjective and noun
        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];
        
        // Decide whether to add a number (50% chance)
        $add_number = (random_int(1, 100) <= 50);
        
        if ($add_number) {
            // Generate a random 2-digit number
            $number = random_int(10, 99);
            return $adjective . $noun . $number;
        } else {
            return $adjective . $noun;
        }
    }
}

if (!function_exists('simulate_bot_answer')) {
    /**
     * Simulate a bot answering a question
     * 
     * @param int $game_id The multiplayer game ID
     * @param int $player_slot The bot's player slot
     * @param int $turn_number The current turn number
     * @return array Result of the bot's answer
     */
    function simulate_bot_answer($game_id, $player_slot, $turn_number) {
        $conn = get_db_connection();

        // Get the bot information
        $stmt = $conn->prepare("
            SELECT * FROM multiplayer_bots 
            WHERE multiplayer_game_id = :game_id 
            AND player_slot = :player_slot
        ");
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->bindParam(':player_slot', $player_slot, PDO::PARAM_INT);
        $stmt->execute();
        $bot = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bot) {
            return array('success' => false, 'message' => 'Bot not found');
        }

        // Get the multiplayer game
        $stmt = $conn->prepare("SELECT * FROM multiplayer_games WHERE id = :id");
        $stmt->bindParam(':id', $game_id, PDO::PARAM_INT);
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return array('success' => false, 'message' => 'Game not found');
        }

        // Check if we've exceeded the total turns
        if (isset($game['total_turns']) && isset($game['current_turn']) && $game['current_turn'] > $game['total_turns']) {
            return array('success' => false, 'message' => 'Game is over - no more turns available');
        }

        // Get the correct answer (which image is real)
        $left_is_real = (random_int(0, 1) === 1); // In a real game, this would be stored somewhere

        // 50% chance of getting the correct answer
        $is_correct = (random_int(0, 1) === 1);

        // Simulate a delay between 3-8 seconds
        $response_time = random_int(3000, 8000); // milliseconds

        // Calculate points - 1 point per correct answer in multiplayer
        $points = $is_correct ? 1 : 0;

        // First check if score columns exist
        $columns = [];
        $result = $conn->query("PRAGMA table_info(multiplayer_games)");
        while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = $row['name'];
        }

        // Add missing score columns if needed
        for ($i = 1; $i <= 4; $i++) {
            $scoreCol = "player{$i}_score";
            if (!in_array($scoreCol, $columns)) {
                $conn->exec("ALTER TABLE multiplayer_games ADD COLUMN $scoreCol INTEGER DEFAULT 0");
            }
        }

        // Ensure all player score columns are properly initialized
        $stmt = $conn->prepare("
            UPDATE multiplayer_games 
            SET player1_score = COALESCE(player1_score, 0),
                player2_score = COALESCE(player2_score, 0),
                player3_score = COALESCE(player3_score, 0),
                player4_score = COALESCE(player4_score, 0)
            WHERE id = :game_id
        ");
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->execute();

        // Now update the bot's score (fixing double update issue)
        $stmt = $conn->prepare("
            UPDATE multiplayer_games 
            SET player{$player_slot}_score = COALESCE(player{$player_slot}_score, 0) + :points 
            WHERE id = :game_id
        ");
        $stmt->bindParam(':points', $points, PDO::PARAM_INT);
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->execute();

        // Removed duplicate update that was causing double scoring

        // Record the bot's answer
        $stmt = $conn->prepare("
            INSERT INTO multiplayer_answers 
            (multiplayer_game_id, turn_number, user_selection, is_correct) 
            VALUES (:game_id, :turn_number, :selection, :is_correct)
        ");
        $selection = $is_correct ? ($left_is_real ? 'left' : 'right') : ($left_is_real ? 'right' : 'left');
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->bindParam(':turn_number', $turn_number, PDO::PARAM_INT);
        $stmt->bindParam(':selection', $selection, PDO::PARAM_STR);
        $stmt->bindParam(':is_correct', $is_correct, PDO::PARAM_BOOL);
        $stmt->execute();

        return array(
            'success' => true,
            'bot_name' => $bot['bot_name'],
            'is_correct' => $is_correct,
            'points' => $points,
            'response_time' => $response_time
        );
    }
}

if (!function_exists('create_bonus_game')) {
    /**
     * Create a bonus game for a player who finished first
     * 
     * @param int $game_id The multiplayer game ID
     * @param int $user_id The user ID
     * @param string $username The username
     * @return array Result of the operation
     */
    function create_bonus_game($game_id, $user_id = null, $username = null) {
        $conn = get_db_connection();

        // Make sure either user_id or username is provided
        if ($user_id === null && $username === null) {
            return array('success' => false, 'message' => 'User ID or username is required');
        }

        // Check if the game exists
        $stmt = $conn->prepare("SELECT * FROM multiplayer_games WHERE id = :id");
        $stmt->bindParam(':id', $game_id, PDO::PARAM_INT);
        $stmt->execute();
        $game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$game) {
            return array('success' => false, 'message' => 'Game not found');
        }

        // Check if a bonus game already exists for this player
        $stmt = $conn->prepare("
            SELECT * FROM multiplayer_bonus_games 
            WHERE multiplayer_game_id = :game_id 
            AND (user_id = :user_id OR username = :username)
        ");
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->fetch()) {
            return array('success' => false, 'message' => 'Bonus game already exists for this player');
        }

        // Create a new bonus game
        $stmt = $conn->prepare("
            INSERT INTO multiplayer_bonus_games 
            (multiplayer_game_id, user_id, username) 
            VALUES (:game_id, :user_id, :username)
        ");
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $bonus_game_id = $conn->lastInsertId();
            return array(
                'success' => true,
                'message' => 'Bonus game created successfully',
                'bonus_game_id' => $bonus_game_id
            );
        } else {
            $errorInfo = $stmt->errorInfo();
            return array('success' => false, 'message' => 'Failed to create bonus game: ' . $errorInfo[2]);
        }
    }
}

if (!function_exists('complete_bonus_game')) {
    /**
     * Complete a bonus game and assign rewards
     * 
     * @param int $bonus_game_id The bonus game ID
     * @param int $score The score from image recognition part
     * @param int $chest_index The chosen chest index (0-3)
     * @return array Result of the operation
     */
    function complete_bonus_game($bonus_game_id, $score, $chest_index) {
        $conn = get_db_connection();

        // Check if the bonus game exists
        $stmt = $conn->prepare("SELECT * FROM multiplayer_bonus_games WHERE id = :id");
        $stmt->bindParam(':id', $bonus_game_id, PDO::PARAM_INT);
        $stmt->execute();
        $bonus_game = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$bonus_game) {
            return array('success' => false, 'message' => 'Bonus game not found');
        }

        if ($bonus_game['completed'] == 1) {
            return array('success' => false, 'message' => 'Bonus game already completed');
        }

        // Determine chest contents (1, 10, 25, 50 points)
        $chest_values = [1, 10, 25, 50];
        shuffle($chest_values); // Randomize the order

        $bonus_points = $chest_values[$chest_index];
        $total_score = $score + $bonus_points;

        // Save the chest selection and scores
        $stmt = $conn->prepare("
            UPDATE multiplayer_bonus_games 
            SET score = :score,
                chests_selected = :chests,
                bonus_points = :bonus_points,
                completed = 1,
                completed_at = :completed_at
            WHERE id = :id
        ");

        $chests_json = json_encode($chest_values);
        $current_time = date('Y-m-d H:i:s');

        $stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $stmt->bindParam(':chests', $chests_json, PDO::PARAM_STR);
        $stmt->bindParam(':bonus_points', $bonus_points, PDO::PARAM_INT);
        $stmt->bindParam(':completed_at', $current_time, PDO::PARAM_STR);
        $stmt->bindParam(':id', $bonus_game_id, PDO::PARAM_INT);

        if ($stmt->execute()) {
            // Update the player's score in the multiplayer_games table
            $user_id = $bonus_game['user_id'];
            $username = $bonus_game['username'];
            $game_id = $bonus_game['multiplayer_game_id'];

            // Find which player slot this user occupies
            $stmt = $conn->prepare("SELECT * FROM multiplayer_games WHERE id = :id");
            $stmt->bindParam(':id', $game_id, PDO::PARAM_INT);
            $stmt->execute();
            $game = $stmt->fetch(PDO::FETCH_ASSOC);

            $player_slot = 0;
            for ($i = 1; $i <= 4; $i++) {
                if (
                    ($user_id !== null && $game["player{$i}_id"] == $user_id) ||
                    ($username !== null && $game["player{$i}_name"] == $username)
                ) {
                    $player_slot = $i;
                    break;
                }
            }

            if ($player_slot > 0) {
                // Update the player's score
                $stmt = $conn->prepare("
                    UPDATE multiplayer_games 
                    SET player{$player_slot}_score = player{$player_slot}_score + :total_score 
                    WHERE id = :id
                ");
                $stmt->bindParam(':total_score', $total_score, PDO::PARAM_INT);
                $stmt->bindParam(':id', $game_id, PDO::PARAM_INT);
                $stmt->execute();
            }

            // Check for the "bonus_master" achievement if they got the max bonus
            if ($bonus_points == 50 && $user_id !== null) {
                unlock_achievement($user_id, 'bonus_master');
            }

            return array(
                'success' => true,
                'message' => 'Bonus game completed successfully',
                'score' => $score,
                'bonus_points' => $bonus_points,
                'total_score' => $total_score,
                'chest_values' => $chest_values
            );
        } else {
            $errorInfo = $stmt->errorInfo();
            return array('success' => false, 'message' => 'Failed to complete bonus game: ' . $errorInfo[2]);
        }
    }
}
/**
 * Handle the request to get multiplayer bonus game images
 * This function is called by the client to get the current state of the bonus game
 * 
 * NOTE: This function is already defined in game_actions.php
 * We are skipping its definition here to avoid conflicts
 */
// Function is defined in game_actions.php

/**
 * Handle the request to start a multiplayer bonus game
 * This function is called when the bonus game should begin
 * 
 * NOTE: This function is already defined in game_actions.php
 * We are skipping its definition here to avoid conflicts
 */
// Function is defined in game_actions.php

/**
 * Handle a player's chest selection in the multiplayer bonus game
 * This function is called when a player selects a chest
 * 
 * NOTE: This function is already defined in game_actions.php
 * We are skipping its definition here to avoid conflicts
 */
// Function is defined in game_actions.php

/**
 * Handle the completion of the multiplayer bonus game
 * This function is called when all players have made their selections
 * 
 * NOTE: This function is already defined in game_actions.php
 * We are skipping its definition here to avoid conflicts
 */
// Function is defined in game_actions.php

function update_multiplayer_game_player_score($game_id, $player_id, $score) {
    global $db;

    $game = get_multiplayer_game_by_id($game_id);
    if (!$game) {
        return false;
    }

    // Determine which player slot to update
    $score_field = null;
    $player_answered_field = null;

    if ($game['player1_id'] == $player_id) {
        $score_field = 'player1_score';
        $player_answered_field = 'player1_answered';
    } elseif ($game['player2_id'] == $player_id) {
        $score_field = 'player2_score';
        $player_answered_field = 'player2_answered';
    } elseif ($game['player3_id'] == $player_id) {
        $score_field = 'player3_score';
        $player_answered_field = 'player3_answered';
    } elseif ($game['player4_id'] == $player_id) {
        $score_field = 'player4_score';
        $player_answered_field = 'player4_answered';
    } else {
        return false; // Player not in game
    }

    // Update player score and mark as answered
    $stmt = $db->prepare("
        UPDATE multiplayer_games SET 
        $score_field = :score,
        $player_answered_field = 1
        WHERE id = :game_id
    ");

    $stmt->bindParam(':score', $score, PDO::PARAM_INT);
    $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);

    $result = $stmt->execute();

    // Check if all players have answered
    $game = get_multiplayer_game_by_id($game_id); // Get updated game state
    $all_answered = true;
    $player_count = 0;

    for ($i = 1; $i <= 4; $i++) {
        if (!empty($game["player{$i}_id"]) || !empty($game["player{$i}_name"])) {
            $player_count++;
            if (empty($game["player{$i}_answered"])) {
                $all_answered = false;
                break;
            }
        }
    }

    // If all players answered, increment turn and reset answered flags
    if ($all_answered && $player_count >= 2) {
        $stmt = $db->prepare("
            UPDATE multiplayer_games SET 
            current_turn = current_turn + 1,
            player1_answered = 0,
            player2_answered = 0,
            player3_answered = 0,
            player4_answered = 0
            WHERE id = :game_id
        ");
        $stmt->bindParam(':game_id', $game_id, PDO::PARAM_INT);
        $stmt->execute();
    }

    return $result;
}
?>