<?php
/**
 * Achievement functions for Real vs AI application
 * Handles achievement unlocking, checking, and related operations
 */

require_once 'database.php';

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
        $sql = "SELECT id FROM achievements WHERE criteria = ? AND threshold = ? LIMIT 1";
        return db_query_value($sql, [$criteria, $threshold]);
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
    function has_achievement($user_id, $achievement_id) {
        if (empty($user_id) || empty($achievement_id)) {
            return false;
        }
        
        $sql = "SELECT COUNT(*) FROM user_achievements WHERE user_id = ? AND achievement_id = ?";
        return db_query_value($sql, [$user_id, $achievement_id]) > 0;
    }
}

if (!function_exists('award_achievement')) {
    /**
     * Award an achievement to a user
     * 
     * @param int|string $achievement_id Achievement ID or criteria
     * @param int $user_id User ID
     * @return bool True if achievement was awarded, false otherwise
     */
    function award_achievement($achievement_id, $user_id) {
        // Check if inputs are valid
        if (empty($user_id) || empty($achievement_id)) {
            return false;
        }
        
        // If $achievement_id is a string (criteria), get the actual achievement ID
        if (!is_numeric($achievement_id)) {
            $criteria = $achievement_id;
            $conn = get_db_connection();
            $stmt = $conn->prepare("SELECT id FROM achievements WHERE criteria = ?");
            $stmt->execute([$criteria]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                error_log("Achievement with criteria $criteria not found");
                return false;
            }
            
            $achievement_id = $result['id'];
        }
        
        // Check if user already has this achievement
        if (has_achievement($user_id, $achievement_id)) {
            return false; // Already has achievement
        }
        
        try {
            // Insert new achievement for the user
            $data = [
                'user_id' => $user_id,
                'achievement_id' => $achievement_id,
                'earned_at' => date('Y-m-d H:i:s')
            ];
            
            $result = db_insert('user_achievements', $data);
            return $result !== false;
        } catch (Exception $e) {
            // Log error
            error_log('Error awarding achievement: ' . $e->getMessage());
            return false;
        }
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
            $sql = "
                SELECT a.*, ua.earned_at 
                FROM achievements a
                JOIN user_achievements ua ON a.id = ua.achievement_id
                WHERE ua.user_id = ? 
                ORDER BY ua.earned_at DESC
            ";
            return db_query($sql, [$user_id]);
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
    function check_and_award_achievements($user_id, $game, $stats = null) {
        if (empty($user_id) || empty($game)) {
            return [];
        }
        
        $awarded = [];
        
        // Game completion achievements - difficulty levels
        if (isset($game['difficulty']) && isset($game['completed']) && $game['completed'] && isset($game['lives_remaining']) && $game['lives_remaining'] > 0) {
            $difficulty = strtolower($game['difficulty']);
            $threshold = 0;
            
            switch ($difficulty) {
                case 'easy':
                    $threshold = 1;
                    break;
                case 'medium':
                    $threshold = 2;
                    break;
                case 'hard':
                    $threshold = 3;
                    break;
            }
            
            if ($threshold > 0) {
                $achievement_id = get_achievement_by_criteria('complete_difficulty', $threshold);
                if ($achievement_id && award_achievement($user_id, $achievement_id)) {
                    $awarded[] = "Complete $difficulty mode";
                }
            }
        }
        
        // Score-based achievements
        $score = isset($game['score']) ? (int)$game['score'] : 0;
        
        $score_thresholds = [100, 50, 20]; // Check highest first
        foreach ($score_thresholds as $threshold) {
            if ($score >= $threshold) {
                $achievement_id = get_achievement_by_criteria('reach_score', $threshold);
                if ($achievement_id && award_achievement($user_id, $achievement_id)) {
                    $awarded[] = "Score $threshold+";
                    break; // Only award the highest achieved
                }
            }
        }
        
        // Multiplayer win achievement
        if (isset($game['game_mode']) && $game['game_mode'] === 'multiplayer' && isset($game['winner']) && $game['winner'] == $user_id) {
            $achievement_id = get_achievement_by_criteria('win_multiplayer', 1);
            if ($achievement_id && award_achievement($user_id, $achievement_id)) {
                $awarded[] = "Multiplayer Victory";
            }
        }
        
        // Multiplayer participation
        if (isset($game['game_mode']) && $game['game_mode'] === 'multiplayer') {
            // Count how many multiplayer games the user has participated in
            $sql = "
                SELECT COUNT(*) FROM multiplayer_players 
                WHERE user_id = ?
            ";
            $game_count = db_query_value($sql, [$user_id]);
            
            if ($game_count >= 5) {
                $achievement_id = get_achievement_by_criteria('play_multiplayer', 5);
                if ($achievement_id && award_achievement($user_id, $achievement_id)) {
                    $awarded[] = "Social Gamer";
                }
            }
        }
        
        return $awarded;
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
        // Get user achievements
        $user_achievements = get_user_achievements($user_id);
        
        // Get all possible achievements
        $sql = "SELECT COUNT(*) FROM achievements";
        $possible = db_query_value($sql, []);
        
        $total = count($user_achievements);
        
        // Count by category
        $categories = [
            'gameplay' => 0,
            'score' => 0,
            'social' => 0
        ];
        
        foreach ($user_achievements as $achievement) {
            $category = $achievement['category'];
            if (isset($categories[$category])) {
                $categories[$category]++;
            }
        }
        
        // Get total counts by category
        $category_totals = [];
        foreach (array_keys($categories) as $category) {
            $sql = "SELECT COUNT(*) FROM achievements WHERE category = ?";
            $category_totals[$category] = db_query_value($sql, [$category]);
        }
        
        return [
            'total' => $total,
            'possible' => $possible,
            'percentage' => $possible > 0 ? round(($total / $possible) * 100) : 0,
            'categories' => $categories,
            'category_totals' => $category_totals
        ];
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

if (!function_exists('award_daily_challenge_achievements')) {
    /**
     * Award daily challenge achievements based on statistics
     * 
     * @param int $user_id User ID
     * @param array $stats Daily challenge statistics
     * @return array List of newly awarded achievements
     */
    function award_daily_challenge_achievements($user_id, $stats) {
        if (empty($user_id) || empty($stats)) {
            return [];
        }
        
        $awarded = [];
        
        // First daily challenge achievement
        if ($stats['games_completed'] == 1) {
            if (award_achievement('daily_challenge_first', $user_id)) {
                $awarded[] = 'Daily Challenger';
            }
        }
        
        // Games completed milestone
        if ($stats['games_completed'] >= 10) {
            if (award_achievement('daily_challenge_10', $user_id)) {
                $awarded[] = 'Daily Challenge Veteran';
            }
        }
        
        // Streak achievements
        $streak_milestones = [
            3 => 'daily_streak_3',
            7 => 'daily_streak_7',
            14 => 'daily_streak_14'
        ];
        
        foreach ($streak_milestones as $days => $criteria) {
            if ($stats['streak'] >= $days) {
                if (award_achievement($criteria, $user_id)) {
                    $achievement_name = '';
                    switch ($criteria) {
                        case 'daily_streak_3':
                            $achievement_name = 'Streak Master';
                            break;
                        case 'daily_streak_7':
                            $achievement_name = 'Weekly Warrior';
                            break;
                        case 'daily_streak_14':
                            $achievement_name = 'Fortnight Champion';
                            break;
                    }
                    if (!empty($achievement_name)) {
                        $awarded[] = $achievement_name;
                    }
                }
            }
        }
        
        return $awarded;
    }
}

if (!function_exists('award_donator_achievement')) {
    /**
     * Award the Donator achievement to a user who has made a successful donation
     * 
     * @param int $user_id User ID
     * @return bool True if achievement was awarded, false otherwise
     */
    function award_donator_achievement($user_id) {
        if (empty($user_id)) {
            return false;
        }
        
        if (award_achievement('donator', $user_id)) {
            return true;
        }
        
        return false;
    }
}

if (!function_exists('award_achievement_if_not_earned')) {
    /**
     * Award achievement to user if they haven't earned it yet
     * 
     * @param int $user_id User ID
     * @param int $achievement_id Achievement ID
     * @return bool True if achievement was awarded, false otherwise
     */
    function award_achievement_if_not_earned($user_id, $achievement_id) {
        if (empty($user_id) || empty($achievement_id)) {
            return false;
        }
        
        // Check if user already has this achievement
        if (has_achievement($user_id, $achievement_id)) {
            return false; // Already has achievement
        }
        
        // Award the achievement
        return award_achievement($achievement_id, $user_id);
    }
}