#include "MessagingService.h"
#include <iostream>
#include <sstream>
#include <iomanip>
#include <random>
#include <chrono>
#include <ctime>
#include <algorithm>
#include <curl/curl.h>
#include <json/json.h>

// Forward declaration for ClientHandler
class ClientHandler;

namespace VeganMessenger {
namespace Messaging {

// Utility function for CURL responses
size_t WriteCallback(void* contents, size_t size, size_t nmemb, std::string* s) {
    size_t newLength = size * nmemb;
    s->append((char*)contents, newLength);
    return newLength;
}

// Constructor
MessagingService::MessagingService(const MessagingServiceConfig& config) 
    : config_(config), dbConn_(nullptr) {
    // Initialize database connection
    if (!connectToDatabase()) {
        std::cerr << "Failed to connect to database" << std::endl;
        throw std::runtime_error("Database connection failed");
    }
    
    // Initialize CURL for API calls
    curl_global_init(CURL_GLOBAL_ALL);
}

// Destructor
MessagingService::~MessagingService() {
    closeDatabase();
    
    // Clean up CURL
    curl_global_cleanup();
}

// Connect to database
bool MessagingService::connectToDatabase() {
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
void MessagingService::closeDatabase() {
    std::lock_guard<std::mutex> lock(dbMutex_);
    
    if (dbConn_ != nullptr) {
        mysql_close(dbConn_);
        dbConn_ = nullptr;
    }
}

// Execute SQL query with mutex lock
bool MessagingService::executeQuery(const std::string& query) {
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
MYSQL_RES* MessagingService::executeQueryWithResult(const std::string& query) {
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

// Convert MySQL row to Message struct
Message MessagingService::rowToMessage(MYSQL_ROW row) {
    Message message;
    
    if (row == nullptr) {
        return message;
    }
    
    // Assuming the query returns columns in this order:
    // id, sender_id, recipient_id, channel_id, content, created_at, is_read, is_deleted
    message.id = row[0] ? std::stoi(row[0]) : 0;
    message.senderId = row[1] ? std::stoi(row[1]) : 0;
    message.recipientId = row[2] ? std::stoi(row[2]) : 0;
    message.channelId = row[3] ? std::stoi(row[3]) : 0;
    message.content = row[4] ? row[4] : "";
    message.createdAt = row[5] ? row[5] : "";
    message.isRead = row[6] ? (std::string(row[6]) == "1") : false;
    message.isDeleted = row[7] ? (std::string(row[7]) == "1") : false;
    
    // Get attachments for this message
    std::stringstream ss;
    ss << "SELECT file_path FROM message_attachments WHERE message_id = " << message.id;
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW attachRow;
        while ((attachRow = mysql_fetch_row(result)) != nullptr) {
            if (attachRow[0]) {
                message.attachments.push_back(attachRow[0]);
            }
        }
        mysql_free_result(result);
    }
    
    return message;
}

// Convert MySQL row to Channel struct
Channel MessagingService::rowToChannel(MYSQL_ROW row) {
    Channel channel;
    
    if (row == nullptr) {
        return channel;
    }
    
    // Assuming the query returns columns in this order:
    // id, name, description, creator_id, created_at, is_private
    channel.id = row[0] ? std::stoi(row[0]) : 0;
    channel.name = row[1] ? row[1] : "";
    channel.description = row[2] ? row[2] : "";
    channel.creatorId = row[3] ? std::stoi(row[3]) : 0;
    channel.createdAt = row[4] ? row[4] : "";
    channel.isPrivate = row[5] ? (std::string(row[5]) == "1") : false;
    
    // Get members for this channel
    std::stringstream ss;
    ss << "SELECT user_id FROM channel_members WHERE channel_id = " << channel.id;
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW memberRow;
        while ((memberRow = mysql_fetch_row(result)) != nullptr) {
            if (memberRow[0]) {
                channel.members.insert(std::stoi(memberRow[0]));
            }
        }
        mysql_free_result(result);
    }
    
    return channel;
}

// Validate token with auth service
bool MessagingService::validateToken(const std::string& token, int& outUserId) {
    CURL* curl = curl_easy_init();
    if (!curl) {
        std::cerr << "Failed to initialize CURL" << std::endl;
        return false;
    }
    
    // Set up request to auth service
    std::string url = config_.authServiceUrl + "/auth/validate";
    std::string postData = "token=" + token;
    std::string response;
    
    curl_easy_setopt(curl, CURLOPT_URL, url.c_str());
    curl_easy_setopt(curl, CURLOPT_POSTFIELDS, postData.c_str());
    curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, WriteCallback);
    curl_easy_setopt(curl, CURLOPT_WRITEDATA, &response);
    
    // Perform request
    CURLcode res = curl_easy_perform(curl);
    curl_easy_cleanup(curl);
    
    if (res != CURLE_OK) {
        std::cerr << "CURL request failed: " << curl_easy_strerror(res) << std::endl;
        return false;
    }
    
    // Parse response (simple JSON parsing)
    Json::Value root;
    Json::Reader reader;
    bool success = reader.parse(response, root);
    
    if (!success) {
        std::cerr << "Failed to parse JSON response: " << reader.getFormattedErrorMessages() << std::endl;
        return false;
    }
    
    if (root["success"].asBool()) {
        outUserId = root["user"]["id"].asInt();
        return true;
    }
    
    return false;
}

// Client registration
bool MessagingService::registerClient(int userId, ClientHandler* client) {
    std::lock_guard<std::mutex> lock(clientsMutex_);
    clients_[userId] = client;
    return true;
}

// Client disconnection
bool MessagingService::unregisterClient(int userId) {
    std::lock_guard<std::mutex> lock(clientsMutex_);
    return clients_.erase(userId) > 0;
}

// Send direct message
bool MessagingService::sendDirectMessage(int senderId, int recipientId, const std::string& content) {
    // Check content length
    if (content.length() > config_.maxMessageSize) {
        std::cerr << "Message too large: " << content.length() << " bytes (max: " << config_.maxMessageSize << " bytes)" << std::endl;
        return false;
    }
    
    // Store message in database
    int messageId = storeMessage(senderId, recipientId, 0, content, {});
    if (messageId <= 0) {
        return false;
    }
    
    // Get the stored message
    Message message = getMessageById(messageId);
    
    // Deliver message to recipient if online
    deliverMessage(message);
    
    return true;
}

// Send message with attachments
bool MessagingService::sendDirectMessageWithAttachments(int senderId, int recipientId, 
                                                      const std::string& content,
                                                      const std::vector<std::string>& attachments) {
    // Check content length
    if (content.length() > config_.maxMessageSize) {
        std::cerr << "Message too large: " << content.length() << " bytes (max: " << config_.maxMessageSize << " bytes)" << std::endl;
        return false;
    }
    
    // Check total attachment size (this is simplified; actual implementation would check file sizes)
    if (attachments.size() > 10) {
        std::cerr << "Too many attachments: " << attachments.size() << " (max: 10)" << std::endl;
        return false;
    }
    
    // Store message in database
    int messageId = storeMessage(senderId, recipientId, 0, content, attachments);
    if (messageId <= 0) {
        return false;
    }
    
    // Get the stored message
    Message message = getMessageById(messageId);
    
    // Deliver message to recipient if online
    deliverMessage(message);
    
    return true;
}

// Get direct messages between users
std::vector<Message> MessagingService::getDirectMessages(int userId1, int userId2, int limit, int offset) {
    std::vector<Message> messages;
    
    std::stringstream ss;
    ss << "SELECT id, sender_id, recipient_id, channel_id, content, created_at, is_read, is_deleted "
       << "FROM messages "
       << "WHERE channel_id = 0 AND is_deleted = 0 AND "
       << "((sender_id = " << userId1 << " AND recipient_id = " << userId2 << ") "
       << "OR (sender_id = " << userId2 << " AND recipient_id = " << userId1 << ")) "
       << "ORDER BY created_at DESC "
       << "LIMIT " << limit << " OFFSET " << offset;
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW row;
        while ((row = mysql_fetch_row(result)) != nullptr) {
            messages.push_back(rowToMessage(row));
        }
        mysql_free_result(result);
    }
    
