<?php
/**
 * AJAX Handler: Generate Sample Bot Names
 * 
 * Generates sample bot names based on the current database data
 * for the bot management preview section in the admin dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/multiplayer_functions.php';
require_once __DIR__ . '/../includes/bot_functions.php'; // Explicitly include bot functions

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Access denied! Admin privileges required.'
    ]);
    exit;
}

// Debug log (always log this for troubleshooting)
error_log("AJAX: Admin generating sample bot names");
error_log("AJAX: Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));
error_log("AJAX: Session is_admin: " . ($_SESSION['is_admin'] ?? 'not set'));
error_log("AJAX: Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("AJAX: HTTP_ACCEPT: " . ($_SERVER['HTTP_ACCEPT'] ?? 'not set'));
error_log("AJAX: HTTP_ORIGIN: " . ($_SERVER['HTTP_ORIGIN'] ?? 'not set'));
error_log("AJAX: HTTP_REFERER: " . ($_SERVER['HTTP_REFERER'] ?? 'not set'));
session_write_close(); // Prevent session locking

// Add CORS headers to allow same-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Explicitly ensure bot tables exist
$conn = get_db_connection();

try {
    // Check if bot_usernames table exists
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bot_usernames'");
    $bot_usernames_exists = ($result && $result->fetchColumn()) ? true : false;
    
    if (!$bot_usernames_exists) {
        error_log("Creating bot_usernames table in AJAX handler");
        
        // Create the bot_usernames table
        $conn->exec("CREATE TABLE bot_usernames (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bot_username TEXT NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert initial bot usernames
        $initial_usernames = [
            'PixelPhantomX', 'ShadowByte77', 'TurboGlitcher', 'QuantumRogue', 'NebulaHackz',
            'Zephyr', 'Icarus', 'Cassian', 'Juno', 'Elio'
        ];
        
        $stmt = $conn->prepare("INSERT INTO bot_usernames (bot_username) VALUES (?)");
        foreach ($initial_usernames as $username) {
            $stmt->execute([$username]);
        }
    }
    
    // Check if bots table exists
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bots'");
    $bots_exists = ($result && $result->fetchColumn()) ? true : false;
    
    if (!$bots_exists) {
        error_log("Creating bots table in AJAX handler");
        
        // Create the bots table
        $conn->exec("CREATE TABLE bots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            adjective TEXT NOT NULL,
            noun TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Initial adjectives and nouns
        $adjectives = [
            'Swift', 'Quick', 'Fast', 'Rapid', 'Speedy',
            'Smart', 'Clever', 'Wise', 'Bright', 'Sharp',
            'Brave', 'Bold', 'Daring', 'Mighty', 'Strong'
        ];
        
        $nouns = [
            'Player', 'Gamer', 'Challenger', 'Competitor', 'Contender',
            'Wizard', 'Ninja', 'Master', 'Champion', 'Warrior'
        ];
        
        // Insert all combinations of adjectives and nouns
        $stmt = $conn->prepare("INSERT INTO bots (adjective, noun) VALUES (?, ?)");
        foreach ($adjectives as $adjective) {
            foreach ($nouns as $noun) {
                $stmt->execute([$adjective, $noun]);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Database error in AJAX handler: " . $e->getMessage());
}

// Check if the generate_bot_name function exists
if (!function_exists('generate_bot_name')) {
    error_log("AJAX: generate_bot_name function is not defined!");
    // Define a fallback generate_bot_name function if it doesn't exist
    function generate_bot_name() {
        $conn = get_db_connection();
        
        // Decide whether to use a predefined username (50% chance) or generate one (50% chance)
        $use_predefined = (random_int(1, 100) <= 50);

        if ($use_predefined) {
            try {
                // Get a random predefined bot username
                $stmt = $conn->query("SELECT bot_username FROM bot_usernames ORDER BY RANDOM() LIMIT 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result && !empty($result['bot_username'])) {
                    return $result['bot_username'];
                }
            } catch (PDOException $e) {
                error_log("Error selecting from bot_usernames: " . $e->getMessage());
                // Continue with fallback method if there's an error
            }
        }
        
        // If no predefined username or we're generating one with adjective+noun
        // Get a random adjective and noun from database
        $stmt = $conn->query("SELECT adjective, noun FROM bots ORDER BY RANDOM() LIMIT 1");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $adjective = $result['adjective'];
            $noun = $result['noun'];
            
            // Decide whether to add a number (50% chance)
            $add_number = (random_int(1, 100) <= 50);
            
            if ($add_number) {
                // Generate a random 2-digit number
                $number = random_int(10, 99);
                return $adjective . $noun . $number;
            } else {
                return $adjective . $noun;
            }
        }
        
        // Fallback to hardcoded values if no database entries exist
        $adjectives = [
            'Swift', 'Quick', 'Fast', 'Rapid', 'Speedy'
        ];
        $nouns = [
            'Player', 'Gamer', 'Challenger', 'Competitor', 'Contender'
        ];
        
        // Randomly select an adjective and noun
        $adjective = $adjectives[array_rand($adjectives)];
        $noun = $nouns[array_rand($nouns)];
        
        // Decide whether to add a number (50% chance)
        $add_number = (random_int(1, 100) <= 50);
        
        if ($add_number) {
            // Generate a random 2-digit number
            $number = random_int(10, 99);
            return $adjective . $noun . $number;
        } else {
            return $adjective . $noun;
        }
    }
} else {
    error_log("AJAX: generate_bot_name function found!");
}

// Generate a list of sample bot names
$sample_count = 10;
$bot_names = [];

for ($i = 0; $i < $sample_count; $i++) {
    $bot_names[] = generate_bot_name();
}

// Return the results as JSON
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'Sample bot names generated successfully',
    'bot_names' => $bot_names
]);