<?php
/**
 * User model - PHP version
 */

require_once __DIR__ . '/../includes/database.php';

/**
 * Get user by ID
 * 
 * @param int $id User ID
 * @return array|null User data or null if not found
 */
// Function already defined in auth_functions.php, using if statement to prevent redeclaration
if (!function_exists('get_user_by_id')) {
    function get_user_by_id($id) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}

/**
 * Get user by username
 * 
 * @param string $username Username
 * @return array|null User data or null if not found
 */
if (!function_exists('get_user_by_username')) {
    function get_user_by_username($username) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}

/**
 * Get user by email
 * 
 * @param string $email Email address
 * @return array|null User data or null if not found
 */
if (!function_exists('get_user_by_email')) {
    function get_user_by_email($email) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    }
}

/**
 * Create a new user
 * 
 * @param string $username Username
 * @param string $email Email address
 * @param string $password Plain password (will be hashed)
 * @param bool $is_admin Is admin user
 * @return int|false User ID or false on failure
 */
if (!function_exists('create_user')) {
    function create_user($username, $email, $password, $is_admin = false) {
        $db = get_db_connection();

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Valid email address is required'
                ];
            }

        $stmt = $db->prepare("
            INSERT INTO users (
                username, 
                email, 
                password_hash, 
                is_admin, 
                created_at,
                avatar
            ) VALUES (
                :username, 
                :email, 
                :password_hash, 
                :is_admin, 
                :created_at,
                NULL
            )
        ");

        $now = date('Y-m-d H:i:s');

        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
        $stmt->bindParam(':is_admin', $is_admin, PDO::PARAM_BOOL);
        $stmt->bindParam(':created_at', $now, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return $db->lastInsertId();
        }

        return false;
    }
}

