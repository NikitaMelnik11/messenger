/**
 * Vegan Messenger - Notifications Module
 */

// Store notifications
let notifications = [];
let unreadCount = 0;
let userId = null;

/**
 * Initialize notifications for a user
 * 
 * @param {number} id The user ID
 */
function initNotifications(id) {
    userId = id;
    
    // Load existing notifications
    loadNotifications();
    
    // Set up polling for new notifications
    setInterval(checkForNewNotifications, 30000); // Check every 30 seconds
    
    // Set up notification actions
    setupNotificationActions();
}

/**
 * Load existing notifications from server
 */
function loadNotifications() {
    fetch('/api/notifications', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            notifications = data.notifications;
            unreadCount = notifications.filter(n => !n.is_read).length;
            updateNotificationBadge();
            renderNotifications();
        }
    })
    .catch(error => {
        console.error('Error loading notifications:', error);
    });
}

/**
 * Check for new notifications
 */
function checkForNewNotifications() {
    if (!userId) return;
    
    const lastNotificationId = notifications.length > 0 ? notifications[0].notification_id : 0;
    
    fetch(`/api/notifications?after=${lastNotificationId}`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.notifications.length > 0) {
            // Add new notifications to the beginning of the array
            notifications = [...data.notifications, ...notifications];
            unreadCount += data.notifications.length;
            updateNotificationBadge();
            renderNotifications();
            
            // Show notification popup for the newest notification
            showNotificationPopup(data.notifications[0]);
        }
    })
    .catch(error => {
        console.error('Error checking for notifications:', error);
    });
}

/**
 * Update the notification badge count
 */
function updateNotificationBadge() {
    const badge = document.getElementById('notification-badge');
    
    if (badge) {
        if (unreadCount > 0) {
            badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
            badge.style.display = 'block';
        } else {
            badge.style.display = 'none';
        }
    }
}

/**
 * Render notifications in the dropdown
 */
function renderNotifications() {
    const notificationsList = document.getElementById('notifications-list');
    
    if (!notificationsList) return;
    
    if (notifications.length === 0) {
        notificationsList.innerHTML = '<li class="dropdown-item text-center">No notifications</li>';
        return;
    }
    
    notificationsList.innerHTML = '';
    
    // Only show the latest 10 notifications
    const recentNotifications = notifications.slice(0, 10);
    
    recentNotifications.forEach(notification => {
        const notificationItem = document.createElement('li');
        notificationItem.classList.add('dropdown-item', 'notification-item');
        if (!notification.is_read) {
            notificationItem.classList.add('bg-light');
        }
        notificationItem.dataset.id = notification.notification_id;
        
        const content = document.createElement('div');
        content.classList.add('d-flex', 'align-items-start', 'py-1');
        
        // Avatar
        const avatar = document.createElement('div');
        avatar.classList.add('me-2');
        if (notification.initiator_profile_picture) {
            avatar.innerHTML = `<img src="${notification.initiator_profile_picture}" alt="User" class="rounded-circle" width="32" height="32">`;
        } else {
            avatar.innerHTML = `<div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 32px; height: 32px;"><i class="bi bi-person"></i></div>`;
        }
        
        // Content
        const details = document.createElement('div');
        details.classList.add('flex-grow-1', 'ps-1');
        
        details.innerHTML = `
            <div class="notification-text">${notification.content}</div>
            <div class="text-muted small">${formatRelativeTime(notification.created_at)}</div>
        `;
        
        // Mark as read button
        const markAsReadButton = document.createElement('button');
        markAsReadButton.classList.add('btn', 'btn-sm', 'text-muted', 'mark-read-btn');
        markAsReadButton.innerHTML = '<i class="bi bi-check2"></i>';
        markAsReadButton.title = 'Mark as read';
        markAsReadButton.dataset.id = notification.notification_id;
        
        content.appendChild(avatar);
        content.appendChild(details);
        
        if (!notification.is_read) {
            content.appendChild(markAsReadButton);
        }
        
        notificationItem.appendChild(content);
        notificationsList.appendChild(notificationItem);
    });
    
    // Add view all link
    const viewAllItem = document.createElement('li');
    viewAllItem.innerHTML = '<hr class="dropdown-divider">';
    notificationsList.appendChild(viewAllItem);
    
    const viewAllLink = document.createElement('li');
    viewAllLink.innerHTML = '<a class="dropdown-item text-center text-primary" href="/notifications">View all notifications</a>';
    notificationsList.appendChild(viewAllLink);
}

