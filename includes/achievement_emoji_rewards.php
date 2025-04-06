<?php
/**
 * Achievement-specific emoji rewards
 * This file contains the mapping of achievement categories to special emoji rewards
 */

/**
 * Get achievement-specific emoji rewards
 * 
 * @return array Associative array of achievement types => emoji arrays
 */
function get_achievement_emoji_rewards() {
    return [
        // Tutorial achievements
        'tutorial_graduate' => ['🎓'],
        
        // Dedicated Player achievements
        'dedicated_player' => ['🖼️'],
        
        // Image Master achievements
        'image_master' => ['👑', '☀️', '💯'],
        
        // Veteran achievements
        'veteran' => ['🎖️', '⚔️', '💂🏼‍♂️', '💂‍♀️'],
        
        // AI Detection achievements
        'ai_detection_guru' => ['👨‍🔬', '👨🏻‍🔬', '👨🏾‍🔬', '👩🏻‍🔬', '👩‍🔬', '👩🏾‍🔬'],
        
        // Streak achievements
        'streak_master' => ['🚣', '🧸'],
        
        // Flawless Victory achievements
        'flawless_victory' => ['🔮', '💎', '🎯'],
        
        // Active Player achievements
        'active_player' => ['🍎'],
        
        // Bonus Hunter achievements
        'bonus_hunter' => ['🤠', '🎲'],
        
        // Enthusiast achievements
        'enthusiast' => ['🐱‍🐉', '🐱‍👤', '🐱‍💻', '🦸‍♀️', '🦸‍♂️'],
        
        // Beginner Detective achievements
        'beginner_detective' => ['🥉', '⭐'],
        
        // Daily Challenger achievements
        'daily_challenger' => ['🍾'],
        
        // Skilled Investigator achievements
        'skilled_investigator' => ['🌠', '🥈', '🌟'],
        
        // Master Analyst achievements
        'master_analyst' => ['👨‍🎤', '👨🏻‍🎤', '👨🏾‍🎤', '👩‍🎤', '👩🏻‍🎤', '👩🏾‍🎤', '☘️', '🥳'],
        
        // Achievement count milestones
        'achievements_5_plus' => ['🐲', '🐉', '🐠', '🐊', '👨‍🎓', '👩‍🎓', '👨🏽‍🎓', '👩🏽‍🎓'],
        'achievements_10_plus' => ['🔭', '🔎', '🦕', '🦖', '🦍', '🦮', '🐝', '🦋'],
        'achievements_15_plus' => ['☄️', '🪂', '🏃‍♂️', '🤾‍♀️', '🧗‍♂️', '🚵‍♀️', '🧘‍♂️'],
        'achievements_18_plus' => ['⛩️', '🌎', '⚡', '✨', '🌈', '🌏', '🌍'],
        
        // Donator achievement (specific to users who have donated)
        'donator' => ['💰', '💵', '💶', '💳', '🧧'],
        
        // Multiplayer achievements
        'multiplayer_champion' => ['🥇', '🏅', '🏆', '⚜️'],
        
        // High-value donor avatars ($100+)
        'high_value_donor' => ['🤑', '💲', '💸', '🎰', '💰']
    ];
}

/**
 * Get achievement emoji rewards for a specific user based on their achievements
 * 
 * @param int $user_id The user ID to check
 * @return array Associative array of achievement types => emoji arrays the user has unlocked
 */
