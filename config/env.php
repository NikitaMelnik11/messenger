<?php
/**
 * Vegan Messenger Social Network
 * Environment Configuration Example
 * 
 * Copy this file to env.php and modify the values according to your environment.
 */

return [
    // Application settings
    'app' => [
        'name' => 'Vegan Messenger',
        'url' => 'http://localhost:8080',
        'environment' => 'development', // development, testing, production
        'debug' => true,
        'timezone' => 'UTC',
        'locale' => 'en',
        'secret_key' => 'change_me_to_a_random_string_in_production',
    ],
    
    // Database settings
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'vegan_messenger',
        'username' => 'vegan_user',
        'password' => 'vegan_password',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ],
    ],
    
    // Authentication service settings
    'auth_service' => [
        'url' => 'http://localhost:8081',
        'timeout' => 5,
    ],
    
    // Messaging service settings
    'messaging_service' => [
        'url' => 'http://localhost:8082',
        'timeout' => 5,
    ],
    
    // Mail settings
    'mail' => [
        'driver' => 'smtp',
        'host' => 'smtp.mailtrap.io',
        'port' => 2525,
        'username' => 'your_mailtrap_username',
        'password' => 'your_mailtrap_password',
        'encryption' => 'tls',
        'from' => [
            'address' => 'noreply@veganmessenger.com',
            'name' => 'Vegan Messenger'
        ],
    ],
    
    // Redis settings
    'redis' => [
        'host' => 'localhost',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ],
    
    // File storage settings
    'storage' => [
        'driver' => 'local',
        'uploads_dir' => __DIR__ . '/../web/public/uploads',
        'uploads_url' => '/uploads',
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'pdf', 'doc', 'docx'],
        'max_file_size' => 10 * 1024 * 1024, // 10 MB
    ],
    
    // Session settings
    'session' => [
        'name' => 'vegan_messenger_session',
        'lifetime' => 7200, // 2 hours in seconds
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
    ],
    
    // Cookie settings
    'cookie' => [
        'prefix' => 'vegan_',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'http_only' => true,
        'same_site' => 'Lax',
    ],
    
    // CORS settings
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['*'],
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => [],
        'max_age' => 0,
        'supports_credentials' => false,
    ],
    
    // Social login settings
    'social' => [
        'facebook' => [
            'client_id' => 'your_facebook_client_id',
            'client_secret' => 'your_facebook_client_secret',
            'redirect' => 'http://localhost:8080/auth/facebook/callback',
        ],
        'google' => [
            'client_id' => 'your_google_client_id',
            'client_secret' => 'your_google_client_secret',
            'redirect' => 'http://localhost:8080/auth/google/callback',
        ],
    ],
    
    // Vegan API integrations
    'vegan_apis' => [
        'recipe_api' => [
            'url' => 'https://api.example.com/vegan-recipes',
            'api_key' => 'your_recipe_api_key',
        ],
        'restaurant_api' => [
            'url' => 'https://api.example.com/vegan-restaurants',
            'api_key' => 'your_restaurant_api_key',
        ],
    ],
]; 