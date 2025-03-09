#pragma once

#include <string>
#include <vector>
#include <map>
#include <mutex>
#include <memory>
#include <mysql/mysql.h>
namespace VeganMessenger {
namespace Auth {

// Configuration for the auth service
struct AuthServiceConfig {
    int tokenLifetime;         // Access token lifetime in seconds
    int refreshTokenLifetime;  // Refresh token lifetime in seconds
    int bcryptCost;            // BCrypt cost factor for password hashing
    std::string jwtSecret;     // Secret key for JWT signing
};

// User data structure
struct User {
    int id;
    std::string username;
    std::string email;
    std::string fullName;
    std::string passwordHash;
    std::string createdAt;
    std::string updatedAt;
    bool isActive;
    bool isVerified;
};

// Token data structure
struct TokenPair {
    std::string accessToken;
    std::string refreshToken;
    int expiresIn;
};

// Result of authentication operations
struct AuthResult {
    bool success;
    std::string message;
    User user;
    TokenPair tokens;
};

// Class for handling authentication operations
class AuthService {
public:
    // Constructor
    AuthService(const AuthServiceConfig& config);
    
    // Destructor
    ~AuthService();
    
    // User registration
    AuthResult registerUser(const std::string& username, const std::string& email, 
                           const std::string& password, const std::string& fullName);
    
    // User login
    AuthResult login(const std::string& usernameOrEmail, const std::string& password);
    
    // Token validation
    bool validateToken(const std::string& token, User& outUser);
    
    // Token refresh
    AuthResult refreshToken(const std::string& refreshToken);
    
    // User logout
    bool logout(const std::string& refreshToken);
    
    // Password reset request
    bool requestPasswordReset(const std::string& email);
    
    // Password reset confirmation
    bool resetPassword(const std::string& token, const std::string& newPassword);
    
    // Email verification
    bool verifyEmail(const std::string& token);
    
    // Get user by ID
    User getUserById(int userId);
    
    // Get user by username
    User getUserByUsername(const std::string& username);
    
    // Get user by email
    User getUserByEmail(const std::string& email);
    
    // Update user profile
    bool updateUserProfile(int userId, const std::map<std::string, std::string>& fields);
    
    // Change password
    bool changePassword(int userId, const std::string& oldPassword, const std::string& newPassword);
    
private:
    // Database connection
    MYSQL* dbConn_;
    std::mutex dbMutex_;
    
    // Configuration
    AuthServiceConfig config_;
    
    // Private methods
    
    // Connect to database
    bool connectToDatabase();
    
    // Close database connection
    void closeDatabase();
    
    // Hash password using BCrypt
    std::string hashPassword(const std::string& password);
    
    // Verify password against hash
    bool verifyPassword(const std::string& password, const std::string& hash);
    
    // Generate JWT token
    std::string generateToken(int userId, const std::string& username, int expiresIn);
    
    // Generate refresh token
    std::string generateRefreshToken();
    
    // Store refresh token in database
    bool storeRefreshToken(int userId, const std::string& refreshToken, int expiresIn);
    
    // Validate refresh token
    bool validateRefreshToken(const std::string& refreshToken, int& outUserId);
    
    // Delete refresh token from database
    bool deleteRefreshToken(const std::string& refreshToken);
    
    // Parse JWT token
    bool parseToken(const std::string& token, int& outUserId, std::string& outUsername);
    
    // Execute SQL query with mutex lock
    bool executeQuery(const std::string& query);
    
    // Execute SQL query and get result with mutex lock
    MYSQL_RES* executeQueryWithResult(const std::string& query);
    
    // Convert MySQL row to User struct
    User rowToUser(MYSQL_ROW row);
};

} // namespace Auth
} // namespace VeganMessenger 