// GameHub - Main JavaScript
// Real-time features and interactions

// Update server time display
function updateServerTime() {
    const timeElement = document.getElementById('server-time');
    if (timeElement) {
        const now = new Date();
        const formatted = now.getFullYear() + '-' + 
                         String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                         String(now.getDate()).padStart(2, '0') + ' ' +
                         String(now.getHours()).padStart(2, '0') + ':' + 
                         String(now.getMinutes()).padStart(2, '0') + ':' + 
                         String(now.getSeconds()).padStart(2, '0');
        timeElement.textContent = formatted;
    }
}

// Update time every second
setInterval(updateServerTime, 1000);

// Auto-refresh chat messages (if on chat page)
let chatRefreshInterval;

function startChatAutoRefresh() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatRefreshInterval = setInterval(function() {
            // Store scroll position
            const wasAtBottom = chatMessages.scrollHeight - chatMessages.clientHeight <= chatMessages.scrollTop + 1;
            
            // AJAX refresh would go here
            // For now, we'll just maintain scroll position
            
            if (wasAtBottom) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        }, 3000); // Refresh every 3 seconds
    }
}

// Stop auto-refresh when leaving page
function stopChatAutoRefresh() {
    if (chatRefreshInterval) {
        clearInterval(chatRefreshInterval);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Start auto-refresh for chat if on chat page
    if (window.location.pathname.includes('chat.php')) {
        startChatAutoRefresh();
    }
    
    // Add smooth scrolling to all links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Add confirmation for logout
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    }
    
    // Form validation enhancement
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = '#c62828';
                } else {
                    field.style.borderColor = '';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
    
    // Add loading animation to buttons on form submit
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Loading...';
            }
        });
    });
});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    stopChatAutoRefresh();
});

// Utility function to format numbers
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// Show notification (can be used for real-time updates)
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#52B788' : type === 'error' ? '#c62828' : '#4A90E2'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Export functions for use in other pages
window.GameHub = {
    formatNumber: formatNumber,
    showNotification: showNotification,
    updateServerTime: updateServerTime
};
