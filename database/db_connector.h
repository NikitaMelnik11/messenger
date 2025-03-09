#ifndef DB_CONNECTOR_H
#define DB_CONNECTOR_H

#include <string>
#include <memory>
#include <stdexcept>

namespace VeganMessenger {
namespace Database {

class MySQLException : public std::runtime_error {
public:
    MySQLException(const std::string& message) : std::runtime_error(message) {}
};

class DBConnector {
public:
    DBConnector(const std::string& host, const std::string& user, 
                const std::string& password, const std::string& database, 
                int port = 3306);
    ~DBConnector();

    // Prevent copying
    DBConnector(const DBConnector&) = delete;
    DBConnector& operator=(const DBConnector&) = delete;

    // Execute a query that doesn't return results
    void execute(const std::string& query);

    // Execute a query and get results
    // In a real implementation, this would return a result set
    // For simplicity, we're just declaring the interface
    void query(const std::string& query);

    // Escape a string to prevent SQL injection
    std::string escapeString(const std::string& str);

    // Check if connected
    bool isConnected() const;

private:
    // Implementation details would go here
    // In a real implementation, this would hold the MySQL connection
    // For now, we're just declaring the interface
    class Impl;
    std::unique_ptr<Impl> pImpl;
};

} // namespace Database
} // namespace VeganMessenger

#endif // DB_CONNECTOR_H 