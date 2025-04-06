<?php
/**
 * Authentication functions for Real vs AI application
 * Handles user registration, login, and session management
 */

// Include needed files
require_once 'database.php';

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('register_user')) {
    /**
     * Register a new user
     * 
     * @param string $username The username
     * @param string $email The email address
     * @param string $password The password (plain text)
     * @return array Result of the registration attempt
     */
    function register_user($username, $email, $password) {
        // Validate inputs
        if (!$username || !$email || !$password) {
            return [
                'success' => false,
                'message' => 'All fields are required'
            ];
        }
        
        // Validate username format (alphanumeric, 3-20 characters)
        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            return [
                'success' => false,
                'message' => 'Username must be 3-20 characters and contain only letters, numbers, and underscores'
            ];
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid email address'
            ];
        }
        
        // Validate password strength (min 8 characters, at least one of each: uppercase, lowercase, number)
        if (strlen($password) < 8 ||
            !preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password)) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters and include uppercase, lowercase, and numbers'
            ];
        }
        
        // Check if username or email already exists
        $pdo = get_db_connection();
        
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert the new user
            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, created_at, is_admin) 
                VALUES (?, ?, ?, ?, 0)
            ");
            
            $stmt->execute([
                $username,
                $email,
                $password_hash,
                date('Y-m-d H:i:s')
            ]);
            
            // Get the new user's ID
            $user_id = $pdo->lastInsertId();
            
            return [
                'success' => true,
                'message' => 'Registration successful',
                'user_id' => $user_id
            ];
        } catch (PDOException $e) {
            error_log('Registration error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Database error occurred during registration'
            ];
        }
    }
}

if (!function_exists('login_user')) {
    /**
     * Log in a user
     * 
     * @param string $username The username or email
     * @param string $password The password (plain text)
     * @param bool $remember Whether to remember the user
     * @return array Result of the login attempt
     */
    function login_user($username, $password, $remember = false) {
        // Validate inputs
        if (!$username || !$password) {
            return [
                'success' => false,
                'message' => 'Username/email and password are required'
            ];
        }
        
        // Check if input is email or username
        $is_email = filter_var($username, FILTER_VALIDATE_EMAIL);
        
        // Get the user from database
        $pdo = get_db_connection();
        
        try {
            // Query based on whether input is email or username
            if ($is_email) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            }
            
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if user exists
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid username/email or password'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid username/email or password'
                ];
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['is_admin'] = $user['is_admin'];
            
            // Set remember me cookie if requested
            if ($remember) {
                $token = generate_remember_token();
                $expiry = time() + (30 * 24 * 60 * 60); // 30 days
                
                // Store token in database
                $stmt = $pdo->prepare("
                    INSERT INTO RememberToken (user_id, token, expires_at) 
                    VALUES (?, ?, ?)
                ");
                
                $stmt->execute([
                    $user['id'],
                    $token,
                    date('Y-m-d H:i:s', $expiry)
                ]);
                
                // Set cookie
                setcookie('remember_token', $token, $expiry, '/', '', false, true);
            }
            
            // Update last login time
            $stmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
            $stmt->execute([date('Y-m-d H:i:s'), $user['id']]);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'is_admin' => $user['is_admin']
                ]
            ];
        } catch (PDOException $e) {
            error_log('Login error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Database error occurred during login'
            ];
        }
    }
}

if (!function_exists('logout_user')) {
    /**
     * Log out the current user
     * 
     * @return void
     */
    function logout_user() {
        // Clear session
        $_SESSION = [];
        
        // Delete session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Clear remember me cookie if exists
        if (isset($_COOKIE['remember_token'])) {
            // Remove token from database
            $token = $_COOKIE['remember_token'];
            
            try {
                $pdo = get_db_connection();
                $stmt = $pdo->prepare("DELETE FROM RememberToken WHERE token = ?");
                $stmt->execute([$token]);
            } catch (PDOException $e) {
                error_log('Error deleting remember token: ' . $e->getMessage());
            }
            
            // Delete cookie
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }
    }
}

if (!function_exists('is_logged_in')) {
    /**
     * Check if a user is currently logged in
     * 
     * @return bool True if a user is logged in
     */
    function is_logged_in() {
        // Check session
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return true;
        }
        
        // Check remember me cookie
        if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
            $user = get_user_by_remember_token($_COOKIE['remember_token']);
            
            if ($user) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                return true;
            }
        }
        
        return false;
    }
}