    // Sort messages by creation time (oldest first)
    std::sort(messages.begin(), messages.end(), [](const Message& a, const Message& b) {
        return a.createdAt < b.createdAt;
    });
    
    return messages;
}

// Mark message as read
bool MessagingService::markMessageAsRead(int messageId, int userId) {
    // Check if message exists and belongs to this user
    std::stringstream ssCheck;
    ssCheck << "SELECT id FROM messages "
            << "WHERE id = " << messageId << " AND recipient_id = " << userId;
    
    MYSQL_RES* result = executeQueryWithResult(ssCheck.str());
    if (result == nullptr || mysql_num_rows(result) == 0) {
        if (result) mysql_free_result(result);
        return false;
    }
    
    mysql_free_result(result);
    
    // Update message
    std::stringstream ssUpdate;
    ssUpdate << "UPDATE messages SET is_read = 1 WHERE id = " << messageId;
    
    return executeQuery(ssUpdate.str());
}

// Delete message
bool MessagingService::deleteMessage(int messageId, int userId) {
    // Check if message exists and belongs to this user
    std::stringstream ssCheck;
    ssCheck << "SELECT id FROM messages "
            << "WHERE id = " << messageId << " AND (sender_id = " << userId << " OR recipient_id = " << userId << ")";
    
    MYSQL_RES* result = executeQueryWithResult(ssCheck.str());
    if (result == nullptr || mysql_num_rows(result) == 0) {
        if (result) mysql_free_result(result);
        return false;
    }
    
    mysql_free_result(result);
    
    // Update message (soft delete)
    std::stringstream ssUpdate;
    ssUpdate << "UPDATE messages SET is_deleted = 1 WHERE id = " << messageId;
    
    return executeQuery(ssUpdate.str());
}

// Create channel
int MessagingService::createChannel(int creatorId, const std::string& name, const std::string& description, bool isPrivate) {
    // Get current time
    auto now = std::chrono::system_clock::now();
    auto nowTimeT = std::chrono::system_clock::to_time_t(now);
    std::stringstream timeStr;
    timeStr << std::put_time(std::localtime(&nowTimeT), "%Y-%m-%d %H:%M:%S");
    
    // Insert channel
    std::stringstream ssInsert;
    ssInsert << "INSERT INTO channels (name, description, creator_id, created_at, is_private) VALUES ("
             << "'" << name << "', "
             << "'" << description << "', "
             << creatorId << ", "
             << "'" << timeStr.str() << "', "
             << (isPrivate ? "1" : "0") << ")";
    
    if (!executeQuery(ssInsert.str())) {
        return 0;
    }
    
    // Get the new channel ID
    int channelId = 0;
    
    {
        std::lock_guard<std::mutex> lock(dbMutex_);
        channelId = mysql_insert_id(dbConn_);
    }
    
    // Add creator as a member
    std::stringstream ssMember;
    ssMember << "INSERT INTO channel_members (channel_id, user_id, is_admin, joined_at) VALUES ("
             << channelId << ", "
             << creatorId << ", 1, '"
             << timeStr.str() << "')";
    
    if (!executeQuery(ssMember.str())) {
        // If adding the creator as a member fails, delete the channel
        std::stringstream ssDelete;
        ssDelete << "DELETE FROM channels WHERE id = " << channelId;
        executeQuery(ssDelete.str());
        return 0;
    }
    
    return channelId;
}

// Join channel
bool MessagingService::joinChannel(int userId, int channelId) {
    // Check if channel exists
    Channel channel = getChannelById(channelId);
    if (channel.id == 0) {
        return false;
    }
    
    // Check if user is already a member
    if (isChannelMember(userId, channelId)) {
        return true; // Already a member, return success
    }
    
    // Check if channel is private
    if (channel.isPrivate) {
        // For private channels, need admin approval
        // In a real implementation, this would create an invitation or request
        return false;
    }
    
    // Get current time
    auto now = std::chrono::system_clock::now();
    auto nowTimeT = std::chrono::system_clock::to_time_t(now);
    std::stringstream timeStr;
    timeStr << std::put_time(std::localtime(&nowTimeT), "%Y-%m-%d %H:%M:%S");
    
    // Add user as a member
    std::stringstream ssMember;
    ssMember << "INSERT INTO channel_members (channel_id, user_id, is_admin, joined_at) VALUES ("
             << channelId << ", "
             << userId << ", 0, '"
             << timeStr.str() << "')";
    
    return executeQuery(ssMember.str());
}

// Leave channel
bool MessagingService::leaveChannel(int userId, int channelId) {
    // Check if user is a member
    if (!isChannelMember(userId, channelId)) {
        return false;
    }
    
    // Check if user is the creator (last admin)
    bool isLastAdmin = false;
    
    {
        std::stringstream ssCheck;
        ssCheck << "SELECT COUNT(*) FROM channel_members "
                << "WHERE channel_id = " << channelId << " AND is_admin = 1";
        
        MYSQL_RES* result = executeQueryWithResult(ssCheck.str());
        if (result != nullptr) {
            MYSQL_ROW row = mysql_fetch_row(result);
            if (row && row[0]) {
                int adminCount = std::stoi(row[0]);
                if (adminCount == 1 && isChannelAdmin(userId, channelId)) {
                    isLastAdmin = true;
                }
            }
            mysql_free_result(result);
        }
    }
    
    if (isLastAdmin) {
        // If user is the last admin, delete the channel
        std::stringstream ssDelete;
        ssDelete << "DELETE FROM channels WHERE id = " << channelId;
        
        if (!executeQuery(ssDelete.str())) {
            return false;
        }
        
        // Delete all members
        std::stringstream ssMembers;
        ssMembers << "DELETE FROM channel_members WHERE channel_id = " << channelId;
        
        if (!executeQuery(ssMembers.str())) {
            return false;
        }
        
        // Mark all messages as deleted
        std::stringstream ssMessages;
        ssMessages << "UPDATE messages SET is_deleted = 1 WHERE channel_id = " << channelId;
        
        return executeQuery(ssMessages.str());
    } else {
        // Just remove the user from the channel
        std::stringstream ssLeave;
        ssLeave << "DELETE FROM channel_members "
                << "WHERE channel_id = " << channelId << " AND user_id = " << userId;
        
        return executeQuery(ssLeave.str());
    }
}

// Send message to channel
bool MessagingService::sendChannelMessage(int senderId, int channelId, const std::string& content) {
    // Check content length
    if (content.length() > config_.maxMessageSize) {
        std::cerr << "Message too large: " << content.length() << " bytes (max: " << config_.maxMessageSize << " bytes)" << std::endl;
        return false;
    }
    
    // Check if user is a member of the channel
    if (!isChannelMember(senderId, channelId)) {
        return false;
    }
    
    // Store message in database
    int messageId = storeMessage(senderId, 0, channelId, content, {});
    if (messageId <= 0) {
        return false;
    }
    
    // Get the stored message
    Message message = getMessageById(messageId);
    
    // Deliver message to all channel members who are online
    deliverMessage(message);
    
    return true;
}

// Send message with attachments to channel
bool MessagingService::sendChannelMessageWithAttachments(int senderId, int channelId, 
                                                       const std::string& content,
                                                       const std::vector<std::string>& attachments) {
    // Check content length
    if (content.length() > config_.maxMessageSize) {
        std::cerr << "Message too large: " << content.length() << " bytes (max: " << config_.maxMessageSize << " bytes)" << std::endl;
        return false;
    }
    
    // Check total attachment size
    if (attachments.size() > 10) {
        std::cerr << "Too many attachments: " << attachments.size() << " (max: 10)" << std::endl;
        return false;
    }
    
    // Check if user is a member of the channel
    if (!isChannelMember(senderId, channelId)) {
        return false;
    }
    
    // Store message in database
    int messageId = storeMessage(senderId, 0, channelId, content, attachments);
    if (messageId <= 0) {
        return false;
    }
    
    // Get the stored message
    Message message = getMessageById(messageId);
    
    // Deliver message to all channel members who are online
    deliverMessage(message);
    
    return true;
}

// Get channel messages
std::vector<Message> MessagingService::getChannelMessages(int channelId, int limit, int offset) {
    std::vector<Message> messages;
    
    std::stringstream ss;
    ss << "SELECT id, sender_id, recipient_id, channel_id, content, created_at, is_read, is_deleted "
       << "FROM messages "
       << "WHERE channel_id = " << channelId << " AND is_deleted = 0 "
       << "ORDER BY created_at DESC "
       << "LIMIT " << limit << " OFFSET " << offset;
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW row;
        while ((row = mysql_fetch_row(result)) != nullptr) {
            messages.push_back(rowToMessage(row));
        }
        mysql_free_result(result);
    }
    
