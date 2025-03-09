<?php
/**
 * Vegan Messenger Social Network
 * Bootstrap file for the application
 */

// Define application root path
define('APP_ROOT', dirname(__FILE__));

/**
 * Autoloader function
 * 
 * @param string $className The class name to load
 * @return void
 */
function autoload($className) {
    // Convert namespace separator to directory separator
    $className = str_replace('\\', '/', $className);
    
    // Remove "VeganMessenger\" prefix if it exists
    if (strpos($className, 'VeganMessenger/') === 0) {
        $className = substr($className, 15);
    }
    
    // Build path to class file
    $classFile = APP_ROOT . '/' . $className . '.php';
    
    // Check if file exists and include it
    if (file_exists($classFile)) {
        require_once $classFile;
    }
}

// Register autoloader
spl_autoload_register('autoload');

// Load helper functions
require_once APP_ROOT . '/helpers.php';

// Start session
session_start();

// Set up error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
        return;
    }

    $message = "Error: [$errno] $errstr in $errfile on line $errline";
    
    switch ($errno) {
        case E_USER_ERROR:
            error_log("Fatal Error: $message");
            echo "Fatal Error: Please contact the administrator.";
            exit(1);
            break;

        case E_USER_WARNING:
            error_log("Warning: $message");
            break;

        case E_USER_NOTICE:
            error_log("Notice: $message");
            break;

        default:
            error_log("Unknown error type: $message");
            break;
    }

    // Don't execute PHP internal error handler
    return true;
});

// Set up exception handling
set_exception_handler(function($exception) {
    $message = "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
    error_log($message);
    
    // Get trace as string
    $trace = $exception->getTraceAsString();
    error_log("Stack trace: $trace");
    
    // Show error page in production, detailed error in development
    if (isset($GLOBALS['config']) && !$GLOBALS['config']['app']['debug']) {
        http_response_code(500);
        include APP_ROOT . '/../views/errors/500.php';
    } else {
        http_response_code(500);
        echo "<h1>An Exception Occurred</h1>";
        echo "<p>" . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<h2>Stack Trace</h2>";
        echo "<pre>" . htmlspecialchars($trace) . "</pre>";
    }
    
    exit;
});

// Load database connection
require_once APP_ROOT . '/Database.php';

// Initialize core components
require_once APP_ROOT . '/App.php';
require_once APP_ROOT . '/Router.php';
require_once APP_ROOT . '/Controller.php';
require_once APP_ROOT . '/Model.php';
require_once APP_ROOT . '/View.php';
require_once APP_ROOT . '/Auth.php';

// Load environment specific configuration
if (file_exists(APP_ROOT . '/../../config/env.local.php')) {
    $localConfig = require_once APP_ROOT . '/../../config/env.local.php';
    if (isset($GLOBALS['config']) && is_array($GLOBALS['config'])) {
        $GLOBALS['config'] = array_merge($GLOBALS['config'], $localConfig);
    }
}

// Initialize date/time handling
date_default_timezone_set(isset($GLOBALS['config']['app']['timezone']) ? $GLOBALS['config']['app']['timezone'] : 'UTC');

// Set default headers
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; img-src \'self\' data: https:; font-src \'self\' https://cdn.jsdelivr.net; connect-src \'self\'; frame-src \'none\'; object-src \'none\';');

// Sanitize input
$_GET = filter_input_array(INPUT_GET, FILTER_SANITIZE_SPECIAL_CHARS);
$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_SPECIAL_CHARS);
$_COOKIE = filter_input_array(INPUT_COOKIE, FILTER_SANITIZE_SPECIAL_CHARS);

// Set up CSRF protection
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
} 