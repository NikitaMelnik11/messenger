const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const path = require('path');
const fs = require('fs');
const bcrypt = require('bcrypt');
const compression = require('compression');

// Инициализация приложения
const app = express();
const server = http.createServer(app);
const io = socketIo(server);

// Middleware
app.use(express.json());
app.use(express.static(path.join(__dirname, 'web'), {
  maxAge: '1d', // Кэширование статических файлов на 1 день
  setHeaders: (res, path) => {
    // Устанавливаем правильные заголовки для разных типов файлов
    if (path.endsWith('.html')) {
      // HTML не кэшируем долго
      res.setHeader('Cache-Control', 'public, max-age=0');
    } else if (path.endsWith('.css') || path.endsWith('.js')) {
      // CSS и JS кэшируем на день
      res.setHeader('Cache-Control', 'public, max-age=86400');
    } else if (path.match(/\.(jpg|jpeg|png|gif|ico|svg|webp)$/)) {
      // Изображения кэшируем на неделю
      res.setHeader('Cache-Control', 'public, max-age=604800, immutable');
    } else if (path.endsWith('.json')) {
      // JSON файлы (например manifest.json) не кэшируем долго
      res.setHeader('Cache-Control', 'public, max-age=3600');
    }
    
    // Устанавливаем правильные типы контента
    if (path.endsWith('.webp')) {
      res.setHeader('Content-Type', 'image/webp');
    } else if (path.endsWith('.webmanifest')) {
      res.setHeader('Content-Type', 'application/manifest+json');
    }
    
    // Для безопасности
    res.setHeader('X-Content-Type-Options', 'nosniff');
  }
}));

// Поддержка gzip/brotli компрессии для уменьшения размера передаваемых данных
app.use(compression());

// Обслуживание service-worker.js для PWA
app.get('/service-worker.js', (req, res) => {
  res.sendFile(path.join(__dirname, 'web', 'service-worker.js'));
});

// Создаем директорию для иконок, если она не существует
const iconsDir = path.join(__dirname, 'web', 'icons');
if (!fs.existsSync(iconsDir)) {
  fs.mkdirSync(iconsDir, { recursive: true });
}

// Промежуточное ПО для оптимизации мобильного опыта
app.use((req, res, next) => {
  // Для безопасности
  res.setHeader('X-Frame-Options', 'SAMEORIGIN');
  res.setHeader('X-XSS-Protection', '1; mode=block');
  res.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  
  // Для мобильной оптимизации
  res.setHeader('Vary', 'User-Agent');
  
  next();
});

// Хранение данных в памяти (в реальном приложении следует использовать базу данных)
let users = [];
let messages = [];
let contacts = {};
let sessions = {};
let onlineUsers = {};

// Загрузка данных при запуске
function loadData() {
    try {
        if (fs.existsSync('./data/users.json')) {
            users = JSON.parse(fs.readFileSync('./data/users.json', 'utf8'));
        }
        
        if (fs.existsSync('./data/messages.json')) {
            messages = JSON.parse(fs.readFileSync('./data/messages.json', 'utf8'));
        }
        
        if (fs.existsSync('./data/contacts.json')) {
            contacts = JSON.parse(fs.readFileSync('./data/contacts.json', 'utf8'));
        }
        
        console.log('Данные успешно загружены');
    } catch (error) {
        console.error('Ошибка при загрузке данных:', error);
        // Создаем директорию data, если она не существует
        if (!fs.existsSync('./data')) {
            fs.mkdirSync('./data');
        }
    }
}

// Сохранение данных
function saveData() {
    try {
        if (!fs.existsSync('./data')) {
            fs.mkdirSync('./data');
        }
        
        fs.writeFileSync('./data/users.json', JSON.stringify(users, null, 2));
        fs.writeFileSync('./data/messages.json', JSON.stringify(messages, null, 2));
        fs.writeFileSync('./data/contacts.json', JSON.stringify(contacts, null, 2));
        
        console.log('Данные успешно сохранены');
    } catch (error) {
        console.error('Ошибка при сохранении данных:', error);
    }
}

// API Маршруты

// Регистрация пользователя
app.post('/api/auth/register', async (req, res) => {
    try {
        const { username, fullName, email, phone, password } = req.body;
        
        // Проверка существующего пользователя
        if (users.some(user => user.email === email || user.phone === phone)) {
            return res.status(400).json({ success: false, message: 'Пользователь с таким email или телефоном уже существует' });
        }
        
        // Хеширование пароля
        const hashedPassword = await bcrypt.hash(password, 10);
        
        // Создание нового пользователя
        const newUser = {
            id: Date.now().toString(),
            username,
            fullName,
            email,
            phone,
            password: hashedPassword,
            avatar: `https://randomuser.me/api/portraits/${Math.random() > 0.5 ? 'men' : 'women'}/${Math.floor(Math.random() * 100)}.jpg`,
            createdAt: new Date().toISOString()
        };
        
        users.push(newUser);
        saveData();
        
        // Создание сессии
        const sessionId = Date.now().toString();
        sessions[sessionId] = { userId: newUser.id, createdAt: new Date().toISOString() };
        
        // Отправка ответа без пароля
        const { password: _, ...userWithoutPassword } = newUser;
        res.status(201).json({ success: true, user: userWithoutPassword, sessionId });
    } catch (error) {
        console.error('Ошибка при регистрации:', error);
        res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }
});

