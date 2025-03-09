#pragma once

#include <string>
#include <vector>
#include <map>
#include <mutex>
#include <memory>
#include <mysql/mysql.h>
#include <unordered_map>
#include <set>

// Forward declaration for ClientHandler
class ClientHandler;

namespace VeganMessenger {
namespace Messaging {

// Configuration for the messaging service
struct MessagingServiceConfig {
    int maxMessageSize;        // Maximum message size in bytes
    int maxAttachmentSize;     // Maximum attachment size in bytes
    std::string authServiceUrl; // URL of the authentication service
};

// Message structure
struct Message {
    int id;
    int senderId;
    int recipientId;
    int channelId;
    std::string content;
    std::string createdAt;
    bool isRead;
    bool isDeleted;
    std::vector<std::string> attachments;
};

// Channel structure
struct Channel {
    int id;
    std::string name;
    std::string description;
    int creatorId;
    std::string createdAt;
    bool isPrivate;
    std::set<int> members;
};

// Class for handling messaging operations
class MessagingService {
public:
    // Constructor
    MessagingService(const MessagingServiceConfig& config);
    
    // Destructor
    ~MessagingService();
    
    // Client registration
    bool registerClient(int userId, ClientHandler* client);
    
    // Client disconnection
    bool unregisterClient(int userId);
    
    // Send direct message
    bool sendDirectMessage(int senderId, int recipientId, const std::string& content);
    
    // Send message with attachments
    bool sendDirectMessageWithAttachments(int senderId, int recipientId, 
                                         const std::string& content,
                                         const std::vector<std::string>& attachments);
    
    // Get direct messages between users
    std::vector<Message> getDirectMessages(int userId1, int userId2, int limit = 50, int offset = 0);
    
    // Mark message as read
    bool markMessageAsRead(int messageId, int userId);
    
    // Delete message
    bool deleteMessage(int messageId, int userId);
    
    // Create channel
    int createChannel(int creatorId, const std::string& name, const std::string& description, bool isPrivate);
    
    // Join channel
    bool joinChannel(int userId, int channelId);
    
    // Leave channel
    bool leaveChannel(int userId, int channelId);
    
    // Send message to channel
    bool sendChannelMessage(int senderId, int channelId, const std::string& content);
    
    // Send message with attachments to channel
    bool sendChannelMessageWithAttachments(int senderId, int channelId, 
                                          const std::string& content,
                                          const std::vector<std::string>& attachments);
    
    // Get channel messages
    std::vector<Message> getChannelMessages(int channelId, int limit = 50, int offset = 0);
    
    // Get user channels
    std::vector<Channel> getUserChannels(int userId);
    
    // Get channel by ID
    Channel getChannelById(int channelId);
    
    // Get channel members
    std::vector<int> getChannelMembers(int channelId);
    
    // Update channel
    bool updateChannel(int channelId, int userId, const std::map<std::string, std::string>& fields);
    
    // Delete channel
    bool deleteChannel(int channelId, int userId);
    
private:
    // Database connection
    MYSQL* dbConn_;
    std::mutex dbMutex_;
    
    // Configuration
    MessagingServiceConfig config_;
    
    // Connected clients
    std::unordered_map<int, ClientHandler*> clients_;
    std::mutex clientsMutex_;
    
    // Private methods
    
    // Connect to database
    bool connectToDatabase();
    
    // Close database connection
    void closeDatabase();
    
    // Execute SQL query with mutex lock
    bool executeQuery(const std::string& query);
    
    // Execute SQL query and get result with mutex lock
    MYSQL_RES* executeQueryWithResult(const std::string& query);
    
    // Convert MySQL row to Message struct
    Message rowToMessage(MYSQL_ROW row);
    
    // Convert MySQL row to Channel struct
    Channel rowToChannel(MYSQL_ROW row);
    
    // Validate token with auth service
    bool validateToken(const std::string& token, int& outUserId);
    
    // Check if user is member of channel
    bool isChannelMember(int userId, int channelId);
    
    // Check if user is channel admin
    bool isChannelAdmin(int userId, int channelId);
    
    // Deliver message to online recipient
    void deliverMessage(const Message& message);
    
    // Store message in database
    int storeMessage(int senderId, int recipientId, int channelId, 
                    const std::string& content, 
                    const std::vector<std::string>& attachments);
    
    // Store attachment
    bool storeAttachment(int messageId, const std::string& attachmentPath);
    
    // Get message by ID
    Message getMessageById(int messageId);
};

} // namespace Messaging
} // namespace VeganMessenger 