    // Sort messages by creation time (oldest first)
    std::sort(messages.begin(), messages.end(), [](const Message& a, const Message& b) {
        return a.createdAt < b.createdAt;
    });
    
    return messages;
}

// Get user channels
std::vector<Channel> MessagingService::getUserChannels(int userId) {
    std::vector<Channel> channels;
    
    std::stringstream ss;
    ss << "SELECT c.id, c.name, c.description, c.creator_id, c.created_at, c.is_private "
       << "FROM channels c "
       << "JOIN channel_members cm ON c.id = cm.channel_id "
       << "WHERE cm.user_id = " << userId << " "
       << "ORDER BY c.name";
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW row;
        while ((row = mysql_fetch_row(result)) != nullptr) {
            channels.push_back(rowToChannel(row));
        }
        mysql_free_result(result);
    }
    
    return channels;
}

// Get channel by ID
Channel MessagingService::getChannelById(int channelId) {
    std::stringstream ss;
    ss << "SELECT id, name, description, creator_id, created_at, is_private "
       << "FROM channels "
       << "WHERE id = " << channelId << " "
       << "LIMIT 1";
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW row = mysql_fetch_row(result);
        Channel channel = rowToChannel(row);
        mysql_free_result(result);
        return channel;
    }
    
    return Channel();
}

