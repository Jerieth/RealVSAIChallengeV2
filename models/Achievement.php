<?php
/**
 * Achievement model
 */

require_once __DIR__ . '/../includes/database.php';

/**
 * Get list of all possible achievements with their metadata
 * 
 * @return array Array of achievements with type, title, description, and icon
 */
if (!function_exists('get_available_achievements')) {
    function get_available_achievements() {
        return [
        // Difficulty completion achievements
        'complete_easy' => [
            'title' => 'Beginner Detective',
            'description' => 'Complete a single player game on Easy difficulty',
            'icon' => 'fas fa-medal',
            'requirements' => 'Complete a Single Player game on Easy difficulty',
            'category' => 'completion'
        ],
        // New achievements
        'active_player' => [
            'title' => 'Active Player',
            'description' => 'Login 3+ times and play 2+ games',
            'icon' => 'fas fa-user-check',
            'requirements' => 'Login at least 3 times and play at least 2 games',
            'category' => 'gameplay'
        ],
        'over_achiever' => [
            'title' => 'Over Achiever',
            'description' => 'Earn all other achievements',
            'icon' => 'fas fa-award',
            'requirements' => 'Earn all other available achievements',
            'category' => 'meta'
        ],
        'complete_medium' => [
            'title' => 'Skilled Investigator',
            'description' => 'Complete a single player game on Medium difficulty',
            'icon' => 'fas fa-award',
            'requirements' => 'Complete a Single Player game on Medium difficulty',
            'category' => 'completion'
        ],
        'complete_hard' => [
            'title' => 'Master Analyst',
            'description' => 'Complete a single player game on Hard difficulty',
            'icon' => 'fas fa-trophy',
            'requirements' => 'Complete a Single Player game on Hard difficulty',
            'category' => 'completion'
        ],
        
        // Score achievements
        'reach_score_20' => [
            'title' => 'Sharp Observer',
            'description' => 'Reach a score of 20 points in any game mode',
            'icon' => 'fas fa-search',
            'requirements' => 'Reach a score of 20 in any game mode',
            'category' => 'score'
        ],
        'reach_score_50' => [
            'title' => 'Eagle Eye',
            'description' => 'Reach a score of 50 points in any game mode',
            'icon' => 'fas fa-eye',
            'requirements' => 'Reach a score of 50 in any game mode',
            'category' => 'score'
        ],
        'reach_score_100' => [
            'title' => 'Image Master',
            'description' => 'Reach a score of 100 points in any game mode',
            'icon' => 'fas fa-certificate',
            'requirements' => 'Reach a score of 100 in any game mode',
            'category' => 'score'
        ],
        'reach_score_200' => [
            'title' => 'AI Detection Guru',
            'description' => 'Reach a score of 200 points in any game mode',
            'icon' => 'fas fa-crown',
            'requirements' => 'Reach a score of 200 in any game mode',
            'category' => 'score'
        ],
        // Multiplayer and perfection achievements
        'win_multiplayer' => [
            'title' => 'Social Champion',
            'description' => 'Win a multiplayer game against another player',
            'icon' => 'fas fa-users',
            'requirements' => 'Win a multiplayer game',
            'category' => 'multiplayer'
        ],
        'perfect_score' => [
            'title' => 'Flawless Victory',
            'description' => 'Complete a game without losing any lives',
            'icon' => 'fas fa-shield-alt',
            'requirements' => 'Complete a game without losing any lives',
            'category' => 'skill'
        ],
        
        // Bonus game achievements
        'bonus_game_win' => [
            'title' => 'Bonus Hunter',
            'description' => 'Successfully win the bonus mini-game',
            'icon' => 'fas fa-gift',
            'requirements' => 'Win a bonus mini-game by selecting the correct real image',
            'category' => 'skill'
        ],
        
        // Tutorial completion achievement
        'complete_tutorial' => [
            'title' => 'Tutorial Graduate',
            'description' => 'Completed the AI recognition tutorial',
            'icon' => 'fas fa-graduation-cap',
            'requirements' => 'Complete the interactive tutorial',
            'category' => 'learning'
        ],
        
        // Game count achievements
        'games_10' => [
            'title' => 'Dedicated Player',
            'description' => 'Play 10 games',
            'icon' => 'fas fa-gamepad',
            'requirements' => 'Play 10 games',
            'category' => 'general'
        ],
        'games_50' => [
            'title' => 'Enthusiast',
            'description' => 'Play 50 games',
            'icon' => 'fas fa-dice',
            'requirements' => 'Play 50 games',
            'category' => 'general'
        ],
        'games_100' => [
            'title' => 'Veteran',
            'description' => 'Play 100 games',
            'icon' => 'fas fa-crown',
            'requirements' => 'Play 100 games',
            'category' => 'general'
        ],
        
        // Daily challenge achievements
        'daily_challenge_first' => [
            'title' => 'Daily Challenger',
            'description' => 'Complete your first daily challenge',
            'icon' => 'fas fa-calendar-check',
            'requirements' => 'Complete a daily challenge for the first time',
            'category' => 'daily'
        ],
        'daily_challenge_streak_10' => [
            'title' => 'Streak Master',
            'description' => 'Maintain a daily challenge streak of 10 days',
            'icon' => 'fas fa-fire',
            'requirements' => 'Complete 10 daily challenges in a row without missing a day',
            'category' => 'daily'
        ]
    ];
}
}

