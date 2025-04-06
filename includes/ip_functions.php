<?php
/**
 * IP Address Tracking and Management Functions
 * 
 * Functions for tracking, recording, and managing IP addresses
 */

/**
 * Record a user's IP address
 * 
 * @param int $user_id User ID
 * @param string $ip_address IP address (defaults to current user IP)
 * @return bool True if successful, false otherwise
 */
/**
 * Get the client IP address
 * 
 * @return string IP address
 */
function get_client_ip() {
    $ip = '';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    
    // Sanitize and validate IP
    $ip = filter_var($ip, FILTER_VALIDATE_IP);
    return $ip ?: 'UNKNOWN';
}

function record_user_ip_address($user_id, $ip_address = null) {
    if (empty($ip_address)) {
        $ip_address = get_client_ip();
    }
    
    if (empty($ip_address) || $ip_address == 'UNKNOWN') {
        return false;
    }
    
    $conn = get_db_connection();
    
    // Check if this IP is already recorded for this user
    $stmt = $conn->prepare("
        SELECT id 
        FROM user_ip_addresses 
        WHERE user_id = ? AND ip_address = ?
    ");
    $stmt->execute([$user_id, $ip_address]);
    
    if ($stmt->fetchColumn()) {
        // IP already recorded, update last_seen
        $stmt = $conn->prepare("
            UPDATE user_ip_addresses 
            SET last_seen = CURRENT_TIMESTAMP, 
                login_count = login_count + 1 
            WHERE user_id = ? AND ip_address = ?
        ");
        return $stmt->execute([$user_id, $ip_address]);
    } else {
        // New IP address for this user
        $stmt = $conn->prepare("
            INSERT INTO user_ip_addresses 
            (user_id, ip_address, first_seen, last_seen, login_count) 
            VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 1)
        ");
        return $stmt->execute([$user_id, $ip_address]);
    }
}

/**
 * Get all IP addresses with their block status
 * 
 * @return array Array of IP address records
 */
function get_all_ip_addresses() {
    $conn = get_db_connection();
    
    $stmt = $conn->query("
        SELECT ip.*, u.username, 
               COUNT(DISTINCT ip.user_id) as user_count
        FROM user_ip_addresses ip
        LEFT JOIN users u ON ip.user_id = u.id
        GROUP BY ip.ip_address
        ORDER BY ip.last_seen DESC
    ");
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Count total unique IP addresses
 * 
 * @return int Total number of unique IP addresses
 */
function count_total_ip_addresses() {
    $conn = get_db_connection();
    
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT ip_address) as total 
        FROM user_ip_addresses
    ");
    
    return $stmt->fetchColumn();
}

/**
 * Block an IP address
 * 
 * @param string $ip_address IP address to block
 * @param string $reason Reason for blocking
 * @return bool True if successful, false otherwise
 */
function block_ip_address($ip_address, $reason = '') {
    if (empty($ip_address)) {
        return false;
    }
    
    $conn = get_db_connection();
    
    try {
        // Check if the IP exists in the tracking table
        $stmt = $conn->prepare("
            SELECT COUNT(*) FROM user_ip_addresses 
            WHERE ip_address = ?
        ");
        $stmt->execute([$ip_address]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // Update all records with this IP to mark as blocked
            $stmt = $conn->prepare("
                UPDATE user_ip_addresses 
                SET is_blocked = 1, 
                    block_reason = ?, 
                    blocked_at = CURRENT_TIMESTAMP 
                WHERE ip_address = ?
            ");
            return $stmt->execute([$reason, $ip_address]);
        } else {
            // Insert a new blocked IP record without user association
            $stmt = $conn->prepare("
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

/**
 * Unblock an IP address
 * 
 * @param string $ip_address IP address to unblock
 * @return bool True if successful, false otherwise
 */
function unblock_ip_address($ip_address) {
    if (empty($ip_address)) {
        return false;
    }
    
    $conn = get_db_connection();
    
    try {
        $stmt = $conn->prepare("
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

/**
 * Check if an IP address is blocked
 * 
 * @param string $ip_address IP address to check
 * @return bool True if blocked, false otherwise
 */
function is_ip_blocked($ip_address) {
    if (empty($ip_address)) {
        return false;
    }
    
    $conn = get_db_connection();
    
    try {
        $stmt = $conn->prepare("
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

?>