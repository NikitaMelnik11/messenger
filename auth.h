#ifndef AUTH_H
#define AUTH_H

#include <string>
#include <vector>
#include <map>
#include <mutex>
#include <memory>
// Заменяем проблемный заголовочный файл на более общий подход
// #include <mysql/mysql.h>
#include "database/db_connector.h" // Предполагаем, что у вас есть собственный класс для работы с БД

namespace VeganMessenger {
namespace Auth {

// Configuration for the auth service
struct AuthServiceConfig {
    int tokenLifetime;      // Access token lifetime in seconds
    int refreshTokenLifetime; // Refresh token lifetime in seconds
    int bcryptCost;         // BCrypt cost factor for password hashing
    std::string jwtSecret;  // Secret key for JWT signing
};

// User data structure
struct User {
    std::string id;
    std::string username;
    std::string email;
    std::string passwordHash;
    std::string fullName;
    std::string phoneNumber;
    std::string profilePicture;
    bool isVerified;
    std::string createdAt;
    std::string updatedAt;
};

// Authentication service class
class AuthService {
public:
    AuthService(const AuthServiceConfig& config, std::shared_ptr<Database::DBConnector> dbConnector);
    ~AuthService();

    // User registration
    bool registerUser(const std::string& username, const std::string& email, 
                     const std::string& password, const std::string& fullName);
    
    // User login
    std::string login(const std::string& username, const std::string& password);
    
    // Validate token
    bool validateToken(const std::string& token);
    
    // Refresh token
    std::string refreshToken(const std::string& refreshToken);
    
    // Get user by ID
    User getUserById(const std::string& userId);
    
    // Get user by username
    User getUserByUsername(const std::string& username);

private:
    AuthServiceConfig config_;
    std::shared_ptr<Database::DBConnector> dbConnector_;
    std::mutex mutex_;
    
    // Helper methods
    std::string hashPassword(const std::string& password);
    bool verifyPassword(const std::string& password, const std::string& hash);
    std::string generateToken(const std::string& userId);
    std::string generateRefreshToken(const std::string& userId);
};

} // namespace Auth
} // namespace VeganMessenger

#endif // AUTH_H 