// Get channel members
std::vector<int> MessagingService::getChannelMembers(int channelId) {
    std::vector<int> members;
    
    std::stringstream ss;
    ss << "SELECT user_id "
       << "FROM channel_members "
       << "WHERE channel_id = " << channelId;
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW row;
        while ((row = mysql_fetch_row(result)) != nullptr) {
            if (row[0]) {
                members.push_back(std::stoi(row[0]));
            }
        }
        mysql_free_result(result);
    }
    
    return members;
}

// Update channel
bool MessagingService::updateChannel(int channelId, int userId, const std::map<std::string, std::string>& fields) {
    // Check if user is an admin of the channel
    if (!isChannelAdmin(userId, channelId)) {
        return false;
    }
    
    if (fields.empty()) {
        return true;
    }
    
    std::stringstream ss;
    ss << "UPDATE channels SET ";
    
    bool first = true;
    for (const auto& field : fields) {
        if (!first) {
            ss << ", ";
        }
        
        // Only allow certain fields to be updated
        if (field.first == "name" || field.first == "description" || field.first == "is_private") {
            if (field.first == "is_private") {
                ss << field.first << " = " << (field.second == "true" || field.second == "1" ? "1" : "0");
            } else {
                ss << field.first << " = '" << field.second << "'";
            }
            first = false;
        }
    }
    
    if (first) {
        // No valid fields to update
        return true;
    }
    
    ss << " WHERE id = " << channelId;
    
    return executeQuery(ss.str());
}

