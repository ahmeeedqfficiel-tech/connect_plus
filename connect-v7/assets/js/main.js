/**
 * CONNECT+ - الملف الرئيسي للجافاسكريبت
 * الإصدار: 2.0
 */

// ============================================================
// تهيئة الصفحة عند التحميل
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ CONNECT+ جاهز للعمل');
    
    // تطبيق الوضع الداكن المحفوظ
    if (localStorage.getItem('darkMode') === 'true') {
        document.body.classList.add('dark-mode');
        updateDarkModeButton();
    }
    
    // تهيئة القائمة الجانبية للجوال
    initMobileSidebar();
    
    // إغلاق النوافذ الجانبية عند النقر خارجها
    initSideModals();
});

// ============================================================
// دوال الوضع الداكن
// ============================================================
function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    const isDark = document.body.classList.contains('dark-mode');
    localStorage.setItem('darkMode', isDark);
    updateDarkModeButton();
}

function updateDarkModeButton() {
    const darkModeText = document.getElementById('darkModeText');
    if (darkModeText) {
        const isDark = document.body.classList.contains('dark-mode');
        darkModeText.textContent = isDark ? 'الوضع الفاتح' : 'الوضع الداكن';
    }
}

// ============================================================
// دوال النوافذ المنبثقة (Modals)
// ============================================================
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

function closeAllModals() {
    document.querySelectorAll('.modal.active').forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.style.overflow = 'auto';
}

// ============================================================
// دوال النوافذ الجانبية (Side Modals)
// ============================================================
function openSideModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeSideModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

function closeAllSideModals() {
    document.querySelectorAll('.side-modal.active').forEach(modal => {
        modal.classList.remove('active');
    });
    document.body.style.overflow = 'auto';
}

function initSideModals() {
    // إغلاق النوافذ الجانبية عند النقر خارجها
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('side-modal')) {
            closeAllSideModals();
        }
    });
    
    // إغلاق النوافذ الجانبية بزر ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeAllSideModals();
        }
    });
}

// ============================================================
// دوال الإشعارات المنبثقة (Toast)
// ============================================================
function createToastElement() {
    if (!document.getElementById('toast')) {
        const toastHTML = `
            <div class="toast" id="toast">
                <i class="fas" id="toastIcon"></i>
                <div style="flex: 1;">
                    <div class="toast-title" id="toastTitle" style="font-weight: 600;"></div>
                    <div class="toast-message" id="toastMessage" style="font-size: 0.9rem;"></div>
                </div>
                <button class="toast-close" onclick="hideToast()" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: var(--gray);">&times;</button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', toastHTML);
    }
}

function showToast(message, type = 'success', duration = 3000) {
    createToastElement();
    
    const toast = document.getElementById('toast');
    const toastIcon = document.getElementById('toastIcon');
    const toastTitle = document.getElementById('toastTitle');
    const toastMessage = document.getElementById('toastMessage');
    
    // تعيين النوع
    let title = '';
    let icon = '';
    
    switch(type) {
        case 'success':
            title = 'تم بنجاح';
            icon = 'fa-check-circle';
            break;
        case 'error':
            title = 'خطأ';
            icon = 'fa-exclamation-circle';
            break;
        case 'warning':
            title = 'تحذير';
            icon = 'fa-exclamation-triangle';
            break;
        case 'info':
            title = 'معلومة';
            icon = 'fa-info-circle';
            break;
    }
    
    toast.className = `toast ${type}`;
    toastIcon.className = `fas ${icon}`;
    toastTitle.textContent = title;
    toastMessage.textContent = message;
    
    toast.classList.add('show');
    
    setTimeout(() => {
        hideToast();
    }, duration);
}

function hideToast() {
    const toast = document.getElementById('toast');
    if (toast) {
        toast.classList.remove('show');
    }
}

// ============================================================
// دوال تحت التطوير
// ============================================================
function showDevMessage(feature) {
    showToast(`خدمة ${feature} قيد التطوير وستتوفر قريباً`, 'warning');
}

// ============================================================
// دوال القائمة الجانبية (للموبايل)
// ============================================================
function initMobileSidebar() {
    const menuToggle = document.querySelector('.menu-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    }
}

// ============================================================
// دوال مساعدة
// ============================================================
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showToast('تم النسخ إلى الحافظة', 'success');
    }).catch(() => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        textarea.select();
        document.execCommand('copy');
        document.body.removeChild(textarea);
        showToast('تم النسخ إلى الحافظة', 'success');
    });
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('ar-EG');
}

function timeAgo(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'الآن';
    if (seconds < 3600) return `منذ ${Math.floor(seconds / 60)} دقيقة`;
    if (seconds < 86400) return `منذ ${Math.floor(seconds / 3600)} ساعة`;
    if (seconds < 2592000) return `منذ ${Math.floor(seconds / 86400)} يوم`;
    
    return formatDate(dateString);
}

function logout() {
    if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
        window.location.href = '../logout.php';
    }
}

// ============================================================
// تصدير الدوال للاستخدام العام
// ============================================================
window.toggleDarkMode = toggleDarkMode;
window.openModal = openModal;
window.closeModal = closeModal;
window.closeAllModals = closeAllModals;
window.openSideModal = openSideModal;
window.closeSideModal = closeSideModal;
window.closeAllSideModals = closeAllSideModals;
window.showToast = showToast;
window.hideToast = hideToast;
window.showDevMessage = showDevMessage;
window.copyToClipboard = copyToClipboard;
window.formatDate = formatDate;
window.timeAgo = timeAgo;
window.logout = logout;