if (!function_exists('get_logged_in_user')) {
    /**
     * Get the currently logged in user
     * 
     * @return array|bool User data or false if not logged in
     */
    function get_logged_in_user() {
        if (!is_logged_in()) {
            return false;
        }
        
        $user_id = $_SESSION['user_id'];
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id, username, email, is_admin, created_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching logged in user: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('is_admin')) {
    /**
     * Check if the current user is an admin
     * 
     * @return bool True if the user is an admin
     */
    function is_admin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
}

if (!function_exists('get_user_by_id')) {
    /**
     * Get a user by ID
     * 
     * @param int $user_id The user ID
     * @return array|bool User data or false if not found
     */
    function get_user_by_id($user_id) {
        if (!$user_id) {
            return false;
        }
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT id, username, email, is_admin, created_at FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching user by ID: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('get_user_by_remember_token')) {
    /**
     * Get a user by remember token
     * 
     * @param string $token The remember token
     * @return array|bool User data or false if not found
     */
    function get_user_by_remember_token($token) {
        if (!$token) {
            return false;
        }
        
        try {
            $pdo = get_db_connection();
            
            // Get token from database and check if it's valid
            $stmt = $pdo->prepare("
                SELECT t.user_id, t.expires_at, u.id, u.username, u.is_admin
                FROM RememberToken t
                JOIN users u ON t.user_id = u.id
                WHERE t.token = ? AND t.expires_at > ?
            ");
            
            $stmt->execute([$token, date('Y-m-d H:i:s')]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                // Token not found or expired
                return false;
            }
            
            return [
                'id' => $result['id'],
                'username' => $result['username'],
                'is_admin' => $result['is_admin']
            ];
        } catch (PDOException $e) {
            error_log('Error fetching user by remember token: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('generate_remember_token')) {
    /**
     * Generate a unique remember token
     * 
     * @return string The generated token
     */
    function generate_remember_token() {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('update_password')) {
    /**
     * Update a user's password
     * 
     * @param int $user_id The user ID
     * @param string $current_password The current password
     * @param string $new_password The new password
     * @return array Result of the password update attempt
     */
    function update_password($user_id, $current_password, $new_password) {
        if (!$user_id || !$current_password || !$new_password) {
            return [
                'success' => false,
                'message' => 'All fields are required'
            ];
        }
        
        // Validate password strength
        if (strlen($new_password) < 8 ||
            !preg_match('/[A-Z]/', $new_password) ||
            !preg_match('/[a-z]/', $new_password) ||
            !preg_match('/[0-9]/', $new_password)) {
            return [
                'success' => false,
                'message' => 'New password must be at least 8 characters and include uppercase, lowercase, and numbers'
            ];
        }
        
        try {
            $pdo = get_db_connection();
            
            // Get the current user with password hash
            $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Verify current password
            if (!password_verify($current_password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Only update if the verification passed
            if ($user['id'] === $user_id) {
                // Hash the new password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Update the password
                $update_stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $success = $update_stmt->execute([$new_hash, $user_id]);
                
                if ($success) {
                    return [
                        'success' => true,
                        'message' => 'Password updated successfully'
                    ];
                }
            }
            
            return [
                'success' => false,
                'message' => 'Failed to update password'
            ];
        } catch (PDOException $e) {
            error_log('Password update error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred during password update'
            ];
        }
    }
}

if (!function_exists('update_profile')) {
    /**
     * Update a user's profile
     * 
     * @param int $user_id The user ID
     * @param array $data The profile data to update
     * @return array Result of the profile update attempt
     */
    function update_profile($user_id, $data) {
        if (!$user_id || !$data) {
            return [
                'success' => false,
                'message' => 'Invalid user ID or data'
            ];
        }
        
        // Validate email if provided
        if (isset($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'Invalid email address'
                ];
            }
        }
        
        try {
            $pdo = get_db_connection();
            
            // Check if email already exists (if being updated)
            if (isset($data['email'])) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $user_id]);
                
                if ($stmt->rowCount() > 0) {
                    return [
                        'success' => false,
                        'message' => 'Email already in use by another account'
                    ];
                }
            }
            
            // Build update query based on provided data
            $fields = [];
            $values = [];
            
            foreach ($data as $field => $value) {
                // Only allow updating specific fields
                if (in_array($field, ['email', 'display_name', 'bio'])) {
                    $fields[] = "$field = ?";
                    $values[] = $value;
                }
            }
            
            if (empty($fields)) {
                return [
                    'success' => false,
                    'message' => 'No valid fields to update'
                ];
            }
            
            // Add user ID to values
            $values[] = $user_id;
            
            // Update user
            $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully'
            ];
        } catch (PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Database error occurred during profile update'
            ];
        }
    }
}


if (!function_exists('has_completed_tutorial')) {
    function has_completed_tutorial($user_id) {
        if (!$user_id) return false;
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT tutorial_completed FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error checking tutorial completion: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('mark_tutorial_completed')) {
    function mark_tutorial_completed($user_id) {
        if (!$user_id) return false;
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("UPDATE users SET tutorial_completed = 1 WHERE id = ?");
            return $stmt->execute([$user_id]);
        } catch (PDOException $e) {
            error_log("Error marking tutorial complete: " . $e->getMessage());
            return false;
        }
    }
}