// Delete channel
bool MessagingService::deleteChannel(int channelId, int userId) {
    // Check if user is an admin of the channel
    if (!isChannelAdmin(userId, channelId)) {
        return false;
    }
    
    // Delete channel
    std::stringstream ssChannel;
    ssChannel << "DELETE FROM channels WHERE id = " << channelId;
    
    if (!executeQuery(ssChannel.str())) {
        return false;
    }
    
    // Delete all members
    std::stringstream ssMembers;
    ssMembers << "DELETE FROM channel_members WHERE channel_id = " << channelId;
    
    if (!executeQuery(ssMembers.str())) {
        return false;
    }
    
    // Mark all messages as deleted
    std::stringstream ssMessages;
    ssMessages << "UPDATE messages SET is_deleted = 1 WHERE channel_id = " << channelId;
    
    return executeQuery(ssMessages.str());
}

// Check if user is member of channel
bool MessagingService::isChannelMember(int userId, int channelId) {
    std::stringstream ss;
    ss << "SELECT 1 FROM channel_members "
       << "WHERE channel_id = " << channelId << " AND user_id = " << userId << " "
       << "LIMIT 1";
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    bool isMember = false;
    
    if (result != nullptr) {
        isMember = (mysql_num_rows(result) > 0);
        mysql_free_result(result);
    }
    
    return isMember;
}

// Check if user is channel admin
bool MessagingService::isChannelAdmin(int userId, int channelId) {
    std::stringstream ss;
    ss << "SELECT 1 FROM channel_members "
       << "WHERE channel_id = " << channelId << " AND user_id = " << userId << " AND is_admin = 1 "
       << "LIMIT 1";
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    bool isAdmin = false;
    
    if (result != nullptr) {
        isAdmin = (mysql_num_rows(result) > 0);
        mysql_free_result(result);
    }
    
    return isAdmin;
}

