<?php
/**
 * User profile page
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/template.php';
require_once __DIR__ . '/includes/achievement_functions.php';
require_once __DIR__ . '/includes/daily_rewards_functions.php';
require_once __DIR__ . '/models/User.php';
require_once __DIR__ . '/models/Achievement.php';
require_once __DIR__ . '/models/Game.php';
require_once __DIR__ . '/models/Leaderboard.php';

// Require login
require_login();

// Get current user
$user = get_current_logged_in_user();

// Include donation functions
require_once __DIR__ . '/includes/donation_functions.php';
// Include achievement emoji rewards
require_once __DIR__ . '/includes/achievement_emoji_rewards.php';

// Get total donations for this user
$total_donations = 0;
if (isset($user['id'])) {
    $total_donations = get_user_total_donations($user['id']);
}

// Get available donation tier avatars
$tier_avatars = get_donation_tier_avatars($total_donations);

// Get achievement-specific emoji rewards
$achievement_avatars = [];
if (isset($user['id'])) {
    $achievement_avatars = get_user_achievement_emoji_rewards($user['id']);
}

// Get high-value donor avatars ($100+)
$high_value_donor_avatars = [];
if ($total_donations >= 100) {
    $high_value_donor_avatars = get_achievement_emoji_rewards();
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Handle avatar update
    if ($action === 'update_avatar') {
        $avatar = $_POST['avatar'] ?? '';

        if (update_user_avatar($user['id'], $avatar)) {
            set_flash_message('Avatar updated successfully!', 'success');
            // Update the user object with the new avatar
            $user['avatar'] = $avatar;
        } else {
            set_flash_message('Failed to update avatar.', 'danger');
        }
    }

    // Handle timer preference update
    if ($action === 'update_timer_preference') {
        $hide_timer = isset($_POST['hide_timer']) ? 1 : 0;
        $hide_username = isset($_POST['hide_username']) ? 1 : 0;

        $timer_success = update_user_timer_preference($user['id'], $hide_timer);
        $username_success = update_user_hide_username_preference($user['id'], $hide_username);

        if ($timer_success && $username_success) {
            set_flash_message('Game preferences updated successfully!', 'success');
            // Update the user object with the new preferences
            $user['hide_timer'] = $hide_timer;
            $user['hide_username'] = $hide_username;
        } else {
            set_flash_message('Failed to update some game preferences.', 'danger');
        }
    }

    // Handle email update
    if ($action === 'update_email') {
        $new_email = $_POST['new_email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validate the email
        if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            set_flash_message('Invalid email format.', 'danger');
        } 
        // Verify current password
        else if (!verify_user_password($user['id'], $password)) {
            set_flash_message('Incorrect password.', 'danger');
        }
        // Check if the email is already taken
        else if (!is_email_available($new_email) && $new_email !== $user['email']) {
            set_flash_message('Email is already in use by another account.', 'danger');
        }
        // Update the email
        else if (update_user_email($user['id'], $new_email, $password)) {
            set_flash_message('Email updated successfully!', 'success');
            // Update the user object with the new email
            $user['email'] = $new_email;
        } else {
            set_flash_message('Failed to update email.', 'danger');
        }
    }

    // Handle password update
    if ($action === 'update_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validate password
        if (strlen($new_password) < 8) {
            set_flash_message('Password must be at least 8 characters long.', 'danger');
        }
        // Verify current password
        else if (!verify_user_password($user['id'], $current_password)) {
            set_flash_message('Incorrect current password.', 'danger');
        }
        // Check if new passwords match
        else if ($new_password !== $confirm_password) {
            set_flash_message('New passwords do not match.', 'danger');
        }
        // Update the password
        else if (update_user_password($user['id'], $new_password)) {
            set_flash_message('Password updated successfully!', 'success');
        } else {
            set_flash_message('Failed to update password.', 'danger');
        }
    }

    // Redirect to remove POST data
    header('Location: /profile.php');
    exit;
}

// Get the already seen achievements from session
$seen_achievements = isset($_SESSION['seen_achievements'][$user['id']]) ? $_SESSION['seen_achievements'][$user['id']] : [];

// Check if user has completed the tutorial (from user table) and award the achievement if needed
require_once __DIR__ . '/includes/auth_functions.php';
if (has_completed_tutorial($user['id'])) {
    // Award the tutorial achievement if they don't have it already
    award_achievement_model($user['id'], 'complete_tutorial');
}

// Direct database check for "Flawless Victory" - check for any games with perfect score
$db = get_db_connection();
$stmt = $db->prepare("
    SELECT 1
    FROM games 
    WHERE user_id = ? AND completed = 1 AND lives = starting_lives AND starting_lives > 0
    LIMIT 1
");
$stmt->execute([$user['id']]);

if ($stmt->rowCount() > 0) {
    award_achievement_model($user['id'], 'perfect_score');
    error_log("Achievement check on profile: Perfect score (Flawless Victory) awarded");
}

// Direct database check for "Beginner Detective" - check for completed easy games
$stmt = $db->prepare("
    SELECT 1 
    FROM games 
    WHERE user_id = ? 
    AND game_mode = 'single' 
    AND difficulty = 'easy' 
    AND completed = 1 
    LIMIT 1
");
$stmt->execute([$user['id']]);
if ($stmt->rowCount() > 0) {
    award_achievement_model($user['id'], 'complete_easy');
    error_log("Achievement check on profile: Beginner Detective awarded");
}

// Check and award any new achievements based on user stats
$newly_awarded = check_and_award_achievements($user['id']);

// Filter out already seen achievements to only show truly new ones
$truly_new = array_diff($newly_awarded, $seen_achievements);

// Add newly seen achievements to the session
if (!isset($_SESSION['seen_achievements'])) {
    $_SESSION['seen_achievements'] = [];
}
if (!isset($_SESSION['seen_achievements'][$user['id']])) {
    $_SESSION['seen_achievements'][$user['id']] = [];
}
$_SESSION['seen_achievements'][$user['id']] = array_unique(array_merge($_SESSION['seen_achievements'][$user['id']], $newly_awarded));

// Show notification only for truly new achievements
if (!empty($truly_new)) {
    $achievement_names = [];
    $all_achievements = get_available_achievements();

    foreach ($truly_new as $achievement_type) {
        if (isset($all_achievements[$achievement_type])) {
            $achievement_names[] = $all_achievements[$achievement_type]['title'];
        }
    }

    if (!empty($achievement_names)) {
        $message = 'Congratulations! You\'ve earned new achievements: ' . implode(', ', $achievement_names);
        set_flash_message($message, 'success');
    }
}

// Get user data for the profile page
$user_stats = [
    'total_games' => get_user_total_games($user['id']),
    'highest_score' => get_user_highest_score($user['id']),
    'joined' => $user['created_at']
];

// Get the user's daily challenge stats if available
$daily_challenge_stats = null;
$db = get_db_connection();
$stmt = $db->prepare("
    SELECT * FROM daily_challenge 
    WHERE username = :username
    LIMIT 1
");
$stmt->bindParam(':username', $user['username']);
$stmt->execute();
$daily_challenge_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's achievements
$user_achievements = get_user_achievements($user['id']);
$all_achievements = get_all_achievements_with_status($user['id']);

// Calculate number of earned achievements
$earned_achievements_count = 0;
foreach ($all_achievements as $achievement) {
    if (isset($achievement['earned']) && $achievement['earned']) {
        $earned_achievements_count++;
    }
}

// Get user's recent games
$recent_games = get_user_game_history($user['id'], 5);

// Get user's best scores
$best_scores = get_user_best_leaderboard_entries($user['id'], 5);

// Get user's daily rewards (avatars)
$db = get_db_connection();
$user_rewards = get_user_daily_rewards($db, $user['id']);

// Use the achievement avatars we already loaded at the beginning of the file

// Get all available avatar emojis by combining all types
$all_available_avatars = [];

// Add daily rewards avatars
if (!empty($user_rewards)) {
    $all_available_avatars = array_merge($all_available_avatars, $user_rewards);
}

// Add donation tier avatars
foreach ($tier_avatars as $tier => $avatars) {
    $all_available_avatars = array_merge($all_available_avatars, $avatars);
}

// Add achievement avatars
foreach ($achievement_avatars as $type => $avatars) {
    $all_available_avatars = array_merge($all_available_avatars, $avatars);
}

// Add high value donor avatars if applicable
if ($total_donations >= 100 && !empty($high_value_donor_avatars['high_value_donor'])) {
    $all_available_avatars = array_merge($all_available_avatars, $high_value_donor_avatars['high_value_donor']);
}

// Remove duplicates
$all_available_avatars = array_unique($all_available_avatars);

// Use the namespaced render_template function from Template namespace
use RealAI\Template;

// Render the profile page
$content = Template\render_template('templates/profile.php', [
    'user' => $user,
    'user_stats' => $user_stats,
    'achievements' => $all_achievements,
    'user_achievements' => $user_achievements,
    'recent_games' => $recent_games,
    'best_scores' => $best_scores,
    'earned_achievements_count' => $earned_achievements_count,
    'daily_challenge_stats' => $daily_challenge_stats,
    'user_rewards' => $user_rewards,
    'tier_avatars' => $tier_avatars,
    'achievement_avatars' => $achievement_avatars,
    'high_value_donor_avatars' => $high_value_donor_avatars,
    'all_available_avatars' => $all_available_avatars,
    'total_donations' => $total_donations
], true);

// Use the namespaced render_template function for the entire layout
echo Template\render_template('templates/layout.php', [
    'page_title' => htmlspecialchars($user['username']) . "'s Profile - " . APP_NAME,
    'content' => $content,
    'additional_head' => '<link rel="stylesheet" href="/static/css/achievements.css"><link rel="stylesheet" href="/static/css/flag-icons.css">',
    'additional_scripts' => '<script src="/static/js/profile.js"></script>'
]);
?>