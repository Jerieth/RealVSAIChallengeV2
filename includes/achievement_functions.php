<?php
/**
 * Achievement functions for Real vs AI application
 * Handles achievement unlocking, checking, and related operations
 */

require_once 'database.php';

/**
 * Get user's unviewed achievements 
 * 
 * @param int $user_id The user ID
 * @return array Array of unviewed achievement data
 */
function get_user_unviewed_achievements($user_id) {
    if (empty($user_id)) {
        return [];
    }
    
    try {
        $db = get_db_connection();
        
        // Get most recent achievements for the user from user_achievements joined with achievements
        $stmt = $db->prepare("
            SELECT 
                a.id,
                a.criteria AS achievement_type,
                a.name AS title,
                a.description,
                a.badge_icon AS icon,
                ua.unlocked_at AS created_at
            FROM user_achievements ua
            JOIN achievements a ON ua.achievement_id = a.id
            WHERE ua.user_id = ?
            ORDER BY ua.unlocked_at DESC
            LIMIT 5
        ");
        
        $stmt->execute([$user_id]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($result)) {
            return [];
        }
        
        // Format achievements for display
        $achievements = [];
        foreach ($result as $achievement) {
            $achievements[] = [
                'type' => $achievement['achievement_type'],
                'title' => $achievement['title'],
                'description' => $achievement['description'],
                'icon' => $achievement['icon']
            ];
        }
        
        return $achievements;
    } catch (Exception $e) {
        error_log("Error getting unviewed achievements: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark achievements as viewed by user
 * 
 * @param int $user_id The user ID
 * @param array $achievement_types Array of achievement types to mark as viewed
 * @return bool Success status
 */
function mark_achievements_viewed($user_id, $achievement_types) {
    if (empty($user_id) || empty($achievement_types)) {
        return false;
    }
    
    // We don't actually need to mark them as viewed in the database yet
    // Just log that they were viewed for now
    error_log("User $user_id viewed achievements: " . implode(', ', $achievement_types));
    return true;
}

// Only define these functions if they haven't been defined already
if (!function_exists('get_achievement_by_criteria')) {
    /**
     * Get all achievement IDs by criteria
     * 
     * @param string $criteria Criteria type
     * @param int $threshold Threshold value
     * @return int|bool Achievement ID or false if not found
     */
    function get_achievement_by_criteria($criteria, $threshold) {
        // This function is deprecated. Use award_achievement_model directly
        // with the appropriate achievement type from the available types
        error_log("get_achievement_by_criteria is deprecated, use award_achievement_model directly");
        return false;
    }
}

if (!function_exists('has_achievement')) {
    /**
     * Check if user has a specific achievement
     * 
     * @param int $user_id User ID
     * @param int $achievement_id Achievement ID
     * @return bool True if user has the achievement, false otherwise
     */
    function has_achievement($user_id, $achievement_type) {
        if (empty($user_id) || empty($achievement_type)) {
            return false;
        }

        // Get achievement ID from criteria, then check if user has it in user_achievements table
        $sql = "
            SELECT COUNT(*) 
            FROM user_achievements ua
            JOIN achievements a ON ua.achievement_id = a.id
            WHERE ua.user_id = ? 
            AND a.criteria = ?
        ";
        return db_query_value($sql, [$user_id, $achievement_type]) > 0;
    }
}

if (!function_exists('award_achievement')) {
    /**
     * Award an achievement to a user
     * 
     * @param int $user_id User ID
     * @param int $achievement_id Achievement ID
     * @return bool True if achievement was awarded, false otherwise
     */
    function award_achievement($user_id, $achievement_type) {
        // This function is now deprecated, use award_achievement_model instead
        return award_achievement_model($user_id, $achievement_type);
    }
}

if (!function_exists('get_user_achievements')) {
    /**
     * Get all achievements for a user
     * 
     * @param int $user_id User ID
     * @return array List of user's achievements
     */
    function get_user_achievements($user_id) {
        if (empty($user_id)) {
            return [];
        }

        try {
            // Get achievements with their earned date from user_achievements table
            $sql = "
                SELECT a.*, ua.unlocked_at 
                FROM achievements a
                JOIN user_achievements ua ON a.id = ua.achievement_id
                WHERE ua.user_id = ? 
                ORDER BY ua.unlocked_at DESC
            ";
            
            $achievements = db_query($sql, [$user_id]);
            
            // Add debugging info
            error_log("get_user_achievements - Found " . count($achievements) . " unique achievements for user $user_id");
            
            return $achievements;
        } catch (Exception $e) {
            // Log error
            error_log('Error getting user achievements: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('check_and_award_achievements')) {
    /**
     * Check and award achievements based on game results
     * 
     * @param int $user_id User ID
     * @param array $game Game data
     * @param array $stats Game statistics
     * @return array List of newly awarded achievements (names)
     */
    function check_and_award_achievements($user_id, $game = null, $stats = null) {
        if (empty($user_id)) {
            return [];
        }

        $awarded = [];

        // Get total games played
        $sql = "SELECT COUNT(*) FROM games WHERE user_id = ?";
        $total_games = db_query_value($sql, [$user_id]);

        // Check game count achievements - using direct award_achievement_model function
        if ($total_games >= 100) {
            if (award_achievement_model($user_id, 'games_100')) {
                $awarded[] = "games_100";
            }
        }
        if ($total_games >= 50) {
            if (award_achievement_model($user_id, 'games_50')) {
                $awarded[] = "games_50";
            }
        }
        if ($total_games >= 10) {
            if (award_achievement_model($user_id, 'games_10')) {
                $awarded[] = "games_10";
            }
        }
        
        // For backward compatibility also check play_games_count achievements
        if ($total_games >= 100) {
            if (award_achievement_model($user_id, 'play_games_count_100')) {
                $awarded[] = "play_games_count_100";
            }
        }
        if ($total_games >= 50) {
            if (award_achievement_model($user_id, 'play_games_count_50')) {
                $awarded[] = "play_games_count_50";
            }
        }
        if ($total_games >= 10) {
            if (award_achievement_model($user_id, 'play_games_count_10')) {
                $awarded[] = "play_games_count_10";
            }
        }

        // Check for Active Player achievement
        check_active_player_achievement($user_id, $awarded);

        // Check for Over Achiever achievement
        check_over_achiever_achievement($user_id, $awarded);

        // If no game data is provided, just return achievements checked so far
        if (empty($game)) {
            return $awarded;
        }

        // Game completion achievements - difficulty levels
        // Try multiple methods to award difficulty-based achievements
        
        // First, use direct parameters from game data if available
        if (isset($game['difficulty']) && isset($game['completed']) && $game['completed']) {
            $difficulty = strtolower($game['difficulty']);
            $is_single_player = isset($game['game_mode']) && strtolower($game['game_mode']) === 'single';
            
            if ($is_single_player) {
                error_log("Achievement check: Checking single player completion for difficulty: $difficulty");
                
                switch ($difficulty) {
                    case 'easy':
                        if (award_achievement_model($user_id, 'complete_easy')) {
                            $awarded[] = "complete_easy";
                            error_log("Achievement awarded: complete_easy (Beginner Detective)");
                        }
                        break;
                    case 'medium':
                        if (award_achievement_model($user_id, 'complete_medium')) {
                            $awarded[] = "complete_medium";
                            error_log("Achievement awarded: complete_medium (Skilled Investigator)");
                        }
                        break;
                    case 'hard':
                        if (award_achievement_model($user_id, 'complete_hard')) {
                            $awarded[] = "complete_hard";
                            error_log("Achievement awarded: complete_hard (Master Analyst)");
                        }
                        break;
                }
            } else {
                error_log("Achievement check: Not a single player game, skipping difficulty achievements");
            }
        }
        
        // Second method: Look at the database for each difficulty level
        $db = get_db_connection();
        
        // Easy difficulty
        $stmt = $db->prepare("
            SELECT 1 
            FROM games 
            WHERE user_id = ? 
            AND game_mode = 'single' 
            AND difficulty = 'easy' 
            AND completed = 1 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            error_log("Achievement check: Found completed easy game in database");
            if (award_achievement_model($user_id, 'complete_easy')) {
                $awarded[] = "complete_easy";
                error_log("Achievement awarded: complete_easy (Beginner Detective) via database check");
            }
        }
        
        // Medium difficulty
        $stmt = $db->prepare("
            SELECT 1 
            FROM games 
            WHERE user_id = ? 
            AND game_mode = 'single' 
            AND difficulty = 'medium' 
            AND completed = 1 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            error_log("Achievement check: Found completed medium game in database");
            if (award_achievement_model($user_id, 'complete_medium')) {
                $awarded[] = "complete_medium";
                error_log("Achievement awarded: complete_medium (Skilled Investigator) via database check");
            }
        }
        
        // Hard difficulty
        $stmt = $db->prepare("
            SELECT 1 
            FROM games 
            WHERE user_id = ? 
            AND game_mode = 'single' 
            AND difficulty = 'hard' 
            AND completed = 1 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        if ($stmt->rowCount() > 0) {
            error_log("Achievement check: Found completed hard game in database");
            if (award_achievement_model($user_id, 'complete_hard')) {
                $awarded[] = "complete_hard";
                error_log("Achievement awarded: complete_hard (Master Analyst) via database check");
            }
        }

        // Score-based achievements
        $score = isset($game['score']) ? (int)$game['score'] : 0;

        if ($score >= 200) {
            if (award_achievement_model($user_id, 'reach_score_200')) {
                $awarded[] = "reach_score_200";
            }
        } else if ($score >= 100) {
            if (award_achievement_model($user_id, 'reach_score_100')) {
                $awarded[] = "reach_score_100";
            }
        } else if ($score >= 50) {
            if (award_achievement_model($user_id, 'reach_score_50')) {
                $awarded[] = "reach_score_50";
            }
        } else if ($score >= 20) {
            if (award_achievement_model($user_id, 'reach_score_20')) {
                $awarded[] = "reach_score_20";
            }
        }

        // Bonus game completion achievement
        if (isset($game['bonus_game_completed']) && $game['bonus_game_completed']) {
            if (award_achievement_model($user_id, 'bonus_game_win')) {
                $awarded[] = "bonus_game_win";
            }
        }

        // Multiplayer win achievement
        if (isset($game['game_mode']) && $game['game_mode'] === 'multiplayer' && isset($game['winner']) && $game['winner'] == $user_id) {
            if (award_achievement_model($user_id, 'win_multiplayer')) {
                $awarded[] = "win_multiplayer";
            }
        }

        // Perfect score achievement - use multiple methods to check for no lives lost
        $perfect_score = false;
        
        // Method 1: Check direct parameters from game state
        if (isset($game['completed']) && $game['completed'] && 
            isset($game['lives_remaining']) && isset($game['lives_total']) && 
            $game['lives_total'] > 0 && $game['lives_remaining'] == $game['lives_total']) {
            $perfect_score = true;
            error_log("Achievement check: Perfect score detected via direct parameters: lives_remaining={$game['lives_remaining']}, lives_total={$game['lives_total']}");
        }
        
        // Method 2: Check if we have a session_id to look up the game
        else if (isset($game['session_id']) && !empty($game['session_id'])) {
            $db = get_db_connection();
            $stmt = $db->prepare("
                SELECT lives, starting_lives
                FROM games 
                WHERE session_id = ? AND completed = 1
                LIMIT 1
            ");
            $stmt->execute([$game['session_id']]);
            $game_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($game_data && $game_data['lives'] == $game_data['starting_lives'] && $game_data['starting_lives'] > 0) {
                $perfect_score = true;
                error_log("Achievement check: Perfect score detected via session_id lookup: lives={$game_data['lives']}, starting_lives={$game_data['starting_lives']}");
            }
        }
        
        // Method 3: Direct database check for any game with perfect score
        if (!$perfect_score) {
            $db = get_db_connection();
            $stmt = $db->prepare("
                SELECT 1
                FROM games 
                WHERE user_id = ? AND completed = 1 AND lives = starting_lives AND starting_lives > 0
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            
            if ($stmt->rowCount() > 0) {
                $perfect_score = true;
                error_log("Achievement check: Perfect score detected via direct database query");
            }
        }
        
        // Award the achievement if any method succeeded
        if ($perfect_score) {
            if (award_achievement_model($user_id, 'perfect_score')) {
                $awarded[] = "perfect_score";
                error_log("Achievement awarded: perfect_score (Flawless Victory)");
            }
        }

        // Tutorial completion achievement
        if (isset($game['game_mode']) && $game['game_mode'] === 'tutorial' && isset($game['completed']) && $game['completed']) {
            if (award_achievement_model($user_id, 'complete_tutorial')) {
                $awarded[] = "complete_tutorial";
            }
        }

        return $awarded;
    }
}

if (!function_exists('check_active_player_achievement')) {
    /**
     * Check and award the Active Player achievement
     * 
     * @param int $user_id User ID
     * @param array &$awarded Array of awarded achievements
     * @return bool True if achievement was awarded, false otherwise
     */
    function check_active_player_achievement($user_id, &$awarded) {
        require_once __DIR__ . '/../models/User.php';
        require_once __DIR__ . '/../models/Achievement.php';

        // Get login count
        $login_count = get_user_login_count($user_id);

        // Get game count
        $game_count = get_user_total_games($user_id);

        // Check if login count >= 3 and game count >= 2
        if ($login_count >= 3 && $game_count >= 2) {
            if (award_achievement_model($user_id, 'active_player')) {
                $awarded[] = "active_player";
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('check_over_achiever_achievement')) {
    /**
     * Check and award the Over Achiever achievement
     * 
     * @param int $user_id User ID
     * @param array &$awarded Array of awarded achievements
     * @return bool True if achievement was awarded, false otherwise
     */
    function check_over_achiever_achievement($user_id, &$awarded) {
        require_once __DIR__ . '/../models/Achievement.php';

        // Get all available achievements
        $available_achievements = get_available_achievements();
        unset($available_achievements['over_achiever']);

        // Get user's earned achievements - join with achievements table to get types
        $db = get_db_connection();
        $stmt = $db->prepare("
            SELECT a.criteria AS achievement_type 
            FROM user_achievements ua
            JOIN achievements a ON ua.achievement_id = a.id
            WHERE ua.user_id = :user_id
        ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $earned_achievements = array_map(function($row) {
            return $row['achievement_type'];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));

        // Check if user has all other achievements
        $all_earned = true;
        foreach (array_keys($available_achievements) as $achievement_type) {
            if (!in_array($achievement_type, $earned_achievements)) {
                $all_earned = false;
                break;
            }
        }

        // If all other achievements earned, award the over_achiever achievement
        if ($all_earned) {
            if (award_achievement_model($user_id, 'over_achiever')) {
                $awarded[] = "over_achiever";
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('get_achievement_counts')) {
    /**
     * Get achievement counts by category for a user
     * 
     * @param int $user_id User ID
     * @return array Counts of achievements by category
     */
    function get_achievement_counts($user_id) {
        try {
            $db = get_db_connection();
            
            // Get all unique achievement types in the system
            $sql = "SELECT COUNT(DISTINCT criteria) FROM achievements";
            $possible = db_query_value($sql, []);
            
            // Default values
            $total = 0;
            $categories = [
                'gameplay' => 0,
                'score' => 0,
                'completion' => 0,
                'general' => 0,
                'multiplayer' => 0,
                'skill' => 0,
                'learning' => 0,
                'meta' => 0,
                'daily' => 0
            ];
            $category_totals = [];
            
            // Get total earned achievements for this user from user_achievements table
            $sql = "
                SELECT COUNT(DISTINCT a.id) 
                FROM user_achievements ua
                JOIN achievements a ON ua.achievement_id = a.id
                WHERE ua.user_id = ?
            ";
            $total = db_query_value($sql, [$user_id]);
            
            // Check if category column exists in achievements table
            $stmt = $db->prepare("PRAGMA table_info(achievements)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $has_category = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'category') {
                    $has_category = true;
                    break;
                }
            }
            
            if ($has_category) {
                // Count earned achievements by category (from user_achievements join with achievements)
                $sql = "
                    SELECT a.category, COUNT(DISTINCT a.id) as count 
                    FROM user_achievements ua
                    JOIN achievements a ON ua.achievement_id = a.id
                    WHERE ua.user_id = ? 
                    GROUP BY a.category";
                
                $earned_by_category = db_query($sql, [$user_id]);
                
                foreach ($earned_by_category as $row) {
                    if (isset($categories[$row['category']])) {
                        $categories[$row['category']] = $row['count'];
                    }
                }
                
                // Get total possible achievements by category
                $sql = "SELECT category, COUNT(DISTINCT id) as count 
                        FROM achievements 
                        GROUP BY category";
                $category_counts = db_query($sql, []);
                
                foreach ($category_counts as $row) {
                    if (isset($categories[$row['category']])) {
                        $category_totals[$row['category']] = $row['count'];
                    }
                }
            } else {
                // Category column doesn't exist, set all category totals to 0
                foreach (array_keys($categories) as $category) {
                    $category_totals[$category] = 0;
                }
            }
            
            return [
                'total' => $total,
                'possible' => $possible,
                'percentage' => $possible > 0 ? round(($total / $possible) * 100) : 0,
                'categories' => $categories,
                'category_totals' => $category_totals
            ];
        } catch (Exception $e) {
            error_log("Error getting achievement counts: " . $e->getMessage());
            
            // Return fallback values
            return [
                'total' => 0,
                'possible' => 0,
                'percentage' => 0,
                'categories' => [
                    'gameplay' => 0,
                    'score' => 0,
                    'social' => 0
                ],
                'category_totals' => [
                    'gameplay' => 0,
                    'score' => 0,
                    'social' => 0
                ]
            ];
        }
    }
}

if (!function_exists('get_achievement_badge_url')) {
    /**
     * Get achievement badge URL
     * 
     * @param string $badge_icon Badge icon filename
     * @return string Full URL to badge image
     */
    function get_achievement_badge_url($badge_icon) {
        return '/static/images/badges/' . $badge_icon;
    }
}