<?php
/**
 * Vegan Messenger Social Network
 * Authentication Class
 */

namespace VeganMessenger;

class Auth {
    /**
     * @var Database The database instance
     */
    private $db;
    
    /**
     * @var array Current authenticated user data
     */
    private $user = null;
    
    /**
     * @var string Session key for user ID
     */
    private const SESSION_USER_ID = 'user_id';
    
    /**
     * @var string Cookie name for remember me token
     */
    private const REMEMBER_COOKIE = 'remember_token';
    
    /**
     * @var array Authentication configuration
     */
    private $config;
    
    /**
     * Constructor
     * 
     * @param Database $db The database instance
     */
    public function __construct(Database $db) {
        $this->db = $db;
        $this->config = $GLOBALS['config']['auth'] ?? [];
        
        // Check for existing session
        $this->checkSession();
        
        // Check for remember me cookie if not logged in via session
        if (!$this->isLoggedIn() && isset($_COOKIE[self::REMEMBER_COOKIE])) {
            $this->loginWithRememberToken($_COOKIE[self::REMEMBER_COOKIE]);
        }
    }
    
    /**
     * Check if a user is currently logged in via session
     * 
     * @return void
     */
    private function checkSession() {
        if (isset($_SESSION[self::SESSION_USER_ID])) {
            $userId = $_SESSION[self::SESSION_USER_ID];
            $this->user = $this->getUserById($userId);
            
            // If user not found or inactive, log them out
            if (!$this->user || !$this->user['is_active']) {
                $this->logout();
            }
        }
    }
    
    /**
     * Attempt to login a user with credentials
     * 
     * @param string $usernameOrEmail The username or email
     * @param string $password The password
     * @param bool $remember Whether to set a remember me cookie
     * @return bool True if login was successful
     */
    public function login($usernameOrEmail, $password, $remember = false) {
        $isEmail = \filter_var($usernameOrEmail, FILTER_VALIDATE_EMAIL);
        
        // Get user by email or username
        $query = $isEmail
            ? "SELECT * FROM users WHERE email = :identifier AND is_active = 1 LIMIT 1"
            : "SELECT * FROM users WHERE username = :identifier AND is_active = 1 LIMIT 1";
        
        $user = $this->db->queryOne($query, ['identifier' => $usernameOrEmail]);
        
        if (!$user) {
            return false;
        }
        
        // Verify password
        if (!$this->verifyPassword($password, $user['password_hash'])) {
            return false;
        }
        
        // Set session
        $_SESSION[self::SESSION_USER_ID] = $user['user_id'];
        
        // Set remember cookie if requested
        if ($remember) {
            $this->setRememberToken($user['user_id']);
        }
        
        // Update last active timestamp
        $this->db->update('users', 
            ['last_active' => \date('Y-m-d H:i:s')], 
            'user_id = :id', 
            ['id' => $user['user_id']]
        );
        
        // Store user data
        $this->user = $user;
        
        // Create session record
        $this->createSession($user['user_id']);
        
        return true;
    }
    
    /**
     * Login a user using a remember me token
     * 
     * @param string $token The remember token
     * @return bool True if login was successful
     */
    private function loginWithRememberToken($token) {
        // Explode token into selector and validator
        $parts = \explode(':', $token);
        
        if (\count($parts) !== 2) {
            return false;
        }
        
        list($selector, $validator) = $parts;
        
        // Look up token in database
        $tokenData = $this->db->queryOne(
            "SELECT * FROM user_tokens WHERE selector = :selector AND type = 'remember' AND expires_at > NOW() LIMIT 1",
            ['selector' => $selector]
        );
        
        if (!$tokenData) {
            return false;
        }
        
        // Verify token hash
        if (!\hash_equals($tokenData['token'], \hash('sha256', $validator))) {
            return false;
        }
        
        // Get user
        $user = $this->getUserById($tokenData['user_id']);
        
        if (!$user || !$user['is_active']) {
            return false;
        }
        
        // Set session
        $_SESSION[self::SESSION_USER_ID] = $user['user_id'];
        
        // Refresh token
        $this->db->delete('user_tokens', 'user_id = :user_id AND type = :type', [
            'user_id' => $user['user_id'],
            'type' => 'remember'
        ]);
        
        $this->setRememberToken($user['user_id']);
        
        // Update last active timestamp
        $this->db->update('users', 
            ['last_active' => \date('Y-m-d H:i:s')], 
            'user_id = :id', 
            ['id' => $user['user_id']]
        );
        
        // Store user data
        $this->user = $user;
        
        // Create session record
        $this->createSession($user['user_id']);
        
        return true;
    }
    
