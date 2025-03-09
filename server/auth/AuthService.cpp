#include "AuthService.h"
#include <iostream>
#include <random>
#include <algorithm>
#include <iomanip>
#include <sstream>
#include <openssl/evp.h>
#include <openssl/hmac.h>
#include <openssl/sha.h>
#include <jwt/jwt.hpp>
#include <mysql/mysql.h>
#include <bcrypt/BCrypt.hpp>
#include <jwt-cpp/jwt.h>

namespace VeganMessenger {
namespace Auth {

// Constructor
AuthService::AuthService(const AuthServiceConfig& config) 
    : config_(config), dbConn_(nullptr) {
    // Initialize database connection
    if (!connectToDatabase()) {
        std::cerr << "Failed to connect to database" << std::endl;
        throw std::runtime_error("Database connection failed");
    }
}

// Destructor
AuthService::~AuthService() {
    closeDatabase();
}

// Connect to database
bool AuthService::connectToDatabase() {
    std::lock_guard<std::mutex> lock(dbMutex_);
    
    if (dbConn_ != nullptr) {
        return true; // Already connected
    }
    
    dbConn_ = mysql_init(nullptr);
    if (dbConn_ == nullptr) {
        std::cerr << "mysql_init() failed" << std::endl;
        return false;
    }
    
    // Connect to database
    // TODO: These should be loaded from configuration
    if (mysql_real_connect(dbConn_, "localhost", "vegan_user", "vegan_password", 
                          "vegan_messenger", 0, nullptr, 0) == nullptr) {
        std::cerr << "mysql_real_connect() failed: " << mysql_error(dbConn_) << std::endl;
        mysql_close(dbConn_);
        dbConn_ = nullptr;
        return false;
    }
    
    return true;
}

// Close database connection
void AuthService::closeDatabase() {
    std::lock_guard<std::mutex> lock(dbMutex_);
    
    if (dbConn_ != nullptr) {
        mysql_close(dbConn_);
        dbConn_ = nullptr;
    }
}

// Execute SQL query with mutex lock
bool AuthService::executeQuery(const std::string& query) {
    std::lock_guard<std::mutex> lock(dbMutex_);
    
    if (dbConn_ == nullptr && !connectToDatabase()) {
        return false;
    }
    
    if (mysql_query(dbConn_, query.c_str()) != 0) {
        std::cerr << "Query execution failed: " << mysql_error(dbConn_) << std::endl;
        std::cerr << "Query: " << query << std::endl;
        return false;
    }
    
    return true;
}

// Execute SQL query and get result with mutex lock
MYSQL_RES* AuthService::executeQueryWithResult(const std::string& query) {
    std::lock_guard<std::mutex> lock(dbMutex_);
    
    if (dbConn_ == nullptr && !connectToDatabase()) {
        return nullptr;
    }
    
    if (mysql_query(dbConn_, query.c_str()) != 0) {
        std::cerr << "Query execution failed: " << mysql_error(dbConn_) << std::endl;
        std::cerr << "Query: " << query << std::endl;
        return nullptr;
    }
    
    MYSQL_RES* result = mysql_store_result(dbConn_);
    if (result == nullptr) {
        std::cerr << "mysql_store_result() failed: " << mysql_error(dbConn_) << std::endl;
        return nullptr;
    }
    
    return result;
}

// Convert MySQL row to User struct
User AuthService::rowToUser(MYSQL_ROW row) {
    User user;
    
    if (row == nullptr) {
        return user;
    }
    
    // Assuming the query returns columns in this order:
    // id, username, email, full_name, password_hash, created_at, updated_at, is_active, is_verified
    user.id = row[0] ? std::stoi(row[0]) : 0;
    user.username = row[1] ? row[1] : "";
    user.email = row[2] ? row[2] : "";
    user.fullName = row[3] ? row[3] : "";
    user.passwordHash = row[4] ? row[4] : "";
    user.createdAt = row[5] ? row[5] : "";
    user.updatedAt = row[6] ? row[6] : "";
    user.isActive = row[7] ? (std::string(row[7]) == "1") : false;
    user.isVerified = row[8] ? (std::string(row[8]) == "1") : false;
    
    return user;
}

// Authentication methods

AuthResult AuthService::login(const std::string& usernameOrEmail, const std::string& password, 
                              const std::string& ipAddress, const std::string& userAgent) {
    AuthResult result;
    result.success = false;
    
    try {
        // Check if input is email or username
        User user;
        if (usernameOrEmail.find('@') != std::string::npos) {
            user = getUserByEmail(usernameOrEmail);
        } else {
            user = getUserByUsername(usernameOrEmail);
        }
        
        // Verify password
        if (!verifyPassword(password, user.passwordHash)) {
            throw InvalidCredentialsException("Invalid credentials provided");
        }
        
        // Check if user is active
        if (!user.isActive) {
            throw AuthException("Account is deactivated");
        }
        
        // Generate tokens
        result.token = generateToken(user);
        result.refreshToken = generateRefreshToken(user.id);
        result.user = user;
        result.success = true;
        
        // Create and store session
        Session session;
        session.userId = user.id;
        session.ipAddress = ipAddress;
        session.userAgent = userAgent;
        session.createdAt = std::time(nullptr);
        session.expiresAt = session.createdAt + config_.tokenLifetime;
        session.lastActivity = session.createdAt;
        storeSession(session);
        
        // Update last active timestamp
        user.lastActive = std::time(nullptr);
        updateUser(user);
        
        result.message = "Login successful";
    } catch (const UserNotFoundException& e) {
        result.message = "Invalid credentials provided";
    } catch (const InvalidCredentialsException& e) {
        result.message = e.what();
    } catch (const AuthException& e) {
        result.message = e.what();
    } catch (const std::exception& e) {
        result.message = "An error occurred during login";
        std::cerr << "Login error: " << e.what() << std::endl;
    }
    
    return result;
}

AuthResult AuthService::registerUser(const User& user, const std::string& password, 
                                     const std::string& ipAddress, const std::string& userAgent) {
    AuthResult result;
    result.success = false;
    
    try {
        // Check if username or email already exists
        try {
            getUserByEmail(user.email);
            result.message = "Email already in use";
            return result;
        } catch (const UserNotFoundException&) {
            // This is good, user not found with this email
        }
        
        try {
            getUserByUsername(user.username);
            result.message = "Username already taken";
            return result;
        } catch (const UserNotFoundException&) {
            // This is good, user not found with this username
        }
        
        // Create new user
        User newUser = user;
        newUser.passwordHash = hashPassword(password);
        newUser.joinedDate = std::time(nullptr);
        newUser.lastActive = newUser.joinedDate;
        newUser.isActive = true;
        newUser.isVerified = false;
        
        // TODO: Insert user into database
        // For now, we'll simulate the user creation
        newUser.id = 1; // In a real implementation, this would be the ID returned from the database
        
        // Generate tokens
        result.token = generateToken(newUser);
        result.refreshToken = generateRefreshToken(newUser.id);
        result.user = newUser;
        result.success = true;
        
        // Create and store session
        Session session;
        session.userId = newUser.id;
        session.ipAddress = ipAddress;
        session.userAgent = userAgent;
        session.createdAt = std::time(nullptr);
        session.expiresAt = session.createdAt + config_.tokenLifetime;
        session.lastActivity = session.createdAt;
        storeSession(session);
        
        result.message = "Registration successful";
    } catch (const std::exception& e) {
        result.message = "An error occurred during registration";
        std::cerr << "Registration error: " << e.what() << std::endl;
    }
    
    return result;
}

bool AuthService::logout(const std::string& token) {
    try {
        // Validate token
        if (!validateToken(token)) {
            return false;
        }
        
        // Extract session ID and invalidate it
        // In a real implementation, you would extract the session ID from the token
        // and use that to invalidate the specific session
        
        return true;
    } catch (const std::exception& e) {
        std::cerr << "Logout error: " << e.what() << std::endl;
        return false;
    }
}

AuthResult AuthService::refreshToken(const std::string& refreshToken, 
                                     const std::string& ipAddress, const std::string& userAgent) {
    AuthResult result;
    result.success = false;
    
    try {
        // Validate refresh token
        // In a real implementation, you would validate the refresh token against the database
        
        // Get user from refresh token
        int userId = 1; // This would be extracted from the refresh token
        User user = getUserById(userId);
        
        // Generate new tokens
        result.token = generateToken(user);
        result.refreshToken = generateRefreshToken(user.id);
        result.user = user;
        result.success = true;
        
        // Create and store new session
        Session session;
        session.userId = user.id;
        session.ipAddress = ipAddress;
        session.userAgent = userAgent;
        session.createdAt = std::time(nullptr);
        session.expiresAt = session.createdAt + config_.tokenLifetime;
        session.lastActivity = session.createdAt;
        storeSession(session);
        
        // Update last active timestamp
        user.lastActive = std::time(nullptr);
        updateUser(user);
        
        result.message = "Token refreshed successfully";
    } catch (const UserNotFoundException& e) {
        result.message = "Invalid refresh token";
    } catch (const InvalidTokenException& e) {
        result.message = e.what();
    } catch (const TokenExpiredException& e) {
        result.message = e.what();
    } catch (const std::exception& e) {
        result.message = "An error occurred during token refresh";
        std::cerr << "Token refresh error: " << e.what() << std::endl;
    }
    
    return result;
}

// Token validation methods

bool AuthService::validateToken(const std::string& token) {
    try {
        // Decode and validate JWT token
        auto decoded = jwt::decode(token);
        
        // Verify token signature
        auto verifier = jwt::verify()
            .allow_algorithm(jwt::algorithm::hs256{config_.jwtSecret});
        
        verifier.verify(decoded);
        
        // Check expiration time
        const auto exp = decoded.get_payload_claim("exp");
        if (exp.empty()) {
            throw InvalidTokenException("Token has no expiration claim");
        }
        
        const auto expValue = exp.as_int();
        const auto now = std::chrono::system_clock::now();
        const auto nowSeconds = std::chrono::duration_cast<std::chrono::seconds>(
            now.time_since_epoch()).count();
        
        if (nowSeconds > expValue) {
            throw TokenExpiredException("Token has expired");
        }
        
        return true;
    } catch (const jwt::token_verification_exception& e) {
        std::cerr << "Token validation error: " << e.what() << std::endl;
        return false;
    } catch (const std::exception& e) {
        std::cerr << "Token validation error: " << e.what() << std::endl;
        return false;
    }
}

User AuthService::getUserFromToken(const std::string& token) {
    try {
        if (!validateToken(token)) {
            throw InvalidTokenException("Invalid token");
        }
        
        return parseTokenPayload(token);
    } catch (const std::exception& e) {
        std::cerr << "Get user from token error: " << e.what() << std::endl;
        throw;
    }
}

// Helper methods

std::string AuthService::hashPassword(const std::string& password) {
    return BCrypt::generateHash(password, config_.bcryptCost);
}

bool AuthService::verifyPassword(const std::string& password, const std::string& hash) {
    return BCrypt::validatePassword(password, hash);
}

std::string AuthService::generateToken(const User& user) {
    // Generate JWT token
    auto now = std::chrono::system_clock::now();
    auto nowSeconds = std::chrono::duration_cast<std::chrono::seconds>(
        now.time_since_epoch()).count();
    
    jwt::jwt_object obj{jwt::params::algorithm("HS256"), jwt::params::secret(config_.jwtSecret)};
    
    obj.add_claim("sub", std::to_string(user.id))
       .add_claim("name", user.fullName)
       .add_claim("username", user.username)
       .add_claim("email", user.email)
       .add_claim("role", user.role)
       .add_claim("verified", user.isVerified)
       .add_claim("iat", nowSeconds)
       .add_claim("exp", nowSeconds + config_.tokenLifetime);
    
    return obj.signature();
}

std::string AuthService::generateRefreshToken(int userId) {
    // Generate a secure random refresh token
    std::random_device rd;
    std::mt19937 gen(rd());
    std::uniform_int_distribution<> dis(0, 255);
    
    std::stringstream ss;
    for (int i = 0; i < 32; ++i) {
        ss << std::hex << std::setw(2) << std::setfill('0') << dis(gen);
    }
    
    // In a real implementation, you would store this token in the database
    // associated with the user ID and an expiration time
    
    return ss.str();
}

bool AuthService::storeSession(const Session& session) {
    // In a real implementation, you would store the session in the database
    // For now, we'll just simulate success
    return true;
}

bool AuthService::removeSession(const std::string& sessionId) {
    // In a real implementation, you would remove the session from the database
    // For now, we'll just simulate success
    return true;
}

User AuthService::parseTokenPayload(const std::string& token) {
    auto decoded = jwt::decode(token);
    
    User user;
    user.id = std::stoi(decoded.get_payload_claim("sub").as_string());
    user.fullName = decoded.get_payload_claim("name").as_string();
    user.username = decoded.get_payload_claim("username").as_string();
    user.email = decoded.get_payload_claim("email").as_string();
    user.role = decoded.get_payload_claim("role").as_string();
    user.isVerified = decoded.get_payload_claim("verified").as_bool();
    
    return user;
}

// User management methods - placeholders for now
// In a real implementation, these would interact with a database

User AuthService::getUserById(int userId) {
    // Placeholder implementation
    if (userId != 1) {
        throw UserNotFoundException("User not found with ID: " + std::to_string(userId));
    }
    
    User user;
    user.id = 1;
    user.username = "johnsmith";
    user.email = "john@example.com";
    user.fullName = "John Smith";
    user.passwordHash = "$2a$12$K8oU5wHKPOmvJ8zAY2jWGeHiSZ2piRsM4xBOZQGkRtz9RzJ.a.uZS"; // "password"
    user.role = "user";
    user.isVerified = true;
    user.isActive = true;
    user.joinedDate = std::time(nullptr) - 86400; // 1 day ago
    user.lastActive = std::time(nullptr);
    
    return user;
}

User AuthService::getUserByEmail(const std::string& email) {
    // Placeholder implementation
    if (email != "john@example.com") {
        throw UserNotFoundException("User not found with email: " + email);
    }
    
    return getUserById(1);
}

User AuthService::getUserByUsername(const std::string& username) {
    // Placeholder implementation
    if (username != "johnsmith") {
        throw UserNotFoundException("User not found with username: " + username);
    }
    
    return getUserById(1);
}

bool AuthService::updateUser(const User& user) {
    // Placeholder implementation
    // In a real implementation, you would update the user in the database
    return true;
}

bool AuthService::deactivateUser(int userId) {
    // Placeholder implementation
    // In a real implementation, you would deactivate the user in the database
    return true;
}

bool AuthService::activateUser(int userId) {
    // Placeholder implementation
    // In a real implementation, you would activate the user in the database
    return true;
}

bool AuthService::verifyUser(int userId) {
    // Placeholder implementation
    // In a real implementation, you would verify the user in the database
    return true;
}

// Password management methods - placeholders for now

bool AuthService::requestPasswordReset(const std::string& email) {
    // Placeholder implementation
    // In a real implementation, you would:
    // 1. Validate email exists
    // 2. Generate a password reset token
    // 3. Store the token and its expiration time
    // 4. Send an email with a reset link
    return true;
}

bool AuthService::resetPassword(const std::string& token, const std::string& newPassword) {
    // Placeholder implementation
    // In a real implementation, you would:
    // 1. Validate the token exists and hasn't expired
    // 2. Get the user ID associated with the token
    // 3. Hash the new password
    // 4. Update the user's password
    // 5. Invalidate the token
    return true;
}

bool AuthService::changePassword(int userId, const std::string& currentPassword, const std::string& newPassword) {
    // Placeholder implementation
    // In a real implementation, you would:
    // 1. Get the user by ID
    // 2. Verify the current password
    // 3. Hash the new password
    // 4. Update the user's password
    return true;
}

// Session management methods - placeholders for now

std::vector<Session> AuthService::getUserSessions(int userId) {
    // Placeholder implementation
    // In a real implementation, you would fetch all active sessions for the user from the database
    return std::vector<Session>();
}

bool AuthService::invalidateAllSessions(int userId) {
    // Placeholder implementation
    // In a real implementation, you would invalidate all sessions for the user in the database
    return true;
}

bool AuthService::invalidateSession(const std::string& sessionId) {
    // Placeholder implementation
    // In a real implementation, you would invalidate the specific session in the database
    return true;
}

} // namespace Auth
} // namespace VeganMessenger 