<?php
/**
 * Vegan Messenger Social Network
 * Base Controller Class
 */

namespace VeganMessenger;

abstract class Controller {
    /**
     * @var Database The database instance
     */
    protected $db;
    
    /**
     * @var View The view renderer instance
     */
    protected $view;
    
    /**
     * @var Auth The authentication instance
     */
    protected $auth;
    
    /**
     * @var array Request data
     */
    protected $request;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Get instances from the app
        $this->db = $GLOBALS['app']->getDb();
        $this->view = $GLOBALS['app']->getView();
        $this->auth = $GLOBALS['app']->getAuth();
        
        // Set up request data
        $this->setupRequest();
        
        // Set common view variables
        $this->setupView();
    }
    
    /**
     * Set up request data
     * 
     * @return void
     */
    protected function setupRequest() {
        $this->request = [
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'cookie' => $_COOKIE,
            'server' => $_SERVER,
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => \parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
            'query' => $_SERVER['QUERY_STRING'] ?? '',
            'isAjax' => !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && \strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest',
        ];
    }
    
    /**
     * Set up common view variables
     * 
     * @return void
     */
    protected function setupView() {
        $this->view->addDefaultVar('auth', $this->auth);
        $this->view->addDefaultVar('user', $this->auth->getUser());
        $this->view->addDefaultVar('isLoggedIn', $this->auth->isLoggedIn());
        $this->view->addDefaultVar('request', $this->request);
        $this->view->addDefaultVar('now', \time());
        $this->view->addDefaultVar('config', $GLOBALS['config']);
        $this->view->addDefaultVar('app', $GLOBALS['app']);
        $this->view->addDefaultVar('csrfToken', $_SESSION['csrf_token'] ?? '');
    }
    
    /**
     * Render a view and return the output
     * 
     * @param string $template The template name
     * @param array $data The data to pass to the view
     * @param string|null $layout The layout to use (null for no layout, '' for default layout)
     * @return string The rendered view
     */
    protected function render($template, array $data = [], $layout = '') {
        return $this->view->render($template, $data, $layout);
    }
    
    /**
     * Render a view and output it
     * 
     * @param string $template The template name
     * @param array $data The data to pass to the view
     * @param string|null $layout The layout to use (null for no layout, '' for default layout)
     * @return void
     */
    protected function display($template, array $data = [], $layout = '') {
        $this->view->display($template, $data, $layout);
    }
    
    /**
     * Render a JSON response
     * 
     * @param mixed $data The data to encode as JSON
     * @param int $statusCode The HTTP status code
     * @return void
     */
    protected function json($data, $statusCode = 200) {
        \http_response_code($statusCode);
        \header('Content-Type: application/json');
        echo \json_encode($data);
        exit;
    }
    
    /**
     * Redirect to another URL
     * 
     * @param string $url The URL to redirect to
     * @param int $statusCode The HTTP status code
     * @return void
     */
    protected function redirect($url, $statusCode = 302) {
        $GLOBALS['app']->redirect($url, $statusCode);
    }
    
    /**
     * Get a POST value
     * 
     * @param string $key The key to get
     * @param mixed $default The default value if the key doesn't exist
     * @return mixed The POST value or default
     */
    protected function post($key = null, $default = null) {
        if ($key === null) {
            return $this->request['post'];
        }
        
        return $this->request['post'][$key] ?? $default;
    }
    
    /**
     * Get a GET value
     * 
     * @param string $key The key to get
     * @param mixed $default The default value if the key doesn't exist
     * @return mixed The GET value or default
     */
    protected function get($key = null, $default = null) {
        if ($key === null) {
            return $this->request['get'];
        }
        
        return $this->request['get'][$key] ?? $default;
    }
    
    /**
     * Check if this is an AJAX request
     * 
     * @return bool True if this is an AJAX request
     */
    protected function isAjax() {
        return $this->request['isAjax'];
    }
    
    /**
     * Check if this is a POST request
     * 
     * @return bool True if this is a POST request
     */
    protected function isPost() {
        return $this->request['method'] === 'POST';
    }
    
    /**
     * Check if this is a GET request
     * 
     * @return bool True if this is a GET request
     */
    protected function isGet() {
        return $this->request['method'] === 'GET';
    }
    
    /**
     * Check if the current user is logged in
     * 
     * @param bool $redirect Whether to redirect to the login page if not logged in
     * @return bool True if logged in
     */
    protected function requireLogin($redirect = true) {
        if (!$this->auth->isLoggedIn()) {
            if ($redirect) {
                $this->setFlash('error', 'You must be logged in to access this page.');
                $this->redirect($GLOBALS['app']->getBaseUrl() . '/login?redirect=' . \urlencode($this->request['uri']));
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if the current user is an admin
     * 
     * @param bool $redirect Whether to redirect if not an admin
     * @return bool True if an admin
     */
    protected function requireAdmin($redirect = true) {
        if (!$this->auth->isAdmin()) {
            if ($redirect) {
                $this->setFlash('error', 'You do not have permission to access this page.');
                $this->redirect($GLOBALS['app']->getBaseUrl());
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if the current user has a specific role
     * 
     * @param string $role The role to check
     * @param bool $redirect Whether to redirect if the user doesn't have the role
     * @return bool True if the user has the role
     */
    protected function requireRole($role, $redirect = true) {
        if (!$this->auth->hasRole($role)) {
            if ($redirect) {
                $this->setFlash('error', 'You do not have permission to access this page.');
                $this->redirect($GLOBALS['app']->getBaseUrl());
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Set a flash message
     * 
     * @param string $type The message type (success, error, info, warning)
     * @param string $message The message
     * @return void
     */
    protected function setFlash($type, $message) {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }
    
    /**
     * Get and clear flash message
     * 
     * @return array|null The flash message or null if none
     */
    protected function getFlash() {
        if (isset($_SESSION['flash_message'])) {
            $flash = $_SESSION['flash_message'];
            unset($_SESSION['flash_message']);
            return $flash;
        }
        
        return null;
    }
    
    /**
     * Validate form input
     * 
     * @param array $rules The validation rules
     * @param array $data The data to validate (defaults to POST data)
     * @return array Array of errors or empty array if validation passed
     */
    protected function validate(array $rules, array $data = null) {
        $data = $data ?? $this->request['post'];
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule => $ruleValue) {
                $error = $this->applyValidationRule($field, $value, $rule, $ruleValue, $data);
                
                if ($error) {
                    $errors[$field] = $error;
                    break; // Stop validating this field after first error
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Apply a validation rule to a field
     * 
     * @param string $field The field name
     * @param mixed $value The field value
     * @param string $rule The rule name
     * @param mixed $ruleValue The rule value
     * @param array $data All form data
     * @return string|null Error message or null if validation passed
     */
    protected function applyValidationRule($field, $value, $rule, $ruleValue, array $data) {
        $fieldLabel = \str_replace('_', ' ', \ucfirst($field));
        
        switch ($rule) {
            case 'required':
                if ($ruleValue && ($value === null || $value === '')) {
                    return "$fieldLabel is required.";
                }
                break;
                
            case 'min_length':
                if (\strlen($value) < $ruleValue) {
                    return "$fieldLabel must be at least $ruleValue characters.";
                }
                break;
                
            case 'max_length':
                if (\strlen($value) > $ruleValue) {
                    return "$fieldLabel cannot exceed $ruleValue characters.";
                }
                break;
                
            case 'email':
                if ($ruleValue && $value && !\filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "$fieldLabel must be a valid email address.";
                }
                break;
                
            case 'numeric':
                if ($ruleValue && $value && !\is_numeric($value)) {
                    return "$fieldLabel must be a number.";
                }
                break;
                
            case 'min_value':
                if ($value < $ruleValue) {
                    return "$fieldLabel must be at least $ruleValue.";
                }
                break;
                
            case 'max_value':
                if ($value > $ruleValue) {
                    return "$fieldLabel cannot exceed $ruleValue.";
                }
                break;
                
            case 'matches':
                if ($value !== $data[$ruleValue]) {
                    $matchField = \str_replace('_', ' ', \ucfirst($ruleValue));
                    return "$fieldLabel must match $matchField.";
                }
                break;
                
            case 'regex':
                if ($value && !\preg_match($ruleValue, $value)) {
                    return "$fieldLabel has an invalid format.";
                }
                break;
                
            case 'in_array':
                if (!\in_array($value, $ruleValue)) {
                    $options = \implode(', ', $ruleValue);
                    return "$fieldLabel must be one of: $options.";
                }
                break;
        }
        
        return null;
    }
} 