function get_user_achievement_emoji_rewards($user_id) {
    // Connect to database
    $conn = get_db_connection();
    
    // Get all achievement definitions
    $stmt = $conn->prepare("SELECT id, name, description, category, criteria FROM achievements");
    $stmt->execute();
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's earned achievements
    $stmt = $conn->prepare("
        SELECT a.id, a.name, a.description, a.category, a.criteria 
        FROM achievements a
        JOIN user_achievements ua ON a.id = ua.achievement_id
        WHERE ua.user_id = :user_id
    ");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $earned_achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count earned achievements
    $earned_count = count($earned_achievements);
    
    // Get all available emoji rewards
    $all_emoji_rewards = get_achievement_emoji_rewards();
    
    // Initialize user rewards
    $user_rewards = [];
    
    // Check for tutorial completion
    foreach ($earned_achievements as $achievement) {
        if ($achievement['category'] == 'Tutorial' && strpos($achievement['name'], 'Tutorial Graduate') !== false) {
            $user_rewards['tutorial_graduate'] = $all_emoji_rewards['tutorial_graduate'];
        }
        
        // Check for dedicated player achievements
        if ($achievement['category'] == 'Game Count' && 
            (strpos($achievement['name'], 'Dedicated Player') !== false || 
             strpos($achievement['name'], '100 Games') !== false)) {
            $user_rewards['dedicated_player'] = $all_emoji_rewards['dedicated_player'];
        }
        
        // Check for image master achievements
        if ($achievement['category'] == 'Score' && 
            (strpos($achievement['name'], 'Image Master') !== false || 
             strpos($achievement['name'], 'Perfect Score') !== false)) {
            $user_rewards['image_master'] = $all_emoji_rewards['image_master'];
        }
        
        // Check for veteran achievements
        if ($achievement['category'] == 'Game Count' && 
            (strpos($achievement['name'], 'Veteran') !== false || 
             strpos($achievement['name'], '500 Games') !== false)) {
            $user_rewards['veteran'] = $all_emoji_rewards['veteran'];
        }
        
        // Check for AI detection guru achievements
        if ($achievement['category'] == 'Skill' && 
            (strpos($achievement['name'], 'AI Detection Guru') !== false)) {
            $user_rewards['ai_detection_guru'] = $all_emoji_rewards['ai_detection_guru'];
        }
        
        // Check for streak master achievements
        if ($achievement['category'] == 'Streak' && 
            (strpos($achievement['name'], 'Streak Master') !== false)) {
            $user_rewards['streak_master'] = $all_emoji_rewards['streak_master'];
        }
        
        // Check for flawless victory achievements
        if ($achievement['category'] == 'Score' && 
            (strpos($achievement['name'], 'Flawless Victory') !== false)) {
            $user_rewards['flawless_victory'] = $all_emoji_rewards['flawless_victory'];
        }
        
        // Check for active player achievements
        if ($achievement['category'] == 'Game Count' && 
            (strpos($achievement['name'], 'Active Player') !== false || 
             strpos($achievement['name'], '50 Games') !== false)) {
            $user_rewards['active_player'] = $all_emoji_rewards['active_player'];
        }
        
        // Check for bonus hunter achievements
        if ($achievement['category'] == 'Bonus' && 
            (strpos($achievement['name'], 'Bonus Hunter') !== false)) {
            $user_rewards['bonus_hunter'] = $all_emoji_rewards['bonus_hunter'];
        }
        
        // Check for enthusiast achievements
        if ($achievement['category'] == 'Game Count' && 
            (strpos($achievement['name'], 'Enthusiast') !== false || 
             strpos($achievement['name'], '25 Games') !== false)) {
            $user_rewards['enthusiast'] = $all_emoji_rewards['enthusiast'];
        }
        
        // Check for beginner detective achievements
        if ($achievement['category'] == 'Score' && 
            (strpos($achievement['name'], 'Beginner Detective') !== false || 
             strpos($achievement['name'], 'Bronze Tier') !== false)) {
            $user_rewards['beginner_detective'] = $all_emoji_rewards['beginner_detective'];
        }
        
        // Check for daily challenger achievements
        if ($achievement['category'] == 'Daily Challenge' && 
            (strpos($achievement['name'], 'Daily Challenger') !== false)) {
            $user_rewards['daily_challenger'] = $all_emoji_rewards['daily_challenger'];
        }
        
        // Check for skilled investigator achievements
        if ($achievement['category'] == 'Score' && 
            (strpos($achievement['name'], 'Skilled Investigator') !== false || 
             strpos($achievement['name'], 'Silver Tier') !== false)) {
            $user_rewards['skilled_investigator'] = $all_emoji_rewards['skilled_investigator'];
        }
        
        // Check for master analyst achievements
        if ($achievement['category'] == 'Score' && 
            (strpos($achievement['name'], 'Master Analyst') !== false || 
             strpos($achievement['name'], 'Gold Tier') !== false)) {
            $user_rewards['master_analyst'] = $all_emoji_rewards['master_analyst'];
        }
        
        // Check for donator achievement
        if ($achievement['category'] == 'Special' && 
            (strpos($achievement['name'], 'Donator') !== false)) {
            $user_rewards['donator'] = $all_emoji_rewards['donator'];
        }
        
        // Check for multiplayer achievements
        if ($achievement['category'] == 'Multiplayer' && 
            (strpos($achievement['name'], 'Multiplayer Champion') !== false)) {
            $user_rewards['multiplayer_champion'] = $all_emoji_rewards['multiplayer_champion'];
        }
    }
    
    // Add achievement count milestone rewards
    if ($earned_count >= 5) {
        $user_rewards['achievements_5_plus'] = $all_emoji_rewards['achievements_5_plus'];
    }
    
    if ($earned_count >= 10) {
        $user_rewards['achievements_10_plus'] = $all_emoji_rewards['achievements_10_plus'];
    }
    
    if ($earned_count >= 15) {
        $user_rewards['achievements_15_plus'] = $all_emoji_rewards['achievements_15_plus'];
    }
    
    if ($earned_count >= 18) {
        $user_rewards['achievements_18_plus'] = $all_emoji_rewards['achievements_18_plus'];
    }
    
    return $user_rewards;
}
?>