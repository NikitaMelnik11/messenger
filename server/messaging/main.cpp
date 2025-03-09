#include "MessagingService.h"
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
#include <vector>
#include <mutex>
#include <condition_variable>
#include <queue>

// Global variables
bool running = true;
VeganMessenger::Messaging::MessagingService* messagingService = nullptr;

// Signal handler for graceful shutdown
void signalHandler(int signum) {
    std::cout << "Interrupt signal (" << signum << ") received. Shutting down..." << std::endl;
    running = false;
}

// Load configuration
VeganMessenger::Messaging::MessagingServiceConfig loadConfig(const std::string& configPath) {
    VeganMessenger::Messaging::MessagingServiceConfig config;
    
    // Default values
    config.maxMessageSize = 4096;
    config.maxAttachmentSize = 10 * 1024 * 1024; // 10 MB
    config.authServiceUrl = "http://localhost:8081";
    
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
                
                if (key == "max_message_size") {
                    config.maxMessageSize = std::stoi(value);
                } else if (key == "max_attachment_size") {
                    config.maxAttachmentSize = std::stoi(value);
                } else if (key == "auth_service_url") {
                    config.authServiceUrl = value;
                }
            }
        }
        configFile.close();
    } else {
        std::cerr << "Warning: Could not open config file. Using default values." << std::endl;
    }
    
    // Check for environment variables (override file config)
    const char* envMaxMessageSize = std::getenv("MESSAGING_MAX_MESSAGE_SIZE");
    if (envMaxMessageSize) {
        config.maxMessageSize = std::stoi(envMaxMessageSize);
    }
    
    const char* envMaxAttachmentSize = std::getenv("MESSAGING_MAX_ATTACHMENT_SIZE");
    if (envMaxAttachmentSize) {
        config.maxAttachmentSize = std::stoi(envMaxAttachmentSize);
    }
    
    const char* envAuthServiceUrl = std::getenv("MESSAGING_AUTH_SERVICE_URL");
    if (envAuthServiceUrl) {
        config.authServiceUrl = envAuthServiceUrl;
    }
    
    return config;
}

// Thread-safe message queue for client connections
class MessageQueue {
public:
    void push(const std::string& message) {
        std::lock_guard<std::mutex> lock(mutex_);
        queue_.push(message);
        cv_.notify_one();
    }
    
    bool pop(std::string& message, int timeoutMs = -1) {
        std::unique_lock<std::mutex> lock(mutex_);
        
        if (timeoutMs < 0) {
            // Wait indefinitely
            cv_.wait(lock, [this] { return !queue_.empty(); });
        } else if (timeoutMs > 0) {
            // Wait with timeout
            auto result = cv_.wait_for(lock, std::chrono::milliseconds(timeoutMs),
                                      [this] { return !queue_.empty(); });
            if (!result) {
                return false; // Timeout
            }
        } else if (queue_.empty()) {
            return false; // No wait and queue is empty
        }
        
        message = queue_.front();
        queue_.pop();
        return true;
    }
    
    bool empty() const {
        std::lock_guard<std::mutex> lock(mutex_);
        return queue_.empty();
    }
    
private:
    std::queue<std::string> queue_;
    mutable std::mutex mutex_;
    std::condition_variable cv_;
};

// Client connection handler
class ClientHandler {
public:
    ClientHandler(int clientSocket, VeganMessenger::Messaging::MessagingService* service)
        : clientSocket_(clientSocket), service_(service), authenticated_(false), userId_(0) {}
    
    ~ClientHandler() {
        if (clientSocket_ >= 0) {
            close(clientSocket_);
        }
    }
    
    void start() {
        thread_ = std::thread(&ClientHandler::handleClient, this);
    }
    
    void join() {
        if (thread_.joinable()) {
            thread_.join();
        }
    }
    
    void sendMessage(const std::string& message) {
        outgoingQueue_.push(message);
    }
    
    int getUserId() const {
        return userId_;
    }
    
private:
    void handleClient() {
        // Start reader and writer threads
        std::thread readerThread(&ClientHandler::readerLoop, this);
        std::thread writerThread(&ClientHandler::writerLoop, this);
        
        // Wait for threads to finish
        readerThread.join();
        writerThread.join();
    }
    
    void readerLoop() {
        char buffer[4096];
        ssize_t bytesRead;
        
        while (running) {
            bytesRead = read(clientSocket_, buffer, sizeof(buffer) - 1);
            
            if (bytesRead <= 0) {
                break; // Connection closed or error
            }
            
            buffer[bytesRead] = '\0';
            
            // Process received message
            std::string message(buffer, bytesRead);
            processMessage(message);
        }
    }
    
    void writerLoop() {
        std::string message;
        
        while (running) {
            if (outgoingQueue_.pop(message, 100)) {
                // Send message to client
                write(clientSocket_, message.c_str(), message.size());
            }
        }
    }
    
