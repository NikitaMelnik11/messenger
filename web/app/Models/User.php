<?php
/**
 * Vegan Messenger Social Network
 * User Model
 */

namespace VeganMessenger\Models;

use VeganMessenger\Model;

class User extends Model {
    /**
     * @var string The table name
     */
    protected $table = 'users';
    
    /**
     * @var string The primary key column
     */
    protected $primaryKey = 'user_id';
    
    /**
     * @var array Fields that can be mass assigned
     */
    protected $fillable = [
        'username', 'email', 'full_name', 'bio', 'profile_picture', 
        'cover_photo', 'location', 'website', 'role', 'is_active', 
        'is_verified', 'joined_date', 'last_active'
    ];
    
    /**
     * @var array Fields that cannot be mass assigned
     */
    protected $guarded = ['user_id', 'password_hash'];
    
    /**
     * @var array Validation rules
     */
    protected $rules = [
        'username' => [
            'required' => true,
            'min_length' => 3,
            'max_length' => 50,
            'unique' => true
        ],
        'email' => [
            'required' => true,
            'email' => true,
            'max_length' => 100,
            'unique' => true
        ],
        'full_name' => [
            'required' => true,
            'max_length' => 100
        ],
        'bio' => [
            'max_length' => 500
        ],
        'profile_picture' => [
            'max_length' => 255
        ],
        'cover_photo' => [
            'max_length' => 255
        ],
        'location' => [
            'max_length' => 100
        ],
        'website' => [
            'max_length' => 255
        ],
        'role' => [
            'required' => true
        ]
    ];
    
