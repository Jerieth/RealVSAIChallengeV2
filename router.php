<?php
/**
 * PHP Router for Clean URLs
 * This script handles URL routing to appropriate PHP files
 * 
 * Note: upload_max_filesize and post_max_size cannot be changed at runtime
 * and must be set in php.ini or through the -c flag when starting the server.
 * See start_php_server.php for the proper way to start PHP with these settings.
 */

// These runtime settings can be modified during execution
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');

// Check if we have the expected upload limits
$upload_max = ini_get('upload_max_filesize');
$post_max = ini_get('post_max_size');
$memory_limit = ini_get('memory_limit');

// Log the actual PHP upload settings for debugging
error_log("PHP upload_max_filesize: " . $upload_max);
error_log("PHP post_max_size: " . $post_max);
error_log("PHP memory_limit: " . $memory_limit);

// Display warning if upload settings are too low
if (strpos($_SERVER['REQUEST_URI'], '/admin') === 0) {
    // Convert sizes to bytes for comparison
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
    
    $upload_max_bytes = return_bytes($upload_max);
    $post_max_bytes = return_bytes($post_max);
    
    if ($upload_max_bytes < 104857600 || $post_max_bytes < 104857600) { // Less than 100M
        $warning = <<<HTML
<div class="alert alert-warning" style="margin-top: 20px;">
    <strong>Warning:</strong> PHP server is running with limited upload capacity!<br>
    Current settings: upload_max_filesize={$upload_max}, post_max_size={$post_max}<br>
    For maximum performance, restart the server using one of these scripts:
    <ul>
        <li><code>./restart_server.sh</code> - Quick restart with correct settings</li>
        <li><code>./start_high_capacity_server.sh</code> - Full restart with proper upload limits</li>
        <li><code>./stop_all_and_run_php.sh</code> - Stop all PHP servers and restart with correct settings</li>
    </ul>
</div>
HTML;
        // Store the warning in a session variable to display after includes
        $_SESSION['php_upload_warning'] = $warning;
    }
}

// Set session parameters with enhanced cookie security
// Must be done before session_start
ini_set('session.cookie_lifetime', 86400); // 24 hours
ini_set('session.gc_maxlifetime', 86400); // 24 hours
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', 1);
ini_set('session.use_trans_sid', 0);

// Set cookie parameters for all cookies
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'domain' => '',  // Empty means same domain as script
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Start session at the very beginning before any output
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie parameters
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
    error_log("Router.php - Session started with ID: " . session_id());
} else {
    error_log("Router.php - Session already active with ID: " . session_id());
}

// Define application constants if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', __DIR__);
    define('APP_PATH', BASE_PATH . '/app');
    define('PUBLIC_PATH', BASE_PATH . '/public');
    define('STORAGE_PATH', BASE_PATH . '/storage');
}

// Create log directory if it doesn't exist
if (!file_exists(STORAGE_PATH . '/logs')) {
    mkdir(STORAGE_PATH . '/logs', 0777, true);
}

// Log requests for debugging
$logFile = STORAGE_PATH . '/logs/router.log';
$requestInfo = date('Y-m-d H:i:s') . " | " . $_SERVER['REMOTE_ADDR'] . " | " . 
               $_SERVER['REQUEST_METHOD'] . " | " . $_SERVER['REQUEST_URI'] . "\n";
file_put_contents($logFile, $requestInfo, FILE_APPEND);

// Debug logging to stderr (shows in server logs)
error_log("Router.php - Request URI: " . $_SERVER['REQUEST_URI']);
if (!empty($_SERVER['QUERY_STRING'])) {
    error_log("Router.php - Query string: " . $_SERVER['QUERY_STRING']);
}
error_log("Router.php - GET params: " . json_encode($_GET));

// Set default timezone
date_default_timezone_set('UTC');

// Get the URI and decode URL-encoded characters
$uri = urldecode($_SERVER["REQUEST_URI"]);
error_log("Router.php - Decoded URI: " . $uri);

// Remove query string
if (($pos = strpos($uri, "?")) !== false) {
    $uri = substr($uri, 0, $pos);
}

// Default file - use index.php for homepage only
$file = ($uri === "/" || $uri === "") ? "index.php" : "";

// Check for static files in common directories first for better performance
$staticExtensions = ['.css', '.js', '.jpg', '.jpeg', '.png', '.gif', '.svg', '.ico', '.woff', '.woff2', '.ttf', '.eot'];
$publicDirs = ['css/', 'js/', 'images/', 'static/', 'assets/', 'uploads/'];

// Fast path for static files
$path = ltrim($uri, "/");
$extension = strrchr($path, '.');
if ($extension && in_array(strtolower($extension), $staticExtensions)) {
    foreach ($publicDirs as $dir) {
        $publicPath = PUBLIC_PATH . '/' . $dir . basename($path);
        if (strpos($path, $dir) === 0 && file_exists($path)) {
            return false; // Let the web server handle static files
        } else if (file_exists($publicPath)) {
            return false; // Let the web server handle static files in public dir
        }
    }
}

