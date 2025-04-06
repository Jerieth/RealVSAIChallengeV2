<?php
/**
 * Database Initialization Script
 *
 * This script initializes the SQLite database with all required tables
 * Run this script once to create the database structure
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting database initialization...\n";

// Include database functions
require_once 'includes/config.php';
require_once 'includes/database.php';

// Get database connection
$pdo = get_db_connection();

echo "Connected to database. Creating tables...\n";

// Create users table
echo "Creating users table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_admin INTEGER DEFAULT 0,
        avatar TEXT DEFAULT NULL,
        country TEXT DEFAULT 'US'
    )
");

// Create games table
echo "Creating games table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL UNIQUE,
        game_mode TEXT NOT NULL,
        difficulty TEXT,
        total_turns INTEGER DEFAULT 10,
        lives INTEGER DEFAULT 3,
        starting_lives INTEGER DEFAULT 3,
        current_turn INTEGER DEFAULT 1,
        score INTEGER DEFAULT 0,
        completed INTEGER DEFAULT 0,
        shown_images TEXT,
        user_id INTEGER,
        current_real_image TEXT,
        current_ai_image TEXT,
        left_is_real INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Create game_sessions table
echo "Creating game_sessions table...\n";
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
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Create images table
echo "Creating images table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filename TEXT NOT NULL,
        type TEXT NOT NULL,
        difficulty TEXT DEFAULT 'medium',
        category TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP
    )
");

// Create game_turns table
echo "Creating game_turns table...\n";
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

// Create game_answers table
echo "Creating game_answers table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS game_answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        game_session_id TEXT NOT NULL,
        turn_number INTEGER NOT NULL,
        chosen_image_id INTEGER,
        is_correct INTEGER DEFAULT 0,
        streak INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
");

// Create leaderboard table
echo "Creating leaderboard table...\n";
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
echo "Creating multiplayer_games table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS multiplayer_games (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        session_id TEXT NOT NULL UNIQUE,
        room_code TEXT,
        is_public INTEGER DEFAULT 1,
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
        shown_images TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        started_at TIMESTAMP,
        ended_at TIMESTAMP,
        completed INTEGER DEFAULT 0,
        wait_timeout TIMESTAMP,
        has_bots INTEGER DEFAULT 0,
        current_real_image TEXT,
        current_ai_image TEXT,
        left_is_real INTEGER DEFAULT 0,
        time_penalty INTEGER DEFAULT 0,
        FOREIGN KEY (player1_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (player2_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (player3_id) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (player4_id) REFERENCES users(id) ON DELETE SET NULL
    )
");

// Create multiplayer_players table
echo "Creating multiplayer_players table...\n";
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
echo "Creating multiplayer_turns table...\n";
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
echo "Creating multiplayer_answers table...\n";
$pdo->exec("
    CREATE TABLE IF NOT EXISTS multiplayer_answers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        multiplayer_game_id INTEGER,
        user_id INTEGER,
        user_name TEXT,
        turn_number INTEGER NOT NULL,
        user_selection TEXT,
        is_correct INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (multiplayer_game_id) REFERENCES multiplayer_games(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )
");

// Create achievements table
echo "Creating achievements table...\n";
$pdo->exec("DROP TABLE IF EXISTS achievements");
$pdo->exec("
CREATE TABLE achievements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    achievement_type VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    icon VARCHAR(50),
    category VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

// Create index for faster lookups
$pdo->exec("CREATE INDEX idx_achievements_user_id ON achievements(user_id)");
$pdo->exec("CREATE INDEX idx_achievements_type ON achievements(achievement_type)");


// Create banned_words table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS banned_words (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        word TEXT NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INTEGER,
        FOREIGN KEY (created_by) REFERENCES users(id)
    )
");

// Create user_achievements table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS user_achievements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        achievement_id INTEGER NOT NULL,
        unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
    )
");


echo "Database tables created successfully.\n";

// Insert default achievements (Removed this section as it is not compatible with the new table schema)

// Create admin user if it doesn't exist
$admin_check = $pdo->query("SELECT id FROM users WHERE username = 'admin'");
if (!$admin_check->fetch()) {
    echo "Creating admin user...\n";
    $admin_password_hash = password_hash('G7&^32@!03920AnRt9@!', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO users 
        (username, email, password_hash, is_admin, created_at, avatar, country) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        'admin',
        'admin@realvsai.com',
        $admin_password_hash,
        1,
        date('Y-m-d H:i:s'),
        NULL,
        'US'
    ]);

    echo "Admin user created successfully.\n";
} else {
    echo "Admin user already exists.\n";
}

echo "Database initialization completed successfully!\n";
?>