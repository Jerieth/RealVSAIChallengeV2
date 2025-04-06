<?php
/**
 * Image model
 * Handles storage and retrieval of image metadata
 */

require_once __DIR__ . '/../includes/database.php';

/**
 * Get image by ID
 * 
 * @param int $id Image ID
 * @return array|null Image data or null if not found
 */
function get_image_by_id($id) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM images WHERE id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    return $image ?: null;
}

/**
 * Get image by filename
 * 
 * @param string $filename Image filename
 * @return array|null Image data or null if not found
 */
function get_image_by_filename($filename) {
    global $db;
    
    $stmt = $db->prepare("SELECT * FROM images WHERE filename = :filename");
    $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
    $stmt->execute();
    
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    return $image ?: null;
}

/**
 * Get all images with optional filtering by type
 * 
 * @param string|null $type Image type ('real' or 'ai'), null for all
 * @return array Array of image data
 */
function get_all_images($type = null) {
    global $db;
    
    if ($type) {
        $stmt = $db->prepare("SELECT * FROM images WHERE type = :type");
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
    } else {
        $stmt = $db->query("SELECT * FROM images");
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Add or update image metadata
 * 
 * @param string $filename Image filename
 * @param string $type Image type ('real' or 'ai')
 * @param string $path Full path to image file
 * @param array $metadata Optional additional metadata (tags, dimensions, etc.)
 * @return int|false Image ID or false on failure
 */
function save_image_metadata($filename, $type, $path, $metadata = []) {
    global $db;
    
    // Check if image already exists
    $existing = get_image_by_filename($filename);
    
    if ($existing) {
        // Update existing record
        $stmt = $db->prepare("
            UPDATE images SET 
                type = :type, 
                path = :path, 
                metadata = :metadata,
                updated_at = :updated_at
            WHERE id = :id
        ");
        
        $now = date('Y-m-d H:i:s');
        $metadata_json = json_encode($metadata);
        
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':path', $path, PDO::PARAM_STR);
        $stmt->bindParam(':metadata', $metadata_json, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $now, PDO::PARAM_STR);
        $stmt->bindParam(':id', $existing['id'], PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return $existing['id'];
        }
        
        return false;
    } else {
        // Create new record
        $stmt = $db->prepare("
            INSERT INTO images (
                filename, 
                type, 
                path, 
                metadata, 
                created_at, 
                updated_at
            ) VALUES (
                :filename, 
                :type, 
                :path, 
                :metadata, 
                :created_at, 
                :updated_at
            )
        ");
        
        $now = date('Y-m-d H:i:s');
        $metadata_json = json_encode($metadata);
        
        $stmt->bindParam(':filename', $filename, PDO::PARAM_STR);
        $stmt->bindParam(':type', $type, PDO::PARAM_STR);
        $stmt->bindParam(':path', $path, PDO::PARAM_STR);
        $stmt->bindParam(':metadata', $metadata_json, PDO::PARAM_STR);
        $stmt->bindParam(':created_at', $now, PDO::PARAM_STR);
        $stmt->bindParam(':updated_at', $now, PDO::PARAM_STR);
        
        if ($stmt->execute()) {
            return $db->lastInsertId();
        }
        
        return false;
    }
}

/**
 * Create the images table if it doesn't exist
 * 
 * @return bool True on success, false on failure
 */
function ensure_images_table_exists() {
    global $db;
    
    $sql = "
        CREATE TABLE IF NOT EXISTS images (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filename TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL,
            path TEXT NOT NULL,
            metadata TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )
    ";
    
    return $db->exec($sql) !== false;
}

/**
 * Initialize the images metadata from filesystem
 * 
 * @return array Array with counts of imported images
 */
function initialize_image_metadata() {
    // Ensure the table exists
    ensure_images_table_exists();
    
    $counts = [
        'real' => 0,
        'ai' => 0
    ];
    
    // Import real images
    $real_images = get_available_images('real');
    foreach ($real_images as $path) {
        $filename = basename($path);
        if (save_image_metadata($filename, 'real', $path)) {
            $counts['real']++;
        }
    }
    
    // Import AI images
    $ai_images = get_available_images('ai');
    foreach ($ai_images as $path) {
        $filename = basename($path);
        if (save_image_metadata($filename, 'ai', $path)) {
            $counts['ai']++;
        }
    }
    
    return $counts;
}

/**
 * Get a random selection of images by type
 * 
 * @param string $type Image type ('real' or 'ai')
 * @param int $count Number of images to return
 * @param array $exclude Array of filenames to exclude
 * @return array Array of image data
 */
function get_random_images($type, $count = 1, $exclude = []) {
    global $db;
    
    $placeholders = count($exclude) > 0 ? implode(',', array_fill(0, count($exclude), '?')) : '';
    $where_exclude = count($exclude) > 0 ? "AND filename NOT IN ($placeholders)" : '';
    
    $sql = "SELECT * FROM images WHERE type = ? $where_exclude ORDER BY RANDOM() LIMIT ?";
    
    $params = [$type];
    foreach ($exclude as $ex) {
        $params[] = $ex;
    }
    $params[] = $count;
    
    $stmt = $db->prepare($sql);
    $types = str_repeat('s', count($exclude) + 1) . 'i'; // string types + integer limit
    
    $stmt->execute($params);
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}