// Map URI to file
if ($uri !== "/" && $uri !== "") {
    // Remove leading slash
    $path = ltrim($uri, "/");
    
    // Special case for game mode routing
    if (($path === "game" || $path === "game.php") && isset($_GET['mode'])) {
        $file = "game.php";
    }
    // Check public directory first (new structure)
    else if (file_exists(PUBLIC_PATH . '/' . $path)) {
        // Direct file access in public directory
        if (substr($path, -4) === ".php") {
            // PHP file, execute it
            $file = PUBLIC_PATH . '/' . $path;
        } else {
            // Static file (CSS, JS, images), serve it directly
            return false;
        }
    } else if (file_exists(PUBLIC_PATH . '/' . $path . ".php")) {
        // Clean URL - PHP file without extension in public directory
        $file = PUBLIC_PATH . '/' . $path . ".php";
    }
    // Fallback to old structure for backward compatibility
    else if (file_exists($path)) {
        // Direct file access
        if (substr($path, -4) === ".php") {
            // PHP file, execute it
            $file = $path;
        } else {
            // Static file (CSS, JS, images), serve it directly
            return false;
        }
    } else if (file_exists($path . ".php")) {
        // Clean URL - PHP file without extension
        $file = $path . ".php";
    // php/ subdirectory has been migrated to root directory
    // No longer searching in php/ subdirectory
    } else {
        // Handle 404 errors
        header("HTTP/1.0 404 Not Found");
        if (file_exists(PUBLIC_PATH . "/404.php")) {
            $file = PUBLIC_PATH . "/404.php";
        } else if (file_exists("404.php")) {
            $file = "404.php";
        } else {
            echo "404 Not Found: " . htmlspecialchars($path);
            exit;
        }
    }
}

// Define a safe include function to prevent redeclaration errors
function safely_require_once($path) {
    static $included_files = array();
    $realpath = realpath($path);
    
    if ($realpath && !isset($included_files[$realpath])) {
        $included_files[$realpath] = true;
        require_once $path;
        return true;
    }
    return false;
}

// Check if bootstrap.php exists and include it
if (file_exists(BASE_PATH . '/bootstrap.php')) {
    safely_require_once(BASE_PATH . '/bootstrap.php');
} else {
    // Fallback to old structure

    // Load the core modules in the right order to prevent function redeclaration
    // First config, then database, then core functions
    if (file_exists('includes/config.php')) {
        safely_require_once('includes/config.php');
    }

    if (file_exists('includes/database.php')) {
        safely_require_once('includes/database.php');
    }

    // Don't load achievement functions directly as they rely on functions.php
    // Load functions.php if needed
    if (!function_exists('get_user_by_id')) {
        if (file_exists('includes/functions.php')) {
            safely_require_once('includes/functions.php');
        }
    }
}

// Debug the file path
error_log("Router.php - Will include file: " . ($file ? $file : "(none)"));

// Security check to avoid including empty file
if (empty($file)) {
    error_log("Router.php - No valid file found to include, checking for common files");
    
    // Try some common PHP files directly
    $common_files = ['index.php', 'game.php', 'login.php', 'register.php'];
    $requested_uri = urldecode($_SERVER['REQUEST_URI']);
    $requested_file = ltrim($requested_uri, '/');
    $requested_file = explode('?', $requested_file)[0]; // Remove query string
    
    error_log("Router.php - Looking for file: " . $requested_file);
    
    // Special case for game mode routing
    if (($requested_file === "game" || $requested_file === "game.php") && isset($_GET['mode'])) {
        $file = "game.php";
        error_log("Router.php - Game mode detected with mode: " . $_GET['mode']);
    }
    // Special case for daily challenge routes
    else if ($requested_file === "daily-summary") {
        $file = "daily-summary.php";
        error_log("Router.php - Daily challenge summary page requested");
    }
    else if ($requested_file === "daily-game") {
        $file = "controllers/daily_game.php";
        error_log("Router.php - Daily challenge game controller requested");
    }
    else if ($requested_file === "daily-victory") {
        $file = "controllers/daily_victory.php";
        error_log("Router.php - Daily challenge victory controller requested");
    }
    else if ($requested_file === "daily-game-over") {
        $file = "controllers/daily_game_over.php";
        error_log("Router.php - Daily challenge game over controller requested");
    }
    // Regular file lookup
    else if (in_array($requested_file, $common_files) && file_exists($requested_file)) {
        $file = $requested_file;
        error_log("Router.php - Found common file: " . $file);
    } else {
        error_log("Router.php - No common file found, serving 404");
        header("HTTP/1.0 404 Not Found");
        if (file_exists("404.php")) {
            $file = "404.php";
        } else {
            echo "404 Not Found: " . htmlspecialchars($requested_file);
            exit;
        }
    }
}

// Execute the PHP file
include $file;