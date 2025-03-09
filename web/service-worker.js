// Название кеша и версия
const CACHE_NAME = 'vegan-messenger-cache-v1';

// Файлы для кеширования при установке
const STATIC_CACHE_FILES = [
  '/',
  '/messenger.html',
  '/manifest.json',
  '/favicon.ico'
];

// Файлы для кеширования при использовании
const DYNAMIC_CACHE_FILES = [
  '/api/contacts',
  '/api/messages'
];

// Установка Service Worker и кеширование статических ресурсов
self.addEventListener('install', event => {
  console.log('Service Worker: Установка');
  
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Service Worker: Кеширование файлов');
        // Предварительное кеширование статических ресурсов
        return cache.addAll(STATIC_CACHE_FILES);
      })
      .then(() => {
        console.log('Service Worker: Установлен');
        // Принудительная активация без ожидания закрытия всех вкладок
        return self.skipWaiting();
      })
  );
});

// Активация Service Worker и очистка старых кешей
self.addEventListener('activate', event => {
  console.log('Service Worker: Активация');
  
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cache => {
            // Удаляем старые версии кеша
            if (cache !== CACHE_NAME) {
              console.log('Service Worker: Очищен старый кеш', cache);
              return caches.delete(cache);
            }
          })
        );
      })
      .then(() => {
        console.log('Service Worker: Активирован');
        // Немедленный захват управления всеми страницами
        return self.clients.claim();
      })
  );
});

// Стратегия кеширования: сначала сеть, затем кеш в случае ошибки
self.addEventListener('fetch', event => {
  // Игнорируем запросы не по HTTPS/HTTP и запросы к другим доменам
  if (!event.request.url.startsWith(self.location.origin) || 
      !event.request.url.startsWith('http')) {
    return;
  }
  
  // Обрабатываем только GET-запросы
  if (event.request.method !== 'GET') {
    return;
  }
  
  // Стратегия для API-запросов: сеть с ограничением времени ожидания, затем кеш
  if (event.request.url.includes('/api/')) {
    event.respondWith(
      networkWithTimeout(event.request, 3000)
        .catch(() => {
          console.log('Service Worker: Не удалось получить данные из сети, используем кеш', event.request.url);
          return caches.match(event.request);
        })
    );
    return;
  }
  
  // Стратегия для статических ресурсов: сначала кеш, затем сеть
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        // Если ресурс есть в кеше, возвращаем его
        if (cachedResponse) {
          console.log('Service Worker: Возвращение ресурса из кеша', event.request.url);
          
          // Асинхронное обновление кеша
          fetch(event.request)
            .then(networkResponse => {
              if (networkResponse.ok) {
                caches.open(CACHE_NAME)
                  .then(cache => cache.put(event.request, networkResponse));
              }
            })
            .catch(() => console.log('Service Worker: Не удалось обновить кеш, используем существующий'));
          
          return cachedResponse;
        }
        
        // Если ресурса нет в кеше, запрашиваем через сеть
        console.log('Service Worker: Загрузка ресурса из сети', event.request.url);
        return fetch(event.request)
          .then(networkResponse => {
            // Копия ответа для кеширования
            const responseToCache = networkResponse.clone();
            
            // Кешируем полученный ресурс
            caches.open(CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
              });
            
            return networkResponse;
          })
          .catch(error => {
            console.log('Service Worker: Ошибка загрузки ресурса', error);
            
            // Для HTML-страниц возвращаем офлайн-страницу
            if (event.request.headers.get('Accept').includes('text/html')) {
              return caches.match('/offline.html');
            }
            
            return new Response('Не удалось загрузить ресурс. Проверьте подключение к интернету.');
          });
      })
  );
});

// Функция для запроса к сети с таймаутом
function networkWithTimeout(request, timeout) {
  return new Promise((resolve, reject) => {
    // Таймер для ограничения времени ожидания
    const timeoutId = setTimeout(() => {
      reject(new Error('Timeout'));
    }, timeout);
    
    fetch(request).then(response => {
      // Очищаем таймер если получили ответ
      clearTimeout(timeoutId);
      
      // Кешируем ответ
      const responseClone = response.clone();
      caches.open(CACHE_NAME).then(cache => {
        cache.put(request, responseClone);
      });
      
      resolve(response);
    }).catch(err => {
      clearTimeout(timeoutId);
      reject(err);
    });
  });
}

// Обработка событий push-уведомлений
self.addEventListener('push', event => {
  console.log('Service Worker: Получено Push-уведомление');
  
  let title = 'Vegan Messenger';
  let options = {
    body: 'Новое сообщение',
    icon: '/icons/icon-192x192.png',
    badge: '/icons/badge-72x72.png',
    vibrate: [100, 50, 100],
    data: {
      url: '/'
    }
  };
  
  // Если пришли данные в уведомлении, используем их
  if (event.data) {
    const data = event.data.json();
    title = data.title || title;
    options.body = data.body || options.body;
    options.data.url = data.url || options.data.url;
  }
  
  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Обработка нажатия на уведомление
self.addEventListener('notificationclick', event => {
  console.log('Service Worker: Клик по уведомлению');
  
  event.notification.close();
  
  // Открываем или фокусируемся на соответствующей странице
  event.waitUntil(
    clients.matchAll({type: 'window'})
      .then(windowClients => {
        const url = event.notification.data.url;
        
        // Если уже открыта вкладка с этим URL, фокусируемся на ней
        for (let client of windowClients) {
          if (client.url === url && 'focus' in client) {
            return client.focus();
          }
        }
        
        // Иначе открываем новую вкладку
        if (clients.openWindow) {
          return clients.openWindow(url);
        }
      })
  );
});

// Синхронизация для отправки сообщений в офлайн-режиме
self.addEventListener('sync', event => {
  console.log('Service Worker: Событие синхронизации', event.tag);
  
  if (event.tag === 'sync-messages') {
    event.waitUntil(syncMessages());
  }
});

// Функция для синхронизации сообщений
async function syncMessages() {
  try {
    // Получаем сообщения из хранилища IndexedDB
    const messagesDB = await openMessagesDB();
    const messages = await getOfflineMessages(messagesDB);
    
    // Отправляем каждое сообщение на сервер
    for (const message of messages) {
      try {
        const response = await fetch('/api/messages/send', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(message)
        });
        
        if (response.ok) {
          // Если сообщение успешно отправлено, удаляем его из очереди
          await deleteOfflineMessage(messagesDB, message.id);
        }
      } catch (error) {
        console.error('Не удалось синхронизировать сообщение:', error);
      }
    }
  } catch (error) {
    console.error('Ошибка при синхронизации сообщений:', error);
  }
}

// Функции для работы с IndexedDB
function openMessagesDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('messages-store', 1);
    
    request.onupgradeneeded = event => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('offline-messages')) {
        db.createObjectStore('offline-messages', { keyPath: 'id' });
      }
    };
    
    request.onsuccess = event => resolve(event.target.result);
    request.onerror = event => reject(event.target.error);
  });
}

function getOfflineMessages(db) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction('offline-messages', 'readonly');
    const store = transaction.objectStore('offline-messages');
    const request = store.getAll();
    
    request.onsuccess = event => resolve(event.target.result);
    request.onerror = event => reject(event.target.error);
  });
}

function deleteOfflineMessage(db, id) {
  return new Promise((resolve, reject) => {
    const transaction = db.transaction('offline-messages', 'readwrite');
    const store = transaction.objectStore('offline-messages');
    const request = store.delete(id);
    
    request.onsuccess = event => resolve();
    request.onerror = event => reject(event.target.error);
  });
} 