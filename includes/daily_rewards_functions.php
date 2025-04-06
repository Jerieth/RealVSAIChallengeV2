<?php
/**
 * Daily Rewards Functions
 * Functions for managing daily rewards (avatars) for completing daily challenges
 */

/**
 * Create daily rewards table if it doesn't exist
 * 
 * @param PDO $conn Database connection
 * @return bool True if table was created or already exists
 */
function create_daily_rewards_table($conn) {
    try {
        // Create the daily_rewards table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS daily_rewards (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            image_path TEXT NOT NULL,
            unlock_requirement TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->exec($sql);
        
        // Create the user_daily_rewards table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS user_daily_rewards (
            id INTEGER PRIMARY KEY,
            user_id INTEGER NOT NULL,
            reward_id INTEGER NOT NULL,
            date_unlocked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (reward_id) REFERENCES daily_rewards(id),
            UNIQUE(user_id, reward_id)
        )";
        $conn->exec($sql);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating daily rewards tables: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if the user has earned any rewards based on daily challenge completion
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param array $daily_stats User's daily challenge stats
 * @return bool True if a new reward was unlocked
 */
function check_and_award_daily_rewards($conn, $user_id, $daily_stats) {
    try {
        // Make sure tables exist
        create_daily_rewards_table($conn);
        
        // Get unlockable rewards based on daily challenge stats
        $unlocked_rewards = [];
        
        // Check games completed milestone (5, 10, 25, 50, 100)
        $completed_milestones = [5, 10, 25, 50, 100];
        $games_completed = $daily_stats['games_completed'];
        
        foreach ($completed_milestones as $milestone) {
            if ($games_completed >= $milestone) {
                // Check if there's a reward for this milestone
                $stmt = $conn->prepare("
                    SELECT * FROM daily_rewards 
                    WHERE unlock_requirement = :requirement
                ");
                $requirement = "games_completed_" . $milestone;
                $stmt->bindParam(':requirement', $requirement);
                $stmt->execute();
                $reward = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($reward) {
                    // Check if user already has this reward
                    $stmt = $conn->prepare("
                        SELECT * FROM user_daily_rewards 
                        WHERE user_id = :user_id AND reward_id = :reward_id
                    ");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':reward_id', $reward['id']);
                    $stmt->execute();
                    
                    if (!$stmt->fetch()) {
                        // Award the reward
                        $stmt = $conn->prepare("
                            INSERT INTO user_daily_rewards (user_id, reward_id)
                            VALUES (:user_id, :reward_id)
                        ");
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->bindParam(':reward_id', $reward['id']);
                        $stmt->execute();
                        
                        $unlocked_rewards[] = $reward;
                    }
                }
            }
        }
        
        // Check streak milestones (3, 7, 14, 30, 90)
        $streak_milestones = [3, 7, 14, 30, 90];
        $current_streak = $daily_stats['streak'];
        
        foreach ($streak_milestones as $milestone) {
            if ($current_streak >= $milestone) {
                // Check if there's a reward for this milestone
                $stmt = $conn->prepare("
                    SELECT * FROM daily_rewards 
                    WHERE unlock_requirement = :requirement
                ");
                $requirement = "streak_" . $milestone;
                $stmt->bindParam(':requirement', $requirement);
                $stmt->execute();
                $reward = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($reward) {
                    // Check if user already has this reward
                    $stmt = $conn->prepare("
                        SELECT * FROM user_daily_rewards 
                        WHERE user_id = :user_id AND reward_id = :reward_id
                    ");
                    $stmt->bindParam(':user_id', $user_id);
                    $stmt->bindParam(':reward_id', $reward['id']);
                    $stmt->execute();
                    
                    if (!$stmt->fetch()) {
                        // Award the reward
                        $stmt = $conn->prepare("
                            INSERT INTO user_daily_rewards (user_id, reward_id)
                            VALUES (:user_id, :reward_id)
                        ");
                        $stmt->bindParam(':user_id', $user_id);
                        $stmt->bindParam(':reward_id', $reward['id']);
                        $stmt->execute();
                        
                        $unlocked_rewards[] = $reward;
                    }
                }
            }
        }
        
        // Check for the secret Frog Explorer reward - playing all single player difficulties
        check_and_award_frog_explorer_reward($conn, $user_id, $unlocked_rewards);
        
        return count($unlocked_rewards) > 0 ? $unlocked_rewards : false;
    } catch (PDOException $e) {
        error_log("Error checking daily rewards: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all daily rewards unlocked by a user
 * 
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return array Array of unlocked rewards
 */
function get_user_daily_rewards($conn, $user_id) {
    try {
        // Make sure tables exist
        create_daily_rewards_table($conn);
        
        $stmt = $conn->prepare("
            SELECT dr.* FROM daily_rewards dr
            JOIN user_daily_rewards udr ON dr.id = udr.reward_id
            WHERE udr.user_id = :user_id
            ORDER BY udr.date_unlocked DESC
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user daily rewards: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if default rewards need to be added to the database
 * and add them if they don't exist
 * 
 * @param PDO $conn Database connection
 * @return bool True if rewards were added or already exist
 */
function initialize_default_rewards($conn) {
    try {
        // Make sure tables exist
        create_daily_rewards_table($conn);
        
        // Check if rewards already exist
        $stmt = $conn->query("SELECT COUNT(*) FROM daily_rewards");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            // Add default rewards
            $default_rewards = [
                // Games completed rewards
                [
                    'name' => 'Novice Player',
                    'description' => 'Complete 5 daily challenges',
                    'image_path' => '/static/images/avatars/avatar_beginner.png',
                    'unlock_requirement' => 'games_completed_5'
                ],
                [
                    'name' => 'Regular Player',
                    'description' => 'Complete 10 daily challenges',
                    'image_path' => '/static/images/avatars/avatar_regular.png',
                    'unlock_requirement' => 'games_completed_10'
                ],
                [
                    'name' => 'Dedicated Player',
                    'description' => 'Complete 25 daily challenges',
                    'image_path' => '/static/images/avatars/avatar_dedicated.png',
                    'unlock_requirement' => 'games_completed_25'
                ],
                [
                    'name' => 'Expert Player',
                    'description' => 'Complete 50 daily challenges',
                    'image_path' => '/static/images/avatars/avatar_expert.png',
                    'unlock_requirement' => 'games_completed_50'
                ],
                [
                    'name' => 'Master Player',
                    'description' => 'Complete 100 daily challenges',
                    'image_path' => '/static/images/avatars/avatar_master.png',
                    'unlock_requirement' => 'games_completed_100'
                ],
                
                // Streak rewards
                [
                    'name' => '3-Day Streak',
                    'description' => 'Maintain a 3-day streak in daily challenges',
                    'image_path' => '/static/images/avatars/avatar_streak_3.png',
                    'unlock_requirement' => 'streak_3'
                ],
                [
                    'name' => 'Weekly Streak',
                    'description' => 'Maintain a 7-day streak in daily challenges',
                    'image_path' => '/static/images/avatars/avatar_streak_7.png',
                    'unlock_requirement' => 'streak_7'
                ],
                [
                    'name' => '2-Week Streak',
                    'description' => 'Maintain a 14-day streak in daily challenges',
                    'image_path' => '/static/images/avatars/avatar_streak_14.png',
                    'unlock_requirement' => 'streak_14'
                ],
                [
                    'name' => 'Monthly Streak',
                    'description' => 'Maintain a 30-day streak in daily challenges',
                    'image_path' => '/static/images/avatars/avatar_streak_30.png',
                    'unlock_requirement' => 'streak_30'
                ],
                [
                    'name' => 'Seasonal Streak',
                    'description' => 'Maintain a 90-day streak in daily challenges',
                    'image_path' => '/static/images/avatars/avatar_streak_90.png',
                    'unlock_requirement' => 'streak_90'
                ],
                
                // Special secret reward
                [
                    'name' => 'Frog Explorer',
                    'description' => 'Secret reward for playing all single player difficulty modes',
                    'image_path' => '/static/images/avatars/avatar_frog.png',
                    'unlock_requirement' => 'play_all_difficulties'
                ],
            ];
            
            $stmt = $conn->prepare("
                INSERT INTO daily_rewards (name, description, image_path, unlock_requirement)
                VALUES (:name, :description, :image_path, :unlock_requirement)
            ");
            
            foreach ($default_rewards as $reward) {
                $stmt->bindParam(':name', $reward['name']);
                $stmt->bindParam(':description', $reward['description']);
                $stmt->bindParam(':image_path', $reward['image_path']);
                $stmt->bindParam(':unlock_requirement', $reward['unlock_requirement']);
                $stmt->execute();
            }
            
            return true;
        }
        
        return true; // Rewards already exist
    } catch (PDOException $e) {
        error_log("Error initializing default rewards: " . $e->getMessage());
        return false;
    }
}

/**
 * Award a donation achievement to a user
 * This function is called when a user makes a donation
 *
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @return bool True if achievement was awarded
 */
function dr_award_donator_achievement($conn, $user_id) {
    try {
        // Check if the Donator achievement exists, create it if it doesn't
        $stmt = $conn->prepare("
            SELECT * FROM achievements 
            WHERE slug = 'donator'
        ");
        $stmt->execute();
        $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$achievement) {
            // Create the Donator achievement
            $stmt = $conn->prepare("
                INSERT INTO achievements (
                    name, description, slug, icon_class, 
                    category, difficulty, points, hidden, 
                    created_at
                )
                VALUES (
                    'Donator', 
                    'Thank you for supporting the game with a donation!', 
                    'donator', 
                    'fas fa-heart', 
                    'special', 
                    'easy', 
                    100, 
                    0, 
                    CURRENT_TIMESTAMP
                )
            ");
            $stmt->execute();
            
            // Get the newly created achievement
            $stmt = $conn->prepare("
                SELECT * FROM achievements 
                WHERE slug = 'donator'
            ");
            $stmt->execute();
            $achievement = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Check if user already has this achievement
        $stmt = $conn->prepare("
            SELECT * FROM user_achievements 
            WHERE user_id = :user_id AND achievement_id = :achievement_id
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':achievement_id', $achievement['id']);
        $stmt->execute();
        
        $new_achievement = false;
        if (!$stmt->fetch()) {
            // Award the achievement
            $stmt = $conn->prepare("
                INSERT INTO user_achievements (user_id, achievement_id)
                VALUES (:user_id, :achievement_id)
            ");
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':achievement_id', $achievement['id']);
            $stmt->execute();
            
            $new_achievement = true;
            // Log the achievement award
            error_log("Awarded Donator achievement to user ID: " . $user_id);
        }
        
        // Set the user's VIP status to 1
        $stmt = $conn->prepare("UPDATE users SET vip = 1 WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        error_log("User ID: " . $user_id . " set as VIP");
        
        return $new_achievement; // Return true if a new achievement was awarded
    } catch (PDOException $e) {
        error_log("Error awarding donator achievement: " . $e->getMessage());
        return false;
    }
}

/**
 * Remove a user's achievement
 *
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param int $achievement_id Achievement ID
 * @return bool True if achievement was removed
 */
function remove_user_achievement($conn, $user_id, $achievement_id) {
    try {
        $stmt = $conn->prepare("
            DELETE FROM user_achievements 
            WHERE user_id = :user_id AND achievement_id = :achievement_id
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':achievement_id', $achievement_id);
        $stmt->execute();
        
        // Check if any rows were affected
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log("Error removing user achievement: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if the user has played all single player difficulties and award the Frog Explorer reward
 *
 * @param PDO $conn Database connection
 * @param int $user_id User ID
 * @param array &$unlocked_rewards Array of unlocked rewards to add to if reward is earned
 * @return bool True if the reward was awarded
 */
function check_and_award_frog_explorer_reward($conn, $user_id, &$unlocked_rewards) {
    try {
        // Check if user already has this reward
        $stmt = $conn->prepare("
            SELECT dr.* FROM daily_rewards dr
            JOIN user_daily_rewards udr ON dr.id = udr.reward_id
            WHERE udr.user_id = :user_id AND dr.unlock_requirement = 'play_all_difficulties'
            LIMIT 1
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // If user already has the reward, don't award it again
        if ($stmt->fetch()) {
            return false;
        }
        
        // Check if user has played all difficulties (easy, medium, hard)
        $stmt = $conn->prepare("
            SELECT DISTINCT difficulty
            FROM games
            WHERE user_id = :user_id
            AND game_mode = 'single'
            AND difficulty IN ('easy', 'medium', 'hard')
        ");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $played_difficulties = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Sort the difficulties to ensure consistent comparison
        sort($played_difficulties);
        $required_difficulties = ['easy', 'hard', 'medium'];
        sort($required_difficulties);
        
        // Check if user has played all three difficulty levels
        if (count($played_difficulties) === 3 && 
            $played_difficulties[0] === $required_difficulties[0] &&
            $played_difficulties[1] === $required_difficulties[1] &&
            $played_difficulties[2] === $required_difficulties[2]) {
            
            // Get the Frog Explorer reward
            $stmt = $conn->prepare("
                SELECT * FROM daily_rewards
                WHERE unlock_requirement = 'play_all_difficulties'
                LIMIT 1
            ");
            $stmt->execute();
            $reward = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reward) {
                // Award the reward
                $stmt = $conn->prepare("
                    INSERT INTO user_daily_rewards (user_id, reward_id)
                    VALUES (:user_id, :reward_id)
                ");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':reward_id', $reward['id']);
                $stmt->execute();
                
                // Add to unlocked rewards list
                $unlocked_rewards[] = $reward;
                
                error_log("Awarded Frog Explorer reward to user ID: " . $user_id);
                return true;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error checking Frog Explorer reward: " . $e->getMessage());
        return false;
    }
}