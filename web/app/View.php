<?php
/**
 * Vegan Messenger Social Network
 * View Renderer Class
 */

namespace VeganMessenger;

class View {
    /**
     * @var string Base path for view templates
     */
    private $basePath;
    
    /**
     * @var array Default variables available to all views
     */
    private $defaultVars = [];
    
    /**
     * @var string Default layout template
     */
    private $layout = 'layouts/main';
    
    /**
     * Constructor
     * 
     * @param string $basePath Base path for view templates
     */
    public function __construct($basePath = null) {
        $this->basePath = $basePath ?: \dirname(__DIR__) . '/views';
    }
    
    /**
     * Set the base path for view templates
     * 
     * @param string $path The base path
     * @return View This view instance for method chaining
     */
    public function setBasePath($path) {
        $this->basePath = $path;
        return $this;
    }
    
    /**
     * Set default variables available to all views
     * 
     * @param array $vars The default variables
     * @return View This view instance for method chaining
     */
    public function setDefaultVars(array $vars) {
        $this->defaultVars = $vars;
        return $this;
    }
    
    /**
     * Add a default variable
     * 
     * @param string $name The variable name
     * @param mixed $value The variable value
     * @return View This view instance for method chaining
     */
    public function addDefaultVar($name, $value) {
        $this->defaultVars[$name] = $value;
        return $this;
    }
    
    /**
     * Set the default layout template
     * 
     * @param string|null $layout The layout template or null for no layout
     * @return View This view instance for method chaining
     */
    public function setLayout($layout) {
        $this->layout = $layout;
        return $this;
    }
    
    /**
     * Render a view template
     * 
     * @param string $template The template name
     * @param array $vars The variables to pass to the template
     * @param string|null $layout The layout to use (null for no layout, '' for default layout)
     * @return string The rendered template
     * @throws \RuntimeException When the template file doesn't exist
     */
    public function render($template, array $vars = [], $layout = '') {
        $templatePath = $this->getTemplatePath($template);
        
        if (!\file_exists($templatePath)) {
            throw new \RuntimeException("Template file not found: {$templatePath}");
        }
        
        // Merge default variables with template variables
        $vars = \array_merge($this->defaultVars, $vars);
        
        // Start output buffering
        \ob_start();
        
        // Extract variables to the local scope
        \extract($vars);
        
        // Include the template file
        require $templatePath;
        
        // Get the rendered content
        $content = \ob_get_clean();
        
        // Determine the layout to use
        if ($layout === '') {
            $layout = $this->layout;
        }
        
        // If a layout is specified, wrap the content in it
        if ($layout !== null) {
            $layoutPath = $this->getTemplatePath($layout);
            
            if (!\file_exists($layoutPath)) {
                throw new \RuntimeException("Layout file not found: {$layoutPath}");
            }
            
            // Start output buffering again
            \ob_start();
            
            // Make the content available to the layout
            $vars['content'] = $content;
            
            // Extract variables to the local scope
            \extract($vars);
            
            // Include the layout file
            require $layoutPath;
            
            // Get the final rendered content
            $content = \ob_get_clean();
        }
        
        return $content;
    }
    
    /**
     * Output a rendered view template
     * 
     * @param string $template The template name
     * @param array $vars The variables to pass to the template
     * @param string|null $layout The layout to use (null for no layout, '' for default layout)
     * @return void
     */
    public function display($template, array $vars = [], $layout = '') {
        echo $this->render($template, $vars, $layout);
    }
    
    /**
     * Render a partial view template (no layout)
     * 
     * @param string $template The template name
     * @param array $vars The variables to pass to the template
     * @return string The rendered template
     */
    public function renderPartial($template, array $vars = []) {
        return $this->render($template, $vars, null);
    }
    
    /**
     * Get the full path to a template file
     * 
     * @param string $template The template name
     * @return string The full path
     */
    private function getTemplatePath($template) {
        // Add .php extension if not already present
        if (\substr($template, -4) !== '.php') {
            $template .= '.php';
        }
        
        return $this->basePath . '/' . $template;
    }
    
    /**
     * Escape HTML output
     * 
     * @param string $string The string to escape
     * @return string The escaped string
     */
    public function escape($string) {
        return \htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Generate a URL for a route
     * 
     * @param string $route The route path
     * @param array $params The route parameters
     * @return string The generated URL
     */
    public function url($route, array $params = []) {
        $baseUrl = isset($GLOBALS['app']) ? $GLOBALS['app']->getBaseUrl() : '';
        
        // Build query string if parameters are provided
        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . \http_build_query($params);
        }
        
        return \rtrim($baseUrl, '/') . '/' . \ltrim($route, '/') . $queryString;
    }
    
    /**
     * Check if a file exists in the public directory
     * 
     * @param string $path The file path relative to public directory
     * @return bool True if the file exists
     */
    public function fileExists($path) {
        $publicPath = \dirname(\dirname(__DIR__)) . '/web/public/' . \ltrim($path, '/');
        return \file_exists($publicPath);
    }
    
    /**
     * Get a URL for a static asset
     * 
     * @param string $path The asset path relative to public directory
     * @return string The asset URL
     */
    public function asset($path) {
        $baseUrl = isset($GLOBALS['app']) ? $GLOBALS['app']->getBaseUrl() : '';
        return \rtrim($baseUrl, '/') . '/' . \ltrim($path, '/');
    }
    
    /**
     * Format a date using the current locale
     * 
     * @param mixed $date The date to format (timestamp, string or DateTime)
     * @param string $format The date format
     * @return string The formatted date
     */
    public function formatDate($date, $format = 'Y-m-d H:i:s') {
        if (\is_string($date) && !\is_numeric($date)) {
            $date = \strtotime($date);
        }
        
        if ($date instanceof \DateTime) {
            return $date->format($format);
        }
        
        return \date($format, (int)$date);
    }
    
    /**
     * Truncate a string to a specified length
     * 
     * @param string $string The string to truncate
     * @param int $length The maximum length
     * @param string $suffix The suffix to add to truncated strings
     * @return string The truncated string
     */
    public function truncate($string, $length = 100, $suffix = '...') {
        if (\mb_strlen($string, 'UTF-8') <= $length) {
            return $string;
        }
        
        return \mb_substr($string, 0, $length, 'UTF-8') . $suffix;
    }
} 