// Авторизация пользователя
app.post('/api/auth/login', async (req, res) => {
    try {
        const { email, phone, password } = req.body;
        
        // Поиск пользователя
        const user = users.find(user => user.email === email || user.phone === phone);
        
        if (!user) {
            return res.status(401).json({ success: false, message: 'Неверные учетные данные' });
        }
        
        // Проверка пароля
        const isPasswordValid = await bcrypt.compare(password, user.password);
        
        if (!isPasswordValid) {
            return res.status(401).json({ success: false, message: 'Неверные учетные данные' });
        }
        
        // Создание сессии
        const sessionId = Date.now().toString();
        sessions[sessionId] = { userId: user.id, createdAt: new Date().toISOString() };
        
        // Отправка ответа без пароля
        const { password: _, ...userWithoutPassword } = user;
        res.status(200).json({ success: true, user: userWithoutPassword, sessionId });
    } catch (error) {
        console.error('Ошибка при авторизации:', error);
        res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }
});

// Поиск пользователя по телефону
app.get('/api/users/search', (req, res) => {
    try {
        const { phone, email } = req.query;
        
        let user;
        
        if (phone) {
            user = users.find(user => user.phone === phone);
        } else if (email) {
            user = users.find(user => user.email === email);
        }
        
        if (!user) {
            return res.status(404).json({ success: false, message: 'Пользователь не найден' });
        }
        
        // Отправка ответа без пароля
        const { password: _, ...userWithoutPassword } = user;
        res.status(200).json({ success: true, user: userWithoutPassword });
    } catch (error) {
        console.error('Ошибка при поиске пользователя:', error);
        res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }
});

