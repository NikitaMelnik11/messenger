<?php
/**
 * Vegan Messenger Social Network
 * Main entry point for the web application
 */

// Load configuration
$config = require_once __DIR__ . '/../../config/env.php';

// Set error reporting based on debug mode
if ($config['app']['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set($config['app']['timezone']);

// Load autoloader
require_once __DIR__ . '/../app/bootstrap.php';

// Initialize application
use VeganMessenger\App;
use VeganMessenger\Router;
use VeganMessenger\Controllers\AuthController;
use VeganMessenger\Controllers\ProfileController;
use VeganMessenger\Controllers\FeedController;
use VeganMessenger\Controllers\MessagingController;
use VeganMessenger\Controllers\GroupController;
use VeganMessenger\Controllers\EventController;
use VeganMessenger\Controllers\SearchController;
use VeganMessenger\Controllers\NotificationController;
use VeganMessenger\Controllers\SettingsController;
use VeganMessenger\Controllers\ErrorController;

// Initialize application
$app = new App($config);

// Get router instance
$router = $app->getRouter();

// Define routes
// Auth routes
$router->get('/', [AuthController::class, 'home']);
$router->get('/login', [AuthController::class, 'loginPage']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'registerPage']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout', [AuthController::class, 'logout']);
$router->get('/forgot-password', [AuthController::class, 'forgotPasswordPage']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password/{token}', [AuthController::class, 'resetPasswordPage']);
$router->post('/reset-password', [AuthController::class, 'resetPassword']);

// Profile routes
$router->get('/profile/{username}', [ProfileController::class, 'show']);
$router->get('/profile/edit', [ProfileController::class, 'editPage']);
$router->post('/profile/update', [ProfileController::class, 'update']);
$router->post('/profile/upload-photo', [ProfileController::class, 'uploadPhoto']);
$router->post('/profile/upload-cover', [ProfileController::class, 'uploadCover']);

// Friend management routes
$router->post('/friends/request/{userId}', [ProfileController::class, 'sendFriendRequest']);
$router->post('/friends/accept/{userId}', [ProfileController::class, 'acceptFriendRequest']);
$router->post('/friends/reject/{userId}', [ProfileController::class, 'rejectFriendRequest']);
$router->post('/friends/remove/{userId}', [ProfileController::class, 'removeFriend']);
$router->post('/friends/block/{userId}', [ProfileController::class, 'blockUser']);
$router->post('/friends/unblock/{userId}', [ProfileController::class, 'unblockUser']);
$router->get('/friends', [ProfileController::class, 'friendsList']);
$router->get('/friends/requests', [ProfileController::class, 'friendRequests']);
$router->get('/friends/suggestions', [ProfileController::class, 'friendSuggestions']);

// Feed routes
$router->get('/feed', [FeedController::class, 'index']);
$router->post('/posts/create', [FeedController::class, 'createPost']);
$router->post('/posts/update/{postId}', [FeedController::class, 'updatePost']);
$router->post('/posts/delete/{postId}', [FeedController::class, 'deletePost']);
$router->post('/posts/{postId}/like', [FeedController::class, 'likePost']);
$router->post('/posts/{postId}/unlike', [FeedController::class, 'unlikePost']);
$router->post('/posts/{postId}/comment', [FeedController::class, 'addComment']);
$router->post('/comments/{commentId}/update', [FeedController::class, 'updateComment']);
$router->post('/comments/{commentId}/delete', [FeedController::class, 'deleteComment']);
$router->get('/hashtag/{tag}', [FeedController::class, 'hashtag']);

// Messaging routes
$router->get('/messages', [MessagingController::class, 'index']);
$router->get('/messages/{userId}', [MessagingController::class, 'conversation']);
$router->post('/messages/send', [MessagingController::class, 'sendMessage']);
$router->post('/messages/read/{messageId}', [MessagingController::class, 'markAsRead']);
$router->post('/messages/delete/{messageId}', [MessagingController::class, 'deleteMessage']);
$router->get('/messages/group/create', [MessagingController::class, 'createGroupPage']);
$router->post('/messages/group/create', [MessagingController::class, 'createGroup']);
$router->get('/messages/group/{groupId}', [MessagingController::class, 'groupConversation']);
$router->post('/messages/group/{groupId}/add/{userId}', [MessagingController::class, 'addToGroup']);
$router->post('/messages/group/{groupId}/remove/{userId}', [MessagingController::class, 'removeFromGroup']);
$router->post('/messages/group/{groupId}/leave', [MessagingController::class, 'leaveGroup']);

// Group routes
$router->get('/groups', [GroupController::class, 'index']);
$router->get('/groups/create', [GroupController::class, 'createPage']);
$router->post('/groups/create', [GroupController::class, 'create']);
$router->get('/groups/{groupId}', [GroupController::class, 'show']);
$router->get('/groups/{groupId}/edit', [GroupController::class, 'editPage']);
$router->post('/groups/{groupId}/update', [GroupController::class, 'update']);
$router->post('/groups/{groupId}/join', [GroupController::class, 'join']);
$router->post('/groups/{groupId}/leave', [GroupController::class, 'leave']);
$router->post('/groups/{groupId}/post', [GroupController::class, 'createPost']);
$router->get('/groups/{groupId}/members', [GroupController::class, 'members']);
$router->post('/groups/{groupId}/members/add/{userId}', [GroupController::class, 'addMember']);
$router->post('/groups/{groupId}/members/remove/{userId}', [GroupController::class, 'removeMember']);
$router->post('/groups/{groupId}/members/promote/{userId}', [GroupController::class, 'promoteMember']);
$router->post('/groups/{groupId}/members/demote/{userId}', [GroupController::class, 'demoteMember']);

// Event routes
$router->get('/events', [EventController::class, 'index']);
$router->get('/events/create', [EventController::class, 'createPage']);
$router->post('/events/create', [EventController::class, 'create']);
$router->get('/events/{eventId}', [EventController::class, 'show']);
$router->get('/events/{eventId}/edit', [EventController::class, 'editPage']);
$router->post('/events/{eventId}/update', [EventController::class, 'update']);
$router->post('/events/{eventId}/attend', [EventController::class, 'attend']);
$router->post('/events/{eventId}/interested', [EventController::class, 'interested']);
$router->post('/events/{eventId}/not-attending', [EventController::class, 'notAttending']);
$router->get('/events/{eventId}/attendees', [EventController::class, 'attendees']);
$router->post('/events/{eventId}/invite/{userId}', [EventController::class, 'invite']);

// Search routes
$router->get('/search', [SearchController::class, 'index']);
$router->get('/search/users', [SearchController::class, 'users']);
$router->get('/search/posts', [SearchController::class, 'posts']);
$router->get('/search/groups', [SearchController::class, 'groups']);
$router->get('/search/events', [SearchController::class, 'events']);
$router->post('/search/save', [SearchController::class, 'saveSearch']);
$router->get('/search/history', [SearchController::class, 'history']);
$router->post('/search/history/clear', [SearchController::class, 'clearHistory']);

// Notification routes
$router->get('/notifications', [NotificationController::class, 'index']);
$router->post('/notifications/mark-read/{notificationId}', [NotificationController::class, 'markAsRead']);
$router->post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead']);
$router->post('/notifications/delete/{notificationId}', [NotificationController::class, 'delete']);
$router->post('/notifications/delete-all', [NotificationController::class, 'deleteAll']);
$router->get('/notifications/settings', [NotificationController::class, 'settings']);
$router->post('/notifications/settings/update', [NotificationController::class, 'updateSettings']);

// Settings routes
$router->get('/settings', [SettingsController::class, 'index']);
$router->post('/settings/update', [SettingsController::class, 'update']);
$router->get('/settings/security', [SettingsController::class, 'security']);
$router->post('/settings/security/update', [SettingsController::class, 'updateSecurity']);
$router->post('/settings/security/change-password', [SettingsController::class, 'changePassword']);
$router->get('/settings/privacy', [SettingsController::class, 'privacy']);
$router->post('/settings/privacy/update', [SettingsController::class, 'updatePrivacy']);
$router->get('/settings/notifications', [SettingsController::class, 'notifications']);
$router->post('/settings/notifications/update', [SettingsController::class, 'updateNotifications']);
$router->get('/settings/account', [SettingsController::class, 'account']);
$router->post('/settings/account/deactivate', [SettingsController::class, 'deactivateAccount']);
$router->post('/settings/account/delete', [SettingsController::class, 'deleteAccount']);

// API routes for AJAX and mobile apps
$router->group('/api', function($router) {
    // Auth API endpoints
    $router->post('/auth/login', [AuthController::class, 'apiLogin']);
    $router->post('/auth/register', [AuthController::class, 'apiRegister']);
    $router->post('/auth/refresh-token', [AuthController::class, 'apiRefreshToken']);
    $router->post('/auth/logout', [AuthController::class, 'apiLogout']);
    
    // User API endpoints
    $router->get('/user/profile', [ProfileController::class, 'apiGetProfile']);
    $router->post('/user/profile/update', [ProfileController::class, 'apiUpdateProfile']);
    $router->get('/user/{userId}', [ProfileController::class, 'apiGetUser']);
    $router->get('/user/{userId}/friends', [ProfileController::class, 'apiGetFriends']);
    
    // Feed API endpoints
    $router->get('/feed', [FeedController::class, 'apiFeed']);
    $router->post('/posts', [FeedController::class, 'apiCreatePost']);
    $router->put('/posts/{postId}', [FeedController::class, 'apiUpdatePost']);
    $router->delete('/posts/{postId}', [FeedController::class, 'apiDeletePost']);
    $router->get('/posts/{postId}/comments', [FeedController::class, 'apiGetComments']);
    $router->post('/posts/{postId}/comments', [FeedController::class, 'apiAddComment']);
    
    // Messaging API endpoints
    $router->get('/conversations', [MessagingController::class, 'apiGetConversations']);
    $router->get('/conversations/{conversationId}/messages', [MessagingController::class, 'apiGetMessages']);
    $router->post('/messages', [MessagingController::class, 'apiSendMessage']);
    
    // Group API endpoints
    $router->get('/groups', [GroupController::class, 'apiGetGroups']);
    $router->get('/groups/{groupId}', [GroupController::class, 'apiGetGroup']);
    $router->post('/groups', [GroupController::class, 'apiCreateGroup']);
    
    // Event API endpoints
    $router->get('/events', [EventController::class, 'apiGetEvents']);
    $router->get('/events/{eventId}', [EventController::class, 'apiGetEvent']);
    $router->post('/events', [EventController::class, 'apiCreateEvent']);
    
    // Notification API endpoints
    $router->get('/notifications', [NotificationController::class, 'apiGetNotifications']);
    $router->post('/notifications/read', [NotificationController::class, 'apiMarkAsRead']);
    
    // Search API endpoints
    $router->get('/search', [SearchController::class, 'apiSearch']);
});

// Error routes
$router->set404([ErrorController::class, 'notFound']);
$router->set500([ErrorController::class, 'serverError']);

// Start the application
$app->run(); 