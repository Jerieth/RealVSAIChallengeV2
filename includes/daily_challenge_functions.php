<?php
/**
 * Daily Challenge Functions
 * Utility functions for handling daily challenge game mechanics
 */

/**
 * Get random images for the daily challenge mode
 * This uses a seed based on the date so everyone gets the same images on the same day
 * Prioritizes recent images and avoids showing previously seen images to the user
 * 
 * @param int $count Number of images to get
 * @param string $seed Seed for random number generation (based on date)
 * @param string $username Current username (for tracking shown images)
 * @return array Array of image IDs
 */
function d_get_random_images_for_game($count, $seed = null, $username = null) {
    // Connect to the database
    $conn = get_db_connection();
    
    // If seed is provided, use it to set the PRNG state
    if ($seed !== null) {
        srand(intval($seed));
    }
    
    try {
        // Get the user's history of seen images if available
        $seen_real_images = [];
        $seen_ai_images = [];
        
        if ($username) {
            $seen_images = d_get_user_seen_images($conn, $username);
            if ($seen_images) {
                $seen_real_images = $seen_images['real'];
                $seen_ai_images = $seen_images['ai'];
            }
        }
        
        // Get counts of real and AI images
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM images WHERE type = 'real'");
        $stmt->execute();
        $real_count = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM images WHERE type = 'ai'");
        $stmt->execute();
        $ai_count = $stmt->fetchColumn();
        
        // Ensure we have enough images of each type
        if ($real_count < floor($count/2) || $ai_count < ceil($count/2)) {
            error_log("Not enough images of each type for daily challenge. Real: $real_count, AI: $ai_count, Needed: $count");
            return false;
        }
        
        // Calculate how many of each type we need
        $real_needed = floor($count/2);
        $ai_needed = ceil($count/2);
        
        // Get recent real images, excluding ones the user has seen if possible
        $query = "SELECT id FROM images WHERE type = 'real' ";
        
        // Only exclude seen images if there are enough unseen images
        if (!empty($seen_real_images) && (count($seen_real_images) < $real_count - $real_needed)) {
            $placeholders = implode(',', array_fill(0, count($seen_real_images), '?'));
            $query .= "AND id NOT IN ($placeholders) ";
        }
        
        $query .= "ORDER BY created_at DESC LIMIT " . ($real_count > 20 ? 20 : $real_count);
        
        $stmt = $conn->prepare($query);
        
        // Bind seen image IDs if we're excluding them
        if (!empty($seen_real_images) && (count($seen_real_images) < $real_count - $real_needed)) {
            $param_index = 1;
            foreach ($seen_real_images as $id) {
                $stmt->bindValue($param_index++, $id);
            }
        }
        
        $stmt->execute();
        $recent_real_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Get recent AI images, excluding ones the user has seen if possible
        $query = "SELECT id FROM images WHERE type = 'ai' ";
        
        // Only exclude seen images if there are enough unseen images
        if (!empty($seen_ai_images) && (count($seen_ai_images) < $ai_count - $ai_needed)) {
            $placeholders = implode(',', array_fill(0, count($seen_ai_images), '?'));
            $query .= "AND id NOT IN ($placeholders) ";
        }
        
        $query .= "ORDER BY created_at DESC LIMIT " . ($ai_count > 20 ? 20 : $ai_count);
        
        $stmt = $conn->prepare($query);
        
        // Bind seen image IDs if we're excluding them
        if (!empty($seen_ai_images) && (count($seen_ai_images) < $ai_count - $ai_needed)) {
            $param_index = 1;
            foreach ($seen_ai_images as $id) {
                $stmt->bindValue($param_index++, $id);
            }
        }
        
        $stmt->execute();
        $recent_ai_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Shuffle the arrays using seed
        shuffle($recent_real_images);
        shuffle($recent_ai_images);
        
        // Take the needed number of each type
        $selected_real_images = array_slice($recent_real_images, 0, $real_needed);
        $selected_ai_images = array_slice($recent_ai_images, 0, $ai_needed);
        
        // If we don't have enough images, get additional ones including seen images
        if (count($selected_real_images) < $real_needed) {
            $need_more = $real_needed - count($selected_real_images);
            error_log("Need $need_more more real images, including previously seen ones");
            
            $stmt = $conn->prepare("SELECT id FROM images WHERE type = 'real' ORDER BY RANDOM() LIMIT ?");
            $stmt->bindValue(1, $need_more);
            $stmt->execute();
            $more_real_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $selected_real_images = array_merge($selected_real_images, $more_real_images);
        }
        
        if (count($selected_ai_images) < $ai_needed) {
            $need_more = $ai_needed - count($selected_ai_images);
            error_log("Need $need_more more AI images, including previously seen ones");
            
            $stmt = $conn->prepare("SELECT id FROM images WHERE type = 'ai' ORDER BY RANDOM() LIMIT ?");
            $stmt->bindValue(1, $need_more);
            $stmt->execute();
            $more_ai_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $selected_ai_images = array_merge($selected_ai_images, $more_ai_images);
        }
        
        // Combine and shuffle the selected images
        $selected_images = array_merge($selected_real_images, $selected_ai_images);
        shuffle($selected_images);
        
        // If the user is logged in, record these images as seen
        if ($username) {
            d_record_images_as_seen($conn, $username, $selected_real_images, $selected_ai_images);
        }
        
        return $selected_images;
    } catch (PDOException $e) {
        error_log("Error getting random images: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a random pair of images - one real, one AI - for the final round
 * 
 * @param array $exclude_ids IDs to exclude
 * @param string $difficulty Difficulty level (easy, medium, hard)
 * @return array Array with two filepaths [real_image_path, ai_image_path]
 */
function d_get_random_image_pair($exclude_ids = array(), $difficulty = 'medium') {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Get a random real image
        $stmt = $conn->prepare("
            SELECT id, filename FROM images 
            WHERE type = 'real'
            AND id NOT IN (" . implode(',', array_map(function($id) { return $id ?: 0; }, $exclude_ids)) . " OR 1=1)
            ORDER BY RANDOM() LIMIT 1
        ");
        $stmt->execute();
        $real_image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get a random AI image with matching difficulty
        $difficulty_condition = "";
        if ($difficulty == 'easy') {
            $difficulty_condition = "AND difficulty = 'easy'";
        } else if ($difficulty == 'medium') {
            $difficulty_condition = "AND difficulty = 'medium'";
        } else if ($difficulty == 'hard') {
            $difficulty_condition = "AND difficulty = 'hard'";
        }
        
        $stmt = $conn->prepare("
            SELECT id, filename FROM images 
            WHERE type = 'ai' " . $difficulty_condition . "
            AND id NOT IN (" . implode(',', array_map(function($id) { return $id ?: 0; }, $exclude_ids)) . " OR 1=1)
            ORDER BY RANDOM() LIMIT 1
        ");
        $stmt->execute();
        $ai_image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($real_image && $ai_image) {
            // Construct the file paths
            $real_image_path = 'static/images/real/' . $real_image['filename'];
            $ai_image_path = 'static/images/ai/' . $ai_image['filename'];
            return [$real_image_path, $ai_image_path];
        } else {
            error_log("Could not get both image types for the pair");
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error getting random image pair: " . $e->getMessage());
        return false;
    }
}

/**
 * Get image data by ID
 * 
 * @param int $image_id The image ID
 * @return array|bool Image data array or false on failure
 */
function d_get_image_by_id($image_id) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM images WHERE id = :id");
        $stmt->bindParam(':id', $image_id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting image by ID: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user can play the daily challenge today
 * 
 * @param string $username The username to check
 * @return bool True if user can play, false otherwise
 */
function d_can_play_daily_challenge($username) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Check if user has a daily challenge record
        $stmt = $conn->prepare("
            SELECT date_last_challenge, next_challenge_date
            FROM daily_challenge
            WHERE username = :username
        ");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $challenge_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $today = date('Y-m-d');
        
        if ($challenge_data) {
            // User has played before, check if they can play today
            $next_challenge_date = $challenge_data['next_challenge_date'];
            return ($next_challenge_date <= $today);
        } else {
            // User has never played before, they can play today
            return true;
        }
    } catch (PDOException $e) {
        error_log("Error checking if user can play daily challenge: " . $e->getMessage());
        // Default to false if there's an error
        return false;
    }
}

/**
 * Update daily challenge record for a user
 * 
 * @param string $username The username to update
 * @param bool $completed Whether the challenge was completed successfully
 * @param bool $is_admin Whether the user is an admin (affects replay behavior)
 * @return bool True on success, false on failure
 */
function d_update_daily_challenge_record($username, $completed = true, $is_admin = false) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Check if user has a daily challenge record
        $stmt = $conn->prepare("SELECT * FROM daily_challenge WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $daily_record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $current_date = date('Y-m-d');
        $next_challenge_time = date('Y-m-d H:i:s', strtotime('tomorrow 8:00 AM'));
        
        if ($daily_record) {
            // Check if this is an admin replay of today's challenge
            $admin_replay = $is_admin && $daily_record['date_last_challenge'] == $current_date;
            
            if ($admin_replay) {
                // Admin is replaying today's challenge - record the score but don't update streak or games_completed
                // Only log that they played again
                error_log("Admin user {$username} replayed Daily Challenge on {$current_date}");
                
                // For admin replays, we don't update any stats
                return true;
            }
            
            // Normal update flow for regular users or admin's first play of the day
            if ($completed) {
                // Successful completion - increment games_completed and streak
                $stmt = $conn->prepare("UPDATE daily_challenge SET 
                    date_last_challenge = :date_last_challenge,
                    next_challenge_date = :next_challenge_date,
                    games_completed = games_completed + 1,
                    streak = streak + 1
                    WHERE username = :username");
            } else {
                // Failed completion - reset streak to 0
                $stmt = $conn->prepare("UPDATE daily_challenge SET 
                    date_last_challenge = :date_last_challenge,
                    next_challenge_date = :next_challenge_date,
                    streak = 0
                    WHERE username = :username");
            }
            
            $stmt->execute([
                'date_last_challenge' => $current_date,
                'next_challenge_date' => $next_challenge_time,
                'username' => $username
            ]);
            
            // Check for achievement unlocks
            if ($completed) {
                // Get user's ID for achievements
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $user_id = $user['id'];
                    
                    // Calculate new values
                    $new_games_completed = $daily_record['games_completed'] + 1;
                    $new_streak = $daily_record['streak'] + 1;
                    
                    // Use our specialized function to award achievements
                    $stats = [
                        'games_completed' => $new_games_completed,
                        'streak' => $new_streak
                    ];
                    d_award_daily_challenge_achievements($user_id, $stats);
                }
            }
        } else {
            // Create a new record
            $stmt = $conn->prepare("INSERT INTO daily_challenge 
                (username, date_last_challenge, next_challenge_date, games_completed, streak) 
                VALUES (:username, :date_last_challenge, :next_challenge_date, :games_completed, :streak)");
                
            $games_completed = $completed ? 1 : 0;
            $streak = $completed ? 1 : 0;
            
            $stmt->execute([
                'username' => $username,
                'date_last_challenge' => $current_date,
                'next_challenge_date' => $next_challenge_time,
                'games_completed' => $games_completed,
                'streak' => $streak
            ]);
            
            // First time completion achievement
            if ($completed) {
                // Get user's ID for achievements
                $stmt = $conn->prepare("SELECT id FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $stats = [
                        'games_completed' => 1,
                        'streak' => 1
                    ];
                    d_award_daily_challenge_achievements($user['id'], $stats);
                }
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error updating daily challenge record: " . $e->getMessage());
        return false;
    }
}

/**
 * Get daily challenge stats for a user
 * 
 * @param string $username The username to check
 * @return array|bool Daily challenge stats or false on failure
 */
/**
 * Award achievements based on daily challenge stats
 * 
 * @param int $user_id The user ID
 * @param array $stats The daily challenge stats
 * @return void
 */
function d_award_daily_challenge_achievements($user_id, $stats) {
    // Include the achievement functions file to ensure we have access to the award_achievement_if_not_earned function
    require_once 'achievement_functions_new.php';
    
    // Connect to database
    $conn = get_db_connection();
    
    // Check for achievements based on games completed
    if ($stats['games_completed'] >= 1) {
        // Award "Daily Challenger" achievement for completing first daily challenge
        $achievement_id = 40; // Daily Challenger achievement ID
        award_achievement_if_not_earned($user_id, $achievement_id);
    }
    
    if ($stats['games_completed'] >= 5) {
        // Award "Daily Devotion" achievement for completing 5 daily challenges
        $achievement_id = 41; // Daily Devotion achievement ID
        award_achievement_if_not_earned($user_id, $achievement_id);
    }
    
    if ($stats['games_completed'] >= 20) {
        // Award "Challenge Master" achievement for completing 20 daily challenges
        $achievement_id = 42; // Challenge Master achievement ID
        award_achievement_if_not_earned($user_id, $achievement_id);
    }
    
    // Check for achievements based on streak
    if ($stats['streak'] >= 3) {
        // Award "Streak Starter" achievement for 3-day streak
        $achievement_id = 43; // Streak Starter achievement ID
        award_achievement_if_not_earned($user_id, $achievement_id);
    }
    
    if ($stats['streak'] >= 7) {
        // Award "Weekly Warrior" achievement for 7-day streak
        $achievement_id = 44; // Weekly Warrior achievement ID
        award_achievement_if_not_earned($user_id, $achievement_id);
    }
    
    if ($stats['streak'] >= 30) {
        // Award "Monthly Master" achievement for 30-day streak
        $achievement_id = 45; // Monthly Master achievement ID
        award_achievement_if_not_earned($user_id, $achievement_id);
    }
}

/**
 * Get daily challenge stats for a user
 * 
 * @param string $username The username to check
 * @return array|bool Daily challenge stats or false on failure
 */
function d_get_daily_challenge_stats($username) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        $stmt = $conn->prepare("SELECT * FROM daily_challenge WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stats) {
            // Format the next challenge date for display
            $next_challenge = new DateTime($stats['next_challenge_date']);
            $now = new DateTime();
            
            if ($next_challenge <= $now) {
                $stats['can_play'] = true;
                $stats['next_challenge_formatted'] = 'Available now!';
            } else {
                $stats['can_play'] = false;
                $stats['next_challenge_formatted'] = $next_challenge->format('F j, Y g:i A');
                
                // Calculate time until next challenge
                $interval = $now->diff($next_challenge);
                $hours = $interval->h + ($interval->days * 24);
                $minutes = $interval->i;
                
                $stats['time_until_next'] = sprintf('%d hours, %d minutes', $hours, $minutes);
            }
            
            return $stats;
        } else {
            // First time player
            return [
                'games_completed' => 0,
                'streak' => 0,
                'can_play' => true,
                'next_challenge_formatted' => 'Available now!',
                'is_new_player' => true
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting daily challenge stats: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the list of images a user has already seen in daily challenges
 * 
 * @param PDO $conn Database connection
 * @param string $username Username to check
 * @return array|bool Array with 'real' and 'ai' arrays of image IDs or false on failure
 */
function d_get_user_seen_images($conn, $username) {
    try {
        // Check if the table exists and create it if it doesn't
        if (!d_ensure_history_table_exists($conn)) {
            error_log("Failed to create or verify daily_challenge_history table");
            return false;
        }
        
        $stmt = $conn->prepare("SELECT * FROM daily_challenge_history WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $history = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($history) {
            // Parse the stored image IDs
            $seen_real_images = !empty($history['seen_real_images']) 
                ? explode(',', $history['seen_real_images']) 
                : [];
            
            $seen_ai_images = !empty($history['seen_ai_images']) 
                ? explode(',', $history['seen_ai_images']) 
                : [];
            
            return [
                'real' => $seen_real_images,
                'ai' => $seen_ai_images
            ];
        } else {
            // No history yet, return empty arrays
            return [
                'real' => [],
                'ai' => []
            ];
        }
    } catch (PDOException $e) {
        error_log("Error getting user seen images: " . $e->getMessage());
        return false;
    }
}

/**
 * Record images as seen by a user
 * 
 * @param PDO $conn Database connection
 * @param string $username Username
 * @param array $real_image_ids Real image IDs to record
 * @param array $ai_image_ids AI image IDs to record
 * @return bool True on success, false on failure
 */
function d_record_images_as_seen($conn, $username, $real_image_ids, $ai_image_ids) {
    try {
        // Ensure the history table exists
        if (!d_ensure_history_table_exists($conn)) {
            error_log("Failed to create or verify daily_challenge_history table");
            return false;
        }
        
        // Get existing history
        $existing = d_get_user_seen_images($conn, $username);
        
        if ($existing) {
            // Combine existing and new image IDs without duplicates
            $all_real_images = array_unique(array_merge($existing['real'], $real_image_ids));
            $all_ai_images = array_unique(array_merge($existing['ai'], $ai_image_ids));
            
            // Convert arrays to comma-separated strings
            $real_images_string = implode(',', $all_real_images);
            $ai_images_string = implode(',', $all_ai_images);
            
            // Check if there's an existing record for this user
            $stmt = $conn->prepare("SELECT COUNT(*) FROM daily_challenge_history WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $exists = ($stmt->fetchColumn() > 0);
            
            if ($exists) {
                // Update existing record
                $stmt = $conn->prepare("
                    UPDATE daily_challenge_history 
                    SET seen_real_images = :real_images,
                        seen_ai_images = :ai_images,
                        last_updated = CURRENT_TIMESTAMP
                    WHERE username = :username
                ");
            } else {
                // Create new record
                $stmt = $conn->prepare("
                    INSERT INTO daily_challenge_history 
                    (username, seen_real_images, seen_ai_images) 
                    VALUES (:username, :real_images, :ai_images)
                ");
            }
            
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':real_images', $real_images_string);
            $stmt->bindParam(':ai_images', $ai_images_string);
            $stmt->execute();
            
            return true;
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("Error recording images as seen: " . $e->getMessage());
        return false;
    }
}

/**
 * Ensure the daily challenge history table exists
 * 
 * @param PDO $conn Database connection
 * @return bool True on success, false on failure
 */
function d_ensure_history_table_exists($conn) {
    try {
        // Check if table exists
        $table_exists = false;
        try {
            $check = $conn->query("SELECT 1 FROM daily_challenge_history LIMIT 1");
            $table_exists = ($check !== false);
        } catch (PDOException $e) {
            // Table doesn't exist, will create it
            $table_exists = false;
        }
        
        if (!$table_exists) {
            // Create the table
            $sql = "CREATE TABLE IF NOT EXISTS daily_challenge_history (
                username TEXT PRIMARY KEY,
                seen_real_images TEXT DEFAULT '',
                seen_ai_images TEXT DEFAULT '',
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
            $conn->exec($sql);
            
            error_log("Created daily_challenge_history table");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error ensuring daily challenge history table exists: " . $e->getMessage());
        return false;
    }
}

/**
 * Get a random pair of HARD difficulty images for the final round,
 * prioritizing unseen images if available
 * 
 * @param PDO $conn Database connection
 * @param string $username Username (or null if not logged in)
 * @param array $exclude_ids IDs to exclude from selection
 * @return array Array with two file paths [real_image_path, ai_image_path]
 */
function d_get_final_round_hard_images($conn, $username = null, $exclude_ids = []) {
    try {
        // Get user's history of seen images if available
        $seen_real_images = [];
        $seen_ai_images = [];
        
        if ($username) {
            $seen_images = d_get_user_seen_images($conn, $username);
            if ($seen_images) {
                $seen_real_images = $seen_images['real'];
                $seen_ai_images = $seen_images['ai'];
            }
        }
        
        // Build exclude IDs list, combining explicitly excluded IDs and seen images
        $all_exclude_real = array_unique(array_merge($exclude_ids, $seen_real_images));
        $all_exclude_ai = array_unique(array_merge($exclude_ids, $seen_ai_images));
        
        // Get count of HARD real images
        $stmt = $conn->prepare("SELECT COUNT(*) FROM images WHERE type = 'real' AND difficulty = 'hard'");
        $stmt->execute();
        $hard_real_count = $stmt->fetchColumn();
        
        // Get count of HARD AI images
        $stmt = $conn->prepare("SELECT COUNT(*) FROM images WHERE type = 'ai' AND difficulty = 'hard'");
        $stmt->execute();
        $hard_ai_count = $stmt->fetchColumn();
        
        // Check if we can exclude seen images
        $can_exclude_real = !empty($seen_real_images) && count($seen_real_images) < $hard_real_count;
        $can_exclude_ai = !empty($seen_ai_images) && count($seen_ai_images) < $hard_ai_count;
        
        // Get a HARD real image, excluding seen ones if possible
        $query = "SELECT id, filename FROM images WHERE type = 'real' AND difficulty = 'hard' ";
        
        if ($can_exclude_real) {
            $placeholders = implode(',', array_fill(0, count($all_exclude_real), '?'));
            $query .= "AND id NOT IN ($placeholders) ";
        }
        
        $query .= "ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters if excluding IDs
        if ($can_exclude_real) {
            $i = 1;
            foreach ($all_exclude_real as $id) {
                $stmt->bindValue($i++, $id);
            }
        }
        
        $stmt->execute();
        $real_image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no HARD real image found with exclusions, try without exclusions
        if (!$real_image) {
            $stmt = $conn->prepare("
                SELECT id, filename FROM images 
                WHERE type = 'real' AND difficulty = 'hard'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute();
            $real_image = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get a HARD AI image, excluding seen ones if possible
        $query = "SELECT id, filename FROM images WHERE type = 'ai' AND difficulty = 'hard' ";
        
        if ($can_exclude_ai) {
            $placeholders = implode(',', array_fill(0, count($all_exclude_ai), '?'));
            $query .= "AND id NOT IN ($placeholders) ";
        }
        
        $query .= "ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $conn->prepare($query);
        
        // Bind parameters if excluding IDs
        if ($can_exclude_ai) {
            $i = 1;
            foreach ($all_exclude_ai as $id) {
                $stmt->bindValue($i++, $id);
            }
        }
        
        $stmt->execute();
        $ai_image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no HARD AI image found with exclusions, try without exclusions
        if (!$ai_image) {
            $stmt = $conn->prepare("
                SELECT id, filename FROM images 
                WHERE type = 'ai' AND difficulty = 'hard'
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute();
            $ai_image = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Ensure we found both image types
        if ($real_image && $ai_image) {
            // Record these images as seen
            if ($username) {
                d_record_images_as_seen($conn, $username, [$real_image['id']], [$ai_image['id']]);
            }
            
            // Construct paths and return them
            $real_image_path = 'static/images/real/' . $real_image['filename'];
            $ai_image_path = 'static/images/ai/' . $ai_image['filename'];
            
            return [$real_image_path, $ai_image_path];
        } else {
            error_log("Could not get both HARD image types for the final round");
            
            // Fall back to regular d_get_random_image_pair with hard difficulty
            return d_get_random_image_pair($exclude_ids, 'hard');
        }
    } catch (PDOException $e) {
        error_log("Error getting final round hard images: " . $e->getMessage());
        
        // Fall back to regular d_get_random_image_pair with hard difficulty
        return d_get_random_image_pair($exclude_ids, 'hard');
    }
}

/**
 * Ensure the daily challenge completion table exists
 * 
 * @param PDO $conn Database connection
 * @return bool True on success, false on failure
 */
function d_ensure_completion_table_exists($conn) {
    try {
        // Check if table exists
        $table_exists = false;
        try {
            $check = $conn->query("SELECT 1 FROM daily_challenge_completion LIMIT 1");
            $table_exists = ($check !== false);
        } catch (PDOException $e) {
            // Table doesn't exist, will create it
            $table_exists = false;
        }
        
        if (!$table_exists) {
            // Create the table
            $sql = "CREATE TABLE IF NOT EXISTS daily_challenge_completion (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL,
                challenge_date TEXT NOT NULL,
                score INTEGER NOT NULL DEFAULT 0,
                remaining_lives INTEGER NOT NULL DEFAULT 0,
                total_rounds INTEGER NOT NULL DEFAULT 10,
                completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(username, challenge_date)
            )";
            $conn->exec($sql);
            
            error_log("Created daily_challenge_completion table");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error creating daily challenge completion table: " . $e->getMessage());
        return false;
    }
}

/**
 * Save daily challenge completion details
 * 
 * @param string $username The username
 * @param int $score Final score
 * @param int $remaining_lives Number of lives remaining
 * @param int $total_rounds Total number of rounds played
 * @return bool True on success, false on failure
 */
function d_save_challenge_completion($username, $score, $remaining_lives, $total_rounds = 10) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Ensure completion table exists
        if (!d_ensure_completion_table_exists($conn)) {
            error_log("Failed to create or verify daily_challenge_completion table");
            return false;
        }
        
        // Get today's date
        $challenge_date = date('Y-m-d');
        
        // Check if there's already a record for today
        $stmt = $conn->prepare("SELECT id FROM daily_challenge_completion 
                              WHERE username = :username AND challenge_date = :challenge_date");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':challenge_date', $challenge_date);
        $stmt->execute();
        $exists = ($stmt->fetchColumn() > 0);
        
        if ($exists) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE daily_challenge_completion 
                SET score = :score,
                    remaining_lives = :remaining_lives,
                    total_rounds = :total_rounds,
                    completed_at = CURRENT_TIMESTAMP
                WHERE username = :username AND challenge_date = :challenge_date
            ");
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO daily_challenge_completion 
                (username, challenge_date, score, remaining_lives, total_rounds)
                VALUES (:username, :challenge_date, :score, :remaining_lives, :total_rounds)
            ");
        }
        
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':challenge_date', $challenge_date);
        $stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $stmt->bindParam(':remaining_lives', $remaining_lives, PDO::PARAM_INT);
        $stmt->bindParam(':total_rounds', $total_rounds, PDO::PARAM_INT);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Error saving challenge completion: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the most recent daily challenge completion data for a user
 * 
 * @param string $username The username to check
 * @return array|bool Completion data or false if none found
 */
function d_get_latest_challenge_completion($username) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Ensure completion table exists
        if (!d_ensure_completion_table_exists($conn)) {
            error_log("Failed to create or verify daily_challenge_completion table");
            return false;
        }
        
        // Get the latest completion record
        $stmt = $conn->prepare("
            SELECT * FROM daily_challenge_completion 
            WHERE username = :username
            ORDER BY completed_at DESC
            LIMIT 1
        ");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $completion = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($completion) {
            return $completion;
        } else {
            return false;
        }
    } catch (PDOException $e) {
        error_log("Error getting challenge completion: " . $e->getMessage());
        return false;
    }
}

/**
 * Create or update the daily challenge progress record for a user
 * 
 * @param string $username The username to update
 * @param int $current_round The current round number
 * @param int $lives_remaining The number of lives remaining
 * @param int $score The current score
 * @param bool $game_over Whether the game is over
 * @param string $images_seen Comma-separated list of image IDs that have been seen
 * @return bool True on success, false on failure
 */
function d_save_daily_challenge_progress($username, $current_round, $lives_remaining, $score, $game_over = false, $images_seen = '') {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Get today's date
        $game_date = date('Y-m-d');
        
        // Check if a record already exists for this user and date
        $stmt = $conn->prepare("
            SELECT id FROM daily_challenge_progress 
            WHERE username = :username AND game_date = :game_date
        ");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':game_date', $game_date);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE daily_challenge_progress 
                SET current_round = :current_round,
                    lives_remaining = :lives_remaining,
                    score = :score,
                    game_over = :game_over,
                    images_seen = :images_seen,
                    updated_at = CURRENT_TIMESTAMP
                WHERE username = :username AND game_date = :game_date
            ");
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO daily_challenge_progress 
                (username, game_date, current_round, lives_remaining, score, game_over, images_seen)
                VALUES (:username, :game_date, :current_round, :lives_remaining, :score, :game_over, :images_seen)
            ");
        }
        
        // Bind parameters
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':game_date', $game_date);
        $stmt->bindParam(':current_round', $current_round, PDO::PARAM_INT);
        $stmt->bindParam(':lives_remaining', $lives_remaining, PDO::PARAM_INT);
        $stmt->bindParam(':score', $score, PDO::PARAM_INT);
        $stmt->bindParam(':game_over', $game_over, PDO::PARAM_BOOL);
        $stmt->bindParam(':images_seen', $images_seen);
        
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error saving daily challenge progress: " . $e->getMessage());
        return false;
    }
}

/**
 * Get the daily challenge progress for a user
 * 
 * @param string $username The username to check
 * @return array|bool Progress data or false if no record exists
 */
function d_get_daily_challenge_progress($username) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Get today's date
        $game_date = date('Y-m-d');
        
        $stmt = $conn->prepare("
            SELECT * FROM daily_challenge_progress 
            WHERE username = :username AND game_date = :game_date
        ");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':game_date', $game_date);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting daily challenge progress: " . $e->getMessage());
        return false;
    }
}

/**
 * Record that a user has viewed a specific image in the Daily Challenge
 * 
 * @param string $username The username to update
 * @param int $image_id The ID of the image viewed
 * @return bool True on success, false on failure
 */
function d_record_daily_challenge_image_view($username, $image_id) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Get the image data to determine type
        $stmt = $conn->prepare("SELECT type FROM images WHERE id = :id");
        $stmt->bindParam(':id', $image_id);
        $stmt->execute();
        $image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$image) {
            error_log("Cannot record image view - image ID $image_id not found");
            return false;
        }
        
        // Determine which array to update based on image type
        $real_image_ids = [];
        $ai_image_ids = [];
        
        if ($image['type'] === 'real') {
            $real_image_ids[] = $image_id;
        } else {
            $ai_image_ids[] = $image_id;
        }
        
        // Use existing function to record this image as seen
        d_record_images_as_seen($conn, $username, $real_image_ids, $ai_image_ids);
        
        // Also update the images_seen field in the progress table
        $progress = d_get_daily_challenge_progress($username);
        if ($progress) {
            $images_seen = $progress['images_seen'];
            $images_array = $images_seen ? explode(',', $images_seen) : [];
            if (!in_array($image_id, $images_array)) {
                $images_array[] = $image_id;
            }
            $new_images_seen = implode(',', $images_array);
            
            // Update the progress record with the new image
            $stmt = $conn->prepare("
                UPDATE daily_challenge_progress 
                SET images_seen = :images_seen
                WHERE username = :username AND game_date = :game_date
            ");
            $game_date = date('Y-m-d');
            $stmt->bindParam(':images_seen', $new_images_seen);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':game_date', $game_date);
            $stmt->execute();
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error recording daily challenge image view: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all available avatars for Daily Challenge bonus rewards
 * 
 * @return array The array of avatar emojis
 */
function d_get_available_avatars() {
    return [
        // Original set
        '😝', '🍍', '💪', '💋', '👾', '👽', '💩', '👨‍💻', '👩‍💻', '🤹', 
        '🏍️', '🏎️', '💅', '🦴', '👁️‍🗨️', '🦷', '👁️', '💥', '💌', '💣', 
        '👘', '🎩', '🕶️', '🧢', '👑', '👛', '👗', '🥽', '🦓', '🐺', 
        '🦡', '🐾', '🦚', '🦜', '🐧', '🐦', '🐙', '🍁', '🌲', '🌴', 
        '🍊', '🍉', '🍓', '🍇', '🍋', '🍎', '🥥', '🍿', '🥧', '🍬', 
        '🍯', '🍭', '🏝️', '🌄', '🌉', '🌅', '♨️', '🌃', '🌆', '🌌', 
        '🚄', '🚗', '🛴', '🚁', '🛰️', '🚀', '🛸', '🎁', '🎆', '🎟️',
        
        // New set requested by user
        '🌮', '❤️', '✅', '✔️', '💫', '🗿', '🍂', '📟', '⚙️', '🛸', 
        '🥨', '🪁', '🧗‍♀️', '🦦', '🧩', '🧇', '☕', '🥏', '🪐', '🧁', 
        '🩰', '🪀', '🦥', '🦩', '🧊', '🥯', '🥒', '📱', '📸', '📅', 
        '🏆', '🗳️', '🌟', '💬', '💙', '🎀', '🏴‍☠️', '👧', '🤪', '🤭', '🧐',
        
        // Additional avatars for daily challenges (April 2025)
        '🏃‍♀️', '🦆', '🐢', '🌷', '🌼', '🌺', '🍄', '🥑', '🍕', '🏀',
        '🎲', '🎭', '🎬', '🎼', '🎵', '🎹', '🥁', '🚲', '🛹', '🏄‍♂️'
    ];
}

/**
 * Get the daily challenge avatar rewards for a user
 * 
 * @param string $username The username to check
 * @return array The avatars this user has earned
 */
function d_get_user_avatar_rewards($username) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Get all avatar rewards for this user
        $stmt = $conn->prepare("
            SELECT avatar FROM daily_challenge_avatar_rewards 
            WHERE username = :username
        ");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $rewards = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rewards[] = $row['avatar'];
        }
        
        return $rewards;
    } catch (PDOException $e) {
        error_log("Error getting user avatar rewards: " . $e->getMessage());
        return [];
    }
}

/**
 * Check if a user has earned all available avatars
 * 
 * @param string $username The username to check
 * @return bool True if all avatars have been earned, false otherwise
 */
function d_has_all_avatars($username) {
    $user_avatars = d_get_user_avatar_rewards($username);
    $all_avatars = d_get_available_avatars();
    
    return count($user_avatars) >= count($all_avatars);
}

/**
 * Award a random avatar reward to a user
 * 
 * @param string $username The username to award
 * @return array|bool The awarded avatar or false on failure
 */
function d_award_random_avatar($username) {
    // Get the user's existing avatars
    $user_avatars = d_get_user_avatar_rewards($username);
    
    // Get all available avatars
    $all_avatars = d_get_available_avatars();
    
    // Find avatars the user doesn't have yet
    $available = array_diff($all_avatars, $user_avatars);
    
    // If no avatars are available, return false
    if (empty($available)) {
        return false;
    }
    
    // Select a random avatar from available ones
    $random_avatar = array_values($available)[array_rand($available)];
    
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Insert the new avatar reward
        $stmt = $conn->prepare("
            INSERT INTO daily_challenge_avatar_rewards (username, avatar)
            VALUES (:username, :avatar)
            ON CONFLICT (username, avatar) DO NOTHING
        ");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':avatar', $random_avatar);
        $stmt->execute();
        
        return $random_avatar;
    } catch (PDOException $e) {
        error_log("Error awarding avatar reward: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset a user's daily challenge streak to zero
 * 
 * @param string $username The username to reset streak for
 * @return bool True on success, false on failure
 */
function d_reset_streak($username) {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Reset streak to zero
        $stmt = $conn->prepare("
            UPDATE daily_challenge 
            SET streak = 0
            WHERE username = :username
        ");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        return true;
    } catch (PDOException $e) {
        error_log("Error resetting streak: " . $e->getMessage());
        return false;
    }
}

/**
 * Get four images for the daily challenge bonus game (one real, three AI)
 * 
 * @param string $username The username to check for previously seen images
 * @param string $difficulty Optional difficulty level (easy, medium, hard)
 * @return array|bool Array with image data or false on failure
 */
function d_get_bonus_game_images($username, $difficulty = 'medium') {
    // Connect to database
    $conn = get_db_connection();
    
    try {
        // Get one real image the user hasn't seen before if possible
        $stmt = $conn->prepare("
            SELECT i.id, i.filename, i.type, i.difficulty, i.category
            FROM images i
            LEFT JOIN user_seen_images u ON i.id = u.image_id AND u.username = :username AND u.image_type = 'real'
            WHERE i.type = 'real' 
            AND i.difficulty = :difficulty
            AND u.image_id IS NULL
            ORDER BY RANDOM()
            LIMIT 1
        ");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':difficulty', $difficulty);
        $stmt->execute();
        $real_image = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no unseen images, get a random one
        if (!$real_image) {
            $stmt = $conn->prepare("
                SELECT id, filename, type, difficulty, category
                FROM images
                WHERE type = 'real' AND difficulty = :difficulty
                ORDER BY RANDOM()
                LIMIT 1
            ");
            $stmt->bindParam(':difficulty', $difficulty);
            $stmt->execute();
            $real_image = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // Get three AI images the user hasn't seen before if possible
        $stmt = $conn->prepare("
            SELECT i.id, i.filename, i.type, i.difficulty, i.category
            FROM images i
            LEFT JOIN user_seen_images u ON i.id = u.image_id AND u.username = :username AND u.image_type = 'ai'
            WHERE i.type = 'ai' 
            AND i.difficulty = :difficulty
            AND u.image_id IS NULL
            ORDER BY RANDOM()
            LIMIT 3
        ");
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':difficulty', $difficulty);
        $stmt->execute();
        $ai_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // If not enough unseen AI images, get random ones
        if (count($ai_images) < 3) {
            $stmt = $conn->prepare("
                SELECT id, filename, type, difficulty, category
                FROM images
                WHERE type = 'ai' AND difficulty = :difficulty
                ORDER BY RANDOM()
                LIMIT 3
            ");
            $stmt->bindParam(':difficulty', $difficulty);
            $stmt->execute();
            $ai_images = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Combine and shuffle the images
        $images = array_merge([$real_image], $ai_images);
        shuffle($images);
        
        // Add the correct index
        $correct_index = null;
        foreach ($images as $index => $image) {
            if ($image['type'] === 'real') {
                $correct_index = $index;
                break;
            }
        }
        
        return [
            'images' => $images,
            'correct_index' => $correct_index
        ];
    } catch (PDOException $e) {
        error_log("Error getting bonus game images: " . $e->getMessage());
        return false;
    }
}
?>