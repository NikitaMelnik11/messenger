/**
 * Автоматический вход в систему для тестирования
 */

// Тестовые данные
const testCredentials = {
    email: 'test@example.com',
    password: 'password123',
    name: 'Test User',
    phone: '+1234567890'
};

// Функция для автоматического входа с тестовыми данными
function autoLogin() {
    // Сначала пробуем войти
    loginWithTestCredentials();
}

// Функция для входа с тестовыми данными
function loginWithTestCredentials() {
    fetch('/api/auth/login', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(testCredentials)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            handleSuccessfulLogin(data);
        } else {
            // Если вход не удался, пробуем зарегистрировать тестового пользователя
            registerTestUser();
        }
    })
    .catch(error => {
        console.error('Ошибка автоматического входа:', error);
        // В случае ошибки также пробуем зарегистрировать пользователя
        registerTestUser();
    });
}

// Функция для регистрации тестового пользователя
function registerTestUser() {
    fetch('/api/auth/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(testCredentials)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            handleSuccessfulLogin(data);
        } else {
            console.error('Не удалось зарегистрировать тестового пользователя:', data.message);
            // Если регистрация не удалась, создаем временный токен для демонстрации
            createDemoSession();
        }
    })
    .catch(error => {
        console.error('Ошибка при регистрации тестового пользователя:', error);
        // В случае ошибки создаем временный токен для демонстрации
        createDemoSession();
    });
}

// Обработка успешного входа
function handleSuccessfulLogin(data) {
    // Сохраняем токен
    if (data.token || data.sessionId) {
        localStorage.setItem('authToken', data.token || data.sessionId);
    }
    
    // Сохраняем информацию о пользователе
    if (data.user) {
        localStorage.setItem('user', JSON.stringify(data.user));
    }
    
    // Перенаправляем на мессенджер
    window.location.href = 'messenger.html';
}

// Создание демо-сессии для тестирования
function createDemoSession() {
    // Создаем временный токен
    const demoToken = 'demo_' + Date.now();
    localStorage.setItem('authToken', demoToken);
    
    // Создаем демо-пользователя
    const demoUser = {
        id: 'demo_user',
        name: 'Demo User',
        email: 'demo@example.com',
        avatar: 'https://randomuser.me/api/portraits/lego/1.jpg'
    };
    localStorage.setItem('user', JSON.stringify(demoUser));
    
    // Перенаправляем на мессенджер
    window.location.href = 'messenger.html';
}

// Экспортируем функцию
window.autoLogin = autoLogin; 