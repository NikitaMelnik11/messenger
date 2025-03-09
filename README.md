# Vegan Messenger Social Network

## Overview
Vegan Messenger is a social networking platform built with C++ backend services and PHP (HHVM) for the web interface. This platform offers a comprehensive set of features for comfortable social interaction, content sharing, and community building focused on vegan lifestyle.

## Key Features
- User profiles and authentication system
- News feed with personalized content
- Real-time messaging and chat
- Friend/connection management
- Photo and media sharing
- Groups and communities
- Events creation and management
- Notifications system
- Search functionality
- Mobile-responsive design

## Technology Stack
- **Backend Services**: C++ for high-performance core services
- **Web Interface**: PHP with HHVM (HipHop Virtual Machine)
- **Database**: MySQL for data storage
- **Caching**: Redis for performance optimization
- **Real-time Communication**: WebSockets
- **Frontend**: HTML5, CSS3, JavaScript (with React for interactive components)

## Directory Structure
```
/
├── server/           # C++ backend services
│   ├── auth/         # Authentication services
│   ├── messaging/    # Messaging services
│   ├── feed/         # Feed generation and algorithms
│   ├── media/        # Media processing services
│   └── api/          # API endpoints
├── web/              # PHP (HHVM) web interface
│   ├── public/       # Publicly accessible files
│   ├── app/          # Application logic
│   ├── views/        # UI templates
│   ├── controllers/  # Request handlers
│   └── models/       # Data models
├── database/         # Database schema and migrations
├── config/           # Configuration files
├── tests/            # Test suites
└── docs/             # Documentation
```

## Setup Instructions

### Prerequisites
- C++ compiler (GCC/Clang) version 11+
- HHVM 4.153+
- MySQL 8.0+
- Redis 6.0+
- Node.js 16+ (for frontend assets)

### Installation
1. Clone this repository
2. Set up the database: `mysql -u root -p < database/schema.sql`
3. Configure environment: `cp config/env.example.php config/env.php` and edit accordingly
4. Compile C++ services: `cd server && make`
5. Install PHP dependencies: `cd web && composer install`
6. Install frontend dependencies: `cd web/public && npm install`
7. Start the services: `./start_services.sh`

### Development
- Backend services: `cd server && make dev`
- Web interface: `cd web && hhvm -m server -p 8080`

## License
MIT License
 