// Получение контактов пользователя
app.get('/api/contacts/:userId', (req, res) => {
    try {
        const { userId } = req.params;
        
        // Проверка существования пользователя
        if (!users.some(user => user.id === userId)) {
            return res.status(404).json({ success: false, message: 'Пользователь не найден' });
        }
        
        // Получение контактов пользователя
        const userContacts = contacts[userId] || [];
        
        // Получение полной информации о контактах
        const contactsWithInfo = userContacts.map(contactId => {
            const contact = users.find(user => user.id === contactId);
            
            if (!contact) return null;
            
            // Получение последнего сообщения
            const conversation = messages.filter(
                msg => (msg.senderId === userId && msg.recipientId === contactId) || 
                       (msg.senderId === contactId && msg.recipientId === userId)
            ).sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
            
            const lastMessage = conversation[0] || null;
            
            // Подсчет непрочитанных сообщений
            const unread = messages.filter(
                msg => msg.senderId === contactId && 
                       msg.recipientId === userId && 
                       !msg.read
            ).length;
            
            // Отправка информации о контакте без пароля
            const { password: _, ...contactWithoutPassword } = contact;
            
            return {
                ...contactWithoutPassword,
                lastMessage: lastMessage ? lastMessage.content : '',
                time: lastMessage ? new Date(lastMessage.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '',
                unread,
                online: Boolean(onlineUsers[contactId])
            };
        }).filter(Boolean);
        
        res.status(200).json({ success: true, contacts: contactsWithInfo });
    } catch (error) {
        console.error('Ошибка при получении контактов:', error);
        res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }
});

// Добавление контакта
app.post('/api/contacts/add', (req, res) => {
    try {
        const { userId, contactId } = req.body;
        
        // Проверка существования пользователя и контакта
        if (!users.some(user => user.id === userId) || !users.some(user => user.id === contactId)) {
            return res.status(404).json({ success: false, message: 'Пользователь не найден' });
        }
        
        // Инициализация массива контактов для пользователя, если он не существует
        if (!contacts[userId]) {
            contacts[userId] = [];
        }
        
        // Проверка, не добавлен ли уже этот контакт
        if (contacts[userId].includes(contactId)) {
            return res.status(400).json({ success: false, message: 'Контакт уже добавлен' });
        }
        
        // Добавление контакта
        contacts[userId].push(contactId);
        
        // Также добавляем пользователя в контакты контакта (взаимное добавление)
        if (!contacts[contactId]) {
            contacts[contactId] = [];
        }
        
        if (!contacts[contactId].includes(userId)) {
            contacts[contactId].push(userId);
        }
        
        saveData();
        
        res.status(200).json({ success: true, message: 'Контакт успешно добавлен' });
    } catch (error) {
        console.error('Ошибка при добавлении контакта:', error);
        res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }
});

// Получение сообщений
app.get('/api/messages/:userId/:contactId', (req, res) => {
    try {
        const { userId, contactId } = req.params;
        
        // Проверка существования пользователя и контакта
        if (!users.some(user => user.id === userId) || !users.some(user => user.id === contactId)) {
            return res.status(404).json({ success: false, message: 'Пользователь не найден' });
        }
        
        // Получение сообщений между пользователем и контактом
        const conversation = messages.filter(
            msg => (msg.senderId === userId && msg.recipientId === contactId) || 
                   (msg.senderId === contactId && msg.recipientId === userId)
        ).sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
        
        // Отметка сообщений как прочитанных
        messages = messages.map(msg => {
            if (msg.senderId === contactId && msg.recipientId === userId && !msg.read) {
                return { ...msg, read: true };
            }
            return msg;
        });
        
        saveData();
        
        res.status(200).json({ success: true, messages: conversation });
    } catch (error) {
        console.error('Ошибка при получении сообщений:', error);
        res.status(500).json({ success: false, message: 'Ошибка сервера' });
    }
});

// WebSocket подключения
io.on('connection', (socket) => {
    console.log('Новое подключение:', socket.id);
    
    // Авторизация пользователя
    socket.on('auth', (userId) => {
        console.log('Пользователь авторизован:', userId);
        
        // Сохранение статуса онлайн
        onlineUsers[userId] = socket.id;
        
        // Отправка статуса пользователя всем контактам
        if (contacts[userId]) {
            contacts[userId].forEach(contactId => {
                const contactSocketId = onlineUsers[contactId];
                if (contactSocketId) {
                    io.to(contactSocketId).emit('user_status', { userId, status: 'online' });
                }
            });
        }
        
        // Сохранение userId в socket для использования при отключении
        socket.userId = userId;
    });
    
    // Отправка сообщения
    socket.on('send_message', (message) => {
        console.log('Новое сообщение:', message);
        
        // Добавление идентификатора и статуса прочтения
        const newMessage = {
            ...message,
            id: Date.now().toString(),
            read: false
        };
        
        // Сохранение сообщения
        messages.push(newMessage);
        saveData();
        
        // Отправка сообщения получателю, если он онлайн
        const recipientSocketId = onlineUsers[message.recipientId];
        if (recipientSocketId) {
            io.to(recipientSocketId).emit('new_message', newMessage);
        }
        
        // Отправка отправителю для обновления интерфейса
        socket.emit('new_message', newMessage);
    });
    
    // Статус набора текста
    socket.on('typing', (data) => {
        const recipientSocketId = onlineUsers[data.contactId];
        if (recipientSocketId) {
            io.to(recipientSocketId).emit('user_typing', {
                userId: data.userId,
                isTyping: data.isTyping
            });
        }
    });
    
    // Отметка сообщений как прочитанных
    socket.on('mark_read', (data) => {
        // Обновляем статус прочтения сообщений
        messages = messages.map(msg => {
            if (msg.senderId === data.contactId && msg.recipientId === data.userId && !msg.read) {
                return { ...msg, read: true };
            }
            return msg;
        });
        
        saveData();
        
        // Отправляем уведомление отправителю
        const senderSocketId = onlineUsers[data.contactId];
        if (senderSocketId) {
            io.to(senderSocketId).emit('messages_read', {
                userId: data.userId
            });
        }
    });
    
    // Отключение пользователя
    socket.on('disconnect', () => {
        console.log('Отключение:', socket.id);
        
        if (socket.userId) {
            // Удаление пользователя из списка онлайн
            delete onlineUsers[socket.userId];
            
            // Отправка статуса офлайн всем контактам
            if (contacts[socket.userId]) {
                contacts[socket.userId].forEach(contactId => {
                    const contactSocketId = onlineUsers[contactId];
                    if (contactSocketId) {
                        io.to(contactSocketId).emit('user_status', { userId: socket.userId, status: 'offline' });
                    }
                });
            }
        }
    });
});

// Маршруты страниц
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'web', 'index.html'));
});

app.get('/messenger', (req, res) => {
    res.sendFile(path.join(__dirname, 'web', 'messenger.html'));
});

app.get('/login', (req, res) => {
    res.sendFile(path.join(__dirname, 'web', 'login.html'));
});

app.get('/register', (req, res) => {
    res.sendFile(path.join(__dirname, 'web', 'register.html'));
});

app.get('/profile', (req, res) => {
    res.sendFile(path.join(__dirname, 'web', 'profile.html'));
});

// Запуск сервера
const PORT = process.env.PORT || 8080;
server.listen(PORT, () => {
    console.log(`Сервер запущен на порту ${PORT}`);
    loadData();
}); 