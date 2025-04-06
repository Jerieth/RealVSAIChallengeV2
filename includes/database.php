<?php
/**
 * Database functions for Real vs AI application
 * Handles database connections and query utilities
 */

/**
 * Get a database connection
 * 
 * @return PDO The database connection
 */
if (!function_exists('get_db_connection')) {
    function get_db_connection() {
        static $pdo = null;
        
        if ($pdo === null) {
            // Create PDO instance for SQLite
            try {
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ];
                
                // Database file path
                $db_path = __DIR__ . '/../storage/database.sqlite';
                
                // Ensure the storage directory exists
                $storage_dir = dirname($db_path);
                if (!is_dir($storage_dir)) {
                    mkdir($storage_dir, 0755, true);
                }
                
                // Connect to SQLite database
                $dsn = "sqlite:" . $db_path;
                $pdo = new PDO($dsn, null, null, $options);
                
            } catch (PDOException $e) {
                error_log('Database connection error: ' . $e->getMessage());
                die('Database connection failed: ' . $e->getMessage());
            }
        }
        
        return $pdo;
    }
}

/**
 * Execute a query and return all results
 * 
 * @param string $sql The SQL query
 * @param array $params The query parameters
 * @return array The query results
 */
if (!function_exists('db_query')) {
    function db_query($sql, $params = []) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Query error: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Execute a query and return a single result
 * 
 * @param string $sql The SQL query
 * @param array $params The query parameters
 * @return array|bool The query result or false if no result
 */
if (!function_exists('db_query_single')) {
    function db_query_single($sql, $params = []) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Query error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Execute a query and return a single column value
 * 
 * @param string $sql The SQL query
 * @param array $params The query parameters
 * @param int $column The column index (0-based)
 * @return mixed The column value or false if no result
 */
if (!function_exists('db_query_value')) {
    function db_query_value($sql, $params = [], $column = 0) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn($column);
        } catch (PDOException $e) {
            error_log('Query error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Execute a query and return the number of affected rows
 * 
 * @param string $sql The SQL query
 * @param array $params The query parameters
 * @return int The number of affected rows
 */
if (!function_exists('db_execute')) {
    function db_execute($sql, $params = []) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Query error: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Insert a row into a table and return the ID
 * 
 * @param string $table The table name
 * @param array $data Associative array of column => value pairs
 * @return int|bool The inserted ID or false on failure
 */
if (!function_exists('db_insert')) {
    function db_insert($table, $data) {
        if (empty($data)) {
            return false;
        }
        
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_values($data));
            return $pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log('Insert error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Update a row in a table
 * 
 * @param string $table The table name
 * @param array $data Associative array of column => value pairs
 * @param string $where The WHERE clause
 * @param array $where_params The WHERE clause parameters
 * @return int The number of affected rows
 */
if (!function_exists('db_update')) {
    function db_update($table, $data, $where, $where_params = []) {
        if (empty($data)) {
            return 0;
        }
        
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s",
            $table,
            implode(', ', $set),
            $where
        );
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute(array_merge(array_values($data), $where_params));
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Update error: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Delete rows from a table
 * 
 * @param string $table The table name
 * @param string $where The WHERE clause
 * @param array $params The WHERE clause parameters
 * @return int The number of affected rows
 */
if (!function_exists('db_delete')) {
    function db_delete($table, $where, $params = []) {
        $sql = sprintf("DELETE FROM %s WHERE %s", $table, $where);
        
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log('Delete error: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Begin a transaction
 * 
 * @return bool True on success, false on failure
 */
if (!function_exists('db_begin_transaction')) {
    function db_begin_transaction() {
        try {
            $pdo = get_db_connection();
            return $pdo->beginTransaction();
        } catch (PDOException $e) {
            error_log('Transaction begin error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Commit a transaction
 * 
 * @return bool True on success, false on failure
 */
if (!function_exists('db_commit')) {
    function db_commit() {
        try {
            $pdo = get_db_connection();
            return $pdo->commit();
        } catch (PDOException $e) {
            error_log('Transaction commit error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Rollback a transaction
 * 
 * @return bool True on success, false on failure
 */
if (!function_exists('db_rollback')) {
    function db_rollback() {
        try {
            $pdo = get_db_connection();
            return $pdo->rollBack();
        } catch (PDOException $e) {
            error_log('Transaction rollback error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get the last inserted ID
 * 
 * @param string|null $name Name of the sequence object (if applicable)
 * @return string The last inserted ID
 */
if (!function_exists('db_last_insert_id')) {
    function db_last_insert_id($name = null) {
        try {
            $pdo = get_db_connection();
            return $pdo->lastInsertId($name);
        } catch (PDOException $e) {
            error_log('Last insert ID error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Check if a table exists
 * 
 * @param string $table The table name
 * @return bool True if the table exists, false otherwise
 */
if (!function_exists('db_table_exists')) {
    function db_table_exists($table) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("
                SELECT name FROM sqlite_master 
                WHERE type='table' 
                AND name=?
            ");
            $stmt->execute([$table]);
            return $stmt->fetchColumn() !== false;
        } catch (PDOException $e) {
            error_log('Table check error: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Initialize the database with required tables
 * 
 * @param PDO $pdo The database connection
 * @return void
 */
if (!function_exists('init_database')) {
    function init_database($pdo) {
        try {
            // Enable foreign keys
            $pdo->exec("PRAGMA foreign_keys = ON");

            // Create users table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    is_admin INTEGER DEFAULT 0,
                    avatar TEXT,
                    country TEXT DEFAULT 'US',
                    status TEXT DEFAULT 'active'
                )
            ");
            
            // Create games table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS games (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL UNIQUE,
                    game_mode TEXT NOT NULL,
                    difficulty TEXT,
                    total_turns INTEGER DEFAULT 10,
                    lives INTEGER DEFAULT 3,
                    current_turn INTEGER DEFAULT 0,
                    score INTEGER DEFAULT 0,
                    user_id INTEGER,
                    is_active INTEGER DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    completed_at TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            
            // Create game_sessions table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS game_sessions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL UNIQUE,
                    user_id INTEGER,
                    difficulty TEXT NOT NULL,
                    score INTEGER DEFAULT 0,
                    lives_remaining INTEGER DEFAULT 0,
                    turns_remaining INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    completed_at TIMESTAMP,
                    current_real_image TEXT,
                    current_ai_image TEXT,
                    left_is_real INTEGER DEFAULT 0,
                    time_penalty INTEGER DEFAULT 0,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            
            // Create images table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS images (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    path TEXT NOT NULL,
                    is_real INTEGER NOT NULL,
                    description TEXT DEFAULT NULL,
                    category TEXT,
                    difficulty TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Create game_turns table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS game_turns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    game_session_id INTEGER,
                    turn_number INTEGER NOT NULL,
                    real_image_id INTEGER,
                    ai_image_id INTEGER,
                    user_selection TEXT,
                    is_correct INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (game_session_id) REFERENCES game_sessions(id) ON DELETE CASCADE,
                    FOREIGN KEY (real_image_id) REFERENCES images(id),
                    FOREIGN KEY (ai_image_id) REFERENCES images(id)
                )
            ");
            
            // Create leaderboard table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS leaderboard (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    username TEXT NOT NULL,
                    score INTEGER NOT NULL,
                    difficulty TEXT NOT NULL,
                    game_session_id INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (game_session_id) REFERENCES game_sessions(id) ON DELETE CASCADE
                )
            ");
            
            // Create multiplayer_games table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS multiplayer_games (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL UNIQUE,
                    player1_id INTEGER,
                    player1_name TEXT,
                    player2_id INTEGER,
                    player2_name TEXT,
                    player3_id INTEGER,
                    player3_name TEXT,
                    player4_id INTEGER,
                    player4_name TEXT,
                    total_turns INTEGER DEFAULT 10,
                    current_turn INTEGER DEFAULT 0,
                    status TEXT DEFAULT 'waiting',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    started_at TIMESTAMP,
                    ended_at TIMESTAMP,
                    FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (player3_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (player4_id) REFERENCES users(id) ON DELETE SET NULL
                )
            ");
            
            // Create multiplayer_players table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS multiplayer_players (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    multiplayer_game_id INTEGER,
                    user_id INTEGER,
                    username TEXT NOT NULL,
                    score INTEGER DEFAULT 0,
                    last_turn_played INTEGER DEFAULT 0,
                    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (multiplayer_game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Create multiplayer_turns table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS multiplayer_turns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    multiplayer_game_id INTEGER,
                    turn_number INTEGER NOT NULL,
                    real_image_id INTEGER,
                    ai_image_id INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (multiplayer_game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE,
                    FOREIGN KEY (real_image_id) REFERENCES images(id),
                    FOREIGN KEY (ai_image_id) REFERENCES images(id)
                )
            ");
            
            // Create multiplayer_answers table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS multiplayer_answers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    multiplayer_game_id INTEGER,
                    user_id INTEGER,
                    turn_number INTEGER NOT NULL,
                    user_selection TEXT,
                    is_correct INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (multiplayer_game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )
            ");
            
            // Create achievements table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS achievements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    description TEXT NOT NULL,
                    badge_icon TEXT NOT NULL,
                    category TEXT NOT NULL,
                    criteria TEXT NOT NULL,
                    threshold INTEGER,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Create user_achievements table
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_achievements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER,
                    achievement_id INTEGER,
                    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    progress INTEGER DEFAULT 0,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
                )
            ");
            
            // Add default admin user if none exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_admin = 1");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, is_admin)
                    VALUES (?, ?, ?, 1)
                ");
                $password_hash = password_hash('admin', PASSWORD_DEFAULT);
                $stmt->execute(['admin', 'admin@example.com', $password_hash]);
            }
            
        } catch (PDOException $e) {
            error_log('Database initialization error: ' . $e->getMessage());
            die('Failed to initialize database: ' . $e->getMessage());
        }
    }
}

/**
 * Add default achievements to the database
 * 
 * @return void
 */
if (!function_exists('add_default_achievements')) {
    function add_default_achievements() {
        $achievements = [
            [
                'name' => 'Rookie Spotter',
                'description' => 'Win your first game on Easy difficulty',
                'badge_icon' => 'badge-rookie.png',
                'category' => 'skill',
                'criteria' => 'win_games_easy',
                'threshold' => 1
            ],
            [
                'name' => 'AI Detective',
                'description' => 'Win 5 games on Medium difficulty',
                'badge_icon' => 'badge-detective.png',
                'category' => 'skill',
                'criteria' => 'win_games_medium',
                'threshold' => 5
            ],
            [
                'name' => 'Master Analyst',
                'description' => 'Win 3 games on Hard difficulty',
                'badge_icon' => 'badge-master.png',
                'category' => 'skill',
                'criteria' => 'win_games_hard',
                'threshold' => 3
            ],
            [
                'name' => 'Sharp Eye',
                'description' => 'Correctly identify 50 images',
                'badge_icon' => 'badge-eye.png',
                'category' => 'progress',
                'criteria' => 'correct_images',
                'threshold' => 50
            ],
            [
                'name' => 'Multiplayer Champion',
                'description' => 'Win 3 multiplayer games',
                'badge_icon' => 'badge-champion.png',
                'category' => 'social',
                'criteria' => 'win_multiplayer',
                'threshold' => 3
            ]
        ];
        
        foreach ($achievements as $achievement) {
            $exists = db_query_single(
                "SELECT id FROM achievements WHERE name = ?",
                [$achievement['name']]
            );
            
            if (!$exists) {
                db_insert('achievements', $achievement);
            }
        }
    }
}