// Deliver message to online recipient
void MessagingService::deliverMessage(const Message& message) {
    // If it's a direct message
    if (message.channelId == 0 && message.recipientId > 0) {
        std::lock_guard<std::mutex> lock(clientsMutex_);
        auto it = clients_.find(message.recipientId);
        if (it != clients_.end() && it->second != nullptr) {
            // Recipient is online, deliver the message
            Json::Value root;
            root["type"] = "direct_message";
            root["message_id"] = message.id;
            root["sender_id"] = message.senderId;
            root["content"] = message.content;
            root["created_at"] = message.createdAt;
            
            Json::Value attachmentsJson(Json::arrayValue);
            for (const auto& attachment : message.attachments) {
                attachmentsJson.append(attachment);
            }
            root["attachments"] = attachmentsJson;
            
            Json::FastWriter writer;
            std::string jsonStr = writer.write(root);
            
            it->second->sendMessage(jsonStr);
        }
    }
    // If it's a channel message
    else if (message.channelId > 0) {
        // Get all channel members
        std::vector<int> members = getChannelMembers(message.channelId);
        
        // Prepare message in JSON format
        Json::Value root;
        root["type"] = "channel_message";
        root["message_id"] = message.id;
        root["sender_id"] = message.senderId;
        root["channel_id"] = message.channelId;
        root["content"] = message.content;
        root["created_at"] = message.createdAt;
        
        Json::Value attachmentsJson(Json::arrayValue);
        for (const auto& attachment : message.attachments) {
            attachmentsJson.append(attachment);
        }
        root["attachments"] = attachmentsJson;
        
        Json::FastWriter writer;
        std::string jsonStr = writer.write(root);
        
        // Deliver to all online members except the sender
        std::lock_guard<std::mutex> lock(clientsMutex_);
        for (int memberId : members) {
            if (memberId != message.senderId) {
                auto it = clients_.find(memberId);
                if (it != clients_.end() && it->second != nullptr) {
                    it->second->sendMessage(jsonStr);
                }
            }
        }
    }
}

// Store message in database
int MessagingService::storeMessage(int senderId, int recipientId, int channelId, 
                                 const std::string& content, 
                                 const std::vector<std::string>& attachments) {
    // Get current time
    auto now = std::chrono::system_clock::now();
    auto nowTimeT = std::chrono::system_clock::to_time_t(now);
    std::stringstream timeStr;
    timeStr << std::put_time(std::localtime(&nowTimeT), "%Y-%m-%d %H:%M:%S");
    
    // Insert message
    std::stringstream ss;
    ss << "INSERT INTO messages (sender_id, recipient_id, channel_id, content, created_at, is_read, is_deleted) VALUES ("
       << senderId << ", "
       << recipientId << ", "
       << channelId << ", '"
       << content << "', '"
       << timeStr.str() << "', 0, 0)";
    
    if (!executeQuery(ss.str())) {
        return 0;
    }
    
    // Get the new message ID
    int messageId = 0;
    
    {
        std::lock_guard<std::mutex> lock(dbMutex_);
        messageId = mysql_insert_id(dbConn_);
    }
    
    // Store attachments
    for (const auto& attachment : attachments) {
        if (!storeAttachment(messageId, attachment)) {
            std::cerr << "Failed to store attachment: " << attachment << std::endl;
        }
    }
    
    return messageId;
}

// Store attachment
bool MessagingService::storeAttachment(int messageId, const std::string& attachmentPath) {
    std::stringstream ss;
    ss << "INSERT INTO message_attachments (message_id, file_path) VALUES ("
       << messageId << ", '"
       << attachmentPath << "')";
    
    return executeQuery(ss.str());
}

// Get message by ID
Message MessagingService::getMessageById(int messageId) {
    std::stringstream ss;
    ss << "SELECT id, sender_id, recipient_id, channel_id, content, created_at, is_read, is_deleted "
       << "FROM messages "
       << "WHERE id = " << messageId << " "
       << "LIMIT 1";
    
    MYSQL_RES* result = executeQueryWithResult(ss.str());
    if (result != nullptr) {
        MYSQL_ROW row = mysql_fetch_row(result);
        Message message = rowToMessage(row);
        mysql_free_result(result);
        return message;
    }
    
    return Message();
}

} // namespace Messaging
} // namespace VeganMessenger 