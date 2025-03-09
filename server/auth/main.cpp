#include "AuthService.h"
#include <iostream>
#include <fstream>
#include <string>
#include <thread>
#include <chrono>
#include <csignal>
#include <cstring>
#include <cstdlib>
#include <unistd.h>
#include <sys/types.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <arpa/inet.h>

// Global variables
bool running = true;
VeganMessenger::Auth::AuthService* authService = nullptr;

// Signal handler for graceful shutdown
void signalHandler(int signum) {
    std::cout << "Interrupt signal (" << signum << ") received. Shutting down..." << std::endl;
    running = false;
}

// Load configuration
VeganMessenger::Auth::AuthServiceConfig loadConfig(const std::string& configPath) {
    VeganMessenger::Auth::AuthServiceConfig config;
    
    // Default values
    config.tokenLifetime = 86400;         // 24 hours in seconds
    config.refreshTokenLifetime = 2592000; // 30 days in seconds
    config.bcryptCost = 12;
    config.jwtSecret = "change_this_to_a_secure_random_string";
    
    std::ifstream configFile(configPath);
    if (configFile.is_open()) {
        std::string line;
        while (std::getline(configFile, line)) {
            if (line.empty() || line[0] == '#') {
                continue; // Skip empty lines and comments
            }
            
            auto delimPos = line.find('=');
            if (delimPos != std::string::npos) {
                std::string key = line.substr(0, delimPos);
                std::string value = line.substr(delimPos + 1);
                
                // Trim whitespace
                key.erase(0, key.find_first_not_of(" \t"));
                key.erase(key.find_last_not_of(" \t") + 1);
                value.erase(0, value.find_first_not_of(" \t"));
                value.erase(value.find_last_not_of(" \t") + 1);
                
                if (key == "token_lifetime") {
                    config.tokenLifetime = std::stoi(value);
                } else if (key == "refresh_token_lifetime") {
                    config.refreshTokenLifetime = std::stoi(value);
                } else if (key == "bcrypt_cost") {
                    config.bcryptCost = std::stoi(value);
                } else if (key == "jwt_secret") {
                    config.jwtSecret = value;
                }
            }
        }
        configFile.close();
    } else {
        std::cerr << "Warning: Could not open config file. Using default values." << std::endl;
    }
    
    // Check for environment variables (override file config)
    const char* envTokenLifetime = std::getenv("AUTH_TOKEN_LIFETIME");
    if (envTokenLifetime) {
        config.tokenLifetime = std::stoi(envTokenLifetime);
    }
    
    const char* envRefreshTokenLifetime = std::getenv("AUTH_REFRESH_TOKEN_LIFETIME");
    if (envRefreshTokenLifetime) {
        config.refreshTokenLifetime = std::stoi(envRefreshTokenLifetime);
    }
    
    const char* envBcryptCost = std::getenv("AUTH_BCRYPT_COST");
    if (envBcryptCost) {
        config.bcryptCost = std::stoi(envBcryptCost);
    }
    
    const char* envJwtSecret = std::getenv("AUTH_JWT_SECRET");
    if (envJwtSecret) {
        config.jwtSecret = envJwtSecret;
    }
    
    return config;
}

// Simple HTTP server to handle authentication requests
class AuthServer {
public:
    AuthServer(VeganMessenger::Auth::AuthService* service, int port = 8081)
        : authService_(service), port_(port), sockfd_(-1) {}
    
    ~AuthServer() {
        stop();
    }
    
    bool start() {
        // Create socket
        sockfd_ = socket(AF_INET, SOCK_STREAM, 0);
        if (sockfd_ < 0) {
            std::cerr << "Error opening socket: " << strerror(errno) << std::endl;
            return false;
        }
        
        // Set socket options
        int opt = 1;
        if (setsockopt(sockfd_, SOL_SOCKET, SO_REUSEADDR, &opt, sizeof(opt)) < 0) {
            std::cerr << "Error setting socket options: " << strerror(errno) << std::endl;
            close(sockfd_);
            return false;
        }
        
        // Bind socket
        struct sockaddr_in servAddr;
        memset(&servAddr, 0, sizeof(servAddr));
        servAddr.sin_family = AF_INET;
        servAddr.sin_addr.s_addr = INADDR_ANY;
        servAddr.sin_port = htons(port_);
        
        if (bind(sockfd_, (struct sockaddr*)&servAddr, sizeof(servAddr)) < 0) {
            std::cerr << "Error binding socket: " << strerror(errno) << std::endl;
            close(sockfd_);
            return false;
        }
        
        // Listen
        if (listen(sockfd_, 5) < 0) {
            std::cerr << "Error listening on socket: " << strerror(errno) << std::endl;
            close(sockfd_);
            return false;
        }
        
        std::cout << "Auth server started on port " << port_ << std::endl;
        
        // Start worker threads
        const int numThreads = 4;
        for (int i = 0; i < numThreads; ++i) {
            threads_.push_back(std::thread(&AuthServer::workerThread, this));
        }
        
        return true;
    }
    
    void stop() {
        if (sockfd_ >= 0) {
            close(sockfd_);
            sockfd_ = -1;
        }
        
        for (auto& thread : threads_) {
            if (thread.joinable()) {
                thread.join();
            }
        }
        
        threads_.clear();
    }
    
private:
    void workerThread() {
        while (running) {
            // Accept connection
            struct sockaddr_in clientAddr;
            socklen_t clientLen = sizeof(clientAddr);
            int clientSock = accept(sockfd_, (struct sockaddr*)&clientAddr, &clientLen);
            
            if (clientSock < 0) {
                if (errno == EINTR && !running) {
                    break; // Interrupted and shutting down
                }
                std::cerr << "Error accepting connection: " << strerror(errno) << std::endl;
                continue;
            }
            
            // Handle connection
            handleClient(clientSock);
            
            // Close connection
            close(clientSock);
        }
    }
    
