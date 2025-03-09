cmake_minimum_required(VERSION 3.10)
project(VeganMessenger VERSION 1.0.0 LANGUAGES CXX)

# Set C++ standard
set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)
set(CMAKE_CXX_EXTENSIONS OFF)

# Set output directories
set(CMAKE_ARCHIVE_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/lib)
set(CMAKE_LIBRARY_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR}/lib)
set(CMAKE_RUNTIME_OUTPUT_DIRECTORY ${CMAKE_BINARY_DIR})

# Add include directories
include_directories(${CMAKE_SOURCE_DIR})

# Find required packages
find_package(OpenSSL REQUIRED)
find_package(Threads REQUIRED)

# Add external dependencies if not found in system
include(ExternalProject)

# JWT-CPP (header-only)
ExternalProject_Add(
    jwt-cpp
    GIT_REPOSITORY https://github.com/Thalhammer/jwt-cpp.git
    GIT_TAG v0.4.0
    CONFIGURE_COMMAND ""
    BUILD_COMMAND ""
    INSTALL_COMMAND ${CMAKE_COMMAND} -E copy_directory <SOURCE_DIR>/include ${CMAKE_BINARY_DIR}/include
)
include_directories(${CMAKE_BINARY_DIR}/include)

# BCrypt
ExternalProject_Add(
    bcrypt
    GIT_REPOSITORY https://github.com/trusch/libbcrypt.git
    CMAKE_ARGS -DCMAKE_INSTALL_PREFIX=${CMAKE_BINARY_DIR}
    BUILD_BYPRODUCTS ${CMAKE_BINARY_DIR}/lib/libbcrypt${CMAKE_SHARED_LIBRARY_SUFFIX}
                    ${CMAKE_BINARY_DIR}/lib/libbcrypt${CMAKE_STATIC_LIBRARY_SUFFIX}
)
include_directories(${CMAKE_BINARY_DIR}/include)
link_directories(${CMAKE_BINARY_DIR}/lib)

# Find MySQL
find_path(MYSQL_INCLUDE_DIR mysql.h
    /usr/include/mysql
    /usr/local/include/mysql
    /opt/mysql/include/mysql
)
find_library(MYSQL_LIBRARY NAMES mysqlclient
    PATHS
    /usr/lib
    /usr/lib/mysql
    /usr/local/lib
    /usr/local/lib/mysql
    /opt/mysql/lib
)
if(MYSQL_INCLUDE_DIR AND MYSQL_LIBRARY)
    set(MYSQL_FOUND TRUE)
endif()

if(NOT MYSQL_FOUND)
    message(FATAL_ERROR "MySQL not found. Please install MySQL development libraries.")
endif()

include_directories(${MYSQL_INCLUDE_DIR})

# AuthService
add_executable(auth_service
    auth/AuthService.cpp
    auth/main.cpp
)

add_dependencies(auth_service jwt-cpp bcrypt)

target_link_libraries(auth_service
    ${MYSQL_LIBRARY}
    bcrypt
    OpenSSL::SSL
    OpenSSL::Crypto
    Threads::Threads
)

# MessagingService
add_executable(messaging_service
    messaging/MessageService.cpp
    messaging/main.cpp
)

add_dependencies(messaging_service jwt-cpp bcrypt)

target_link_libraries(messaging_service
    ${MYSQL_LIBRARY}
    bcrypt
    OpenSSL::SSL
    OpenSSL::Crypto
    Threads::Threads
)

# Install targets
install(TARGETS auth_service messaging_service
    RUNTIME DESTINATION bin
)

# Print configuration summary
message(STATUS "CMAKE_SOURCE_DIR: ${CMAKE_SOURCE_DIR}")
message(STATUS "CMAKE_BINARY_DIR: ${CMAKE_BINARY_DIR}")
message(STATUS "MYSQL_INCLUDE_DIR: ${MYSQL_INCLUDE_DIR}")
message(STATUS "MYSQL_LIBRARY: ${MYSQL_LIBRARY}")
message(STATUS "OPENSSL_INCLUDE_DIR: ${OPENSSL_INCLUDE_DIR}")
message(STATUS "OPENSSL_LIBRARIES: ${OPENSSL_LIBRARIES}")

message(STATUS "Configuration complete. Run 'make' to build the project.") 