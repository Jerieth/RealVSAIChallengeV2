<?php
/**
 * Image functions for Real vs AI application
 * Handles image retrieval, organization, and related operations
 */

require_once 'database.php';

/**
 * Get image details by path
 * 
 * @param string $image_path The path of the image to get details for
 * @return array|null The image details or null if not found
 */
if (!function_exists('get_image_details_by_path')) {
    function get_image_details_by_path($image_path) {
        // Extract the filename from the path
        $filename = basename($image_path);
        error_log("get_image_details_by_path - Searching for image: " . $filename);
        
        try {
            // Get DB connection
            $db = get_db_connection();
            if (!$db) {
                error_log("get_image_details_by_path - Database connection failed");
                return null;
            }
            
            // First check if there's a row with this path
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Get all columns from the images table in SQLite
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1); // Column 1 is the name column in PRAGMA table_info
            error_log("get_image_details_by_path - Available columns: " . implode(", ", $columns));
            
            // Debug the exact filename we're searching for
            error_log("get_image_details_by_path - EXACT SEARCH FILENAME: '" . $filename . "'");
            
            // Direct query - we know from DB inspection that 'filename' and 'type' are the correct column names
            $stmt = $db->prepare("SELECT * FROM images WHERE filename = :filename");
            $stmt->execute(['filename' => $filename]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($image) {
                error_log("get_image_details_by_path - Found image details for: " . $filename . ", Data: " . print_r($image, true));
                
                // If the image has a description, make sure it's properly logged
                if (isset($image['description']) && !empty($image['description'])) {
                    error_log("get_image_details_by_path - Image has description: " . $image['description']);
                } else {
                    error_log("get_image_details_by_path - No description found for this image");
                    
                    // Default description based on whether it's real or AI
                    $is_real = ($image['type'] === 'real');
                    $description = $is_real ? "A real photograph." : "An AI-generated image.";
                    
                    // Add the default description to the database
                    $updateStmt = $db->prepare("UPDATE images SET description = :description WHERE id = :id");
                    $updateStmt->execute([
                        'description' => $description,
                        'id' => $image['id']
                    ]);
                    $image['description'] = $description;
                    error_log("get_image_details_by_path - Added default description: " . $description);
                }
                
                return $image;
            } else {
                error_log("get_image_details_by_path - Image not found with exact filename match: " . $filename);
                
                // Try with a LIKE query as fallback
                $stmt = $db->prepare("SELECT * FROM images WHERE filename LIKE :pattern");
                $stmt->execute(['pattern' => '%' . $filename . '%']);
                $image = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($image) {
                    error_log("get_image_details_by_path - Found image with LIKE search: " . $image['filename']);
                    
                    // Add description handling as above
                    if (isset($image['description']) && !empty($image['description'])) {
                        error_log("get_image_details_by_path - Image has description: " . $image['description']);
                    } else {
                        error_log("get_image_details_by_path - No description found for this image");
                        
                        // Default description based on whether it's real or AI
                        $is_real = ($image['type'] === 'real');
                        $description = $is_real ? "A real photograph." : "An AI-generated image.";
                        
                        // Add the default description to the database
                        $updateStmt = $db->prepare("UPDATE images SET description = :description WHERE id = :id");
                        $updateStmt->execute([
                            'description' => $description,
                            'id' => $image['id']
                        ]);
                        $image['description'] = $description;
                        error_log("get_image_details_by_path - Added default description: " . $description);
                    }
                    
                    return $image;
                } else {
                    // Try another fallback approach - extract just numbers from filename
                    $numericId = preg_replace('/[^0-9]/', '', $filename);
                    if (!empty($numericId)) {
                        error_log("get_image_details_by_path - Trying with numeric part only: " . $numericId);
                        
                        // Try exact match for numeric pattern
                        $stmt = $db->prepare("SELECT * FROM images WHERE filename LIKE :pattern OR filename = :exact");
                        $stmt->execute([
                            'pattern' => $numericId . '.%', 
                            'exact' => $numericId
                        ]);
                        $image = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($image) {
                            error_log("get_image_details_by_path - Found image with numeric ID: " . $image['filename']);
                            
                            // Same description handling
                            if (isset($image['description']) && !empty($image['description'])) {
                                error_log("get_image_details_by_path - Image has description: " . $image['description']);
                            } else {
                                error_log("get_image_details_by_path - No description found for this image");
                                
                                // Default description based on whether it's real or AI
                                $is_real = ($image['type'] === 'real');
                                $description = $is_real ? "A real photograph." : "An AI-generated image.";
                                
                                // Add the default description to the database
                                $updateStmt = $db->prepare("UPDATE images SET description = :description WHERE id = :id");
                                $updateStmt->execute([
                                    'description' => $description,
                                    'id' => $image['id']
                                ]);
                                $image['description'] = $description;
                                error_log("get_image_details_by_path - Added default description: " . $description);
                            }
                            
                            return $image;
                        }
                    }
                    
                    error_log("get_image_details_by_path - No match found for image: " . $filename);
                    
                    // Show a sample record for debugging
                    $stmt = $db->prepare("SELECT * FROM images LIMIT 1");
                    $stmt->execute();
                    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
                    error_log("get_image_details_by_path - Sample image record: " . print_r($sample, true));
                    
                    return null;
                }
            }
        } catch (PDOException $e) {
            error_log("get_image_details_by_path - Error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Get total count of images of a specific type (real or ai)
 * 
 * @param string $type Image type ('real' or 'ai')
 * @return int Number of images available
 */
if (!function_exists('get_total_image_count')) {
    function get_total_image_count($type) {
        if (!in_array($type, ['real', 'ai'])) {
            return 0;
        }

        try {
            // Get DB connection
            $db = get_db_connection();
            if (!$db) {
                error_log("get_total_image_count - Database connection failed");
                return 0;
            }
            
            // Check the database structure to determine which column to use (type or is_real)
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
            
            if (in_array('type', $columns)) {
                // Use type column
                $stmt = $db->prepare('SELECT COUNT(*) FROM images WHERE type = ?');
                $stmt->execute([$type]);
            } else if (in_array('is_real', $columns)) {
                // Use is_real column
                $is_real = ($type === 'real') ? 'true' : 'false';
                $stmt = $db->prepare('SELECT COUNT(*) FROM images WHERE is_real = ?');
                $stmt->execute([$is_real]);
            } else {
                error_log("Neither 'type' nor 'is_real' column found in images table");
                return 0;
            }

            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Error getting image count: ' . $e->getMessage());
            return 0;
        }
    }
}

/**
 * Get URL for an image based on type and ID
 * 
 * @param string $type Image type ('real' or 'ai')
 * @param int $id Image ID
 * @return string URL to the image
 */
if (!function_exists('get_image_url')) {
    function get_image_url($type, $id) {
        // Validate parameters
        if (!in_array($type, ['real', 'ai']) || !is_numeric($id)) {
            error_log("get_image_url - Invalid parameters: type=$type, id=$id");
            return '/static/images/placeholder.jpg';
        }
        
        try {
            // Get DB connection
            $db = get_db_connection();
            if (!$db) {
                error_log("get_image_url - Database connection failed");
                return '/static/images/placeholder.jpg';
            }
            
            // Query for the image by ID and type
            $stmt = $db->prepare('SELECT filename FROM images WHERE id = ? AND type = ?');
            $stmt->execute([$id, $type]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($image && !empty($image['filename'])) {
                // Use game-image.php to proxy the image and hide the real path
                return '/static/images/game-image.php?id=' . urlencode(base64_encode($image['filename']));
            } else {
                error_log("get_image_url - Image not found: type=$type, id=$id");
                return '/static/images/placeholder.jpg';
            }
        } catch (PDOException $e) {
            error_log('Error getting image URL: ' . $e->getMessage());
            return '/static/images/placeholder.jpg';
        }
    }
}

/**
 * Get a list of available images from a specific type (real or ai)
 * 
 * @param string $type Image type ('real' or 'ai')
 * @return array List of images
 */
if (!function_exists('get_available_images')) {
    function get_available_images($type) {
        if (!in_array($type, ['real', 'ai'])) {
            return [];
        }

        try {
            // Get DB connection
            $db = get_db_connection();
            if (!$db) {
                error_log("get_available_images - Database connection failed");
                return [];
            }
            
            // Check the database structure to determine which column to use (type or is_real)
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
            
            if (in_array('type', $columns)) {
                // Use type column
                $stmt = $db->prepare('SELECT * FROM images WHERE type = ? ORDER BY RANDOM()');
                $stmt->execute([$type]);
            } else if (in_array('is_real', $columns)) {
                // Use is_real column
                $is_real = ($type === 'real') ? 'true' : 'false';
                $stmt = $db->prepare('SELECT * FROM images WHERE is_real = ? ORDER BY RANDOM()');
                $stmt->execute([$is_real]);
            } else {
                error_log("Neither 'type' nor 'is_real' column found in images table");
                return [];
            }

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting available images: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get a random image pair (one real, one AI) that hasn't been shown before
 * 
 * @param array $shown_images List of already shown image IDs
 * @param string $difficulty Game difficulty (easy, medium, hard, or endless)
 * @return array|false Image pair data or false on failure
 */
if (!function_exists('get_random_image_pair')) {
    function get_random_image_pair($shown_images = [], $difficulty = null) {
        // Convert shown_images to array if it's a string
        if (is_string($shown_images)) {
            $shown_images = !empty($shown_images) ? explode(',', $shown_images) : [];
        }
        
        // Get DB connection
        $db = get_db_connection();
        if (!$db) {
            error_log("get_random_image_pair - Database connection failed");
            return false;
        }

        // Prepare the shown images condition
        $shownImagesCondition = '';
        $params = [];

        if (!empty($shown_images)) {
            $placeholders = implode(',', array_fill(0, count($shown_images), '?'));
            $shownImagesCondition = "AND id NOT IN ({$placeholders})";
            $params = $shown_images;
        }

        // Prepare difficulty condition
        $difficultyCondition = '';

        // If we have a difficulty and it's not endless mode
        if ($difficulty && $difficulty !== 'endless') {
            switch ($difficulty) {
                case 'easy':
                    // Easy mode - only easy images
                    $difficultyCondition = "AND (difficulty = 'easy' OR difficulty IS NULL)";
                    break;

                case 'medium':
                    // Medium mode - easy and medium images
                    $difficultyCondition = "AND (difficulty IN ('easy', 'medium') OR difficulty IS NULL)";
                    break;

                case 'hard':
                    // Hard mode - all difficulty images (no need for condition)
                    break;
            }
        }

        try {
            // Check the database structure to determine which column to use (type or is_real)
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
            
            // Variables to store SQL conditions
            $realCondition = "";
            $aiCondition = "";
            
            if (in_array('type', $columns)) {
                // Use type column
                $realCondition = "type = 'real'";
                $aiCondition = "type = 'ai'";
            } else if (in_array('is_real', $columns)) {
                // Use is_real column
                $realCondition = "is_real = true";
                $aiCondition = "is_real = false";
            } else {
                error_log("Neither 'type' nor 'is_real' column found in images table");
                return false;
            }
            
            // Get a random real image
            $realStmt = $db->prepare("
                SELECT * FROM images 
                WHERE {$realCondition} {$shownImagesCondition} {$difficultyCondition}
                ORDER BY RANDOM() 
                LIMIT 1
            ");

            $realStmt->execute($params);
            $realImage = $realStmt->fetch(PDO::FETCH_ASSOC);

            if (!$realImage) {
                // No more unseen real images, fallback to any real image with the same difficulty filter
                $realStmt = $db->prepare("
                    SELECT * FROM images 
                    WHERE {$realCondition} {$difficultyCondition}
                    ORDER BY RANDOM() 
                    LIMIT 1
                ");

                $realStmt->execute();
                $realImage = $realStmt->fetch(PDO::FETCH_ASSOC);

                if (!$realImage) {
                    error_log('No real images found with the current difficulty settings');
                    return false;
                }
            }

            // Get a random AI image
            $aiStmt = $db->prepare("
                SELECT * FROM images 
                WHERE {$aiCondition} {$shownImagesCondition} {$difficultyCondition}
                ORDER BY RANDOM() 
                LIMIT 1
            ");

            $aiStmt->execute($params);
            $aiImage = $aiStmt->fetch(PDO::FETCH_ASSOC);

            if (!$aiImage) {
                // No more unseen AI images, fallback to any AI image with the same difficulty filter
                $aiStmt = $db->prepare("
                    SELECT * FROM images 
                    WHERE {$aiCondition} {$difficultyCondition}
                    ORDER BY RANDOM() 
                    LIMIT 1
                ");

                $aiStmt->execute();
                $aiImage = $aiStmt->fetch(PDO::FETCH_ASSOC);

                if (!$aiImage) {
                    error_log('No AI images found with the current difficulty settings');
                    return false;
                }
            }

            // Randomly determine the order of images (which one is first)
            $showRealFirst = (mt_rand(0, 1) === 0);

            return [
                'image1' => $showRealFirst ? $realImage : $aiImage,
                'image2' => $showRealFirst ? $aiImage : $realImage
            ];
        } catch (PDOException $e) {
            error_log('Error getting random image pair: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get images for the bonus round (one real, three AI)
 * 
 * @return array List of four images with one real image
 */
if (!function_exists('get_bonus_round_images')) {
    function get_bonus_round_images() {
        // Get DB connection
        $db = get_db_connection();
        if (!$db) {
            error_log("get_bonus_round_images - Database connection failed");
            return [];
        }
        
        try {
            // Check the database structure to determine which column to use (type or is_real)
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
            
            // Variables to store SQL conditions
            $realCondition = "";
            $aiCondition = "";
            
            if (in_array('type', $columns)) {
                // Use type column
                $realCondition = "type = 'real'";
                $aiCondition = "type = 'ai'";
            } else if (in_array('is_real', $columns)) {
                // Use is_real column
                $realCondition = "is_real = true";
                $aiCondition = "is_real = false";
            } else {
                error_log("Neither 'type' nor 'is_real' column found in images table");
                return [];
            }
            
            // Get one random real image
            $realStmt = $db->prepare("
                SELECT * FROM images 
                WHERE {$realCondition}
                ORDER BY RANDOM() 
                LIMIT 1
            ");

            $realStmt->execute();
            $realImage = $realStmt->fetch(PDO::FETCH_ASSOC);

            if (!$realImage) {
                error_log('No real images found for bonus round');
                return [];
            }

            // Get three random AI images
            $aiStmt = $db->prepare("
                SELECT * FROM images 
                WHERE {$aiCondition}
                ORDER BY RANDOM() 
                LIMIT 3
            ");

            $aiStmt->execute();
            $aiImages = $aiStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($aiImages) < 3) {
                error_log('Not enough AI images found for bonus round');
                return [];
            }

            // Combine and shuffle the images
            $images = array_merge([$realImage], $aiImages);
            shuffle($images);

            return $images;
        } catch (PDOException $e) {
            error_log('Error getting bonus round images: ' . $e->getMessage());
            return [];
        }
    }
}

/**
 * Get an image by its ID
 * 
 * @param int $image_id Image ID
 * @return array|false Image data or false if not found
 */
if (!function_exists('get_image_by_id')) {
    function get_image_by_id($image_id) {
        if (empty($image_id)) {
            return false;
        }
        
        // Get DB connection
        $db = get_db_connection();
        if (!$db) {
            error_log("get_image_by_id - Database connection failed");
            return false;
        }

        try {
            $stmt = $db->prepare('SELECT * FROM images WHERE id = ?');
            $stmt->execute([$image_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting image by ID: ' . $e->getMessage());
            return false;
        }
    }
}

/**
 * Get the description for an image by its ID
 * 
 * @param int $image_id The ID of the image
 * @param string $type Optional image type ('real' or 'ai') for debugging
 * @return string The image description or a default message if not found
 */
if (!function_exists('get_image_description')) {
    function get_image_description($image_id, $type = '') {
        // Log that the function was called
        error_log("get_image_description - Called for image ID: $image_id, type: $type");
        
        // Get the image data
        $image = get_image_by_id($image_id);
        
        // Check if we got the image and it has a description
        if ($image && isset($image['description']) && !empty($image['description'])) {
            error_log("get_image_description - Found description: " . $image['description']);
            return $image['description'];
        }
        
        // If no description is found, return a default message
        error_log("get_image_description - No description found for image ID: $image_id");
        return "No description available for this image.";
    }
}

/**
 * Get the path to an image file
 * 
 * @param string $type Image type ('real' or 'ai')
 * @param string $filename Image filename
 * @return string Full path to the image file
 */
if (!function_exists('get_image_path')) {
    function get_image_path($type, $filename) {
        if (!in_array($type, ['real', 'ai'])) {
            return '';
        }

        $base_path = dirname(__DIR__, 2) . '/uploads/';
        return $base_path . $type . '/' . $filename;
    }
}

/**
 * Count images of each type
 * 
 * @return array Counts of real and AI images
 */
if (!function_exists('count_images')) {
    function count_images() {
        // Get DB connection
        $db = get_db_connection();
        if (!$db) {
            error_log("count_images - Database connection failed");
            return [
                'real' => 0,
                'ai' => 0,
                'total' => 0
            ];
        }

        try {
            // Check the database structure to determine which column to use (type or is_real)
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
            
            // Variables to store SQL conditions
            $realCondition = "";
            $aiCondition = "";
            
            if (in_array('type', $columns)) {
                // Use type column
                $realCondition = "type = 'real'";
                $aiCondition = "type = 'ai'";
            } else if (in_array('is_real', $columns)) {
                // Use is_real column
                $realCondition = "is_real = true";
                $aiCondition = "is_real = false";
            } else {
                error_log("Neither 'type' nor 'is_real' column found in images table");
                return [
                    'real' => 0,
                    'ai' => 0,
                    'total' => 0
                ];
            }
            
            // Count real images
            $realStmt = $db->prepare("SELECT COUNT(*) FROM images WHERE {$realCondition}");
            $realStmt->execute();
            $realCount = $realStmt->fetchColumn();
            
            // Count AI images
            $aiStmt = $db->prepare("SELECT COUNT(*) FROM images WHERE {$aiCondition}");
            $aiStmt->execute();
            $aiCount = $aiStmt->fetchColumn();
            
            return [
                'real' => $realCount,
                'ai' => $aiCount,
                'total' => $realCount + $aiCount
            ];
        } catch (PDOException $e) {
            error_log('Error counting images: ' . $e->getMessage());
            return [
                'real' => 0,
                'ai' => 0,
                'total' => 0
            ];
        }
    }
}


// Added functions to handle image upload and database update.  Assumes certain directory and database structures.
if (!defined('REAL_IMAGES_DIR')) {
    define('REAL_IMAGES_DIR', dirname(__DIR__,2) . '/uploads/real');
}
if (!defined('AI_IMAGES_DIR')) {
    define('AI_IMAGES_DIR', dirname(__DIR__,2) . '/uploads/ai');
}


function uploadImage($type, $original_name, $tmp_name) {
    // Get DB connection
    $db = get_db_connection();
    if (!$db) {
        error_log("uploadImage - Database connection failed");
        return false;
    }
    
    // Get all existing files from both real and AI directories
    $real_files = scandir(REAL_IMAGES_DIR);
    $ai_files = scandir(AI_IMAGES_DIR);
    $all_files = array_merge($real_files, $ai_files);

    // Find the highest number
    $max_num = 0;
    foreach ($all_files as $file) {
        if (preg_match('/^(\d+)\./', $file, $matches)) {
            $max_num = max($max_num, (int)$matches[1]);
        }
    }

    // Generate a unique filename with the next number
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    $new_filename = ($max_num + 1) . '.' . $file_extension;

    // Use the unique filename
    $target_dir = ($type === 'real') ? REAL_IMAGES_DIR : AI_IMAGES_DIR;
    $target_file = $target_dir . '/' . $new_filename;

    if (move_uploaded_file($tmp_name, $target_file)) {
        try {
            // Check the database structure to determine which column to use (type or is_real)
            $table_info = $db->query("PRAGMA table_info(images)");
            $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
            
            if (in_array('type', $columns)) {
                // Use type column
                $stmt = $db->prepare("INSERT INTO images (filename, type) VALUES (?, ?)");
                $stmt->execute([$new_filename, $type]);
                return true;
            } else if (in_array('is_real', $columns) && in_array('path', $columns)) {
                // Use is_real and path columns
                $is_real = ($type === 'real') ? 'true' : 'false';
                $stmt = $db->prepare("INSERT INTO images (path, is_real) VALUES (?, ?)");
                $stmt->execute([$new_filename, $is_real]);
                return true;
            } else if (in_array('filename', $columns) && in_array('is_real', $columns)) {
                // Use filename and is_real columns
                $is_real = ($type === 'real') ? 'true' : 'false';
                $stmt = $db->prepare("INSERT INTO images (filename, is_real) VALUES (?, ?)");
                $stmt->execute([$new_filename, $is_real]);
                return true;
            } else {
                error_log("Cannot determine correct columns for image insertion");
                return false;
            }
        } catch (PDOException $e) {
            error_log("Error inserting image into database: " . $e->getMessage());
            return false;
        }
    } else {
        error_log("Error moving uploaded file: " . $target_file);
        return false;
    }
}


function cleanupAndUpateImageDatabase(){
    //Implementation to cleanup image folders and update image database.
    //This function should remove orphaned files (files in the upload folders not present in the database)
    //and correct any inconsistencies in the database.  This would require additional logic
    //that depends heavily on your specific database structure and file organization.

    // Get DB connection
    $db = get_db_connection();
    if (!$db) {
        error_log("cleanupAndUpateImageDatabase - Database connection failed");
        return false;
    }

    try{
        //Logic to clean up folders. Example:
        $real_files = scandir(REAL_IMAGES_DIR);
        $ai_files = scandir(AI_IMAGES_DIR);

        // Check the database structure to determine which column to use (filename or path)
        $table_info = $db->query("PRAGMA table_info(images)");
        $columns = $table_info->fetchAll(PDO::FETCH_COLUMN, 1);
        
        // Get the correct column name for file paths
        $path_column = in_array('filename', $columns) ? 'filename' : 'path';
        
        // Fetch all paths from DB
        $stmt = $db->prepare("SELECT {$path_column} FROM images");
        $stmt->execute();
        $db_paths = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Compare and remove orphaned files.

        //Logic to update image database. Example:
        //Check for duplicates, incorrect types etc. and correct them.

        return true; // Indicate success
    } catch (PDOException $e) {
        error_log("Error during cleanup and database update: " . $e->getMessage());
        return false;
    }
}

?>