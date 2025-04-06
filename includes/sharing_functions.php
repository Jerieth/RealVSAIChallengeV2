<?php
/**
 * Sharing functions for Real vs AI application
 * Handles social sharing functionality
 */

/**
 * Generate a share URL for Facebook
 * 
 * @param string $title Title to share
 * @param string $description Description to share
 * @param string $url URL to share
 * @return string Facebook share URL
 */
function get_facebook_share_url($title, $description, $url) {
    $url = urlencode($url);
    $title = urlencode($title);
    
    return "https://www.facebook.com/sharer/sharer.php?u={$url}&quote={$title}";
}

/**
 * Generate a share URL for Twitter/X
 * 
 * @param string $text Text to share
 * @param string $url URL to share
 * @return string Twitter/X share URL
 */
function get_twitter_share_url($text, $url) {
    $url = urlencode($url);
    $text = urlencode($text);
    
    return "https://twitter.com/intent/tweet?text={$text}&url={$url}";
}

/**
 * Generate an email share link
 * 
 * @param string $subject Email subject
 * @param string $body Email body
 * @return string Email mailto link
 */
function get_email_share_url($subject, $body) {
    $subject = urlencode($subject);
    $body = urlencode($body);
    
    return "mailto:?subject={$subject}&body={$body}";
}

/**
 * Generate a share URL for LinkedIn
 * 
 * @param string $title Title to share
 * @param string $summary Summary to share
 * @param string $url URL to share
 * @return string LinkedIn share URL
 */
function get_linkedin_share_url($title, $summary, $url) {
    $url = urlencode($url);
    $title = urlencode($title);
    $summary = urlencode($summary);
    
    return "https://www.linkedin.com/shareArticle?mini=true&url={$url}&title={$title}&summary={$summary}";
}

/**
 * Generate a share URL for WhatsApp
 * 
 * @param string $text Text to share
 * @return string WhatsApp share URL
 */
function get_whatsapp_share_url($text) {
    $text = urlencode($text);
    
    return "https://wa.me/?text={$text}";
}

/**
 * Generate all share links for a game score
 * 
 * @param array $game Game data
 * @param array $stats Game statistics
 * @return array Share links
 */
function get_game_share_links($game, $stats) {
    $site_url = 'https://www.realvsai.com';
    $share_url = "{$site_url}/start-game";
    
    // Determine game mode text
    $game_mode_text = '';
    if ($game['game_mode'] === 'single') {
        $game_mode_text = 'Single Player - ' . ucfirst($game['difficulty']);
    } else if ($game['game_mode'] === 'endless') {
        $game_mode_text = 'Endless Mode';
    } else if ($game['game_mode'] === 'multiplayer') {
        $game_mode_text = 'Multiplayer';
    }
    
    // Create share text
    $title = "I scored {$game['score']} points in Real vs AI!";
    $description = "I got {$stats['accuracy']}% accuracy in {$game_mode_text}. Think you can beat my score? Try Real vs AI, a game that challenges you to distinguish between real and AI-generated images!";
    $combined_text = "{$title} {$description}";
    
    $email_body = "{$title}\n\n{$description}\n\nPlay now at: {$share_url}";
    
    return [
        'facebook' => get_facebook_share_url($title, $description, $share_url),
        'twitter' => get_twitter_share_url($combined_text, $share_url),
        'email' => get_email_share_url($title, $email_body),
        'linkedin' => get_linkedin_share_url($title, $description, $share_url),
        'whatsapp' => get_whatsapp_share_url("{$combined_text} {$share_url}")
    ];
}