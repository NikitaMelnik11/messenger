<?php
/**
 * Vegan Messenger Social Network
 * Main Application Class
 */

namespace VeganMessenger;

class App {
    /**
     * @var array The application configuration
     */
    private $config;
    
    /**
     * @var Router The router instance
     */
    private $router;
    
    /**
     * @var Database The database instance
     */
    private $db;
    
    /**
     * @var Auth The authentication instance
     */
    private $auth;
    
    /**
     * @var View The view renderer instance
     */
    private $view;
    
    /**
     * @var array Instances of services
     */
    private $services = [];
    
    /**
     * Constructor
     * 
     * @param array $config Application configuration
     */
    public function __construct(array $config) {
        $this->config = $config;
        $GLOBALS['config'] = $config;
        
        // Initialize router
        $this->router = new Router();
        
        // Initialize database
        $this->db = new Database(
            $config['database']['driver'],
            $config['database']['host'],
            $config['database']['port'],
            $config['database']['database'],
            $config['database']['username'],
            $config['database']['password']
        );
        
        // Initialize view renderer
        $this->view = new View();
        
        // Initialize authentication
        $this->auth = new Auth($this->db);
        
        // Set the app instance in global scope for use in other classes
        $GLOBALS['app'] = $this;
    }
    
    /**
     * Run the application
     * 
     * @return void
     */
    public function run() {
        try {
            // Handle the current request
            $this->router->dispatch();
        } catch (\Exception $e) {
            // Log the error
            \error_log("Application error: " . $e->getMessage());
            
            // Show error page based on environment
            if ($this->config['app']['debug']) {
                $this->showDebugError($e);
            } else {
                $this->showProductionError($e);
            }
        }
    }
    
    /**
     * Display detailed error information in debug mode
     * 
     * @param \Exception $e The exception to display
     * @return void
     */
    private function showDebugError(\Exception $e) {
        \http_response_code(500);
        
        echo '<h1>Application Error</h1>';
        echo '<p><strong>Message:</strong> ' . \htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>File:</strong> ' . \htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>Line:</strong> ' . $e->getLine() . '</p>';
        echo '<h2>Stack Trace</h2>';
        echo '<pre>' . \htmlspecialchars($e->getTraceAsString()) . '</pre>';
    }
    
    /**
     * Display user-friendly error page in production mode
     * 
     * @param \Exception $e The exception that occurred
     * @return void
     */
    private function showProductionError(\Exception $e) {
        \http_response_code(500);
        
        // Try to load the error view
        try {
            $this->view->render('errors/500', [
                'errorMessage' => 'An unexpected error has occurred. Please try again later.'
            ]);
        } catch (\Exception $viewError) {
            // If the view cannot be rendered, show a simple error message
            echo '<h1>Server Error</h1>';
            echo '<p>We apologize for the inconvenience. Please try again later.</p>';
        }
    }
    
    /**
     * Get the router instance
     * 
     * @return Router
     */
    public function getRouter() {
        return $this->router;
    }
    
    /**
     * Get the database instance
     * 
     * @return Database
     */
    public function getDb() {
        return $this->db;
    }
    
    /**
     * Get the auth instance
     * 
     * @return Auth
     */
    public function getAuth() {
        return $this->auth;
    }
    
    /**
     * Get the view renderer instance
     * 
     * @return View
     */
    public function getView() {
        return $this->view;
    }
    
    /**
     * Get the application configuration
     * 
     * @param string|null $key Optional configuration key to retrieve
     * @return mixed The configuration value or entire config array
     */
    public function getConfig($key = null) {
        if ($key === null) {
            return $this->config;
        }
        
        // Handle nested keys with dot notation (e.g., 'database.host')
        if (\strpos($key, '.') !== false) {
            $keys = \explode('.', $key);
            $value = $this->config;
            
            foreach ($keys as $part) {
                if (!isset($value[$part])) {
                    return null;
                }
                
                $value = $value[$part];
            }
            
            return $value;
        }
        
        return isset($this->config[$key]) ? $this->config[$key] : null;
    }
    
    /**
     * Register a service with the application
     * 
     * @param string $name The service name
     * @param object $instance The service instance
     * @return void
     */
    public function registerService($name, $instance) {
        $this->services[$name] = $instance;
    }
    
    /**
     * Get a registered service
     * 
     * @param string $name The service name
     * @return object|null The service instance or null if not found
     */
    public function getService($name) {
        return isset($this->services[$name]) ? $this->services[$name] : null;
    }
    
    /**
     * Check if a service is registered
     * 
     * @param string $name The service name
     * @return bool True if the service exists, false otherwise
     */
    public function hasService($name) {
        return isset($this->services[$name]);
    }
    
    /**
     * Redirect to another URL
     * 
     * @param string $url The URL to redirect to
     * @param int $statusCode The HTTP status code (default: 302)
     * @return void
     */
    public function redirect($url, $statusCode = 302) {
        \http_response_code($statusCode);
        \header('Location: ' . $url);
        exit;
    }
    
    /**
     * Get the base URL of the application
     * 
     * @return string The base URL
     */
    public function getBaseUrl() {
        return $this->config['app']['url'];
    }
    
    /**
     * Get the current environment (development, testing, production)
     * 
     * @return string The current environment
     */
    public function getEnvironment() {
        return $this->config['app']['debug'] ? 'development' : 'production';
    }
} 