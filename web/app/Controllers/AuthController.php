<?php
/**
 * Vegan Messenger Social Network
 * Authentication Controller
 */

namespace VeganMessenger\Controllers;

use VeganMessenger\Controller;
use VeganMessenger\Models\User;

class AuthController extends Controller {
    /**
     * Display the homepage
     * 
     * @return void
     */
    public function home() {
        // If user is logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        // Otherwise show landing page
        $this->display('home');
    }
    
    /**
     * Display the login page
     * 
     * @return void
     */
    public function loginPage() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        $redirectUrl = $this->get('redirect', 'feed');
        
        $this->display('auth/login', [
            'redirectUrl' => $redirectUrl
        ]);
    }
    
    /**
     * Handle login form submission
     * 
     * @return void
     */
    public function login() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        // Validate input
        $errors = $this->validate([
            'username_or_email' => ['required' => true],
            'password' => ['required' => true]
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'Please correct the errors below.');
            $this->display('auth/login', [
                'errors' => $errors,
                'input' => $this->post(),
                'redirectUrl' => $this->post('redirect', 'feed')
            ]);
            return;
        }
        
        // Attempt login
        $remember = (bool)$this->post('remember', false);
        $result = $this->auth->login(
            $this->post('username_or_email'),
            $this->post('password'),
            $remember
        );
        
        if (!$result) {
            $this->setFlash('error', 'Invalid username/email or password.');
            $this->display('auth/login', [
                'input' => $this->post(),
                'redirectUrl' => $this->post('redirect', 'feed')
            ]);
            return;
        }
        
        // Redirect after successful login
        $redirectUrl = $this->post('redirect', 'feed');
        $this->setFlash('success', 'You have been successfully logged in.');
        $this->redirect($this->view->url($redirectUrl));
    }
    
    /**
     * Display the registration page
     * 
     * @return void
     */
    public function registerPage() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        $this->display('auth/register');
    }
    
    /**
     * Handle registration form submission
     * 
     * @return void
     */
    public function register() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        // Validate input
        $errors = $this->validate([
            'username' => [
                'required' => true,
                'min_length' => 3,
                'max_length' => 50,
                'regex' => '/^[a-zA-Z0-9_]+$/'
            ],
            'email' => [
                'required' => true,
                'email' => true,
                'max_length' => 100
            ],
            'password' => [
                'required' => true,
                'min_length' => 8
            ],
            'password_confirm' => [
                'required' => true,
                'matches' => 'password'
            ],
            'full_name' => [
                'required' => true,
                'max_length' => 100
            ],
            'terms' => [
                'required' => true
            ]
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'Please correct the errors below.');
            $this->display('auth/register', [
                'errors' => $errors,
                'input' => $this->post()
            ]);
            return;
        }
        
        // Check if username or email already exists
        if (User::exists('username', $this->post('username'))) {
            $errors['username'] = 'This username is already taken.';
        }
        
        if (User::exists('email', $this->post('email'))) {
            $errors['email'] = 'This email is already registered.';
        }
        
        if (!empty($errors)) {
            $this->setFlash('error', 'Please correct the errors below.');
            $this->display('auth/register', [
                'errors' => $errors,
                'input' => $this->post()
            ]);
            return;
        }
        
        // Create user
        $user = new User();
        $user->fill([
            'username' => $this->post('username'),
            'email' => $this->post('email'),
            'full_name' => $this->post('full_name'),
            'bio' => '',
            'role' => 'user',
            'is_active' => 1,
            'is_verified' => 0,
            'joined_date' => \date('Y-m-d H:i:s'),
            'last_active' => \date('Y-m-d H:i:s')
        ]);
        
        // Set password
        $user->setPassword($this->post('password'));
        
        // Save user
        if (!$user->save()) {
            $this->setFlash('error', 'An error occurred during registration. Please try again.');
            $this->display('auth/register', [
                'input' => $this->post()
            ]);
            return;
        }
        
        // Auto login after registration
        $this->auth->login($this->post('username'), $this->post('password'));
        
        // Redirect to feed
        $this->setFlash('success', 'Your account has been created successfully!');
        $this->redirect($this->view->url('feed'));
    }
    
    /**
     * Log the user out
     * 
     * @return void
     */
    public function logout() {
        $this->auth->logout();
        $this->setFlash('success', 'You have been successfully logged out.');
        $this->redirect($this->view->url('login'));
    }
    
    /**
     * Display the forgot password page
     * 
     * @return void
     */
    public function forgotPasswordPage() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        $this->display('auth/forgot_password');
    }
    
    /**
     * Handle forgot password form submission
     * 
     * @return void
     */
    public function forgotPassword() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        // Validate input
        $errors = $this->validate([
            'email' => [
                'required' => true,
                'email' => true
            ]
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'Please correct the errors below.');
            $this->display('auth/forgot_password', [
                'errors' => $errors,
                'input' => $this->post()
            ]);
            return;
        }
        
        // Check if email exists
        $user = User::findByEmail($this->post('email'));
        
        if (!$user) {
            // Don't reveal that the email doesn't exist for security reasons
            $this->setFlash('success', 'If your email is registered, you will receive a password reset link shortly.');
            $this->redirect($this->view->url('login'));
            return;
        }
        
        // Request password reset
        $result = $this->auth->requestPasswordReset($this->post('email'));
        
        // Show success message regardless of result
        $this->setFlash('success', 'If your email is registered, you will receive a password reset link shortly.');
        $this->redirect($this->view->url('login'));
    }
    
    /**
     * Display the reset password page
     * 
     * @param string $token The reset token
     * @return void
     */
    public function resetPasswordPage($token) {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        $this->display('auth/reset_password', [
            'token' => $token
        ]);
    }
    
    /**
     * Handle reset password form submission
     * 
     * @return void
     */
    public function resetPassword() {
        // If user is already logged in, redirect to feed
        if ($this->auth->isLoggedIn()) {
            $this->redirect($this->view->url('feed'));
            return;
        }
        
        // Validate input
        $errors = $this->validate([
            'token' => [
                'required' => true
            ],
            'password' => [
                'required' => true,
                'min_length' => 8
            ],
            'password_confirm' => [
                'required' => true,
                'matches' => 'password'
            ]
        ]);
        
        if (!empty($errors)) {
            $this->setFlash('error', 'Please correct the errors below.');
            $this->display('auth/reset_password', [
                'errors' => $errors,
                'input' => $this->post(),
                'token' => $this->post('token')
            ]);
            return;
        }
        
        // Reset password
        $result = $this->auth->resetPassword(
            $this->post('token'),
            $this->post('password')
        );
        
        if (!$result) {
            $this->setFlash('error', 'Invalid or expired password reset token.');
            $this->redirect($this->view->url('forgot-password'));
            return;
        }
        
        // Show success message
        $this->setFlash('success', 'Your password has been reset successfully. You can now log in with your new password.');
        $this->redirect($this->view->url('login'));
    }
    
    /**
     * API login endpoint
     * 
     * @return void
     */
    public function apiLogin() {
        // Validate input
        $data = \json_decode(\file_get_contents('php://input'), true);
        
        if (!$data) {
            $this->json([
                'success' => false,
                'message' => 'Invalid request format.'
            ], 400);
            return;
        }
        
        // Check required fields
        if (!isset($data['username_or_email']) || !isset($data['password'])) {
            $this->json([
                'success' => false,
                'message' => 'Username/email and password are required.'
            ], 400);
            return;
        }
        
        // Attempt login
        $remember = isset($data['remember']) ? (bool)$data['remember'] : false;
        $result = $this->auth->login(
            $data['username_or_email'],
            $data['password'],
            $remember
        );
        
        if (!$result) {
            $this->json([
                'success' => false,
                'message' => 'Invalid username/email or password.'
            ], 401);
            return;
        }
        
        // Return user data and tokens
        $user = $this->auth->getUser();
        
        $this->json([
            'success' => true,
            'message' => 'Login successful.',
            'user' => [
                'id' => $user['user_id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'full_name' => $user['full_name'],
                'profile_picture' => $user['profile_picture'],
                'role' => $user['role']
            ],
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token']
        ]);
    }
    
    /**
     * API register endpoint
     * 
     * @return void
     */
    public function apiRegister() {
        // Validate input
        $data = \json_decode(\file_get_contents('php://input'), true);
        
        if (!$data) {
            $this->json([
                'success' => false,
                'message' => 'Invalid request format.'
            ], 400);
            return;
        }
        
        // Check required fields
        $requiredFields = ['username', 'email', 'password', 'full_name'];
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $errors[$field] = \ucfirst(\str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate email
        if (isset($data['email']) && !\filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        
        // Validate password
        if (isset($data['password']) && \strlen($data['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }
        
        // Check if username or email already exists
        if (isset($data['username']) && User::exists('username', $data['username'])) {
            $errors['username'] = 'This username is already taken.';
        }
        
        if (isset($data['email']) && User::exists('email', $data['email'])) {
            $errors['email'] = 'This email is already registered.';
        }
        
        if (!empty($errors)) {
            $this->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errors
            ], 422);
            return;
        }
        
        // Create user
        $user = new User();
        $user->fill([
            'username' => $data['username'],
            'email' => $data['email'],
            'full_name' => $data['full_name'],
            'bio' => $data['bio'] ?? '',
            'role' => 'user',
            'is_active' => 1,
            'is_verified' => 0,
            'joined_date' => \date('Y-m-d H:i:s'),
            'last_active' => \date('Y-m-d H:i:s')
        ]);
        
        // Set password
        $user->setPassword($data['password']);
        
        // Save user
        if (!$user->save()) {
            $this->json([
                'success' => false,
                'message' => 'An error occurred during registration.'
            ], 500);
            return;
        }
        
        // Auto login after registration
        $loginResult = $this->auth->login($data['username'], $data['password']);
        
        if (!$loginResult) {
            $this->json([
                'success' => true,
                'message' => 'Registration successful, but auto-login failed.',
                'user_id' => $user->getId()
            ]);
            return;
        }
        
        // Return user data and tokens
        $userData = $this->auth->getUser();
        
        $this->json([
            'success' => true,
            'message' => 'Registration successful.',
            'user' => [
                'id' => $userData['user_id'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'full_name' => $userData['full_name'],
                'profile_picture' => $userData['profile_picture'],
                'role' => $userData['role']
            ],
            'token' => $loginResult['token'],
            'refresh_token' => $loginResult['refresh_token']
        ]);
    }
    
    /**
     * API refresh token endpoint
     * 
     * @return void
     */
    public function apiRefreshToken() {
        // Get refresh token from request
        $data = \json_decode(\file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['refresh_token'])) {
            $this->json([
                'success' => false,
                'message' => 'Refresh token is required.'
            ], 400);
            return;
        }
        
        // Refresh token
        $result = $this->auth->refreshToken($data['refresh_token']);
        
        if (!$result) {
            $this->json([
                'success' => false,
                'message' => 'Invalid or expired refresh token.'
            ], 401);
            return;
        }
        
        // Return new tokens
        $this->json([
            'success' => true,
            'message' => 'Token refreshed successfully.',
            'token' => $result['token'],
            'refresh_token' => $result['refresh_token']
        ]);
    }
    
    /**
     * API logout endpoint
     * 
     * @return void
     */
    public function apiLogout() {
        // Get token from Authorization header
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (\preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
            $this->auth->logout($token);
        }
        
        $this->json([
            'success' => true,
            'message' => 'Logout successful.'
        ]);
    }
} 