<?php
/**
 * Vegan Messenger Social Network
 * Helper functions
 */

/**
 * Get the configuration value
 * 
 * @param string $key The configuration key
 * @param mixed $default The default value if not found
 * @return mixed The configuration value
 */
function config($key, $default = null) {
    $keys = explode('.', $key);
    $value = $GLOBALS['config'];
    
    foreach ($keys as $segment) {
        if (!isset($value[$segment])) {
            return $default;
        }
        
        $value = $value[$segment];
    }
    
    return $value;
}

/**
 * Generate a URL for a route
 * 
 * @param string $path The route path
 * @param array $params The query parameters
 * @return string The URL
 */
function url($path = '', array $params = []) {
    $baseUrl = config('app.url', '');
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    return $url;
}

/**
 * Get a URL for a static asset
 * 
 * @param string $path The asset path
 * @return string The asset URL
 */
function asset($path) {
    return url(ltrim($path, '/'));
}

/**
 * Escape HTML output
 * 
 * @param string $string The string to escape
 * @return string The escaped string
 */
function e($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Format a date using the current locale
 * 
 * @param mixed $date The date to format (timestamp, string or DateTime)
 * @param string $format The date format
 * @return string The formatted date
 */
function format_date($date, $format = 'Y-m-d H:i:s') {
    if (is_string($date) && !is_numeric($date)) {
        $date = strtotime($date);
    }
    
    if ($date instanceof DateTime) {
        return $date->format($format);
    }
    
    return date($format, (int)$date);
}

/**
 * Format a date relative to now (e.g. "2 hours ago")
 * 
 * @param mixed $date The date to format (timestamp, string or DateTime)
 * @return string The relative date
 */
function relative_time($date) {
    if (is_string($date) && !is_numeric($date)) {
        $date = strtotime($date);
    }
    
    if ($date instanceof DateTime) {
        $date = $date->getTimestamp();
    }
    
    $diff = time() - (int)$date;
    
    if ($diff < 60) {
        return 'just now';
    }
    
    $intervals = [
        1 => [60, 'minute'],
        2 => [3600, 'hour'],
        3 => [86400, 'day'],
        4 => [604800, 'week'],
        5 => [2592000, 'month'],
        6 => [31536000, 'year'],
    ];
    
    foreach ($intervals as $i => $interval) {
        if ($diff < $interval[0] * 2) {
            return '1 ' . $interval[1] . ' ago';
        }
        
        if ($diff < $interval[0] * 60 || $i == 6) {
            return floor($diff / $interval[0]) . ' ' . $interval[1] . 's ago';
        }
    }
}

/**
 * Convert bytes to a human readable format
 * 
 * @param int $bytes The bytes to format
 * @param int $precision The number of decimal places
 * @return string The formatted size
 */
function format_size($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Truncate a string to a specified length
 * 
 * @param string $string The string to truncate
 * @param int $length The maximum length
 * @param string $suffix The suffix to add to truncated strings
 * @return string The truncated string
 */
function str_truncate($string, $length = 100, $suffix = '...') {
    if (mb_strlen($string, 'UTF-8') <= $length) {
        return $string;
    }
    
    return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
}

/**
 * Generate a random string
 * 
 * @param int $length The length of the string
 * @return string The random string
 */
function str_random($length = 32) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    
    return $randomString;
}

/**
 * Get the current user
 * 
 * @return array|null The current user or null if not logged in
 */
function current_user() {
    return isset($GLOBALS['app']) ? $GLOBALS['app']->getAuth()->getUser() : null;
}

/**
 * Check if the current user is logged in
 * 
 * @return bool True if logged in
 */
function is_logged_in() {
    return isset($GLOBALS['app']) && $GLOBALS['app']->getAuth()->isLoggedIn();
}

/**
 * Check if the current user is an admin
 * 
 * @return bool True if admin
 */
function is_admin() {
    return isset($GLOBALS['app']) && $GLOBALS['app']->getAuth()->isAdmin();
}

/**
 * Get the CSRF token
 * 
 * @return string The CSRF token
 */
function csrf_token() {
    return $_SESSION['csrf_token'] ?? '';
}

/**
 * Generate a CSRF token field
 * 
 * @return string The CSRF token field
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/**
 * Generate a method field for spoofing HTTP methods
 * 
 * @param string $method The HTTP method to spoof
 * @return string The method field
 */
function method_field($method) {
    return '<input type="hidden" name="_method" value="' . $method . '">';
}

/**
 * Get the flash message
 * 
 * @return array|null The flash message or null if none
 */
function flash() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    
    return null;
}

/**
 * Set a flash message
 * 
 * @param string $type The message type (success, error, info, warning)
 * @param string $message The message
 * @return void
 */
