<?php
/**
 * Game functions for Real vs AI application
 * Handles game creation, state management, and related operations
 */

require_once 'database.php';
require_once 'image_functions.php';
require_once 'achievement_functions.php';

/**
 * Create a new single player game
 * 
 * @param string $game_mode Game mode (single, endless)
 * @param string $difficulty Game difficulty (easy, medium, hard)
 * @param int $user_id User ID (optional)
 * @return array|false Game data or false on failure
 */
if (!function_exists('create_single_player_game')) {
    function create_single_player_game($game_mode, $difficulty, $user_id = null) {
        global $db;

        // Validate inputs
        if (!in_array($game_mode, ['single', 'endless'])) {
            return false;
        }

        if ($game_mode === 'single' && !in_array($difficulty, ['easy', 'medium', 'hard'])) {
            return false;
        }

        // Determine game parameters based on mode and difficulty
        $total_turns = 0;
        $lives = 0;

        if ($game_mode === 'single') {
            switch ($difficulty) {
                case 'easy':
                    $total_turns = 20;
                    $lives = 5;
                    break;
                case 'medium':
                    $total_turns = 50;
                    $lives = 3;
                    break;
                case 'hard':
                    $total_turns = 100;
                    $lives = 1;
                    break;
            }
        } else {
            // Endless mode
            $total_turns = 0; // Unlimited
            $lives = 3;
        }

        // Generate unique session ID
        $session_id = generate_session_id();

        try {
            // Create new game in database
            $stmt = $db->prepare('
                INSERT INTO games 
                (session_id, game_mode, difficulty, current_turn, total_turns, 
                lives, starting_lives, score, completed, user_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $result = $stmt->execute([
                $session_id,
                $game_mode,
                $difficulty,
                1, // Current turn starts at 1
                $total_turns,
                $lives,
                $lives, // Starting lives for reference
                0, // Starting score
                0, // Not completed
                $user_id,
                date('Y-m-d H:i:s')
            ]);

            if (!$result) {
                return false;
            }

            // Return the created game
            return get_game($session_id);
        } catch (PDOException $e) {
            // Log error
            error_log('Error creating game: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Create a new multiplayer game
 * 
 * @param int $host_user_id Host user ID
 * @param string $host_username Host username
 * @param int $total_turns Total number of turns
 * @return array|false Game data or false on failure
 */
if (!function_exists('create_multiplayer_game')) {
    function create_multiplayer_game($is_public = true, $total_turns = 10, $play_anonymous = false) {
        global $db;
        
        // Start session if not already started
        session_start_if_not_started();
        
        // Get user information
        $host_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $host_username = isset($_SESSION['username']) ? $_SESSION['username'] : null;
        
        // If playing anonymously, create temporary username
        if ($play_anonymous || (empty($host_user_id) && empty($host_username))) {
            // Generate a random anonymous username
            $anon_id = generate_session_id(8);
            $host_username = "Anonymous" . $anon_id;
            $_SESSION['is_anonymous'] = true;
            $_SESSION['username'] = $host_username;
            $host_user_id = null; // No user_id for anonymous users
        }
        
        // Validate remaining inputs
        if (empty($host_username)) {
            return [
                'success' => false,
                'message' => 'Unable to create game: Missing player information'
            ];
        }

        if ($total_turns < 5 || $total_turns > 100) {
            $total_turns = 10; // Default to 10 if invalid
        }

        // Generate unique session ID and room code
        $session_id = generate_session_id();
        $room_code = generate_room_code();
        $current_time = date('Y-m-d H:i:s');
        $wait_timeout = date('Y-m-d H:i:s', strtotime($current_time) + 60); // 1 minute wait time

        try {
            // Ensure we have a database connection
            if (!$db) {
                $db = get_db_connection();
            }
            
            // Create new game in database
            $stmt = $db->prepare('
                INSERT INTO multiplayer_games 
                (session_id, room_code, is_public, current_turn, total_turns, completed, created_at,
                player1_id, player1_name, status, wait_timeout, has_bots)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $result = $stmt->execute([
                $session_id,
                $room_code,
                $is_public ? 1 : 0,
                1, // Current turn starts at 1
                $total_turns,
                0, // Not completed
                $current_time,
                $host_user_id,
                $host_username,
                'waiting', // Waiting for players
                $wait_timeout,
                0 // No bots initially
            ]);

            if (!$result) {
                return [
                    'success' => false,
                    'message' => 'Failed to create multiplayer game'
                ];
            }

            // Store mode-specific session ID
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION['game_session_id_multiplayer'] = $session_id;
                $_SESSION['game_session_id'] = $session_id; // For backward compatibility
                error_log("create_multiplayer_game - Set mode-specific multiplayer session ID: " . $session_id);
            }

            // Get the game data and format as a response array
            $game = get_multiplayer_game($session_id);
            if ($game) {
                return [
                    'success' => true,
                    'session_id' => $session_id,
                    'room_code' => $room_code,
                    'game' => $game
                ];
            }

            // Return the created game
            return [
                'success' => true,
                'session_id' => $session_id,
                'room_code' => $room_code
            ];
        } catch (PDOException $e) {
            // Log error
            error_log('Error creating multiplayer game: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred while creating game'
            ];
        }
    }
}

/**
 * Get a game by its session ID
 * 
 * @param string $session_id Game session ID
 * @return array|false Game data or false if not found
 */
if (!function_exists('get_game')) {
    function get_game($session_id) {
        global $db;

        if (empty($session_id)) {
            return false;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM games WHERE session_id = ?');
            $stmt->execute([$session_id]);

            $game = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$game) {
                return false;
            }

            // Parse shown images
            $game['shown_images'] = !empty($game['shown_images']) 
                ? explode(',', $game['shown_images']) 
                : [];

            return $game;
        } catch (PDOException $e) {
            error_log('Error getting game: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get the state of a single player game
 * 
 * @param string $session_id Game session ID
 * @return array Game state data
 */
if (!function_exists('get_single_player_game_state')) {
    function get_single_player_game_state($session_id) {
        // Get the game
        $game = get_game($session_id);

        if (!$game) {
            return [
                'status' => 'error',
                'message' => 'Game not found'
            ];
        }

        // Check if game is completed
        if ($game['completed']) {
            // Check if player still has lives
            if ($game['lives'] > 0) {
                // Game completed successfully
                return [
                    'status' => 'victory',
                    'game' => $game
                ];
            } else {
                // Game over
                return [
                    'status' => 'game_over',
                    'game' => $game
                ];
            }
        }

        // Get current image pair
        $image_pair = get_current_image_pair($game);

        if (!$image_pair) {
            return [
                'status' => 'error',
                'message' => 'Could not get image pair for current turn'
            ];
        }

        // Game is active, return active game state
        return [
            'status' => 'active',
            'game' => $game,
            'image_pair' => $image_pair,
            'turn' => $game['current_turn'],
            'lives' => $game['lives'],
            'score' => $game['score']
        ];
    }
}

/**
 * Get a multiplayer game by its session ID
 * 
 * @param string $session_id Game session ID
 * @return array|false Game data or false if not found
 */
if (!function_exists('get_multiplayer_game')) {
    function get_multiplayer_game($session_id) {
        global $db;

        if (empty($session_id)) {
            return false;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM multiplayer_games WHERE session_id = ?');
            $stmt->execute([$session_id]);

            $game = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$game) {
                return false;
            }

            // Parse shown images
            $game['shown_images'] = !empty($game['shown_images']) 
                ? explode(',', $game['shown_images']) 
                : [];

            return $game;
        } catch (PDOException $e) {
            error_log('Error getting multiplayer game: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get a completed game by its session ID
 * 
 * @param string $session_id Game session ID
 * @return array|false Game data or false if not found
 */
if (!function_exists('get_completed_game')) {
    function get_completed_game($session_id) {
        global $db;

        if (empty($session_id)) {
            return false;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM games WHERE session_id = ? AND completed = TRUE');
            $stmt->execute([$session_id]);

            $game = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$game) {
                return false;
            }

            // Parse shown images
            $game['shown_images'] = !empty($game['shown_images']) 
                ? explode(',', $game['shown_images']) 
                : [];

            return $game;
        } catch (PDOException $e) {
            error_log('Error getting completed game: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Update the current turn of a game
 * 
 * @param string $session_id Game session ID
 * @param array $shown_images List of shown image IDs
 * @return bool True on success, false on failure
 */
if (!function_exists('update_game_shown_images')) {
    function update_game_shown_images($session_id, $shown_images) {
        global $db;

        if (empty($session_id)) {
            return false;
        }

        $shown_images_str = implode(',', $shown_images);

        try {
            $stmt = $db->prepare('UPDATE games SET shown_images = ? WHERE session_id = ?');
            return $stmt->execute([$shown_images_str, $session_id]);
        } catch (PDOException $e) {
            error_log('Error updating game shown images: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Update multiplayer game shown images
 * 
 * @param string $session_id Game session ID
 * @param array $shown_images List of shown image IDs
 * @return bool True on success, false on failure
 */
if (!function_exists('update_multiplayer_game_shown_images')) {
    function update_multiplayer_game_shown_images($session_id, $shown_images) {
        global $db;

        if (empty($session_id)) {
            return false;
        }

        $shown_images_str = implode(',', $shown_images);

        try {
            $stmt = $db->prepare('UPDATE multiplayer_games SET shown_images = ? WHERE session_id = ?');
            return $stmt->execute([$shown_images_str, $session_id]);
        } catch (PDOException $e) {
            error_log('Error updating multiplayer game shown images: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Submit an answer for a single player game
 * 
 * @param string $session_id Game session ID
 * @param int $answer Answer (1 or 2)
 * @return array Result data
 */
function submit_single_player_answer($session_id, $answer) {
    global $db;

    if (empty($session_id) || !in_array($answer, [1, 2])) {
        return [
            'success' => false,
            'message' => 'Invalid input'
        ];
    }

    // Get current game
    $game = get_game($session_id);

    if (!$game) {
        return [
            'success' => false,
            'message' => 'Game not found'
        ];
    }

    if ($game['completed']) {
        return [
            'success' => false,
            'message' => 'Game is already completed'
        ];
    }

    // Get current turn images
    $current_turn = $game['current_turn'];
    $image_pair = get_current_image_pair($game);

    if (!$image_pair) {
        return [
            'success' => false,
            'message' => 'Could not get image pair for current turn'
        ];
    }

    // Determine which image the player chose (real or AI)
    $chosen_image = $answer === 1 ? $image_pair['image1'] : $image_pair['image2'];

    // Check if answer is correct (chose the real image)
    $is_correct = $chosen_image['type'] === 'real';

    // Log answer verification details for debugging
    error_log("Answer verification - Session: $session_id, Turn: $current_turn, Chosen: $answer, Chosen Type: {$chosen_image['type']}, Is Correct: " . ($is_correct ? 'Yes' : 'No'));

    // Update game data
    $new_score = $game['score'];
    $new_lives = $game['lives'];
    $streak = 0;

    try {
        // First, retrieve the current streak from answers
        $streak_stmt = $db->prepare('
            SELECT streak FROM game_answers 
            WHERE game_session_id = ? 
            ORDER BY id DESC LIMIT 1
        ');
        $streak_stmt->execute([$session_id]);
        $streak_result = $streak_stmt->fetch(PDO::FETCH_ASSOC);

        if ($streak_result) {
            $streak = $is_correct ? $streak_result['streak'] + 1 : 0;
        } else {
            $streak = $is_correct ? 1 : 0;
        }

        // Record answer
        $answer_stmt = $db->prepare('
            INSERT INTO game_answers 
            (game_session_id, turn_number, chosen_image_id, is_correct, streak, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $answer_stmt->execute([
            $session_id,
            $current_turn,
            $chosen_image['id'],
            $is_correct ? 1 : 0,
            $streak,
            date('Y-m-d H:i:s')
        ]);

        // Update game state
        if ($is_correct) {
            // Base score for correct answer
            $base_score = 10;

            // Calculate streak multiplier (doubles every 5 correct answers)
            $streak_multiplier = isset($game['current_streak']) ? pow(2, floor($game['current_streak'] / 5)) : 1;

            // Apply multiplier to base score
            $score_to_add = $base_score * $streak_multiplier;

            // Increment streak counter
            $game['current_streak'] = isset($game['current_streak']) ? $game['current_streak'] + 1 : 1;

            // Add to total score (ensure it's numeric)
            $game['score'] = (int)$game['score'] + $score_to_add;

            // Log score update
            error_log("Score update in submit_single_player_answer - Adding: $score_to_add, New total: {$game['score']}");

            // Initialize response array if not already set
            $response = [];
            
            // Add streak info to response
            $response['current_streak'] = $game['current_streak'];
            $response['streak_multiplier'] = $streak_multiplier;
            $response['points_earned'] = $score_to_add;
            $response['score'] = $game['score']; // Ensure score is in response
        } else {
            $game['lives']--;
            // Reset streak on wrong answer
            $game['current_streak'] = 0;
        }

        // Determine if game is over or continues
        $game_completed = false;
        $game_over = false;

        if ($new_lives <= 0) {
            // Game over - ran out of lives
            $game_completed = true;
            $game_over = true;
        } else if ($game['game_mode'] === 'single' && $current_turn >= $game['total_turns']) {
            // Game completed - reached final turn in single player
            $game_completed = true;
        }

        // Update game in database
        $next_turn = $current_turn + 1;

        $update_stmt = $db->prepare('
            UPDATE games 
            SET current_turn = ?, score = ?, lives = ?, completed = ? 
            WHERE session_id = ?
        ');

        $update_stmt->execute([
            $game_completed ? $current_turn : $next_turn,
            $game['score'],
            $game['lives'],
            $game_completed ? 1 : 0,
            $session_id
        ]);

        // Check for achievements if game is completed and player is logged in
        if ($game_completed && $game['user_id']) {
            $updated_game = get_game($session_id);
            $stats = get_game_stats($session_id);
            check_and_award_achievements($game['user_id'], $updated_game, $stats);
        }

        // Return result
        return [
            'success' => true,
            'is_correct' => $is_correct,
            'chosen_image' => $chosen_image['type'],
            'correct_image' => $is_correct ? null : ($image_pair['image1']['type'] === 'real' ? 1 : 2),
            'score' => $game['score'],
            'lives' => $game['lives'],
            'streak' => $streak,
            'next_turn' => $next_turn,
            'game_completed' => $game_completed,
            'game_over' => $game_over
        ];
    } catch (PDOException $e) {
        error_log('Error submitting answer: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error while submitting answer'
        ];
    }
}

/**
 * Submit an answer for a multiplayer game
 * 
 * @param string $session_id Game session ID
 * @param int $player_number Player number (1-4)
 * @param int $answer Answer (1 or 2)
 * @return array Result data
 */
function submit_multiplayer_answer($session_id, $player_number, $answer) {
    global $db;

    if (empty($session_id) || !in_array($player_number, [1, 2, 3, 4]) || !in_array($answer, [1, 2])) {
        return [
            'success' => false,
            'message' => 'Invalid input'
        ];
    }

    // Get current game
    $game = get_multiplayer_game($session_id);

    if (!$game) {
        return [
            'success' => false,
            'message' => 'Game not found'
        ];
    }

    if ($game['completed']) {
        return [
            'success' => false,
            'message' => 'Game is already completed'
        ];
    }

    // Check if status is "in_progress"
    if ($game['status'] !== 'in_progress') {
        return [
            'success' => false,
            'message' => 'Game is not in progress'
        ];
    }

    // Get current turn images
    $current_turn = $game['current_turn'];
    $image_pair = get_current_image_pair_multiplayer($game);

    if (!$image_pair) {
        return [
            'success' => false,
            'message' => 'Could not get image pair for current turn'
        ];
    }

    // Determine which image the player chose (real or AI)
    $chosen_image = $answer === 1 ? $image_pair['image1'] : $image_pair['image2'];

    // Check if answer is correct (chose the real image)
    $is_correct = $chosen_image['type'] === 'real';

    // Update player score
    $score_column = "player{$player_number}_score";
    $has_answered_column = "player{$player_number}_answered";
    $new_score = $game[$score_column] + ($is_correct ? 10 : 0);

    try {
        // Record answer and update player score
        $answer_stmt = $db->prepare('
            INSERT INTO multiplayer_game_answers 
            (game_session_id, turn_number, player_number, chosen_image_id, is_correct, created_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $answer_stmt->execute([
            $session_id,
            $current_turn,
            $player_number,
            $chosen_image['id'],
            $is_correct ? 1 : 0,
            date('Y-m-d H:i:s')
        ]);

        // Update player's score and mark as answered
        $update_player_stmt = $db->prepare("
            UPDATE multiplayer_games 
            SET {$score_column} = ?, {$has_answered_column} = 1 
            WHERE session_id = ?
        ");

        $update_player_stmt->execute([
            $new_score,
            $session_id
        ]);

        // Check if all active players have answered
        $player_count = $game['player_count'];
        $all_answered = true;

        for ($i = 1; $i <= $player_count; $i++) {
            $answered_column = "player{$i}_answered";
            if (!$game[$answered_column]) {
                $all_answered = false;
                break;
            }
        }

        // If all players have answered, move to next turn
        if ($all_answered) {
            $next_turn = $current_turn + 1;
            $game_completed = $next_turn > $game['total_turns'];

            // Reset all "answered" flags
            $reset_columns = [];
            $params = [];

            for ($i = 1; $i <= 4; $i++) {
                $reset_columns[] = "player{$i}_answered = 0";
            }

            $reset_sql = implode(', ', $reset_columns);

            // Update game state
            $update_game_stmt = $db->prepare("
                UPDATE multiplayer_games 
                SET current_turn = ?, completed = ?, {$reset_sql} 
                WHERE session_id = ?
            ");

            $update_game_stmt->execute([
                $next_turn,
                $game_completed ? 1 : 0,
                $session_id
            ]);

            // Check for multiplayer win achievement if game is completed
            if ($game_completed) {
                // Get the winning player(s)
                $winners = [];
                $max_score = 0;

                for ($i = 1; $i <= $player_count; $i++) {
                    $player_id_column = "player{$i}_id";
                    $player_score_column = "player{$i}_score";

                    if (!empty($game[$player_id_column])) {
                        $score = $i === $player_number ? $new_score : $game[$player_score_column];

                        if ($score > $max_score) {
                            $max_score = $score;
                            $winners = [$game[$player_id_column]];
                        } else if ($score === $max_score) {
                            $winners[] = $game[$player_id_column];
                        }
                    }
                }

                // Award achievement to winner(s)
                foreach ($winners as $winner_id) {
                    if ($player_count > 1) {
                        award_achievement($winner_id, 'win_multiplayer');
                    }
                }
            }
        }

        // Get current turn data for response
        $has_next = !$game_completed && $current_turn < $game['total_turns'];

        // Return result
        return [
            'success' => true,
            'is_correct' => $is_correct,
            'chosen_image' => $chosen_image['type'],
            'correct_image' => $is_correct ? null : ($image_pair['image1']['type'] === 'real' ? 1 : 2),
            'score' => $new_score,
            'all_answered' => $all_answered,
            'has_next' => $has_next,
            'game_completed' => $game_completed
        ];
    } catch (PDOException $e) {
        error_log('Error submitting multiplayer answer: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Database error while submitting answer'
        ];
    }
}

/**
 * Get current image pair for a single player game
 * 
 * @param array $game Game data
 * @return array|false Image pair data or false on failure
 */
function get_current_image_pair($game) {
    if (empty($game) || empty($game['session_id'])) {
        return false;
    }

    // Get one real and one AI image that haven't been shown before
    $shown_images = $game['shown_images'] ?? [];
    $real_image = get_random_images('real', 1, $shown_images);
    $ai_image = get_random_images('ai', 1, $shown_images);

    // Make sure we're actually getting one real and one AI image
    if (empty($real_image) || empty($ai_image)) {
        error_log("Warning: Unable to get both real and AI images for the game");
        return false;
    }

    $image_pair = [
        'image1' => random_int(0, 1) == 1 ? $real_image[0] : $ai_image[0],
        'image2' => random_int(0, 1) == 1 ? $ai_image[0] : $real_image[0]
    ];

    // Add these images to the "shown" list
    $shown_images = $game['shown_images'];
    $shown_images[] = $image_pair['image1']['id'];
    $shown_images[] = $image_pair['image2']['id'];

    // Update the game's shown images
    update_game_shown_images($game['session_id'], $shown_images);

    return $image_pair;
}

/**
 * Get current image pair for a multiplayer game
 * 
 * @param array $game Game data
 * @return array|false Image pair data or false on failure
 */
function get_current_image_pair_multiplayer($game) {
    if (empty($game) || empty($game['session_id'])) {
        return false;
    }

    // Get one real and one AI image that haven't been shown before
    $shown_images = $game['shown_images'] ?? [];
    $real_image = get_random_images('real', 1, $shown_images);
    $ai_image = get_random_images('ai', 1, $shown_images);

    // Make sure we're actually getting one real and one AI image
    if (empty($real_image) || empty($ai_image)) {
        error_log("Warning: Unable to get both real and AI images for the game");
        return false;
    }

    // Randomly determine order (left/right)
    $left_is_real = random_int(0, 1) == 1;

    $image_pair = [
        'image1' => $left_is_real ? $real_image[0] : $ai_image[0],
        'image2' => $left_is_real ? $ai_image[0] : $real_image[0]
    ];

    // Add these images to the "shown" list
    $shown_images = $game['shown_images'];
    $shown_images[] = $image_pair['image1']['id'];
    $shown_images[] = $image_pair['image2']['id'];

    // Update the game's shown images
    update_multiplayer_game_shown_images($game['session_id'], $shown_images);

    return $image_pair;
}

/**
 * Join a multiplayer game
 * 
 * @param string $session_id Game session ID
 * @param int $user_id User ID
 * @param string $username Username
 * @return array|false Game data or false on failure
 */
function join_multiplayer_game($session_id, $user_id, $username) {
    global $db;

    if (empty($session_id) || empty($username)) {
        return false;
    }

    // Get game
    $game = get_multiplayer_game($session_id);

    if (!$game) {
        return false;
    }

    // Check if game is waiting for players
    if ($game['status'] !== 'waiting') {
        return false;
    }

    // Find an empty player slot
    $player_number = 0;

    for ($i = 2; $i <= 4; $i++) {
        $player_id_column = "player{$i}_id";

        if (empty($game[$player_id_column])) {
            $player_number = $i;
            break;
        }
    }

    if ($player_number === 0) {
        return false; // No empty slots
    }

    try {
        // Update player in database
        $player_id_column = "player{$player_number}_id";
        $player_name_column = "player{$player_number}_name";

        $stmt = $db->prepare("
            UPDATE multiplayer_games 
            SET {$player_id_column} = ?, {$player_name_column} = ?, player_count = player_count + 1 
            WHERE session_id = ?
        ");

        $result = $stmt->execute([
            $user_id,
            $username,
            $session_id
        ]);

        if (!$result) {
            return false;
        }

        // If at least 2 players have joined, set status to ready
        if ($game['player_count'] + 1 >= 2) {
            $stmt = $db->prepare("
                UPDATE multiplayer_games 
                SET status = 'ready' 
                WHERE session_id = ?
            ");

            $stmt->execute([$session_id]);
        }

        // Return updated game
        return get_multiplayer_game($session_id);
    } catch (PDOException $e) {
        error_log('Error joining multiplayer game: ' . $e->getMessage());
        return false;
    }
}

/**
 * Start a multiplayer game
 * 
 * @param string $session_id Game session ID
 * @return bool True on success, false on failure
 */
function start_multiplayer_game($session_id) {
    global $db;

    if (empty($session_id)) {
        return false;
    }

    // Get game
    $game = get_multiplayer_game($session_id);

    if (!$game) {
        return false;
    }

    // Check if game is ready to start
    if ($game['status'] !== 'ready') {
        return false;
    }

    try {
        // Update game status
        $stmt = $db->prepare("
            UPDATE multiplayer_games 
            SET status = 'in_progress' 
            WHERE session_id = ?
        ");

        return $stmt->execute([$session_id]);
    } catch (PDOException $e) {
        error_log('Error starting multiplayer game: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get game statistics
 * 
 * @param string $session_id Game session ID
 * @return array Game statistics
 */
function get_game_stats($session_id) {
    global $db;

    if (empty($session_id)) {
        return [
            'correct_answers' => 0,
            'incorrect_answers' => 0,
            'accuracy' => 0,
            'best_streak' => 0
        ];
    }

    try {
        // Get correct and incorrect answers
        $stats_stmt = $db->prepare('
            SELECT 
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_answers,
                SUM(CASE WHEN is_correct = 0 THEN 1 ELSE 0 END) as incorrect_answers,
                MAX(streak) as best_streak
            FROM game_answers 
            WHERE game_session_id = ?
        ');

        $stats_stmt->execute([$session_id]);
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$stats) {
            return [
                'correct_answers' => 0,
                'incorrect_answers' => 0,
                'accuracy' => 0,
                'best_streak' => 0
            ];        }

        // Calculate accuracy
        $total_answers = $stats['correct_answers'] + $stats['incorrect_answers'];
        $accuracy = $total_answers > 0 ? round(($stats['correct_answers'] / $total_answers) * 100) : 0;

        return [
            'correct_answers' => (int) $stats['correct_answers'],            'incorrect_answers' => (int) $stats['incorrect_answers'],
            'accuracy' => $accuracy,
            'best_streak' => (int) $stats['best_streak']
        ];
    } catch (PDOException $e) {
        error_log('Error getting game stats: ' . $e->getMessage());
        return [
            'correct_answers' => 0,
            'incorrect_answers' => 0,
            'accuracy' => 0,
            'best_streak' => 0
        ];
    }
}

/**
 * Submit a score to the leaderboard
 * 
 * @param array $data Score data
 * @return bool True on success, false on failure
 */
function submit_score($data) {
    global $db;

    if (empty($data['session_id']) || empty($data['score']) || empty($data['game_mode'])) {
        return false;
    }

    // Validate initials if not logged in
    if (empty($data['user_id']) && (empty($data['initials']) || strlen($data['initials']) > 3)) {
        return false;
    }

    try {
        // Insert score into leaderboard
        $stmt = $db->prepare('
            INSERT INTO leaderboard_entries 
            (user_id, initials, email, score, game_mode, difficulty, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');

        $result = $stmt->execute([
            $data['user_id'] ?? null,
            $data['user_id'] ? substr($data['username'], 0, 3) : $data['initials'],
            $data['email'] ?? null,
            $data['score'],
            $data['game_mode'],
            $data['difficulty'] ?? null,
            date('Y-m-d H:i:s')
        ]);

        if (!$result) {
            return false;
        }

        // Mark game as having score submitted
        $update_stmt = $db->prepare('
            UPDATE games 
            SET score_submitted = 1 
            WHERE session_id = ?
        ');

        return $update_stmt->execute([$data['session_id']]);
    } catch (PDOException $e) {
        error_log('Error submitting score: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get user total games
 * 
 * @param int $user_id User ID
 * @return int Total games played
 */
if (!function_exists('get_user_total_games')) {
    function get_user_total_games($user_id) {
        global $db;

        if (empty($user_id)) {
            return 0;
        }

        try {
            $stmt = $db->prepare('SELECT COUNT(*) FROM games WHERE user_id = ?');
            $stmt->execute([$user_id]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error getting user total games: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Get user highest score
 * 
 * @param int $user_id User ID
 * @return int Highest score
 */
if (!function_exists('get_user_highest_score')) {
    function get_user_highest_score($user_id) {
        global $db;

        if (empty($user_id)) {
            return 0;
        }

        try {
            $stmt = $db->prepare('SELECT MAX(score) FROM games WHERE user_id = ?');
            $stmt->execute([$user_id]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error getting user highest score: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Get user games by mode
 * 
 * @param int $user_id User ID
 * @return array Games by mode
 */
if (!function_exists('get_user_games_by_mode')) {
    function get_user_games_by_mode($user_id) {
        global $db;

        if (empty($user_id)) {
            return [];
        }

        try {
            $stmt = $db->prepare('
                SELECT 
                    CASE 
                        WHEN game_mode = "single" THEN difficulty 
                        ELSE game_mode 
                    END AS mode,
                    COUNT(*) as count
                FROM games 
                WHERE user_id = ? 
                GROUP BY mode
            ');

            $stmt->execute([$user_id]);

            $results = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Initialize with all modes
            $modes = [
                'easy' => 0,
                'medium' => 0,
                'hard' => 0,
                'endless' => 0,
                'multiplayer' => 0
            ];

            // Update with actual counts
            foreach ($results as $mode => $count) {
                $modes[$mode] = (int) $count;
            }

            return $modes;
        } catch (PDOException $e) {
            error_log('Error getting user games by mode: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get user recent games
 * 
 * @param int $user_id User ID
 * @param int $limit Number of games to return
 * @return array Recent games
 */
if (!function_exists('get_user_recent_games')) {
    function get_user_recent_games($user_id, $limit = 5) {
        global $db;

        if (empty($user_id)) {
            return [];
        }

        try {
            $stmt = $db->prepare('
                SELECT * FROM games 
                WHERE user_id = ? 
                ORDER BY created_at DESC 
                LIMIT ?
            ');

            $stmt->execute([$user_id, $limit]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting user recent games: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get user total correct answers
 * 
 * @param int $user_id User ID
 * @return int Total correct answers
 */
if (!function_exists('get_user_total_correct_answers')) {
    function get_user_total_correct_answers($user_id) {
        global $db;

        if (empty($user_id)) {
            return 0;
        }

        try {
            $stmt = $db->prepare('
                SELECT COUNT(*) 
                FROM game_answers ga
                JOIN games g ON ga.game_session_id = g.session_id
                WHERE g.user_id = ? AND ga.is_correct = 1
            ');

            $stmt->execute([$user_id]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error getting user total correct answers: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Get user total incorrect answers
 * 
 * @param int $user_id User ID
 * @return int Total incorrect answers
 */
if (!function_exists('get_user_total_incorrect_answers')) {
    function get_user_total_incorrect_answers($user_id) {
        global $db;

        if (empty($user_id)) {
            return 0;
        }

        try {
            $stmt = $db->prepare('
                SELECT COUNT(*) 
                FROM game_answers ga
                JOIN games g ON ga.game_session_id = g.session_id
                WHERE g.user_id = ? AND ga.is_correct = 0
            ');

            $stmt->execute([$user_id]);

            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error getting user total incorrect answers: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Generate a random session ID
 * 
 * @param int $length Length of the session ID
 * @return string Random session ID
 */
if (!function_exists('generate_session_id')) {
    function generate_session_id($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $session_id = '';

        for ($i = 0; $i < $length; $i++) {
            $session_id .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $session_id;
    }
}
?>