<?php
/**
 * Vegan Messenger Social Network
 * Router Class
 */

namespace VeganMessenger;

class Router {
    /**
     * @var array Routes registered with the router
     */
    private $routes = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'DELETE' => [],
        'PATCH' => []
    ];
    
    /**
     * @var array Named routes for easy URL generation
     */
    private $namedRoutes = [];
    
    /**
     * @var string Current route prefix for grouped routes
     */
    private $prefix = '';
    
    /**
     * @var array Middleware for grouped routes
     */
    private $groupMiddleware = [];
    
    /**
     * @var callable|array Handler for 404 errors
     */
    private $notFoundHandler;
    
    /**
     * @var callable|array Handler for 500 errors
     */
    private $serverErrorHandler;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Default error handlers
        $this->notFoundHandler = function() {
            \http_response_code(404);
            echo '<h1>404 Not Found</h1>';
            echo '<p>The requested URL was not found on this server.</p>';
        };
        
        $this->serverErrorHandler = function() {
            \http_response_code(500);
            echo '<h1>500 Server Error</h1>';
            echo '<p>An internal server error has occurred.</p>';
        };
    }
    
    /**
     * Register a GET route
     * 
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    public function get($route, $handler, $name = null) {
        return $this->addRoute('GET', $route, $handler, $name);
    }
    
    /**
     * Register a POST route
     * 
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    public function post($route, $handler, $name = null) {
        return $this->addRoute('POST', $route, $handler, $name);
    }
    
    /**
     * Register a PUT route
     * 
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    public function put($route, $handler, $name = null) {
        return $this->addRoute('PUT', $route, $handler, $name);
    }
    
    /**
     * Register a DELETE route
     * 
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    public function delete($route, $handler, $name = null) {
        return $this->addRoute('DELETE', $route, $handler, $name);
    }
    
    /**
     * Register a PATCH route
     * 
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    public function patch($route, $handler, $name = null) {
        return $this->addRoute('PATCH', $route, $handler, $name);
    }
    
    /**
     * Register a route that responds to multiple HTTP methods
     * 
     * @param array $methods Array of HTTP methods
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    public function map(array $methods, $route, $handler, $name = null) {
        foreach ($methods as $method) {
            $this->addRoute(\strtoupper($method), $route, $handler, $name);
        }
        
        return $this;
    }
    
    /**
     * Group routes with a common prefix and/or middleware
     * 
     * @param string $prefix The route prefix
     * @param callable $callback The callback to define routes within the group
     * @param array $middleware Optional middleware for the group
     * @return Router This router instance for method chaining
     */
    public function group($prefix, callable $callback, array $middleware = []) {
        // Save current prefix and middleware
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        
        // Update prefix and middleware for the group
        $this->prefix .= $prefix;
        $this->groupMiddleware = \array_merge($this->groupMiddleware, $middleware);
        
        // Call the callback to define routes in this group
        $callback($this);
        
        // Restore previous prefix and middleware
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
        
        return $this;
    }
    
    /**
     * Set the 404 Not Found handler
     * 
     * @param callable|array $handler The handler
     * @return Router This router instance for method chaining
     */
    public function set404($handler) {
        $this->notFoundHandler = $handler;
        return $this;
    }
    
    /**
     * Set the 500 Server Error handler
     * 
     * @param callable|array $handler The handler
     * @return Router This router instance for method chaining
     */
    public function set500($handler) {
        $this->serverErrorHandler = $handler;
        return $this;
    }
    
    /**
     * Dispatch the current request
     * 
     * @return mixed The result of the route handler
     */
    public function dispatch() {
        // Get current HTTP method and requested URI
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = \parse_url($_SERVER['REQUEST_URI'], \PHP_URL_PATH);
        
        // Handle CORS preflight requests
        if ($method === 'OPTIONS') {
            $this->handleCorsPreflightRequest();
            return;
        }
        
        // Support for method override via _method POST parameter or X-HTTP-Method-Override header
        if ($method === 'POST') {
            if (isset($_POST['_method'])) {
                $method = \strtoupper($_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = \strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }
        
        // Ensure requested method is supported
        if (!isset($this->routes[$method])) {
            $this->handleNotFound();
            return;
        }
        
        // Try to find a matching route
        foreach ($this->routes[$method] as $route) {
            $pattern = $this->buildRoutePattern($route['path']);
            
            if (\preg_match($pattern, $uri, $matches)) {
                // Extract route parameters
                $params = $this->extractRouteParameters($route['path'], $matches);
                
                // Execute any middleware first
                $middlewareResult = $this->executeMiddleware(
                    \array_merge($this->groupMiddleware, $route['middleware'] ?? [])
                );
                
                if ($middlewareResult !== null) {
                    return $middlewareResult;
                }
                
                // Execute the route handler
                return $this->executeHandler($route['handler'], $params);
            }
        }
        
        // No matching route found
        $this->handleNotFound();
    }
    
    /**
     * Generate a URL for a named route
     * 
     * @param string $name The route name
     * @param array $params The route parameters
     * @return string The generated URL
     * @throws \Exception If the named route doesn't exist
     */
    public function generateUrl($name, array $params = []) {
        if (!isset($this->namedRoutes[$name])) {
            throw new \Exception("Named route '{$name}' does not exist");
        }
        
        $route = $this->namedRoutes[$name]['path'];
        
        // Replace route parameters
        foreach ($params as $key => $value) {
            $route = \str_replace("{{$key}}", $value, $route);
            $route = \str_replace(":{$key}", $value, $route);
        }
        
        // Remove any remaining placeholders
        $route = \preg_replace('/{[^}]+}/', '', $route);
        $route = \preg_replace('/:([^\/]+)/', '', $route);
        
        // Get base URL from config
        $baseUrl = isset($GLOBALS['app']) ? $GLOBALS['app']->getBaseUrl() : '';
        
        return \rtrim($baseUrl, '/') . $route;
    }
    
    /**
     * Add a route to the router
     * 
     * @param string $method The HTTP method
     * @param string $route The route pattern
     * @param callable|array $handler The route handler
     * @param string|null $name Optional route name
     * @return Router This router instance for method chaining
     */
    private function addRoute($method, $route, $handler, $name = null) {
        // Apply current prefix
        $prefixedRoute = $this->prefix . $route;
        
        // Add route to the routes array
        $this->routes[$method][] = [
            'path' => $prefixedRoute,
            'handler' => $handler,
            'middleware' => $this->groupMiddleware
        ];
        
        // If the route has a name, store it in the named routes array
        if ($name !== null) {
            $this->namedRoutes[$name] = [
                'method' => $method,
                'path' => $prefixedRoute
            ];
        }
        
        return $this;
    }
    
    /**
     * Build a regex pattern for a route
     * 
     * @param string $route The route pattern
     * @return string The regex pattern
     */
    private function buildRoutePattern($route) {
        // Escape forward slashes
        $pattern = \str_replace('/', '\/', $route);
        
        // Replace named parameters {param} with capturing groups
        $pattern = \preg_replace('/\{([^\/]+)\}/', '(?P<$1>[^\/]+)', $pattern);
        
        // Replace named parameters :param with capturing groups
        $pattern = \preg_replace('/:([^\/]+)/', '(?P<$1>[^\/]+)', $pattern);
        
        // Add start and end delimiters
        return '/^' . $pattern . '$/';
    }
    
    /**
     * Extract parameters from a matched route
     * 
     * @param string $route The route pattern
     * @param array $matches The preg_match result
     * @return array The extracted parameters
     */
    private function extractRouteParameters($route, array $matches) {
        $params = [];
        
        // Extract parameter names from the route pattern
        \preg_match_all('/\{([^\/]+)\}|:([^\/]+)/', $route, $parameterNames);
        
        // Merge both types of parameter names
        $parameterNames = \array_filter(\array_merge($parameterNames[1], $parameterNames[2]));
        
        // Add named parameters from regex matches
        foreach ($parameterNames as $name) {
            if (isset($matches[$name])) {
                $params[$name] = $matches[$name];
            }
        }
        
        return $params;
    }
    
    /**
     * Execute route middleware
     * 
     * @param array $middleware The middleware to execute
     * @return mixed|null The middleware result or null to continue execution
     */
    private function executeMiddleware(array $middleware) {
        foreach ($middleware as $handler) {
            $result = $this->executeHandler($handler);
            
            if ($result !== null) {
                return $result;
            }
        }
        
        return null;
    }
    
    /**
     * Execute a route handler
     * 
     * @param callable|array $handler The handler
     * @param array $params The route parameters
     * @return mixed The handler result
     */
    private function executeHandler($handler, array $params = []) {
        // If handler is a callable, just call it with the parameters
        if (\is_callable($handler)) {
            return \call_user_func_array($handler, $params);
        }
        
        // If handler is an array [ControllerClass, method], instantiate controller and call method
        if (\is_array($handler) && \count($handler) === 2) {
            list($controllerClass, $method) = $handler;
            
            // Instantiate controller
            $controller = new $controllerClass();
            
            // Call method with parameters
            return \call_user_func_array([$controller, $method], $params);
        }
        
        // Unknown handler format
        $this->handleServerError();
    }
    
    /**
     * Handle a 404 Not Found error
     * 
     * @return mixed The result of the not found handler
     */
    private function handleNotFound() {
        \http_response_code(404);
        
        if (\is_callable($this->notFoundHandler)) {
            return \call_user_func($this->notFoundHandler);
        } elseif (\is_array($this->notFoundHandler) && \count($this->notFoundHandler) === 2) {
            list($controllerClass, $method) = $this->notFoundHandler;
            $controller = new $controllerClass();
            return \call_user_func([$controller, $method]);
        }
    }
    
    /**
     * Handle a 500 Server Error
     * 
     * @return mixed The result of the server error handler
     */
    private function handleServerError() {
        \http_response_code(500);
        
        if (\is_callable($this->serverErrorHandler)) {
            return \call_user_func($this->serverErrorHandler);
        } elseif (\is_array($this->serverErrorHandler) && \count($this->serverErrorHandler) === 2) {
            list($controllerClass, $method) = $this->serverErrorHandler;
            $controller = new $controllerClass();
            return \call_user_func([$controller, $method]);
        }
    }
    
    /**
     * Handle CORS preflight requests
     * 
     * @return void
     */
    private function handleCorsPreflightRequest() {
        $config = $GLOBALS['config']['security']['cors'] ?? [];
        
        // Set CORS headers
        \header('Access-Control-Allow-Origin: ' . \implode(', ', $config['allowed_origins'] ?? ['*']));
        \header('Access-Control-Allow-Methods: ' . \implode(', ', $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']));
        \header('Access-Control-Allow-Headers: ' . \implode(', ', $config['allowed_headers'] ?? ['Content-Type', 'Authorization']));
        \header('Access-Control-Max-Age: 86400'); // 24 hours
        
        // End request
        \http_response_code(204);
        exit;
    }
} 