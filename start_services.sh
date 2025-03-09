#!/bin/bash
# Vegan Messenger Social Network - Service Starter

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${GREEN}Starting Vegan Messenger services...${NC}"

# Check if database exists, if not create it
echo -e "${YELLOW}Checking database...${NC}"
if mysql -u root -e "USE vegan_messenger" 2>/dev/null; then
    echo -e "${GREEN}Database exists.${NC}"
else
    echo -e "${YELLOW}Creating database...${NC}"
    mysql -u root -e "CREATE DATABASE vegan_messenger CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    echo -e "${GREEN}Database created.${NC}"
fi

# Import schema if tables don't exist
if ! mysql -u root -e "SELECT 1 FROM users LIMIT 1" vegan_messenger 2>/dev/null; then
    echo -e "${YELLOW}Importing database schema...${NC}"
    mysql -u root vegan_messenger < database/schema.sql
    echo -e "${GREEN}Schema imported.${NC}"
fi

# Check for config file, create if it doesn't exist
if [ ! -f "config/env.php" ]; then
    echo -e "${YELLOW}Creating configuration file...${NC}"
    cp config/env.example.php config/env.php
    echo -e "${GREEN}Configuration file created.${NC}"
fi

# Start Redis if installed
echo -e "${YELLOW}Starting Redis...${NC}"
if command -v redis-server >/dev/null 2>&1; then
    redis-server --daemonize yes
    echo -e "${GREEN}Redis started.${NC}"
else
    echo -e "${RED}Redis not found. Please install Redis for optimal performance.${NC}"
fi

# Compile C++ services
echo -e "${YELLOW}Compiling C++ services...${NC}"
if [ ! -d "server/build" ]; then
    mkdir -p server/build
fi

cd server/build
if command -v cmake >/dev/null 2>&1; then
    cmake ..
    make
    echo -e "${GREEN}C++ services compiled.${NC}"
    
    # Start C++ services in the background
    echo -e "${YELLOW}Starting C++ services...${NC}"
    ./auth_service &
    ./messaging_service &
    echo -e "${GREEN}C++ services started.${NC}"
else
    echo -e "${RED}CMake not found. Please install CMake to compile C++ services.${NC}"
fi

cd ../..

# Start HHVM server
echo -e "${YELLOW}Starting HHVM web server...${NC}"
if command -v hhvm >/dev/null 2>&1; then
    hhvm -m server -p 8080 -d hhvm.server.source_root=web/public &
    echo -e "${GREEN}HHVM web server started on port 8080.${NC}"
else
    echo -e "${RED}HHVM not found. Please install HHVM to run the web interface.${NC}"
    echo -e "${YELLOW}Falling back to PHP built-in server...${NC}"
    if command -v php >/dev/null 2>&1; then
        php -S 0.0.0.0:8080 -t web/public &
        echo -e "${GREEN}PHP web server started on port 8080.${NC}"
    else
        echo -e "${RED}PHP not found. Please install PHP to run the web interface.${NC}"
    fi
fi

echo -e "${GREEN}All services started. Vegan Messenger is now available at http://localhost:8080${NC}"
echo -e "${YELLOW}Press Ctrl+C to stop all services.${NC}"

# Wait for Ctrl+C
trap "echo -e '${YELLOW}Stopping services...${NC}'; pkill -P $$; exit" INT
wait 