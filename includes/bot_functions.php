<?php
/**
 * Bot Functions
 * 
 * This file contains functions for managing bot data such as:
 * - Bot adjectives and nouns for dynamic name generation
 * - Custom bot usernames
 */

/**
 * Make sure the bot tables exist in the database and create them if they don't
 * 
 * @param PDO|null $conn Database connection, or null to create a new connection
 * @return bool True if tables exist or were created successfully, false otherwise
 */
function ensure_bot_tables_exist($conn = null) {
    if ($conn === null) {
        $conn = get_db_connection();
    }
    
    try {
        // Check if bot_usernames table exists
        $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table' AND name='bot_usernames'");
        $bot_usernames_exists = ($result && $result->fetchColumn()) ? true : false;
        
        if (!$bot_usernames_exists) {
            error_log("Creating bot_usernames table");
            
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
            error_log("Creating bots table");
            
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
        
        return true;
    } catch (Exception $e) {
        error_log("Error ensuring bot tables exist: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all bot adjectives from the database
 * 
 * @return array Associative array of adjective records with id and adjective
 */
function get_bot_adjectives() {
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    $stmt = $conn->query("SELECT id, adjective FROM bots GROUP BY adjective ORDER BY adjective ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all bot nouns from the database
 * 
 * @return array Associative array of noun records with id and noun
 */
function get_bot_nouns() {
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    $stmt = $conn->query("SELECT id, noun FROM bots GROUP BY noun ORDER BY noun ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get all custom bot usernames from the database
 * 
 * @return array Associative array of bot username records
 */
function get_bot_usernames() {
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    $stmt = $conn->query("SELECT id, bot_username FROM bot_usernames ORDER BY bot_username ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add a new adjective to the bots table
 * Supports comma-separated list of adjectives
 * 
 * @param string $adjective The adjective(s) to add (can be comma-separated)
 * @return array Result array with success status and message
 */
function add_bot_adjective($adjective) {
    if (empty($adjective)) {
        return ['success' => false, 'message' => 'Adjective cannot be empty'];
    }
    
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    // Get a random noun to pair with the new adjective(s)
    $stmt = $conn->query("SELECT noun FROM bots ORDER BY RANDOM() LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        // If no nouns exist, create a default one
        $noun = 'Player';
    } else {
        $noun = $result['noun'];
    }
    
    // Check if this is a comma-separated list of adjectives
    $adjectives = array_map('trim', explode(',', $adjective));
    $added_count = 0;
    $existing_count = 0;
    $failed_count = 0;
    
    foreach ($adjectives as $single_adjective) {
        // Skip empty items
        if (empty($single_adjective)) {
            continue;
        }
        
        // Check if this adjective already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bots WHERE adjective = :adjective");
        $stmt->bindParam(':adjective', $single_adjective, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $existing_count++;
            continue;
        }
        
        // Add the new adjective with a random existing noun
        $stmt = $conn->prepare("INSERT INTO bots (adjective, noun) VALUES (:adjective, :noun)");
        $stmt->bindParam(':adjective', $single_adjective, PDO::PARAM_STR);
        $stmt->bindParam(':noun', $noun, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $added_count++;
        } else {
            $failed_count++;
        }
    }
    
    // Build response message based on results
    if ($added_count > 0) {
        $message = $added_count . ' adjective(s) added successfully';
        if ($existing_count > 0) {
            $message .= ' (' . $existing_count . ' already existed)';
        }
        return ['success' => true, 'message' => $message];
    } else if ($existing_count > 0) {
        return ['success' => false, 'message' => 'All ' . $existing_count . ' adjective(s) already exist'];
    } else {
        return ['success' => false, 'message' => 'Failed to add adjectives'];
    }
}

/**
 * Add a new noun to the bots table
 * Supports comma-separated list of nouns
 * 
 * @param string $noun The noun(s) to add (can be comma-separated)
 * @return array Result array with success status and message
 */
function add_bot_noun($noun) {
    if (empty($noun)) {
        return ['success' => false, 'message' => 'Noun cannot be empty'];
    }
    
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    // Get a random adjective to pair with the new noun(s)
    $stmt = $conn->query("SELECT adjective FROM bots ORDER BY RANDOM() LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        // If no adjectives exist, create a default one
        $adjective = 'Swift';
    } else {
        $adjective = $result['adjective'];
    }
    
    // Check if this is a comma-separated list of nouns
    $nouns = array_map('trim', explode(',', $noun));
    $added_count = 0;
    $existing_count = 0;
    $failed_count = 0;
    
    foreach ($nouns as $single_noun) {
        // Skip empty items
        if (empty($single_noun)) {
            continue;
        }
        
        // Check if this noun already exists
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bots WHERE noun = :noun");
        $stmt->bindParam(':noun', $single_noun, PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($count > 0) {
            $existing_count++;
            continue;
        }
        
        // Add the new noun with a random existing adjective
        $stmt = $conn->prepare("INSERT INTO bots (adjective, noun) VALUES (:adjective, :noun)");
        $stmt->bindParam(':adjective', $adjective, PDO::PARAM_STR);
        $stmt->bindParam(':noun', $single_noun, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            $added_count++;
        } else {
            $failed_count++;
        }
    }
    
    // Build response message based on results
    if ($added_count > 0) {
        $message = $added_count . ' noun(s) added successfully';
        if ($existing_count > 0) {
            $message .= ' (' . $existing_count . ' already existed)';
        }
        return ['success' => true, 'message' => $message];
    } else if ($existing_count > 0) {
        return ['success' => false, 'message' => 'All ' . $existing_count . ' noun(s) already exist'];
    } else {
        return ['success' => false, 'message' => 'Failed to add nouns'];
    }
}

/**
 * Add a new custom bot username
 * 
 * @param string $username The custom bot username to add
 * @return array Result array with success status and message
 */
function add_bot_username($username) {
    if (empty($username)) {
        return ['success' => false, 'message' => 'Username cannot be empty'];
    }
    
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    // Check if the username already exists
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bot_usernames WHERE bot_username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count > 0) {
        return ['success' => false, 'message' => 'Bot username already exists'];
    }
    
    // Add the new username
    $stmt = $conn->prepare("INSERT INTO bot_usernames (bot_username) VALUES (:username)");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Bot username added successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to add bot username'];
    }
}

/**
 * Delete a bot adjective
 * 
 * @param int $id The ID of the adjective to delete
 * @return array Result array with success status and message
 */
function delete_bot_adjective($id) {
    if (empty($id)) {
        return ['success' => false, 'message' => 'Invalid adjective ID'];
    }
    
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    // Get the adjective to delete
    $stmt = $conn->prepare("SELECT adjective FROM bots WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Adjective not found'];
    }
    
    $adjective = $result['adjective'];
    
    // First, check how many entries use this adjective
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bots WHERE adjective = :adjective");
    $stmt->bindParam(':adjective', $adjective, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // If this is the only entry with this adjective, make sure we have other adjectives
    if ($count <= 1) {
        $stmt = $conn->query("SELECT COUNT(DISTINCT adjective) as count FROM bots");
        $total_adjectives = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($total_adjectives <= 1) {
            return ['success' => false, 'message' => 'Cannot delete the last adjective'];
        }
    }
    
    // Delete the specific entry
    $stmt = $conn->prepare("DELETE FROM bots WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Adjective deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete adjective'];
    }
}

/**
 * Delete a bot noun
 * 
 * @param int $id The ID of the noun to delete
 * @return array Result array with success status and message
 */
function delete_bot_noun($id) {
    if (empty($id)) {
        return ['success' => false, 'message' => 'Invalid noun ID'];
    }
    
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    // Get the noun to delete
    $stmt = $conn->prepare("SELECT noun FROM bots WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        return ['success' => false, 'message' => 'Noun not found'];
    }
    
    $noun = $result['noun'];
    
    // First, check how many entries use this noun
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM bots WHERE noun = :noun");
    $stmt->bindParam(':noun', $noun, PDO::PARAM_STR);
    $stmt->execute();
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // If this is the only entry with this noun, make sure we have other nouns
    if ($count <= 1) {
        $stmt = $conn->query("SELECT COUNT(DISTINCT noun) as count FROM bots");
        $total_nouns = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($total_nouns <= 1) {
            return ['success' => false, 'message' => 'Cannot delete the last noun'];
        }
    }
    
    // Delete the specific entry
    $stmt = $conn->prepare("DELETE FROM bots WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Noun deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete noun'];
    }
}

/**
 * Generate a random bot name
 * 
 * - 50% chance to use a predefined bot username from bot_usernames table
 * - 50% chance to use adjective+noun combination
 *   - When using adjective+noun, 50% chance of adding a number suffix
 * 
 * @return string The generated bot name
 */
function generate_bot_name() {
    $conn = get_db_connection();
    
    // Make sure the bot tables exist
    ensure_bot_tables_exist($conn);

    // Decide whether to use a predefined username (50% chance) or generate one (50% chance)
    $use_predefined = (random_int(1, 100) <= 50);
    error_log("Bot name generation - Using predefined username: " . ($use_predefined ? 'yes' : 'no'));

    if ($use_predefined) {
        try {
            // Get a random predefined bot username from the bot_usernames table
            $stmt = $conn->query("SELECT bot_username FROM bot_usernames ORDER BY RANDOM() LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['bot_username'])) {
                error_log("Bot name generation - Using predefined username: " . $result['bot_username']);
                return $result['bot_username'];
            } else {
                error_log("Bot name generation - No predefined username found in database");
            }
        } catch (PDOException $e) {
            error_log("Error selecting from bot_usernames: " . $e->getMessage());
            // Continue with fallback method if there's an error
        }
    }
    
    // If we get here, either:
    // 1. We chose to use adjective+noun combination (50% chance)
    // 2. Or we tried to use a predefined username but none were found
    
    // Get a random adjective and noun from database
    $stmt = $conn->query("SELECT adjective, noun FROM bots ORDER BY RANDOM() LIMIT 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $adjective = $result['adjective'];
        $noun = $result['noun'];
        
        // When using adjective+noun, decide whether to add a number (50% chance)
        $add_number = (random_int(1, 100) <= 50);
        error_log("Bot name generation - Adding number: " . ($add_number ? 'yes' : 'no'));
        
        if ($add_number) {
            // Generate a random 2-digit number
            $number = random_int(10, 99);
            $bot_name = $adjective . $noun . $number;
            error_log("Bot name generation - Created with number: " . $bot_name);
            return $bot_name;
        } else {
            $bot_name = $adjective . $noun;
            error_log("Bot name generation - Created without number: " . $bot_name);
            return $bot_name;
        }
    }
    
    // Fallback to hardcoded values if no database entries exist
    error_log("Bot name generation - Using fallback hardcoded values");
    $adjectives = [
        'Swift', 'Quick', 'Fast', 'Rapid', 'Speedy',
        'Smart', 'Clever', 'Wise', 'Bright', 'Sharp'
    ];
    $nouns = [
        'Player', 'Gamer', 'Challenger', 'Competitor', 'Contender'
    ];
    
    // Randomly select an adjective and noun
    $adjective = $adjectives[array_rand($adjectives)];
    $noun = $nouns[array_rand($nouns)];
    
    // When using adjective+noun in fallback mode, decide whether to add a number (50% chance)
    $add_number = (random_int(1, 100) <= 50);
    
    if ($add_number) {
        // Generate a random 2-digit number
        $number = random_int(10, 99);
        $bot_name = $adjective . $noun . $number;
        error_log("Bot name generation (fallback) - Created with number: " . $bot_name);
        return $bot_name;
    } else {
        $bot_name = $adjective . $noun;
        error_log("Bot name generation (fallback) - Created without number: " . $bot_name);
        return $bot_name;
    }
}

/**
 * Delete a custom bot username
 * 
 * @param int $id The ID of the username to delete
 * @return array Result array with success status and message
 */
function delete_bot_username($id) {
    if (empty($id)) {
        return ['success' => false, 'message' => 'Invalid username ID'];
    }
    
    $conn = get_db_connection();
    
    // Ensure tables exist
    ensure_bot_tables_exist($conn);
    
    // Make sure we have at least one username after this deletion
    $stmt = $conn->query("SELECT COUNT(*) as count FROM bot_usernames");
    $total_usernames = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($total_usernames <= 1) {
        // Check if this is the last username
        $stmt = $conn->prepare("SELECT id FROM bot_usernames");
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($ids) == 1 && $ids[0] == $id) {
            return ['success' => false, 'message' => 'Cannot delete the last bot username'];
        }
    }
    
    // Delete the username
    $stmt = $conn->prepare("DELETE FROM bot_usernames WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Bot username deleted successfully'];
    } else {
        return ['success' => false, 'message' => 'Failed to delete bot username'];
    }
}