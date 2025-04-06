<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/countries.php';

// Get leaderboard entries
$leaderboard_entries = get_top_leaderboard_entries(50);

// Filter for game modes
$game_mode = isset($_GET['mode']) ? $_GET['mode'] : 'all';
$difficulty = isset($_GET['difficulty']) ? $_GET['difficulty'] : 'all';

// Filter entries by game mode and difficulty if requested
if ($game_mode !== 'all' || $difficulty !== 'all') {
    $filtered_entries = [];
    
    foreach ($leaderboard_entries as $entry) {
        $mode_match = ($game_mode === 'all' || $entry['game_mode'] === $game_mode);
        $difficulty_match = ($difficulty === 'all' || $entry['difficulty'] === $difficulty);
        
        if ($mode_match && $difficulty_match) {
            $filtered_entries[] = $entry;
        }
    }
    
    $leaderboard_entries = $filtered_entries;
}

// Set page title
$page_title = 'Leaderboard - ' . SITE_TITLE;

// Include header
include 'includes/header.php';

// Check for new achievements in session
if (isset($_SESSION['new_achievements']) && !empty($_SESSION['new_achievements'])) {
    $new_achievements = [];
    
    // Get achievement details for display
    if (function_exists('get_available_achievements')) {
        $all_achievements = get_available_achievements();
        
        foreach ($_SESSION['new_achievements'] as $achievement_type) {
            if (isset($all_achievements[$achievement_type])) {
                $new_achievements[] = $all_achievements[$achievement_type];
            }
        }
    }
    
    // Clear from session to prevent showing again
    unset($_SESSION['new_achievements']);
    
    // Add script to show achievement notifications
    if (!empty($new_achievements)) {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const achievements = ' . json_encode($new_achievements) . ';
                setTimeout(function() { 
                    processNewAchievements(achievements);
                }, 1000);
            });
        </script>';
    }
}
?>

