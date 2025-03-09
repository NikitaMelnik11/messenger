/**
 * Vegan Messenger - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Enable Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Enable Bootstrap popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Handle flash messages auto-close
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Handle confirm dialogs
    document.querySelectorAll('[data-confirm]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            if (!confirm(this.getAttribute('data-confirm'))) {
                e.preventDefault();
                return false;
            }
        });
    });
    
    // Handle form validation
    document.querySelectorAll('form.needs-validation').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function(toggle) {
        toggle.addEventListener('click', function() {
            var target = document.querySelector(this.getAttribute('data-target'));
            if (target.type === 'password') {
                target.type = 'text';
                this.innerHTML = '<i class="bi bi-eye-slash"></i>';
            } else {
                target.type = 'password';
                this.innerHTML = '<i class="bi bi-eye"></i>';
            }
        });
    });
    
    // Post character counter
    var postTextarea = document.querySelector('#post-content');
    var charCounter = document.querySelector('#char-counter');
    
    if (postTextarea && charCounter) {
        var maxLength = parseInt(postTextarea.getAttribute('maxlength') || 1000);
        
        postTextarea.addEventListener('input', function() {
            var remaining = maxLength - this.value.length;
            charCounter.textContent = remaining;
            
            if (remaining < 20) {
                charCounter.classList.add('text-danger');
            } else {
                charCounter.classList.remove('text-danger');
            }
        });
    }
    
    // Like button functionality
    document.querySelectorAll('.btn-like').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            var postId = this.getAttribute('data-post-id');
            var likeCount = this.querySelector('.like-count');
            var icon = this.querySelector('i');
            
            if (this.classList.contains('liked')) {
                // Unlike
                unlikePost(postId).then(function(response) {
                    if (response.success) {
                        button.classList.remove('liked');
                        icon.classList.remove('bi-heart-fill');
                        icon.classList.add('bi-heart');
                        likeCount.textContent = parseInt(likeCount.textContent) - 1;
                    }
                });
            } else {
                // Like
                likePost(postId).then(function(response) {
                    if (response.success) {
                        button.classList.add('liked');
                        icon.classList.remove('bi-heart');
                        icon.classList.add('bi-heart-fill');
                        likeCount.textContent = parseInt(likeCount.textContent) + 1;
                    }
                });
            }
        });
    });
    
    // Comment form submission
    var commentForms = document.querySelectorAll('.comment-form');
    
    if (commentForms.length > 0) {
        commentForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                var postId = this.getAttribute('data-post-id');
                var commentInput = this.querySelector('.comment-input');
                var commentText = commentInput.value.trim();
                
                if (commentText.length === 0) {
                    return;
                }
                
                addComment(postId, commentText).then(function(response) {
                    if (response.success) {
                        // Append new comment to the list
                        var commentsList = document.querySelector('#comments-list-' + postId);
                        var commentTemplate = document.querySelector('#comment-template');
                        
                        if (commentsList && commentTemplate) {
                            var newComment = commentTemplate.content.cloneNode(true);
                            newComment.querySelector('.comment-author').textContent = response.comment.author;
                            newComment.querySelector('.comment-content').textContent = response.comment.content;
                            newComment.querySelector('.comment-time').textContent = 'Just now';
                            
                            commentsList.appendChild(newComment);
                        }
                        
                        // Clear input
                        commentInput.value = '';
                        
                        // Update comments count
                        var commentsCount = document.querySelector('#comments-count-' + postId);
                        if (commentsCount) {
                            commentsCount.textContent = parseInt(commentsCount.textContent) + 1;
                        }
                    }
                });
            });
        });
    }
});

/**
 * Like a post
 * 
 * @param {number} postId The post ID
 * @returns {Promise} The fetch promise
 */
function likePost(postId) {
    return fetch('/posts/' + postId + '/like', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }).then(function(response) {
        return response.json();
    });
}

/**
 * Unlike a post
 * 
 * @param {number} postId The post ID
 * @returns {Promise} The fetch promise
 */
function unlikePost(postId) {
    return fetch('/posts/' + postId + '/unlike', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    }).then(function(response) {
        return response.json();
    });
}

/**
 * Add a comment to a post
 * 
 * @param {number} postId The post ID
 * @param {string} content The comment content
 * @returns {Promise} The fetch promise
 */
function addComment(postId, content) {
    return fetch('/posts/' + postId + '/comment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            content: content
        })
    }).then(function(response) {
        return response.json();
    });
}

/**
 * Format a date relative to now
 * 
 * @param {string} dateString The date string
 * @returns {string} The formatted date
 */
function formatRelativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    
    if (diff < 60) {
        return 'just now';
    } else if (diff < 3600) {
        const minutes = Math.floor(diff / 60);
        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
    } else if (diff < 86400) {
        const hours = Math.floor(diff / 3600);
        return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
    } else if (diff < 604800) {
        const days = Math.floor(diff / 86400);
        return `${days} ${days === 1 ? 'day' : 'days'} ago`;
    } else {
        return date.toLocaleDateString();
    }
} 