/**
 * Show a notification popup
 * 
 * @param {Object} notification The notification object
 */
function showNotificationPopup(notification) {
    // Create toast container if it doesn't exist
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.classList.add('toast-container', 'position-fixed', 'bottom-0', 'end-0', 'p-3');
        document.body.appendChild(toastContainer);
    }
    
    // Create toast element
    const toastId = 'notification-toast-' + notification.notification_id;
    const toast = document.createElement('div');
    toast.classList.add('toast');
    toast.setAttribute('id', toastId);
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    toast.setAttribute('data-bs-delay', '5000');
    
    const avatarSrc = notification.initiator_profile_picture || '';
    const avatarHtml = avatarSrc ? 
        `<img src="${avatarSrc}" class="rounded-circle me-2" width="32" height="32" alt="User">` : 
        `<div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center text-white me-2" style="width: 32px; height: 32px;"><i class="bi bi-person"></i></div>`;
    
    toast.innerHTML = `
        <div class="toast-header">
            ${avatarHtml}
            <strong class="me-auto">Notification</strong>
            <small>${formatRelativeTime(notification.created_at)}</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body">
            ${notification.content}
        </div>
    `;
    
    // Add to container
    toastContainer.appendChild(toast);
    
    // Initialize and show the toast
    const toastElement = new bootstrap.Toast(toast);
    toastElement.show();
    
    // Add click handler to navigate
    toast.addEventListener('click', function(e) {
        if (!e.target.closest('.btn-close')) {
            if (notification.content_type && notification.content_id) {
                navigateToContent(notification.content_type, notification.content_id);
            } else {
                window.location.href = '/notifications';
            }
        }
    });
}

/**
 * Set up notification action handlers
 */
function setupNotificationActions() {
    // Mark notification as read
    document.addEventListener('click', function(e) {
        const markReadBtn = e.target.closest('.mark-read-btn');
        if (markReadBtn) {
            e.preventDefault();
            e.stopPropagation();
            
            const notificationId = markReadBtn.dataset.id;
            markNotificationAsRead(notificationId);
        }
    });
    
    // Click on notification item
    document.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && !e.target.closest('.mark-read-btn')) {
            e.preventDefault();
            
            const notificationId = notificationItem.dataset.id;
            const notification = notifications.find(n => n.notification_id == notificationId);
            
            if (notification) {
                if (!notification.is_read) {
                    markNotificationAsRead(notificationId);
                }
                
                if (notification.content_type && notification.content_id) {
                    navigateToContent(notification.content_type, notification.content_id);
                } else {
                    window.location.href = '/notifications';
                }
            }
        }
    });
    
    // Mark all as read
    const markAllReadBtn = document.querySelector('.mark-all-read-btn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllNotificationsAsRead();
        });
    }
}

/**
 * Mark a notification as read
 * 
 * @param {number} notificationId The notification ID
 */
function markNotificationAsRead(notificationId) {
    fetch('/api/notifications/read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            notification_id: notificationId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update notification in array
            const index = notifications.findIndex(n => n.notification_id == notificationId);
            if (index !== -1 && !notifications[index].is_read) {
                notifications[index].is_read = true;
                unreadCount = Math.max(0, unreadCount - 1);
                updateNotificationBadge();
                renderNotifications();
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    fetch('/api/notifications/read-all', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update all notifications in array
            notifications.forEach(notification => {
                notification.is_read = true;
            });
            unreadCount = 0;
            updateNotificationBadge();
            renderNotifications();
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

/**
 * Navigate to specific content
 * 
 * @param {string} contentType The content type
 * @param {number} contentId The content ID
 */
function navigateToContent(contentType, contentId) {
    let url;
    
    switch (contentType) {
        case 'post':
            url = `/posts/${contentId}`;
            break;
        case 'comment':
            url = `/comments/${contentId}`;
            break;
        case 'friend_request':
            url = `/friends/requests`;
            break;
        case 'user':
            url = `/profile/${contentId}`;
            break;
        case 'message':
            url = `/messages/${contentId}`;
            break;
        case 'group':
            url = `/groups/${contentId}`;
            break;
        case 'event':
            url = `/events/${contentId}`;
            break;
        default:
            url = '/notifications';
    }
    
    window.location.href = url;
} 