/**
 * Update user information
 * 
 * @param int $id User ID
 * @param array $data Associative array of fields to update
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user')) {
    function update_user($id, $data) {
        $db = get_db_connection();

        // Build the SQL query dynamically based on provided data
        $sql = "UPDATE users SET ";
        $updates = [];
        $params = [':id' => $id];

        // Add each field to the query
        foreach ($data as $field => $value) {
            if ($field == 'id') continue; // Skip ID field

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
            if ($param == ':is_admin') {
                $stmt->bindValue($param, $value, PDO::PARAM_BOOL);
            } elseif ($param == ':id') {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
        }

        return $stmt->execute();
    }
}

/**
 * Update user password
 * 
 * @param int $id User ID
 * @param string $password New plain password (will be hashed)
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_password')) {
    function update_user_password($id, $password) {
        $db = get_db_connection();

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");
        $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Activate a user account
     * 
     * @param int $id User ID
     * @return bool True if successful, false otherwise
     */
    function activate_user($id) {
        $db = get_db_connection();

        $stmt = $db->prepare("UPDATE users SET active = TRUE WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Deactivate a user account
     * 
     * @param int $id User ID
     * @return bool True if successful, false otherwise
     */
    function deactivate_user($id) {
        $db = get_db_connection();

        $stmt = $db->prepare("UPDATE users SET active = FALSE WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Toggle user active status
     * 
     * @param int $id User ID
     * @return bool True if successful, false otherwise
     */
    function toggle_user_active_status($id) {
        $db = get_db_connection();

        // First get current status
        $stmt = $db->prepare("SELECT active FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $new_status = !$user['active'];

            $stmt = $db->prepare("UPDATE users SET active = :active WHERE id = :id");
            $stmt->bindParam(':active', $new_status, PDO::PARAM_BOOL);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            return $stmt->execute();
        }

        return false;
    }
}

/**
 * Verify user password
 * 
 * @param int $id User ID
 * @param string $password Plain password to check
 * @return bool True if password matches, false otherwise
 */
if (!function_exists('verify_user_password')) {
    function verify_user_password($id, $password) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            return false;
        }

        return password_verify($password, $user['password_hash']);
    }
}

/**
 * Get user's highest score from leaderboard entries
 * 
 * @param int $user_id User ID
 * @return int Highest score or 0 if no games played
 */
if (!function_exists('get_user_highest_score')) {
    function get_user_highest_score($user_id) {
        $db = get_db_connection();

        $stmt = $db->prepare("
            SELECT MAX(score) as highest_score 
            FROM leaderboard 
            WHERE user_id = :user_id
        ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['highest_score'] ?: 0;
    }
}

/**
 * Get total number of games played by the user
 * 
 * @param int $user_id User ID
 * @return int Total number of games played
 */
if (!function_exists('get_user_total_games')) {
    function get_user_total_games($user_id) {
        $db = get_db_connection();

        // First check if games_played field is available in users table
        try {
            $stmt = $db->prepare("
                SELECT games_played
                FROM users
                WHERE id = :user_id
            ");
            
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && isset($result['games_played'])) {
                return (int)$result['games_played'];
            }
        } catch (Exception $e) {
            // Column doesn't exist, fall back to counting games
            error_log("Error getting games_played from users table: " . $e->getMessage());
        }
        
        // Fall back to counting games
        $stmt = $db->prepare("
            SELECT COUNT(*) as total 
            FROM games 
            WHERE user_id = :user_id
        ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?: 0;
    }
}

/**
 * Get user's game history
 * 
 * @param int $user_id User ID
 * @param int $limit Number of games to retrieve
 * @return array List of user's games
 */
if (!function_exists('get_user_game_history')) {
    function get_user_game_history($user_id, $limit = 5) {
        $db = get_db_connection();

        $stmt = $db->prepare("
            SELECT * 
            FROM games 
            WHERE user_id = :user_id 
            AND score > 0
            ORDER BY created_at DESC 
            LIMIT :limit
        ");

        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Get user's best leaderboard entries
 * 
 * @param int $user_id User ID
 * @param int $limit Number of entries to retrieve
 * @return array List of user's best leaderboard entries
 */
if (!function_exists('get_user_best_leaderboard_entries')) {
    function get_user_best_leaderboard_entries($user_id, $limit = 10) {
        $db = get_db_connection();

        try {
            $stmt = $db->prepare("
                SELECT *
                FROM leaderboard 
                WHERE user_id = ?
                ORDER BY score DESC, created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting user's best leaderboard entries: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Check if username is available
 * 
 * @param string $username Username to check
 * @return bool True if username is available, false if already taken
 */
if (!function_exists('is_username_available')) {
    function is_username_available($username) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT 1 FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() === 0;
    }
}

/**
 * Check if email is available
 * 
 * @param string $email Email to check
 * @return bool True if email is available, false if already taken
 */
if (!function_exists('is_email_available')) {
    function is_email_available($email) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT 1 FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() === 0;
    }
}

/**
 * Get all users
 * 
 * @param int $limit Number of users to retrieve
 * @param int $offset Offset for pagination
 * @return array List of users
 */
if (!function_exists('get_all_users')) {
    function get_all_users($limit = 100, $offset = 0) {
        $db = get_db_connection();

        $stmt = $db->prepare("
            SELECT * 
            FROM users 
            ORDER BY id 
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

/**
 * Count total users
 * 
 * @return int Total number of users
 */
if (!function_exists('count_total_users')) {
    function count_total_users() {
        $db = get_db_connection();

        $stmt = $db->query("SELECT COUNT(*) as total FROM users");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['total'] ?: 0;
    }
}

/**
 * Verify user credentials for login
 * 
 * @param string $username Username or email
 * @param string $password Plain password
 * @return array|false User data if credentials are valid, false otherwise
 */
if (!function_exists('verify_user_credentials')) {
    function verify_user_credentials($username, $password) {
        $db = get_db_connection();

        // Check if the input is an email
        $is_email = filter_var($username, FILTER_VALIDATE_EMAIL);

        if ($is_email) {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $username, PDO::PARAM_STR);
        } else {
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        }

        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        // Check if user is active (if the column exists)
        if (array_key_exists('active', $user) && $user['active'] === false) {
            return false; // Account is deactivated
        }

        // Verify password
        if (password_verify($password, $user['password_hash'])) {
            return $user;
        }

        return false;
    }
}

/**
 * Set a user's avatar
 * 
 * @param int $user_id User ID
 * @param string $avatar Avatar identifier
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_avatar')) {
    function update_user_avatar($user_id, $avatar) {
        $db = get_db_connection();

        $stmt = $db->prepare("UPDATE users SET avatar = :avatar WHERE id = :user_id");
        $stmt->bindParam(':avatar', $avatar, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

/**
 * Get a user's avatar
 * 
 * @param int $user_id User ID
 * @return string|null Avatar identifier or null if not set
 */
if (!function_exists('get_user_avatar')) {
    function get_user_avatar($user_id) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT avatar FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['avatar'] ?? null;
    }
}

/**
 * Update user email
 * 
 * @param int $user_id User ID
 * @param string $email New email address
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_email')) {
    function update_user_email($user_id, $email) {
        $db = get_db_connection();

        $stmt = $db->prepare("UPDATE users SET email = :email WHERE id = :user_id");
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

/**
 * Update user country
 * 
 * @param int $user_id User ID
 * @param string $country Country code (ISO 2-letter code)
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_country')) {
    function update_user_country($user_id, $country) {
        $db = get_db_connection();

        $stmt = $db->prepare("UPDATE users SET country = :country WHERE id = :user_id");
        $stmt->bindParam(':country', $country, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

/**
 * Update user timer visibility preference
 * 
 * @param int $user_id User ID
 * @param bool $hide_timer Whether to hide the timer (1) or show it (0)
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_timer_preference')) {
    function update_user_timer_preference($user_id, $hide_timer) {
        $db = get_db_connection();

        $hide_timer_int = $hide_timer ? 1 : 0;

        $stmt = $db->prepare("UPDATE users SET hide_timer = :hide_timer WHERE id = :user_id");
        $stmt->bindParam(':hide_timer', $hide_timer_int, PDO::PARAM_INT);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}



/**
 * Update user's preference to hide username on leaderboard
 * 
 * @param int $user_id User ID
 * @param int $hide_username 1 to hide, 0 to show
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_hide_username_preference')) {
    function update_user_hide_username_preference($user_id, $hide_username) {
        $db = get_db_connection();
        
        try {
            // Check if the hide_username column exists, if not, add it
            try {
                $stmt = $db->prepare("SELECT hide_username FROM users LIMIT 1");
                $stmt->execute();
            } catch (Exception $e) {
                // Column likely doesn't exist, add it
                $db->exec("ALTER TABLE users ADD COLUMN hide_username BOOLEAN DEFAULT 0");
                error_log('Added hide_username column to users table');
            }
            
            $hide_username_int = $hide_username ? 1 : 0;
            
            $stmt = $db->prepare("UPDATE users SET hide_username = :hide_username WHERE id = :user_id");
            $stmt->bindParam(':hide_username', $hide_username_int, PDO::PARAM_INT);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log('Error updating username hiding preference: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Update user login count and last login timestamp
 * 
 * @param int $user_id User ID
 * @return bool True on success, false on failure
 */
if (!function_exists('update_user_login_count')) {
    function update_user_login_count($user_id) {
        $db = get_db_connection();

        $now = date('Y-m-d H:i:s');

        $stmt = $db->prepare("
            UPDATE users 
            SET login_count = login_count + 1, 
                last_login_at = :last_login_at 
            WHERE id = :user_id
        ");

        $stmt->bindParam(':last_login_at', $now, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}

/**
 * Get user login count
 * 
 * @param int $user_id User ID
 * @return int Login count or 0 if not found
 */
if (!function_exists('get_user_login_count')) {
    function get_user_login_count($user_id) {
        $db = get_db_connection();

        $stmt = $db->prepare("SELECT login_count FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['login_count'] ?? 0;
    }
}

/**
 * Record user IP address during login
 * 
 * @param int $user_id User ID
 * @param string $ip_address User's IP address
 * @return bool True on success, false on failure
 */
if (!function_exists('record_user_ip_address')) {
    function record_user_ip_address($user_id, $ip_address = null) {
        // Include IP functions if not already included
        if (!function_exists('get_client_ip')) {
            require_once __DIR__ . '/../includes/ip_functions.php';
        }

        if (empty($ip_address)) {
            $ip_address = get_client_ip();
        }

        if (empty($ip_address) || $ip_address == 'UNKNOWN') {
            return false;
        }

        $db = get_db_connection();

        try {
            // Update user's last IP address
            $stmt = $db->prepare("
                UPDATE users 
                SET last_ip_address = :ip_address
                WHERE id = :user_id
            ");

            $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();

            // Check if this IP is already recorded for this user
            $stmt = $db->prepare("
                SELECT id 
                FROM user_ip_addresses 
                WHERE user_id = ? AND ip_address = ?
            ");
            $stmt->execute([$user_id, $ip_address]);

            if ($stmt->fetchColumn()) {
                // IP already recorded, update last_seen
                $stmt = $db->prepare("
                    UPDATE user_ip_addresses 
                    SET last_seen = CURRENT_TIMESTAMP, 
                        login_count = login_count + 1 
                    WHERE user_id = ? AND ip_address = ?
                ");
                return $stmt->execute([$user_id, $ip_address]);
            } else {
                // New IP address for this user
                $stmt = $db->prepare("
                    INSERT INTO user_ip_addresses 
                    (user_id, ip_address, first_seen, last_seen, login_count) 
                    VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)
                ");
                return $stmt->execute([$user_id, $ip_address]);
            }
        } catch (PDOException $e) {
            error_log('Error recording IP address: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Check if an IP address is blocked
 * 
 * @param string $ip_address IP address to check
 * @return bool True if IP is blocked, false otherwise
 */
if (!function_exists('is_ip_blocked')) {
    function is_ip_blocked($ip_address) {
        // Include IP functions if not already included
        if (!function_exists('get_client_ip')) {
            require_once __DIR__ . '/../includes/ip_functions.php';
        }

        if (empty($ip_address)) {
            $ip_address = get_client_ip();
        }

        if (empty($ip_address) || $ip_address == 'UNKNOWN') {
            return false;
        }

        $db = get_db_connection();

        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM user_ip_addresses 
                WHERE ip_address = ? AND is_blocked = 1
            ");
            $stmt->execute([$ip_address]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log("Error checking if IP is blocked: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Block an IP address
 * 
 * @param string $ip_address IP address to block
 * @param string $reason Reason for blocking
 * @return bool True on success, false on failure
 */
if (!function_exists('block_ip_address')) {
    function block_ip_address($ip_address, $reason = '') {
        // Include IP functions if not already included
        if (!function_exists('get_client_ip')) {
            require_once __DIR__ . '/../includes/ip_functions.php';
        }

        if (empty($ip_address)) {
            return false;
        }

        $db = get_db_connection();

        try {
            // Check if the IP exists in the tracking table
            $stmt = $db->prepare("
                SELECT COUNT(*) FROM user_ip_addresses 
                WHERE ip_address = ?
            ");
            $stmt->execute([$ip_address]);
            $exists = $stmt->fetchColumn() > 0;

            if ($exists) {
                // Update all records with this IP to mark as blocked
                $stmt = $db->prepare("
                    UPDATE user_ip_addresses 
                    SET is_blocked = 1, 
                        block_reason = ?, 
                        blocked_at = CURRENT_TIMESTAMP 
                    WHERE ip_address = ?
                ");
                return $stmt->execute([$reason, $ip_address]);
            } else {
                // Insert a new blocked IP record without user association
                $stmt = $db->prepare("
                    INSERT INTO user_ip_addresses 
                    (ip_address, is_blocked, block_reason, blocked_at, first_seen, last_seen) 
                    VALUES (?, 1, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ");
                return $stmt->execute([$ip_address, $reason]);
            }
        } catch (PDOException $e) {
            error_log("Error blocking IP address: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Unblock an IP address
 * 
 * @param string $ip_address IP address to unblock
 * @return bool True on success, false on failure
 */
if (!function_exists('unblock_ip_address')) {
    function unblock_ip_address($ip_address) {
        if (empty($ip_address)) {
            return false;
        }

        $db = get_db_connection();

        try {
            $stmt = $db->prepare("
                UPDATE user_ip_addresses 
                SET is_blocked = 0, 
                    block_reason = NULL, 
                    blocked_at = NULL 
                WHERE ip_address = ?
            ");
            return $stmt->execute([$ip_address]);
        } catch (PDOException $e) {
            error_log("Error unblocking IP address: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get all tracked IP addresses
 * 
 * @return array List of IP addresses with tracking data
 */
if (!function_exists('get_all_ip_addresses')) {
    function get_all_ip_addresses() {
        $db = get_db_connection();

        try {
            $stmt = $db->query("
                SELECT ip.*, u.username, 
                       COUNT(DISTINCT ip.user_id) as user_count
                FROM user_ip_addresses ip
                LEFT JOIN users u ON ip.user_id = u.id
                GROUP BY ip.ip_address
                ORDER BY ip.last_seen DESC
            ");

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error retrieving IP addresses: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get IP addresses that match a specific user
 * 
 * @param int $user_id User ID
 * @return array List of IP addresses matching this user
 */
if (!function_exists('get_user_ip_addresses')) {
    function get_user_ip_addresses($user_id) {
        $db = get_db_connection();

        try {
            $stmt = $db->prepare("
                SELECT * FROM user_ip_addresses
                WHERE user_id = ?
                ORDER BY last_seen DESC
            ");
            $stmt->execute([$user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error retrieving user IP addresses: " . $e->getMessage());
            return [];
        }
    }
}

/**
 * Count total tracked IP addresses
 * 
 * @return int Total number of unique IP addresses
 */
if (!function_exists('count_total_ip_addresses')) {
    function count_total_ip_addresses() {
        $db = get_db_connection();

        try {
            $stmt = $db->query("
                SELECT COUNT(DISTINCT ip_address) as total 
                FROM user_ip_addresses
            ");

            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            error_log("Error counting IP addresses: " . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Count blocked IP addresses
 * 
 * @return int Number of blocked IP addresses
 */
if (!function_exists('count_blocked_ip_addresses')) {
    function count_blocked_ip_addresses() {
        $db = get_db_connection();

        try {
            $stmt = $db->query("
                SELECT COUNT(DISTINCT ip_address) as total 
                FROM user_ip_addresses
                WHERE is_blocked = 1
            ");

            return $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {
            error_log("Error counting blocked IP addresses: " . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Get users by IP address
 * 
 * @param string $ip_address IP address to search for
 * @return array List of users with this IP address
 */
if (!function_exists('get_users_by_ip_address')) {
    function get_users_by_ip_address($ip_address) {
        $db = get_db_connection();

        try {
            $stmt = $db->prepare("
                SELECT u.* 
                FROM users u
                JOIN user_ip_addresses ip ON u.id = ip.user_id
                WHERE ip.ip_address = ?
                GROUP BY u.id
                ORDER BY u.username
            ");
            $stmt->execute([$ip_address]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error retrieving users by IP address: " . $e->getMessage());
            return [];
        }
    }
}