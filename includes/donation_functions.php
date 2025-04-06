<?php
/**
 * Functions for handling donations and donation rewards
 */

/**
 * Get the total amount a user has donated
 * 
 * @param int $user_id The user ID
 * @return float Total donation amount
 */
function get_user_total_donations($user_id) {
    // Default to 0
    $total = 0.00;
    
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("
            SELECT SUM(amount) as total_donations 
            FROM donations 
            WHERE user_id = :user_id
        ");
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['total_donations'])) {
            $total = (float)$result['total_donations'];
        }
    } catch (PDOException $e) {
        error_log("Error getting total donations: " . $e->getMessage());
    }
    
    return $total;
}

/**
 * Get donation tier avatars available for a user based on total donation amount
 * 
 * @param float $total_donated The total amount a user has donated
 * @return array Array of avatars organized by tiers
 */
function get_donation_tier_avatars($total_donated) {
    // Initialize with base VIP avatars for any donation amount
    $avatars = [
        'vip' => ['🧛', '🧙‍♂️', '🧙‍♀️', '🍑', '🍕', '🦉', '🍄', '🐤', '🐣', '🏰', '🍤', '💃🏻', '🕺', '💃🏼', '🐞', '🐶', '🐩', '🐕']
    ];
    
    // Tier 1: $2 or more
    if ($total_donated >= 2) {
        $avatars['tier1'] = ['🧒', '👦', '👧', '🧑', '👱', '👨', '🧔', '🧒🏻', '🧒🏼', '🧒🏽', '🧒🏾', '👩🏻', '👩🏼', '👨🏿', '🧒🏿', '👩🏿', '🙎🏿', '🧑🏿', '👧🏿', '🤴🏿', '👸🏿', '🧑🏻', '🧑🏾', '🧑🏽', '👩‍🦱', '👩🏻‍🦱', '👩🏾‍🦱', '👩🏿‍🦱', '👳', '👳🏻', '👳🏽', '👳🏾', '👳🏿', '💃🏽', '💃🏿', '⛷️'];
    }
    
    // Tier 2: $5 or more
    if ($total_donated >= 5) {
        $avatars['tier2'] = ['👄', '💋', '🕵️‍♀️', '✿', '👨🏿‍💻', '👩🏿‍💻', '👨🏿‍🚀', '👩🏿‍🚀', '💏🏿', '🕵🏿', '🤵🏿', '🕵🏿‍♀️', '👨🏿‍⚖️', '👩🏿‍⚖️', '🤴🏿', '👸🏿', '👍', '👍🏻', '👍🏼', '👍🏽', '👍🏾', '👍🏿', '👶', '🧕', '🧕🏻', '🧕🏽', '🧕🏾', '👩‍🦼', '👩🏻‍🦼', '👩🏾‍🦼', '👩🏿‍🦼', '👨‍🦼', '👨🏻‍🦼', '👨🏾‍🦼'];
    }
    
    // Tier 3: $25 or more
    if ($total_donated >= 25) {
        $avatars['tier3'] = ['🧢', '💀', '🖱️', '👩‍⚖️', '🦹', '🎱'];
    }
    
    // Tier 4: $50 or more
    if ($total_donated >= 50) {
        $avatars['tier4'] = ['🍆', '👨‍🎨', '🎠', '🎪', '🧖', '🛀', '🛀🏻', '🛀🏾', '🛀🏿', '🌻'];
    }
    
    return $avatars;
}

/**
 * Check if a specific avatar is unlocked for a user based on their total donations
 * 
 * @param string $avatar The avatar emoji to check
 * @param float $total_donated The total amount the user has donated
 * @return bool Whether the avatar is unlocked
 */
function is_avatar_unlocked($avatar, $total_donated) {
    $tier_avatars = get_donation_tier_avatars($total_donated);
    
    // Flatten the array of avatars
    $all_avatars = [];
    foreach ($tier_avatars as $tier => $avatars) {
        $all_avatars = array_merge($all_avatars, $avatars);
    }
    
    return in_array($avatar, $all_avatars);
}