    /**
     * Create a new user
     * 
     * @param array $userData The user data
     * @param string $password The plain password
     * @return int|bool The new user ID or false on failure
     */
    public function register(array $userData, $password) {
        // Check if email or username already exists
        $existingUser = $this->db->queryOne(
            "SELECT user_id FROM users WHERE email = :email OR username = :username LIMIT 1",
            [
                'email' => $userData['email'],
                'username' => $userData['username']
            ]
        );
        
        if ($existingUser) {
            return false;
        }
        
        // Hash password
        $passwordHash = $this->hashPassword($password);
        
        // Prepare user data for insertion
        $data = [
            'username' => $userData['username'],
            'email' => $userData['email'],
            'password_hash' => $passwordHash,
            'full_name' => $userData['full_name'],
            'bio' => $userData['bio'] ?? '',
            'profile_picture' => $userData['profile_picture'] ?? null,
            'joined_date' => \date('Y-m-d H:i:s'),
            'last_active' => \date('Y-m-d H:i:s'),
            'is_verified' => 0,
            'is_active' => 1,
            'role' => 'user'
        ];
        
        // Insert user and get ID
        $userId = $this->db->insert('users', $data);
        
        if (!$userId) {
            return false;
        }
        
        // Create default user settings
        $this->db->insert('user_settings', [
            'user_id' => $userId,
            'privacy_settings' => \json_encode([
                'profile_visibility' => 'public',
                'friend_list_visibility' => 'friends',
                'post_visibility' => 'public'
            ]),
            'notification_settings' => \json_encode([
                'friend_requests' => true,
                'comments' => true,
                'likes' => true,
                'messages' => true,
                'email_notifications' => true
            ]),
            'theme_preferences' => \json_encode([
                'theme' => 'light',
                'font_size' => 'medium'
            ]),
            'language' => 'en',
            'timezone' => 'UTC'
        ]);
        
        return $userId;
    }
    
    /**
     * Log out the current user
     * 
     * @return void
     */
    public function logout() {
        // Clear session data
        if (isset($_SESSION[self::SESSION_USER_ID])) {
            $userId = $_SESSION[self::SESSION_USER_ID];
            
            // Invalidate the current session in the database
            if (isset($_COOKIE['PHPSESSID'])) {
                $this->db->delete('sessions', 'session_id = :session_id', [
                    'session_id' => $_COOKIE['PHPSESSID']
                ]);
            }
            
            // Remove remember tokens
            $this->db->delete('user_tokens', 'user_id = :user_id AND type = :type', [
                'user_id' => $userId,
                'type' => 'remember'
            ]);
        }
        
        // Unset session variable
        unset($_SESSION[self::SESSION_USER_ID]);
        
        // Delete remember cookie
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            \setcookie(self::REMEMBER_COOKIE, '', \time() - 3600, '/', '', false, true);
        }
        
        // Reset user property
        $this->user = null;
        