    void handleClient(int clientSock) {
        char buffer[4096];
        ssize_t bytesRead = read(clientSock, buffer, sizeof(buffer) - 1);
        
        if (bytesRead <= 0) {
            return;
        }
        
        buffer[bytesRead] = '\0';
        
        // Very simple HTTP request parsing
        std::string request(buffer);
        std::string method, path, version;
        std::string body;
        
        // Parse request line
        size_t requestLineEnd = request.find("\r\n");
        if (requestLineEnd != std::string::npos) {
            std::string requestLine = request.substr(0, requestLineEnd);
            size_t methodEnd = requestLine.find(' ');
            if (methodEnd != std::string::npos) {
                method = requestLine.substr(0, methodEnd);
                size_t pathEnd = requestLine.find(' ', methodEnd + 1);
                if (pathEnd != std::string::npos) {
                    path = requestLine.substr(methodEnd + 1, pathEnd - methodEnd - 1);
                    version = requestLine.substr(pathEnd + 1);
                }
            }
        }
        
        // Get request body if it's POST or PUT
        if (method == "POST" || method == "PUT") {
            size_t bodyStart = request.find("\r\n\r\n");
            if (bodyStart != std::string::npos) {
                body = request.substr(bodyStart + 4);
            }
        }
        
        // Handle different endpoints
        std::string response;
        if (path == "/auth/login" && method == "POST") {
            response = handleLogin(body);
        } else if (path == "/auth/register" && method == "POST") {
            response = handleRegister(body);
        } else if (path == "/auth/refresh" && method == "POST") {
            response = handleRefresh(body);
        } else if (path == "/auth/validate" && method == "POST") {
            response = handleValidate(body);
        } else if (path == "/auth/logout" && method == "POST") {
            response = handleLogout(body);
        } else if (path == "/health" && method == "GET") {
            response = handleHealth();
        } else {
            response = "HTTP/1.1 404 Not Found\r\n"
                      "Content-Type: application/json\r\n"
                      "Content-Length: 27\r\n"
                      "\r\n"
                      "{\"error\":\"Endpoint not found\"}";
        }
        
        // Send response
        write(clientSock, response.c_str(), response.size());
    }
    
    // Endpoint handlers
    std::string handleLogin(const std::string& body) {
        // TODO: Parse JSON body and call authService_->login()
        return "HTTP/1.1 200 OK\r\n"
               "Content-Type: application/json\r\n"
               "Content-Length: 30\r\n"
               "\r\n"
               "{\"message\":\"Login endpoint\"}";
    }
    
    std::string handleRegister(const std::string& body) {
        // TODO: Parse JSON body and call authService_->registerUser()
        return "HTTP/1.1 200 OK\r\n"
               "Content-Type: application/json\r\n"
               "Content-Length: 33\r\n"
               "\r\n"
               "{\"message\":\"Register endpoint\"}";
    }
    
    std::string handleRefresh(const std::string& body) {
        // TODO: Parse JSON body and call authService_->refreshToken()
        return "HTTP/1.1 200 OK\r\n"
               "Content-Type: application/json\r\n"
               "Content-Length: 32\r\n"
               "\r\n"
               "{\"message\":\"Refresh endpoint\"}";
    }
    
    std::string handleValidate(const std::string& body) {
        // TODO: Parse JSON body and call authService_->validateToken()
        return "HTTP/1.1 200 OK\r\n"
               "Content-Type: application/json\r\n"
               "Content-Length: 33\r\n"
               "\r\n"
               "{\"message\":\"Validate endpoint\"}";
    }
    
    std::string handleLogout(const std::string& body) {
        // TODO: Parse JSON body and call authService_->logout()
        return "HTTP/1.1 200 OK\r\n"
               "Content-Type: application/json\r\n"
               "Content-Length: 31\r\n"
               "\r\n"
               "{\"message\":\"Logout endpoint\"}";
    }
    
    std::string handleHealth() {
        return "HTTP/1.1 200 OK\r\n"
               "Content-Type: application/json\r\n"
               "Content-Length: 15\r\n"
               "\r\n"
               "{\"status\":\"ok\"}";
    }
    
private:
    VeganMessenger::Auth::AuthService* authService_;
    int port_;
    int sockfd_;
    std::vector<std::thread> threads_;
};

int main(int argc, char* argv[]) {
    // Register signal handler
    signal(SIGINT, signalHandler);
    signal(SIGTERM, signalHandler);
    
    // Default config path
    std::string configPath = "../config/auth.conf";
    
    // Check for config path argument
    for (int i = 1; i < argc; ++i) {
        if (strcmp(argv[i], "--config") == 0 && i + 1 < argc) {
            configPath = argv[i + 1];
            ++i;
        }
    }
    
    // Load configuration
    auto config = loadConfig(configPath);
    
    // Create auth service
    authService = new VeganMessenger::Auth::AuthService(config);
    
    // Create and start server
    AuthServer server(authService);
    if (!server.start()) {
        std::cerr << "Failed to start auth server." << std::endl;
        delete authService;
        return 1;
    }
    
    // Run until interrupted
    while (running) {
        std::this_thread::sleep_for(std::chrono::seconds(1));
    }
    
    // Clean up
    server.stop();
    delete authService;
    
    std::cout << "Auth service shut down successfully." << std::endl;
    return 0;
} 