/**
 * Get user's achievements
 * 
 * @param int $user_id User ID
 * @return array List of user's achievements with full details
 */
if (!function_exists('get_user_achievements_model')) {
    function get_user_achievements_model($user_id) {
        $db = get_db_connection();
        
        // Modified to get only distinct achievement types
        // Group by achievement_type and select the most recent entry
        $stmt = $db->prepare("
            SELECT 
                MAX(a.id) as id, 
                a.achievement_type, 
                a.title,
                a.description,
                a.icon,
                a.category,
                MAX(a.created_at) as earned_at
            FROM achievements a 
            WHERE a.user_id = :user_id
            GROUP BY a.achievement_type, a.title, a.description, a.icon, a.category
            ORDER BY a.category, MAX(a.created_at) DESC
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("get_user_achievements_model - Found " . count($achievements) . " unique achievements for user $user_id");
        
        return $achievements;
    }
}

/**
 * Get all achievements with earned status for a user
 * 
 * @param int $user_id User ID
 * @return array List of all achievements with earned status and date
 */
if (!function_exists('get_all_achievements_with_status')) {
    function get_all_achievements_with_status($user_id) {
        try {
            $db = get_db_connection();
            
            // Get all available achievements
            $all_achievements = get_available_achievements();
            
            // Get user's earned achievements from the user_achievements table with earliest date
            $stmt = $db->prepare("
                SELECT 
                    a.criteria as achievement_type, 
                    MIN(ua.unlocked_at) as earliest_earned_date 
                FROM user_achievements ua
                JOIN achievements a ON ua.achievement_id = a.id
                WHERE ua.user_id = :user_id
                GROUP BY a.criteria
            ");
            
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $earned_achievements = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $achievement) {
                $earned_achievements[$achievement['achievement_type']] = $achievement['earliest_earned_date'];
            }
            
            // Combine data
            $result = [];
            foreach ($all_achievements as $type => $data) {
                $result[$type] = $data;
                $result[$type]['earned'] = isset($earned_achievements[$type]);
                $result[$type]['date'] = $result[$type]['earned'] ? $earned_achievements[$type] : null;
            }
            
            // Sort achievements - earned first, then alphabetically by title
            uasort($result, function($a, $b) {
                // If one is earned and the other is not, earned comes first
                if ($a['earned'] && !$b['earned']) return -1;
                if (!$a['earned'] && $b['earned']) return 1;
                
                // If they're both earned or both not earned, sort by category first
                if ($a['category'] !== $b['category']) {
                    return strcmp($a['category'], $b['category']);
                }
                
                // Then sort by title
                return strcmp($a['title'], $b['title']);
            });
            
            return $result;
        } catch (Exception $e) {
            error_log("Error getting achievements with status: " . $e->getMessage());
            
            // Return basic data without earned status
            $result = [];
            $all_achievements = get_available_achievements();
            
            foreach ($all_achievements as $type => $data) {
                $result[$type] = $data;
                $result[$type]['earned'] = false;
                $result[$type]['date'] = null;
            }
            
            // Sort alphabetically in error case
            uasort($result, function($a, $b) {
                // First sort by category
                if ($a['category'] !== $b['category']) {
                    return strcmp($a['category'], $b['category']);
                }
                // Then by title
                return strcmp($a['title'], $b['title']);
            });
            
            return $result;
        }
    }
}

/**
 * Award an achievement to a user if they don't already have it
 * Uses the user_achievements table to track which users have earned which achievements
 * 
 * @param int $user_id User ID
 * @param string $achievement_type Achievement type
 * @return bool True if awarded, false if already earned
 */
if (!function_exists('award_achievement_model')) {
    function award_achievement_model($user_id, $achievement_type) {
        $db = get_db_connection();
        
        try {
            // Check if achievement is valid
            $available_achievements = get_available_achievements();
            if (!isset($available_achievements[$achievement_type])) {
                error_log("Achievement type not found: $achievement_type");
                return false;
            }
            
            // Get achievement ID from achievement type (lookup from achievements table)
            $stmt = $db->prepare("
                SELECT id 
                FROM achievements 
                WHERE criteria = :achievement_type
                LIMIT 1
            ");
            
            $stmt->bindParam(':achievement_type', $achievement_type, PDO::PARAM_STR);
            $stmt->execute();
            
            $achievement_row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$achievement_row) {
                // Achievement definition doesn't exist yet, create it
                $achievement_data = $available_achievements[$achievement_type];
                $category = isset($achievement_data['category']) ? $achievement_data['category'] : 'general';
                
                $stmt = $db->prepare("
                    INSERT INTO achievements (
                        name, 
                        description, 
                        badge_icon,
                        category,
                        criteria
                    ) VALUES (
                        :title, 
                        :description, 
                        :icon,
                        :category,
                        :criteria
                    )
                ");
                
                $stmt->bindParam(':title', $achievement_data['title'], PDO::PARAM_STR);
                $stmt->bindParam(':description', $achievement_data['description'], PDO::PARAM_STR);
                $stmt->bindParam(':icon', $achievement_data['icon'], PDO::PARAM_STR);
                $stmt->bindParam(':category', $category, PDO::PARAM_STR);
                $stmt->bindParam(':criteria', $achievement_type, PDO::PARAM_STR);
                
                $stmt->execute();
                $achievement_id = $db->lastInsertId();
            } else {
                $achievement_id = $achievement_row['id'];
            }
            
            // Check if user already has this achievement
            $stmt = $db->prepare("
                SELECT id 
                FROM user_achievements 
                WHERE user_id = :user_id 
                AND achievement_id = :achievement_id
                LIMIT 1
            ");
            
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':achievement_id', $achievement_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                // User already has this achievement
                error_log("User $user_id already has achievement $achievement_type");
                return false;
            }
            
            // Award the achievement by creating a record in user_achievements
            $stmt = $db->prepare("
                INSERT INTO user_achievements (
                    user_id, 
                    achievement_id,
                    unlocked_at
                ) VALUES (
                    :user_id, 
                    :achievement_id,
                    CURRENT_TIMESTAMP
                )
            ");
            
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->bindParam(':achievement_id', $achievement_id, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            
            if ($result) {
                error_log("Achievement $achievement_type awarded to user $user_id");
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Error awarding achievement: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Check for and award achievements based on user stats
 * 
 * @param int $user_id User ID
 * @return array List of newly awarded achievement types
 */
if (!function_exists('check_and_award_achievements_model')) {
    function check_and_award_achievements_model($user_id) {
        $db = get_db_connection();
        require_once __DIR__ . '/User.php';
        
        $newly_awarded = [];
        
        // Check for Active Player achievement - login 3+ times and play 2+ games
        $login_count = get_user_login_count($user_id);
        $game_count = get_user_total_games($user_id);
        
        if ($login_count >= 3 && $game_count >= 2) {
            if (award_achievement_model($user_id, 'active_player')) {
                $newly_awarded[] = 'active_player';
            }
        }
        
        // Check game count achievements
        $total_games = get_user_total_games($user_id);
        
        // Check game count achievements
        if ($total_games >= 100) {
            if (award_achievement_model($user_id, 'games_100')) {
                $newly_awarded[] = 'games_100';
            }
        }
        
        if ($total_games >= 50) {
            if (award_achievement_model($user_id, 'games_50')) {
                $newly_awarded[] = 'games_50';
            }
        }
        
        if ($total_games >= 10) {
            if (award_achievement_model($user_id, 'games_10')) {
                $newly_awarded[] = 'games_10';
            }
        }
        
        // Check high score achievements
        $highest_score = get_user_highest_score($user_id);
        
        if ($highest_score >= 200) {
            if (award_achievement_model($user_id, 'reach_score_200')) {
                $newly_awarded[] = 'reach_score_200';
            }
        }
        
        if ($highest_score >= 100) {
            if (award_achievement_model($user_id, 'reach_score_100')) {
                $newly_awarded[] = 'reach_score_100';
            }
        }
        
        if ($highest_score >= 50) {
            if (award_achievement_model($user_id, 'reach_score_50')) {
                $newly_awarded[] = 'reach_score_50';
            }
        }
        
        if ($highest_score >= 20) {
            if (award_achievement_model($user_id, 'reach_score_20')) {
                $newly_awarded[] = 'reach_score_20';
            }
        }
        
        // Check for completed games by difficulty
        // Easy difficulty completion
        $stmt = $db->prepare("
            SELECT 1 
            FROM games 
            WHERE user_id = :user_id 
            AND game_mode = 'single' 
            AND difficulty = 'easy' 
            AND completed = 1 
            LIMIT 1
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            if (award_achievement_model($user_id, 'complete_easy')) {
                $newly_awarded[] = 'complete_easy';
            }
        }
        
        // Medium difficulty completion
        $stmt = $db->prepare("
            SELECT 1 
            FROM games 
            WHERE user_id = :user_id 
            AND game_mode = 'single' 
            AND difficulty = 'medium' 
            AND completed = 1 
            LIMIT 1
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            if (award_achievement_model($user_id, 'complete_medium')) {
                $newly_awarded[] = 'complete_medium';
            }
        }
        
        // Hard difficulty completion
        $stmt = $db->prepare("
            SELECT 1 
            FROM games 
            WHERE user_id = :user_id 
            AND game_mode = 'single' 
            AND difficulty = 'hard' 
            AND completed = 1 
            LIMIT 1
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            if (award_achievement_model($user_id, 'complete_hard')) {
                $newly_awarded[] = 'complete_hard';
            }
        }
        
        // Perfect score achievement (no lives lost)
        $stmt = $db->prepare("
            SELECT starting_lives 
            FROM games 
            WHERE user_id = :user_id 
            AND completed = 1 
            AND lives = starting_lives
            LIMIT 1
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            if (award_achievement_model($user_id, 'perfect_score')) {
                $newly_awarded[] = 'perfect_score';
            }
        }
        
        // Multiplayer win achievement
        $stmt = $db->prepare("
            SELECT 1 
            FROM multiplayer_games mg
            WHERE (
                (mg.player1_id = :user_id AND mg.player1_score > mg.player2_score) OR
                (mg.player2_id = :user_id AND mg.player2_score > mg.player1_score) OR
                (mg.player3_id = :user_id AND mg.player3_score > mg.player1_score AND mg.player3_score > mg.player2_score AND mg.player3_score > mg.player4_score) OR
                (mg.player4_id = :user_id AND mg.player4_score > mg.player1_score AND mg.player4_score > mg.player2_score AND mg.player4_score > mg.player3_score)
            )
            AND mg.completed = 1
            LIMIT 1
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            if (award_achievement_model($user_id, 'win_multiplayer')) {
                $newly_awarded[] = 'win_multiplayer';
            }
        }
        
        // Check for daily challenge achievements
        // Get username for the current user
        $stmt = $db->prepare("SELECT username FROM users WHERE id = :user_id LIMIT 1");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && isset($user['username'])) {
            $username = $user['username'];
            
            // Check for daily challenge completion achievement
            $stmt = $db->prepare("
                SELECT games_completed, streak 
                FROM daily_challenge 
                WHERE username = :username
                LIMIT 1
            ");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->execute();
            $daily_challenge = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($daily_challenge) {
                // Award achievement for completing first daily challenge
                if ($daily_challenge['games_completed'] > 0) {
                    if (award_achievement_model($user_id, 'daily_challenge_first')) {
                        $newly_awarded[] = 'daily_challenge_first';
                    }
                    
                    // Award achievement for 10-day streak
                    if ($daily_challenge['streak'] >= 10) {
                        if (award_achievement_model($user_id, 'daily_challenge_streak_10')) {
                            $newly_awarded[] = 'daily_challenge_streak_10';
                        }
                    }
                }
            }
        }
        
        // Check for the Over Achiever achievement (earn all other achievements)
        // First, get all available achievements except the over_achiever itself
        $available_achievements = get_available_achievements();
        unset($available_achievements['over_achiever']);
        
        // Get all user's earned achievements by first getting achievement IDs from achievements table
        // then joining with user_achievements table to see which ones the user has earned
        $stmt = $db->prepare("
            SELECT a.criteria AS achievement_type
            FROM achievements a
            JOIN user_achievements ua ON a.id = ua.achievement_id
            WHERE ua.user_id = :user_id
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $earned_achievements = array_map(function($row) {
            return $row['achievement_type'];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
        
        // Check if user has earned all other achievements
        $all_earned = true;
        foreach (array_keys($available_achievements) as $achievement_type) {
            if (!in_array($achievement_type, $earned_achievements)) {
                $all_earned = false;
                break;
            }
        }
        
        if ($all_earned) {
            if (award_achievement_model($user_id, 'over_achiever')) {
                $newly_awarded[] = 'over_achiever';
            }
        }
        
        return $newly_awarded;
    }
}