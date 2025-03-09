#ifndef MESSAGE_SERVICE_H
#define MESSAGE_SERVICE_H

#include <string>
#include <vector>
#include <memory>
#include <unordered_map>
#include <ctime>
#include <functional>
#include <chrono>
#include <stdexcept>
#include <optional>
#include "../auth/AuthService.h"

namespace VeganMessenger {
namespace Messaging {

struct Message {
    int id;
    int senderId;
    int receiverId;
    std::string content;
    std::vector<std::string> mediaUrls;
    bool isRead;
    std::time_t createdAt;
};

struct Conversation {
    int id;
    std::vector<int> participantIds;
    std::string title;  // For group conversations
    std::string avatar; // For group conversations
    std::time_t createdAt;
    std::time_t updatedAt;
    Message lastMessage;
    bool isGroup;
    int unreadCount;
};

struct MessageSearchCriteria {
    std::optional<int> senderId;
    std::optional<int> receiverId;
    std::optional<int> conversationId;
    std::optional<std::string> keyword;
    std::optional<std::time_t> startDate;
    std::optional<std::time_t> endDate;
    std::optional<int> limit;
    std::optional<int> offset;
};

struct MessageStats {
    int totalMessages;
    int unreadMessages;
    int sentMessages;
    int receivedMessages;
    std::time_t lastMessageTime;
};

// Callback type for real-time message handling
using MessageCallback = std::function<void(const Message&)>;

class MessageException : public std::runtime_error {
public:
    explicit MessageException(const std::string& message) : std::runtime_error(message) {}
};

class MessageNotFoundException : public MessageException {
public:
    explicit MessageNotFoundException(const std::string& message) : MessageException(message) {}
};

class ConversationNotFoundException : public MessageException {
public:
    explicit ConversationNotFoundException(const std::string& message) : MessageException(message) {}
};

class MessageServiceConfig {
public:
    std::string dbHost;
    int dbPort;
    std::string dbUser;
    std::string dbPassword;
    std::string dbName;
    std::string redisHost;
    int redisPort;
    std::string wsHost;
    int wsPort;
};

class IMessageService {
public:
    virtual ~IMessageService() = default;
    
    // Messaging methods
    virtual Message sendMessage(int senderId, int receiverId, const std::string& content, const std::vector<std::string>& mediaUrls = {}) = 0;
    virtual Message sendGroupMessage(int senderId, int conversationId, const std::string& content, const std::vector<std::string>& mediaUrls = {}) = 0;
    virtual bool markAsRead(int messageId, int userId) = 0;
    virtual bool markConversationAsRead(int conversationId, int userId) = 0;
    virtual bool deleteMessage(int messageId, int userId) = 0;
    virtual bool editMessage(int messageId, int userId, const std::string& newContent) = 0;
    
    // Conversation methods
    virtual Conversation createConversation(const std::vector<int>& participantIds, const std::string& title = "", const std::string& avatar = "") = 0;
    virtual bool addUserToConversation(int conversationId, int userId) = 0;
    virtual bool removeUserFromConversation(int conversationId, int userId) = 0;
    virtual bool leaveConversation(int conversationId, int userId) = 0;
    virtual Conversation getConversation(int conversationId) = 0;
    virtual std::vector<Conversation> getUserConversations(int userId, int limit = 20, int offset = 0) = 0;
    virtual std::vector<Auth::User> getConversationParticipants(int conversationId) = 0;
    
    // Message retrieval methods
    virtual Message getMessage(int messageId) = 0;
    virtual std::vector<Message> getConversationMessages(int conversationId, int limit = 50, int offset = 0) = 0;
    virtual std::vector<Message> searchMessages(const MessageSearchCriteria& criteria) = 0;
    virtual MessageStats getUserMessageStats(int userId) = 0;
    
    // Real-time messaging methods
    virtual void subscribeToUserMessages(int userId, MessageCallback callback) = 0;
    virtual void unsubscribeFromUserMessages(int userId) = 0;
    virtual void subscribeToConversation(int conversationId, int userId, MessageCallback callback) = 0;
    virtual void unsubscribeFromConversation(int conversationId, int userId) = 0;
    
    // Media handling
    virtual std::string uploadMediaForMessage(int userId, const std::string& filePath, const std::string& mimeType) = 0;
    virtual bool deleteMessageMedia(int messageId, int userId, const std::string& mediaUrl) = 0;
};

class MessageService : public IMessageService {
public:
    explicit MessageService(const MessageServiceConfig& config, std::shared_ptr<Auth::IAuthService> authService);
    ~MessageService() override;
    
    // Messaging methods
    Message sendMessage(int senderId, int receiverId, const std::string& content, const std::vector<std::string>& mediaUrls = {}) override;
    Message sendGroupMessage(int senderId, int conversationId, const std::string& content, const std::vector<std::string>& mediaUrls = {}) override;
    bool markAsRead(int messageId, int userId) override;
    bool markConversationAsRead(int conversationId, int userId) override;
    bool deleteMessage(int messageId, int userId) override;
    bool editMessage(int messageId, int userId, const std::string& newContent) override;
    
    // Conversation methods
    Conversation createConversation(const std::vector<int>& participantIds, const std::string& title = "", const std::string& avatar = "") override;
    bool addUserToConversation(int conversationId, int userId) override;
    bool removeUserFromConversation(int conversationId, int userId) override;
    bool leaveConversation(int conversationId, int userId) override;
    Conversation getConversation(int conversationId) override;
    std::vector<Conversation> getUserConversations(int userId, int limit = 20, int offset = 0) override;
    std::vector<Auth::User> getConversationParticipants(int conversationId) override;
    
    // Message retrieval methods
    Message getMessage(int messageId) override;
    std::vector<Message> getConversationMessages(int conversationId, int limit = 50, int offset = 0) override;
    std::vector<Message> searchMessages(const MessageSearchCriteria& criteria) override;
    MessageStats getUserMessageStats(int userId) override;
    
    // Real-time messaging methods
    void subscribeToUserMessages(int userId, MessageCallback callback) override;
    void unsubscribeFromUserMessages(int userId) override;
    void subscribeToConversation(int conversationId, int userId, MessageCallback callback) override;
    void unsubscribeFromConversation(int conversationId, int userId) override;
    
    // Media handling
    std::string uploadMediaForMessage(int userId, const std::string& filePath, const std::string& mimeType) override;
    bool deleteMessageMedia(int messageId, int userId, const std::string& mediaUrl) override;

private:
    MessageServiceConfig config_;
    std::shared_ptr<Auth::IAuthService> authService_;
    
    // WebSocket clients for real-time messaging
    std::unordered_map<int, MessageCallback> userSubscriptions_;
    std::unordered_map<std::string, MessageCallback> conversationSubscriptions_; // Key: conversationId_userId
    
    // Private helper methods
    int getOrCreateDirectConversation(int user1Id, int user2Id);
    bool isUserInConversation(int conversationId, int userId);
    bool notifyMessageParticipants(const Message& message, int conversationId);
    std::string serializeMessage(const Message& message);
    Message deserializeMessage(const std::string& json);
};

} // namespace Messaging
} // namespace VeganMessenger

#endif // MESSAGE_SERVICE_H 