    /**
     * Set the user's password
     * 
     * @param string $password The plain text password
     * @return User This user instance
     */
    public function setPassword($password) {
        $this->data['password_hash'] = \password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12
        ]);
        
        return $this;
    }
    
    /**
     * Verify the user's password
     * 
     * @param string $password The plain text password
     * @return bool True if the password is correct
     */
    public function verifyPassword($password) {
        return \password_verify($password, $this->data['password_hash']);
    }
    
    /**
     * Get the user's profile URL
     * 
     * @return string The profile URL
     */
    public function getProfileUrl() {
        return \url('profile/' . $this->data['username']);
    }
    
    /**
     * Get the user's profile picture URL
     * 
     * @param string $defaultPicture The default picture if not set
     * @return string The profile picture URL
     */
    public function getProfilePictureUrl($defaultPicture = 'img/default-avatar.png') {
        return !empty($this->data['profile_picture']) 
            ? \asset($this->data['profile_picture']) 
            : \asset($defaultPicture);
    }
    
    /**
     * Get the user's cover photo URL
     * 
     * @param string $defaultCover The default cover if not set
     * @return string The cover photo URL
     */
    public function getCoverPhotoUrl($defaultCover = 'img/default-cover.jpg') {
        return !empty($this->data['cover_photo']) 
            ? \asset($this->data['cover_photo']) 
            : \asset($defaultCover);
    }
    
    /**
     * Get the user's full name or username
     * 
     * @return string The name to display
     */
    public function getDisplayName() {
        return !empty($this->data['full_name']) 
            ? $this->data['full_name'] 
            : $this->data['username'];
    }
    
    /**
     * Check if the user is an admin
     * 
     * @return bool True if the user is an admin
     */
    public function isAdmin() {
        return $this->data['role'] === 'admin';
    }
    
    /**
     * Check if the user is a moderator
     * 
     * @return bool True if the user is a moderator
     */
    public function isModerator() {
        return $this->data['role'] === 'moderator' || $this->isAdmin();
    }
    
    /**
     * Check if the user is verified
     * 
     * @return bool True if the user is verified
     */
    public function isVerified() {
        return (bool)$this->data['is_verified'];
    }
    
    /**
     * Check if the user is active
     * 
     * @return bool True if the user is active
     */
    public function isActive() {
        return (bool)$this->data['is_active'];
    }
    
    /**
     * Deactivate the user
     * 
     * @return bool True if the user was deactivated
     */
    public function deactivate() {
        $this->data['is_active'] = 0;
        return $this->save();
    }
    
    /**
     * Activate the user
     * 
     * @return bool True if the user was activated
     */
    public function activate() {
        $this->data['is_active'] = 1;
        return $this->save();
    }
    
    /**
     * Verify the user
     * 
     * @return bool True if the user was verified
     */
    public function verify() {
        $this->data['is_verified'] = 1;
        return $this->save();
    }
    
    /**
     * Get the user's settings
     * 
     * @return array|null The user settings
     */
    public function getSettings() {
        $settings = $this->db->queryOne(
            "SELECT * FROM user_settings WHERE user_id = :user_id LIMIT 1",
            ['user_id' => $this->getId()]
        );
        
        // Decode JSON fields
        if ($settings) {
            $settings['privacy_settings'] = \json_decode($settings['privacy_settings'], true);
            $settings['notification_settings'] = \json_decode($settings['notification_settings'], true);
            $settings['theme_preferences'] = \json_decode($settings['theme_preferences'], true);
        }
        
        return $settings;
    }
    
    /**
     * Update the user's settings
     * 
     * @param array $settings The settings to update
     * @return bool True if the settings were updated
     */
    public function updateSettings(array $settings) {
        // Get current settings
        $currentSettings = $this->getSettings();
        
        if (!$currentSettings) {
            // Create default settings if not found
            return $this->db->insert('user_settings', [
                'user_id' => $this->getId(),
                'privacy_settings' => isset($settings['privacy_settings']) ? \json_encode($settings['privacy_settings']) : \json_encode([
                    'profile_visibility' => 'public',
                    'friend_list_visibility' => 'friends',
                    'post_visibility' => 'public'
                ]),
                'notification_settings' => isset($settings['notification_settings']) ? \json_encode($settings['notification_settings']) : \json_encode([
                    'friend_requests' => true,
                    'comments' => true,
                    'likes' => true,
                    'messages' => true,
                    'email_notifications' => true
                ]),
                'theme_preferences' => isset($settings['theme_preferences']) ? \json_encode($settings['theme_preferences']) : \json_encode([
                    'theme' => 'light',
                    'font_size' => 'medium'
                ]),
                'language' => $settings['language'] ?? 'en',
                'timezone' => $settings['timezone'] ?? 'UTC'
            ]) > 0;
        }
        
        // Update settings
        $updateData = [];
        
        if (isset($settings['privacy_settings'])) {
            $updateData['privacy_settings'] = \json_encode($settings['privacy_settings']);
        }
        
        if (isset($settings['notification_settings'])) {
            $updateData['notification_settings'] = \json_encode($settings['notification_settings']);
        }
        
        if (isset($settings['theme_preferences'])) {
            $updateData['theme_preferences'] = \json_encode($settings['theme_preferences']);
        }
        
        if (isset($settings['language'])) {
            $updateData['language'] = $settings['language'];
        }
        
        if (isset($settings['timezone'])) {
            $updateData['timezone'] = $settings['timezone'];
        }
        
        if (empty($updateData)) {
            return true; // Nothing to update
        }
        
        return $this->db->update('user_settings', $updateData, 'user_id = :user_id', [
            'user_id' => $this->getId()
        ]) > 0;
    }
    
    /**
     * Get the user's friends
     * 
     * @param int $limit The maximum number of friends to return
     * @param int $offset The offset for pagination
     * @return array The user's friends
     */
    public function getFriends($limit = 20, $offset = 0) {
        $query = "
            SELECT u.* FROM users u
            JOIN friendships f ON (f.friend_id = u.user_id OR f.user_id = u.user_id)
            WHERE
                (f.user_id = :user_id OR f.friend_id = :user_id)
                AND f.status = 'accepted'
                AND u.user_id != :user_id
            ORDER BY u.full_name
            LIMIT :limit OFFSET :offset
        ";
        
        $result = $this->db->query($query, [
            'user_id' => $this->getId(),
            'limit' => $limit,
            'offset' => $offset
        ]);
        
        $friends = [];
        
        foreach ($result as $row) {
            $friends[] = new User($row);
        }
        
        return $friends;
    }
    
    /**
     * Count the user's friends
     * 
     * @return int The number of friends
     */
    public function countFriends() {
        $query = "
            SELECT COUNT(*) FROM users u
            JOIN friendships f ON (f.friend_id = u.user_id OR f.user_id = u.user_id)
            WHERE
                (f.user_id = :user_id OR f.friend_id = :user_id)
                AND f.status = 'accepted'
                AND u.user_id != :user_id
        ";
        
        return (int)$this->db->queryValue($query, [
            'user_id' => $this->getId()
        ]);
    }
    
    /**
     * Check if the user is friends with another user
     * 
     * @param int $userId The other user's ID
     * @return bool True if the users are friends
     */
    public function isFriendWith($userId) {
        $query = "
            SELECT COUNT(*) FROM friendships
            WHERE
                ((user_id = :user_id AND friend_id = :other_id) OR (user_id = :other_id AND friend_id = :user_id))
                AND status = 'accepted'
        ";
        
        $count = (int)$this->db->queryValue($query, [
            'user_id' => $this->getId(),
            'other_id' => $userId
        ]);
        
        return $count > 0;
    }
    
    /**
     * Find a user by email
     * 
     * @param string $email The email address
     * @return User|null The user or null if not found
     */
    public static function findByEmail($email) {
        return self::findBy('email', $email);
    }
    
    /**
     * Find a user by username
     * 
     * @param string $username The username
     * @return User|null The user or null if not found
     */
    public static function findByUsername($username) {
        return self::findBy('username', $username);
    }
} 