function set_flash($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Check if a string starts with another string
 * 
 * @param string $haystack The string to check
 * @param string $needle The string to search for
 * @return bool True if the string starts with the needle
 */
function str_starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Check if a string ends with another string
 * 
 * @param string $haystack The string to check
 * @param string $needle The string to search for
 * @return bool True if the string ends with the needle
 */
function str_ends_with($haystack, $needle) {
    return substr($haystack, -strlen($needle)) === $needle;
}

/**
 * Check if a string contains another string
 * 
 * @param string $haystack The string to check
 * @param string $needle The string to search for
 * @return bool True if the string contains the needle
 */
function str_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

/**
 * Convert a string to snake_case
 * 
 * @param string $string The string to convert
 * @return string The converted string
 */
function snake_case($string) {
    $string = preg_replace('/\s+/u', '_', $string);
    $string = preg_replace('~[^\pL\d_]+~u', '_', $string);
    $string = preg_replace('~[^-\w]+~', '_', $string);
    $string = trim($string, '_');
    $string = preg_replace('~-+~', '_', $string);
    $string = strtolower($string);
    
    return $string;
}

/**
 * Convert a string to camelCase
 * 
 * @param string $string The string to convert
 * @return string The converted string
 */
function camel_case($string) {
    $string = snake_case($string);
    $string = ucwords(str_replace('_', ' ', $string));
    $string = str_replace(' ', '', $string);
    $string = lcfirst($string);
    
    return $string;
}

/**
 * Convert a string to PascalCase
 * 
 * @param string $string The string to convert
 * @return string The converted string
 */
function pascal_case($string) {
    $string = snake_case($string);
    $string = ucwords(str_replace('_', ' ', $string));
    $string = str_replace(' ', '', $string);
    
    return $string;
}

/**
 * Check if request is AJAX
 * 
 * @return bool True if AJAX request
 */
function is_ajax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get the current URL
 * 
 * @param bool $withQuery Whether to include the query string
 * @return string The current URL
 */
function current_url($withQuery = true) {
    $url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $url .= '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    if (!$withQuery) {
        $url = strtok($url, '?');
    }
    
    return $url;
}

/**
 * Redirect to another URL
 * 
 * @param string $url The URL to redirect to
 * @param int $status The HTTP status code
 * @return void
 */
function redirect($url, $status = 302) {
    header('Location: ' . $url, true, $status);
    exit;
}

/**
 * Get a value from the query string
 * 
 * @param string $key The key to get
 * @param mixed $default The default value if not found
 * @return mixed The value
 */
function query($key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Get a value from the request body
 * 
 * @param string $key The key to get
 * @param mixed $default The default value if not found
 * @return mixed The value
 */
function input($key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Get all input values
 * 
 * @return array The input values
 */
function all_input() {
    return $_POST;
}

/**
 * Check if the request has a specific input
 * 
 * @param string $key The key to check
 * @return bool True if the input exists
 */
function has_input($key) {
    return isset($_POST[$key]);
}

/**
 * Get a value from the session
 * 
 * @param string $key The key to get
 * @param mixed $default The default value if not found
 * @return mixed The value
 */
function session($key, $default = null) {
    return $_SESSION[$key] ?? $default;
}

/**
 * Set a value in the session
 * 
 * @param string $key The key to set
 * @param mixed $value The value to set
 * @return void
 */
function session_set($key, $value) {
    $_SESSION[$key] = $value;
}

/**
 * Remove a value from the session
 * 
 * @param string $key The key to remove
 * @return void
 */
function session_remove($key) {
    unset($_SESSION[$key]);
}

/**
 * Check if the session has a specific key
 * 
 * @param string $key The key to check
 * @return bool True if the key exists
 */
function session_has($key) {
    return isset($_SESSION[$key]);
}

/**
 * Get the current page number from the query string
 * 
 * @param int $default The default page number
 * @return int The page number
 */
function page($default = 1) {
    $page = (int)($_GET['page'] ?? $default);
    return $page < 1 ? 1 : $page;
}

/**
 * Calculate the offset for pagination
 * 
 * @param int $page The page number
 * @param int $perPage The number of items per page
 * @return int The offset
 */
function pagination_offset($page, $perPage) {
    return ($page - 1) * $perPage;
}

/**
 * Generate pagination links
 * 
 * @param int $totalItems The total number of items
 * @param int $perPage The number of items per page
 * @param int $currentPage The current page number
 * @param string $url The base URL for pagination links
 * @param int $adjacents The number of adjacent pages to show
 * @return string The pagination HTML
 */
function pagination($totalItems, $perPage, $currentPage, $url = '?page=', $adjacents = 2) {
    $totalPages = ceil($totalItems / $perPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous link
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . ($currentPage - 1) . '">&laquo; Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">&laquo; Previous</a></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - $adjacents);
    $end = min($totalPages, $currentPage + $adjacents);
    
    // Show first page
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . '1">1</a></li>';
        
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
    }
    
    // Page links
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $url . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Show last page
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><a class="page-link" href="#">...</a></li>';
        }
        
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next link
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $url . ($currentPage + 1) . '">Next &raquo;</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next &raquo;</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
} 