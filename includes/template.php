<?php
/**
 * Template functions for Real vs AI application
 * Handles rendering templates and template utilities
 */

namespace RealAI\Template;

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Get the current URL
 * 
 * @return string The current URL
 */
function get_current_url() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $uri = $_SERVER['REQUEST_URI'];
    
    return "$protocol://$host$uri";
}

/**
 * Check if a given URL matches the current URL path
 * 
 * @param string $url URL to check
 * @return bool True if the URL matches the current URL path
 */
function is_current_url($url) {
    $current_uri = $_SERVER['REQUEST_URI'];
    
    // Remove query string if present
    $current_uri = strtok($current_uri, '?');
    
    // Remove trailing slash if present
    $current_uri = rtrim($current_uri, '/');
    
    // If empty URI, set to home page
    if (empty($current_uri)) {
        $current_uri = '/';
    }
    
    return $current_uri === $url;
}

/**
 * Get a flash message from the session and clear it
 * 
 * @param string $key The flash message key
 * @param mixed $default The default value if no flash message exists
 * @return mixed The flash message or default value
 */
function get_flash($key, $default = null) {
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    
    return $default;
}

/**
 * Set a flash message in the session
 * 
 * @param string $key The flash message key
 * @param mixed $value The flash message value
 * @return void
 */
function set_flash($key, $value) {
    $_SESSION['flash'][$key] = $value;
}

/**
 * Render flash messages as HTML
 * 
 * @return string The HTML for all flash messages
 */
function render_flash_messages() {
    $html = '';
    
    if (isset($_SESSION['flash']) && !empty($_SESSION['flash'])) {
        foreach ($_SESSION['flash'] as $type => $message) {
            if (in_array($type, ['success', 'danger', 'warning', 'info'])) {
                $icon = '';
                
                // Add appropriate icon
                switch ($type) {
                    case 'success':
                        $icon = '<i class="fas fa-check-circle me-2"></i>';
                        break;
                    case 'danger':
                        $icon = '<i class="fas fa-exclamation-circle me-2"></i>';
                        break;
                    case 'warning':
                        $icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
                        break;
                    case 'info':
                        $icon = '<i class="fas fa-info-circle me-2"></i>';
                        break;
                }
                
                $html .= '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">';
                $html .= $icon . htmlspecialchars($message);
                $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                $html .= '</div>';
                
                // Clear the flash message
                unset($_SESSION['flash'][$type]);
            }
        }
    }
    
    return $html;
}

/**
 * Render a template with variables
 * 
 * @param string $template The template path
 * @param array $vars Variables to pass to the template
 * @param bool $return_content Whether to return the content or output it directly
 * @return string|void The rendered template content if $return_content is true, otherwise void
 */
function render_template($template, $vars = [], $return_content = false) {
    // Extract variables to make them available in the template
    extract($vars);
    
    // Start output buffering
    ob_start();
    
    // Include the main template
    if (file_exists(__DIR__ . '/../' . $template)) {
        include __DIR__ . '/../' . $template;
    } else if (file_exists(__DIR__ . '/../templates/' . $template)) {
        include __DIR__ . '/../templates/' . $template;
    } else if (file_exists(__DIR__ . '/../templates/' . $template . '.php')) {
        include __DIR__ . '/../templates/' . $template . '.php';
    } else {
        echo "Template not found: " . $template;
    }
    
    // Get the buffered content
    $content = ob_get_clean();
    
    if ($return_content) {
        return $content;
    } else {
        // Output to browser
        echo $content;
    }
}

/**
 * Render a template with a layout
 * 
 * @param string $layout The layout template to use
 * @param array $vars Variables to pass to the layout
 * @return void
 */
function render_with_layout($layout, $vars = []) {
    // Set default values for optional variables
    $default_vars = [
        'page_title' => APP_NAME,
        'content' => '',
        'additional_css' => [],
        'additional_js' => [],
        'custom_js' => ''
    ];
    
    // Merge default variables with provided variables
    $vars = array_merge($default_vars, $vars);
    
    // Extract variables to make them available in the template
    extract($vars);
    
    // Start output buffering
    ob_start();
    
    // Include the header template
    include __DIR__ . '/../templates/layout/header.php';
    
    // Display flash messages
    echo '<div class="container">';
    echo render_flash_messages();
    echo '</div>';
    
    // Output the content
    if (!empty($content)) {
        echo $content;
    }
    
    // Include the footer template
    include __DIR__ . '/../templates/layout/footer.php';
    
    // Send the output to the browser
    ob_end_flush();
}

/**
 * Render a template without the header and footer
 * 
 * @param string $template The template path
 * @param array $vars Variables to pass to the template
 * @return void
 */
function render_template_without_layout($template, $vars = []) {
    // Extract variables to make them available in the template
    extract($vars);
    
    // Include the template
    include __DIR__ . '/../' . $template;
}

/**
 * Format a date using a specified format
 * 
 * @param string $date The date to format
 * @param string $format The format to use
 * @return string The formatted date
 */
function template_format_date($date, $format = 'F j, Y') {
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format a date as a relative time (e.g., "5 minutes ago")
 * 
 * @param string $date The date to format
 * @return string The relative time
 */
function format_relative_time($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } else if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } else if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return template_format_date($date);
    }
}

/**
 * Format a datetime string
 * 
 * @param string $datetime Datetime string
 * @param string $format Format string
 * @return string Formatted datetime
 */
function format_datetime($datetime, $format = 'M d, Y') {
    if (empty($datetime)) {
        return '';
    }
    $date = new \DateTime($datetime); // Use global namespace DateTime
    return $date->format($format);
}

/**
 * Truncate a string to a specified length
 * 
 * @param string $string The string to truncate
 * @param int $length The maximum length
 * @param string $append The string to append if truncated
 * @return string The truncated string
 */
function truncate_string($string, $length = 50, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    return substr($string, 0, $length) . $append;
}

/**
 * Format a number with appropriate units (K for thousands, M for millions)
 * 
 * @param int $number The number to format
 * @return string The formatted number
 */
function format_number($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } else if ($number >= 1000) {
        return round($number / 1000, 1) . 'K';
    }
    
    return $number;
}

/**
 * Generate a pagination HTML
 * 
 * @param int $current_page The current page
 * @param int $total_pages The total number of pages
 * @param string $url_pattern The URL pattern for page links
 * @return string The pagination HTML
 */
function generate_pagination($current_page, $total_pages, $url_pattern = '?page=%d') {
    if ($total_pages <= 1) {
        return '';
    }
    
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous page link
    if ($current_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    }
    
    // Page number links
    $start_page = max(1, $current_page - 2);
    $end_page = min($total_pages, $current_page + 2);
    
    // Always show first page
    if ($start_page > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, 1) . '">1</a></li>';
        
        if ($start_page > 2) {
            $pagination .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    // Page links
    for ($i = $start_page; $i <= $end_page; $i++) {
        if ($i == $current_page) {
            $pagination .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    // Always show last page
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $pagination .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $total_pages) . '">' . $total_pages . '</a></li>';
    }
    
    // Next page link
    if ($current_page < $total_pages) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    } else {
        $pagination .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
    }
    
    $pagination .= '</ul></nav>';
    
    return $pagination;
}