        // Destroy the session
        \session_regenerate_id(true);
    }
    
    /**
     * Check if a user is logged in
     * 
     * @return bool True if a user is logged in
     */
    public function isLoggedIn() {
        return $this->user !== null;
    }
    
    /**
     * Get the current user data
     * 
     * @return array|null The user data or null if not logged in
     */
    public function getUser() {
        return $this->user;
    }
    
    /**
     * Get the current user ID
     * 
     * @return int|null The user ID or null if not logged in
     */
    public function getUserId() {
        return $this->user ? $this->user['user_id'] : null;
    }
    
    /**
     * Check if the current user has a specific role
     * 
     * @param string $role The role to check
     * @return bool True if the user has the role
     */
    public function hasRole($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $this->user['role'] === $role;
    }
    
    /**
     * Check if the current user is an admin
     * 
     * @return bool True if the user is an admin
     */
    public function isAdmin() {
        return $this->hasRole('admin');
    }
    
    /**
     * Check if the current user is a moderator or higher
     * 
     * @return bool True if the user is a moderator or admin
     */
    public function isModerator() {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return \in_array($this->user['role'], ['moderator', 'admin']);
    }
    
    /**
     * Get a user by ID
     * 
     * @param int $userId The user ID
     * @return array|null The user data or null if not found
     */
    public function getUserById($userId) {
        return $this->db->queryOne("SELECT * FROM users WHERE user_id = :id LIMIT 1", ['id' => $userId]);
    }
    
    /**
     * Get a user by email
     * 
     * @param string $email The email address
     * @return array|null The user data or null if not found
     */
    public function getUserByEmail($email) {
        return $this->db->queryOne("SELECT * FROM users WHERE email = :email LIMIT 1", ['email' => $email]);
    }
    
    /**
     * Get a user by username
     * 
     * @param string $username The username
     * @return array|null The user data or null if not found
     */
    public function getUserByUsername($username) {
        return $this->db->queryOne("SELECT * FROM users WHERE username = :username LIMIT 1", ['username' => $username]);
    }
    
    /**
     * Update a user's profile
     * 
     * @param int $userId The user ID
     * @param array $data The profile data to update
     * @return bool True if the update was successful
     */
    public function updateProfile($userId, array $data) {
        // Filter allowed fields
        $allowedFields = [
            'full_name', 'bio', 'profile_picture', 'cover_photo', 
            'location', 'website'
        ];
        
        $updateData = \array_intersect_key($data, \array_flip($allowedFields));
        
        if (empty($updateData)) {
            return false;
        }
        
        // Perform update
        $result = $this->db->update('users', $updateData, 'user_id = :id', ['id' => $userId]);
        
        // Update local user data if this is the current user
        if ($result && $this->isLoggedIn() && $this->user['user_id'] == $userId) {
            $this->user = \array_merge($this->user, $updateData);
        }
        
        return $result > 0;
    }
    
    /**
     * Change a user's password
     * 
     * @param int $userId The user ID
     * @param string $currentPassword The current password
     * @param string $newPassword The new password
     * @return bool True if the password was changed
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        // Get user
        $user = $this->getUserById($userId);
        
        if (!$user) {
            return false;
        }
        
        // Verify current password
        if (!$this->verifyPassword($currentPassword, $user['password_hash'])) {
            return false;
        }
        
        // Hash new password
        $newHash = $this->hashPassword($newPassword);
        
        // Update password
        $result = $this->db->update('users', 
            ['password_hash' => $newHash], 
            'user_id = :id', 
            ['id' => $userId]
        );
        
        // Invalidate all sessions except current one
        if ($result) {
            $this->db->delete('sessions', 
                'user_id = :user_id AND session_id != :current_session', 
                [
                    'user_id' => $userId,
                    'current_session' => \session_id()
                ]
            );
            
            // Remove all remember tokens
            $this->db->delete('user_tokens', 
                'user_id = :user_id AND type = :type', 
                [
                    'user_id' => $userId,
                    'type' => 'remember'
                ]
            );
        }
        
        return $result > 0;
    }
    
    /**
     * Request a password reset
     * 
     * @param string $email The user's email
     * @return bool True if the reset request was created
     */
    public function requestPasswordReset($email) {
        // Get user by email
        $user = $this->getUserByEmail($email);
        
        if (!$user) {
            return false;
        }
        
        // Generate reset token
        $selector = \bin2hex(\random_bytes(8));
        $validator = \bin2hex(\random_bytes(32));
        
        // Hash the validator for storage
        $token = \hash('sha256', $validator);
        
        // Calculate expiration date (1 hour)
        $expires = \date('Y-m-d H:i:s', \time() + $this->config['password_reset_lifetime']);
        
        // Remove any existing tokens for this user
        $this->db->delete('user_tokens', 
            'user_id = :user_id AND type = :type', 
            [
                'user_id' => $user['user_id'],
                'type' => 'password_reset'
            ]
        );
        
        // Store token in database
        $tokenInserted = $this->db->insert('user_tokens', [
            'user_id' => $user['user_id'],
            'type' => 'password_reset',
            'selector' => $selector,
            'token' => $token,
            'expires_at' => $expires
        ]);
        
        if (!$tokenInserted) {
            return false;
        }
        
        // The full token to send to the user
        $resetToken = $selector . ':' . $validator;
        
        // In a real application, you would send an email with the reset link
        // For now, we'll just return true if the token was created
        return true;
    }
    
    /**
     * Reset a password using a reset token
     * 
     * @param string $token The reset token
     * @param string $newPassword The new password
     * @return bool True if the password was reset
     */
    public function resetPassword($token, $newPassword) {
        // Explode token into selector and validator
        $parts = \explode(':', $token);
        
        if (\count($parts) !== 2) {
            return false;
        }
        
        list($selector, $validator) = $parts;
        
        // Look up token in database
        $tokenData = $this->db->queryOne(
            "SELECT * FROM user_tokens WHERE selector = :selector AND type = 'password_reset' AND expires_at > NOW() LIMIT 1",
            ['selector' => $selector]
        );
        
        if (!$tokenData) {
            return false;
        }
        
        // Verify token hash
        if (!\hash_equals($tokenData['token'], \hash('sha256', $validator))) {
            return false;
        }
        
        // Get user
        $user = $this->getUserById($tokenData['user_id']);
        
        if (!$user || !$user['is_active']) {
            return false;
        }
        
        // Hash new password
        $newHash = $this->hashPassword($newPassword);
        
        // Update password
        $result = $this->db->update('users', 
            ['password_hash' => $newHash], 
            'user_id = :id', 
            ['id' => $user['user_id']]
        );
        
        if (!$result) {
            return false;
        }
        
        // Remove the used token
        $this->db->delete('user_tokens', 'id = :id', ['id' => $tokenData['id']]);
        
        // Invalidate all sessions and tokens for this user
        $this->db->delete('sessions', 'user_id = :user_id', ['user_id' => $user['user_id']]);
        $this->db->delete('user_tokens', 
            'user_id = :user_id AND type = :type', 
            [
                'user_id' => $user['user_id'],
                'type' => 'remember'
            ]
        );
        
        return true;
    }
    
    /**
     * Create a session record in the database
     * 
     * @param int $userId The user ID
     * @return bool True if the session was created
     */
    private function createSession($userId) {
        $sessionId = \session_id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Calculate expiration date (24 hours by default)
        $expires = \date('Y-m-d H:i:s', \time() + $this->config['token_lifetime']);
        
        return $this->db->insert('sessions', [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => \date('Y-m-d H:i:s'),
            'expires_at' => $expires,
            'last_activity' => \date('Y-m-d H:i:s')
        ]) > 0;
    }
    
    /**
     * Set a remember me token for a user
     * 
     * @param int $userId The user ID
     * @return bool True if the token was set
     */
    private function setRememberToken($userId) {
        // Generate token
        $selector = \bin2hex(\random_bytes(8));
        $validator = \bin2hex(\random_bytes(32));
        
        // Hash the validator for storage
        $token = \hash('sha256', $validator);
        
        // Calculate expiration date (30 days by default)
        $expires = \date('Y-m-d H:i:s', \time() + $this->config['remember_me_lifetime']);
        
        // Remove any existing remember tokens for this user
        $this->db->delete('user_tokens', 
            'user_id = :user_id AND type = :type', 
            [
                'user_id' => $userId,
                'type' => 'remember'
            ]
        );
        
        // Store token in database
        $tokenInserted = $this->db->insert('user_tokens', [
            'user_id' => $userId,
            'type' => 'remember',
            'selector' => $selector,
            'token' => $token,
            'expires_at' => $expires
        ]);
        
        if (!$tokenInserted) {
            return false;
        }
        
        // Set cookie with the full token
        $cookieToken = $selector . ':' . $validator;
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        \setcookie(
            self::REMEMBER_COOKIE,
            $cookieToken,
            [
                'expires' => \time() + $this->config['remember_me_lifetime'],
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
        
        return true;
    }
    
    /**
     * Hash a password
     * 
     * @param string $password The plain password
     * @return string The hashed password
     */
    private function hashPassword($password) {
        return \password_hash($password, PASSWORD_BCRYPT, [
            'cost' => $this->config['bcrypt_cost'] ?? 12
        ]);
    }
    
    /**
     * Verify a password against a hash
     * 
     * @param string $password The plain password
     * @param string $hash The password hash
     * @return bool True if the password is correct
     */
    private function verifyPassword($password, $hash) {
        return \password_verify($password, $hash);
    }
    
    /**
     * Clean up expired sessions and tokens
     * 
     * @return void
     */
    public function cleanUp() {
        // Delete expired sessions
        $this->db->delete('sessions', 'expires_at < NOW()');
        
        // Delete expired tokens
        $this->db->delete('user_tokens', 'expires_at < NOW()');
    }
} 