<div class="container py-5">
    <h1 class="text-center mb-5">Leaderboard</h1>
    
    <!-- Filters -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card bg-dark">
                <div class="card-body">
                    <h5 class="card-title">Filter Results</h5>
                    
                    <form action="leaderboard.php" method="get" class="row g-3">
                        <div class="col-md-5">
                            <label for="mode" class="form-label">Game Mode</label>
                            <select class="form-select" id="mode" name="mode">
                                <option value="all" <?= $game_mode === 'all' ? 'selected' : '' ?>>All Modes</option>
                                <option value="single" <?= $game_mode === 'single' ? 'selected' : '' ?>>Single Player</option>
                                <option value="endless" <?= $game_mode === 'endless' ? 'selected' : '' ?>>Endless Mode</option>
                                <option value="multiplayer" <?= $game_mode === 'multiplayer' ? 'selected' : '' ?>>Multiplayer</option>
                            </select>
                        </div>
                        
                        <div class="col-md-5">
                            <label for="difficulty" class="form-label">Difficulty</label>
                            <select class="form-select" id="difficulty" name="difficulty">
                                <option value="all" <?= $difficulty === 'all' ? 'selected' : '' ?>>All Difficulties</option>
                                <option value="easy" <?= $difficulty === 'easy' ? 'selected' : '' ?>>Easy</option>
                                <option value="medium" <?= $difficulty === 'medium' ? 'selected' : '' ?>>Medium</option>
                                <option value="hard" <?= $difficulty === 'hard' ? 'selected' : '' ?>>Hard</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Leaderboard Table -->
    <div class="table-responsive">
        <table class="table table-striped table-dark leaderboard-table text-center">
            <thead>
                <tr>
                    <th scope="col" class="text-center">#</th>
                    <th scope="col" class="text-center">Player</th>
                    <th scope="col" class="text-center">Country</th>
                    <th scope="col" class="text-center">Score</th>
                    <th scope="col" class="text-center">Mode</th>
                    <th scope="col" class="text-center">Difficulty</th>
                    <th scope="col" class="text-center">Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaderboard_entries)): ?>
                    <tr>
                        <td colspan="7" class="text-center">No leaderboard entries found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($leaderboard_entries as $index => $entry): ?>
                        <tr>
                            <td>
                                <?php if ($index === 0): ?>
                                    <img src="/attached_assets/1st.png" alt="1st Place" title="1st Place" style="height: 30px;" />
                                <?php elseif ($index === 1): ?>
                                    <img src="/attached_assets/2nd.png" alt="2nd Place" title="2nd Place" style="height: 30px;" />
                                <?php elseif ($index === 2): ?>
                                    <img src="/attached_assets/3rd.png" alt="3rd Place" title="3rd Place" style="height: 30px;" />
                                <?php else: ?>
                                    <?= $index + 1 ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($entry['user_id'])): ?>
                                    <?php
                                    $username = htmlspecialchars($entry['username']);
                                    // Check if admin is viewing
                                    $is_admin = isset($_SESSION['user_id']) && $_SESSION['is_admin'] === true;
                                    
                                    // If admin is viewing OR this is the current user's own entry, show full username
                                    // (unless user has enabled hide_username option)
                                    $is_current_user = isset($_SESSION['user_id']) && $entry['user_id'] == $_SESSION['user_id'];
                                    
                                    // Check if user has enabled the hide_username option
                                    $hide_username = false;
                                    
                                    // If not already set in the entry, query the database
                                    if (!isset($entry['hide_username'])) {
                                        $conn = get_db_connection();
                                        $stmt = $conn->prepare("SELECT hide_username FROM users WHERE id = :user_id");
                                        $stmt->bindParam(':user_id', $entry['user_id'], PDO::PARAM_INT);
                                        $stmt->execute();
                                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                        if ($user_data && isset($user_data['hide_username'])) {
                                            $hide_username = (bool)$user_data['hide_username'];
                                        }
                                    } else {
                                        $hide_username = (bool)$entry['hide_username'];
                                    }
                                    
                                    // Only admins see all usernames fully; users who want to hide their username will have it hidden
                                    // even on their own profile
                                    // Check if user is logged in
                                    $is_logged_in = isset($_SESSION['user_id']);
                                    
                                    // If user is admin or current user viewing their own profile, and username isn't hidden
                                    if ($is_logged_in && ($is_admin || ($is_current_user && !$hide_username))) {
                                        // Create a link to the user's profile
                                        echo '<a href="view_profile.php?username=' . urlencode($username) . '" class="text-white">' . $username . '</a>';
                                    } else {
                                        // For non-logged in users or when username should be hidden
                                        
                                        // Obfuscate username: show first and last character, replace middle chars with asterisks
                                        $len = mb_strlen($username);
                                        if ($len > 2) {
                                            $first = mb_substr($username, 0, 1);
                                            $last = mb_substr($username, $len - 1, 1);
                                            $asterisks = str_repeat('*', min($len - 2, 5)); // Cap asterisks at 5 for very long names
                                            
                                            // If user is logged in, they can still view the profile even if username is masked
                                            if ($is_logged_in) {
                                                echo '<a href="view_profile.php?username=' . urlencode($username) . '" class="text-white">' . 
                                                     $first . $asterisks . $last . '</a>';
                                            } else {
                                                echo $first . $asterisks . $last;
                                            }
                                        } else {
                                            // For very short usernames (1-2 chars), just show as is
                                            if ($is_logged_in) {
                                                echo '<a href="view_profile.php?username=' . urlencode($username) . '" class="text-white">' . 
                                                     $username . '</a>';
                                            } else {
                                                echo $username;
                                            }
                                        }
                                    }
                                    ?>
                                <?php else: ?>
                                    <?php
                                    // For anonymous users, show either "Anonymous" or their provided initials
                                    if (isset($entry['username']) && $entry['username'] === 'Anonymous') {
                                        echo 'Anonymous';
                                    } else {
                                        // For backwards compatibility with older entries
                                        $display_initials = isset($entry['username']) && !empty($entry['username']) ? 
                                            htmlspecialchars(strtoupper(substr($entry['username'], 0, 3))) : 'AAA';
                                        echo $display_initials;
                                    }
                                    ?>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php
                                // Display flag if country is set
                                $country_code = isset($entry['country']) && !empty($entry['country']) ? $entry['country'] : 'US';
                                echo get_country_flag_html($country_code, 'md');
                                ?>
                            </td>
                            <td><strong><?= $entry['score'] ?></strong></td>
                            <td><?= ucfirst($entry['game_mode']) ?></td>
                            <td>
                                <?php if ($entry['game_mode'] === 'endless'): ?>
                                    &mdash;&mdash; <!-- Long dash for Endless mode -->
                                <?php else: ?>
                                    <?= ucfirst($entry['difficulty'] ?? 'Standard') ?>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M j, Y', strtotime($entry['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-outline-primary btn-lg">Back to Home</a>
    </div>
</div>

<?php
// Include footer
include 'includes/footer.php';
?>