    void processMessage(const std::string& message) {
        // TODO: Implement proper message parsing and handling
        
        // Simple protocol for demonstration:
        // AUTH <token> - authenticate with token
        // SEND <recipient_id> <message> - send message to recipient
        // JOIN <channel_id> - join a channel
        // LEAVE <channel_id> - leave a channel
        // CHANNEL <channel_id> <message> - send message to channel
        
        if (message.substr(0, 5) == "AUTH ") {
            std::string token = message.substr(5);
            authenticateUser(token);
        } else if (!authenticated_) {
            sendMessage("ERROR Not authenticated");
        } else if (message.substr(0, 5) == "SEND ") {
            size_t spacePos = message.find(' ', 5);
            if (spacePos != std::string::npos) {
                int recipientId = std::stoi(message.substr(5, spacePos - 5));
                std::string content = message.substr(spacePos + 1);
                sendDirectMessage(recipientId, content);
            }
        } else if (message.substr(0, 5) == "JOIN ") {
            int channelId = std::stoi(message.substr(5));
            joinChannel(channelId);
        } else if (message.substr(0, 6) == "LEAVE ") {
            int channelId = std::stoi(message.substr(6));
            leaveChannel(channelId);
        } else if (message.substr(0, 8) == "CHANNEL ") {
            size_t spacePos = message.find(' ', 8);
            if (spacePos != std::string::npos) {
                int channelId = std::stoi(message.substr(8, spacePos - 8));
                std::string content = message.substr(spacePos + 1);
                sendChannelMessage(channelId, content);
            }
        } else {
            sendMessage("ERROR Unknown command");
        }
    }
    
    void authenticateUser(const std::string& token) {
        // TODO: Implement proper token validation with auth service
        
        // For demonstration, just accept any token and assign a random user ID
        authenticated_ = true;
        userId_ = rand() % 1000 + 1;
        
        sendMessage("OK Authenticated as user " + std::to_string(userId_));
        
        // Register with messaging service
        service_->registerClient(userId_, this);
    }
    
    void sendDirectMessage(int recipientId, const std::string& content) {
        if (service_->sendDirectMessage(userId_, recipientId, content)) {
            sendMessage("OK Message sent");
        } else {
            sendMessage("ERROR Failed to send message");
        }
    }
    
    void joinChannel(int channelId) {
        if (service_->joinChannel(userId_, channelId)) {
            sendMessage("OK Joined channel " + std::to_string(channelId));
        } else {
            sendMessage("ERROR Failed to join channel");
        }
    }
    
    void leaveChannel(int channelId) {
        if (service_->leaveChannel(userId_, channelId)) {
            sendMessage("OK Left channel " + std::to_string(channelId));
        } else {
            sendMessage("ERROR Failed to leave channel");
        }
    }
    
    void sendChannelMessage(int channelId, const std::string& content) {
        if (service_->sendChannelMessage(userId_, channelId, content)) {
            sendMessage("OK Channel message sent");
        } else {
            sendMessage("ERROR Failed to send channel message");
        }
    }
    
private:
    int clientSocket_;
    VeganMessenger::Messaging::MessagingService* service_;
    std::thread thread_;
    MessageQueue outgoingQueue_;
    bool authenticated_;
    int userId_;
};

// Simple messaging server
class MessagingServer {
public:
    MessagingServer(VeganMessenger::Messaging::MessagingService* service, int port = 8082)
        : service_(service), port_(port), sockfd_(-1) {}
    
    ~MessagingServer() {
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
        
        std::cout << "Messaging server started on port " << port_ << std::endl;
        
        // Start acceptor thread
        acceptorThread_ = std::thread(&MessagingServer::acceptLoop, this);
        
        return true;
    }
    
    void stop() {
        if (sockfd_ >= 0) {
            close(sockfd_);
            sockfd_ = -1;
        }
        
        if (acceptorThread_.joinable()) {
            acceptorThread_.join();
        }
        
        // Clean up client handlers
        for (auto& client : clients_) {
            client->join();
            delete client;
        }
        
        clients_.clear();
    }
    
private:
    void acceptLoop() {
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
            
            // Create client handler
            ClientHandler* client = new ClientHandler(clientSock, service_);
            client->start();
            
            // Store client handler
            std::lock_guard<std::mutex> lock(clientsMutex_);
            clients_.push_back(client);
            
            // Clean up disconnected clients
            auto it = clients_.begin();
            while (it != clients_.end()) {
                if (*it != client) {
                    (*it)->join();
                    delete *it;
                    it = clients_.erase(it);
                } else {
                    ++it;
                }
            }
        }
    }
    
private:
    VeganMessenger::Messaging::MessagingService* service_;
    int port_;
    int sockfd_;
    std::thread acceptorThread_;
    std::vector<ClientHandler*> clients_;
    std::mutex clientsMutex_;
};

int main(int argc, char* argv[]) {
    // Register signal handler
    signal(SIGINT, signalHandler);
    signal(SIGTERM, signalHandler);
    
    // Default config path
    std::string configPath = "../config/messaging.conf";
    
    // Check for config path argument
    for (int i = 1; i < argc; ++i) {
        if (strcmp(argv[i], "--config") == 0 && i + 1 < argc) {
            configPath = argv[i + 1];
            ++i;
        }
    }
    
    // Load configuration
    auto config = loadConfig(configPath);
    
    // Create messaging service
    messagingService = new VeganMessenger::Messaging::MessagingService(config);
    
    // Create and start server
    MessagingServer server(messagingService);
    if (!server.start()) {
        std::cerr << "Failed to start messaging server." << std::endl;
        delete messagingService;
        return 1;
    }
    
    // Run until interrupted
    while (running) {
        std::this_thread::sleep_for(std::chrono::seconds(1));
    }
    
    // Clean up
    server.stop();
    delete messagingService;
    
    std::cout << "Messaging service shut down successfully." << std::endl;
    return 0;
} 