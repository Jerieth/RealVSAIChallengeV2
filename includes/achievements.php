<?php
/**
 * Achievements System
 * Handles personal achievements/badges for users
 */

require_once __DIR__ . '/../models/Achievement.php';

/**
 * Check for game completion achievements
 * 
 * @param int $user_id User ID
 * @param string $game_mode Game mode (single, multiplayer, endless)
 * @param string $difficulty Game difficulty (easy, medium, hard)
 * @param int $score Final score
 * @return array List of newly awarded achievement types
 */
if (!function_exists('check_game_completion_achievements')) {
function check_game_completion_achievements($user_id, $game_mode, $difficulty, $score) {
    // Only check for logged-in users
    if (!$user_id) return [];
    
    $newly_awarded = [];
    
    // Check score-based achievements for all game modes
    if ($score >= 200) {
        if (award_achievement($user_id, 'reach_score_200')) {
            $newly_awarded[] = 'reach_score_200';
        }
    }
    if ($score >= 100) {
        if (award_achievement($user_id, 'reach_score_100')) {
            $newly_awarded[] = 'reach_score_100';
        }
    }
    if ($score >= 50) {
        if (award_achievement($user_id, 'reach_score_50')) {
            $newly_awarded[] = 'reach_score_50';
        }
    }
    if ($score >= 20) {
        if (award_achievement($user_id, 'reach_score_20')) {
            $newly_awarded[] = 'reach_score_20';
        }
    }
    
    if ($game_mode == 'single') {
        // Check difficulty completions
        if ($difficulty == 'easy') {
            if (award_achievement($user_id, 'complete_easy')) {
                $newly_awarded[] = 'complete_easy';
            }
        } elseif ($difficulty == 'medium') {
            if (award_achievement($user_id, 'complete_medium')) {
                $newly_awarded[] = 'complete_medium';
            }
            // Also award the easy achievement if they don't have it
            award_achievement($user_id, 'complete_easy');
        } elseif ($difficulty == 'hard') {
            if (award_achievement($user_id, 'complete_hard')) {
                $newly_awarded[] = 'complete_hard';
            }
            // Also award the lesser achievements if they don't have them
            award_achievement($user_id, 'complete_medium');
            award_achievement($user_id, 'complete_easy');
        }
    } elseif ($game_mode == 'endless' && $score >= 100) {
        // Award endless mode achievement if implemented
        // Currently not in Achievement model
    }
    
    // Check for multiplayer achievements
    if ($game_mode == 'multiplayer') {
        $db = get_db_connection();
        
        // Check if the user won this multiplayer game
        $stmt = $db->prepare("
            SELECT * FROM multiplayer_games 
            WHERE session_id = :session_id AND completed = 1
        ");
        
        $session_id = $_SESSION['game_session_id'] ?? '';
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($game) {
            // Determine the winner
            $player_scores = [
                $game['player1_id'] => $game['player1_score'],
                $game['player2_id'] => $game['player2_score'],
                $game['player3_id'] => $game['player3_score'],
                $game['player4_id'] => $game['player4_score'],
            ];
            
            // Remove null entries
            foreach ($player_scores as $pid => $pscore) {
                if (is_null($pid)) {
                    unset($player_scores[$pid]);
                }
            }
            
            // Find max score
            $max_score = max($player_scores);
            $winners = array_keys($player_scores, $max_score);
            
            // Award to winners
            if (in_array($user_id, $winners)) {
                if (award_achievement($user_id, 'win_multiplayer')) {
                    $newly_awarded[] = 'win_multiplayer';
                }
            }
        }
    }
    
    // Check for perfect score (no lives lost)
    if ($game_mode == 'single') {
        $db = get_db_connection();
        
        $stmt = $db->prepare("
            SELECT * FROM games 
            WHERE session_id = :session_id AND completed = 1
        ");
        
        $session_id = $_SESSION['game_session_id'] ?? '';
        $stmt->bindParam(':session_id', $session_id, PDO::PARAM_STR);
        $stmt->execute();
        
        $game = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $original_lives = 0;
        if ($difficulty == 'easy') {
            $original_lives = 5;
        } elseif ($difficulty == 'medium') {
            $original_lives = 3;
        } elseif ($difficulty == 'hard') {
            $original_lives = 1;
        }
        
        // Check if no lives were lost
        if ($game && $game['lives'] == $original_lives) {
            if (award_achievement($user_id, 'perfect_score')) {
                $newly_awarded[] = 'perfect_score';
            }
        }
    }
    
    // Check other general achievements (game count, etc)
    $game_data = [
        'difficulty' => $difficulty,
        'mode' => $game_mode,
        'score' => $score
    ];
    check_and_award_achievements($user_id, $game_data);
    
    return $newly_awarded;
}
}

/**
 * Generate social share links for various platforms
 * 
 * @param int $score Player's score
 * @param string $game_mode Game mode (single, multiplayer, endless)
 * @param string $difficulty Game difficulty (easy, medium, hard)
 * @return array Social share links for various platforms
 */
if (!function_exists('generate_social_share_links')) {
function generate_social_share_links($score, $game_mode, $difficulty = '') {
    // Use fixed URL for the production site
    $site_url = "https://www.realvsai.com";
    $page_url = $site_url;
    
    $mode_text = ucfirst($game_mode);
    if ($game_mode == 'single' && !empty($difficulty)) {
        $mode_text .= " (" . ucfirst($difficulty) . ")";
    }
    
    $share_text = urlencode("I scored $score points in $mode_text mode on Real vs AI! Can you beat my score?");
    
    return [
        'facebook' => "https://www.facebook.com/sharer/sharer.php?u=" . urlencode($page_url) . "&quote=" . $share_text,
        'twitter' => "https://twitter.com/intent/tweet?text=" . $share_text . "&url=" . urlencode($page_url),
        'email' => "mailto:?subject=" . urlencode("My Real vs AI Score") . "&body=" . $share_text . " " . urlencode($page_url)
    ];
}
}

/**
 * Get user's total games played (including unfinished)
 * 
 * @param int $user_id User ID
 * @return int Total number of games played
 */
if (!function_exists('get_user_total_games')) {
    function get_user_total_games($user_id) {
        $db = get_db_connection();
        
        $stmt = $db->prepare("
            SELECT COUNT(*) as total_games 
            FROM games 
            WHERE user_id = :user_id
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total_games'] ?? 0;
    }
}

/**
 * Get user's highest score from any game
 * 
 * @param int $user_id User ID
 * @return int Highest score
 */
if (!function_exists('get_user_highest_score')) {
    function get_user_highest_score($user_id) {
        $db = get_db_connection();
        
        $stmt = $db->prepare("
            SELECT MAX(score) as highest_score 
            FROM games 
            WHERE user_id = :user_id
        ");
        
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['highest_score'] ?? 0;
    }
}