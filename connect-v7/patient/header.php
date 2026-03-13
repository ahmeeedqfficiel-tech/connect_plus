<?php
/**
 * patient/header.php - الشريط العلوي للمريض
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
    $patient = getPatientData($pdo, $user['id']);
}

// جلب عدد الإشعارات غير المقروءة
$unreadNotifications = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unreadNotifications = $stmt->fetchColumn();
}

// جلب آخر الإشعارات (للنافذة الجانبية)
$notifications = [];
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
}
?>
<div class="top-bar">
    <div class="top-left">
        <!-- اللوجو -->
        <a href="dashboard.php" class="logo">
            <i class="fas fa-plus-circle"></i>
            <span>CONNECT+</span>
        </a>
        
        <!-- زر القائمة للجوال -->
        <button class="menu-toggle top-btn" onclick="toggleSidebar()" style="display:none;">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <div class="top-actions">
        <!-- أزرار تحت التطوير (NFC, بصمة, خريطة) -->
        <button class="top-btn dev-btn" onclick="showDevMessage('NFC')" title="قيد التطوير">
            <i class="fas fa-id-card"></i>
            <span>NFC</span>
        </button>
        
        <button class="top-btn dev-btn" onclick="showDevMessage('البصمة')" title="قيد التطوير">
            <i class="fas fa-fingerprint"></i>
            <span>بصمة</span>
        </button>
        
        <button class="top-btn dev-btn" onclick="showDevMessage('الخريطة')" title="قيد التطوير">
            <i class="fas fa-map-marked-alt"></i>
            <span>خريطة</span>
        </button>
        
        <!-- الحساب الشخصي -->
        <a href="profile.php" class="top-btn">
            <i class="fas fa-user-circle"></i>
            <span>الحساب</span>
        </a>
        
        <!-- الإعدادات -->
        <a href="settings.php" class="top-btn">
            <i class="fas fa-cog"></i>
            <span>الإعدادات</span>
        </a>
        
        <!-- الوضع الداكن -->
        <button class="top-btn" onclick="toggleDarkMode()">
            <i class="fas fa-moon"></i>
            <span id="darkModeText">الوضع الداكن</span>
        </button>
        
        <!-- الإشعارات -->
        <button class="top-btn" onclick="openSideModal('notificationsSideModal')">
            <i class="fas fa-bell"></i>
            <span>الإشعارات</span>
            <?php if ($unreadNotifications > 0): ?>
                <span class="badge badge-danger"><?= $unreadNotifications ?></span>
            <?php endif; ?>
        </button>
        
        <!-- رمز QR -->
        <button class="top-btn" onclick="openSideModal('qrSideModal')">
            <i class="fas fa-qrcode"></i>
            <span>QR</span>
        </button>
        
        <!-- الطوارئ -->
        <a href="emergency.php" class="top-btn emergency">
            <i class="fas fa-ambulance"></i>
            <span>الطوارئ</span>
        </a>
    </div>
</div>

<!-- ============================================================
     النافذة الجانبية للإشعارات
     ============================================================ -->
<div class="side-modal" id="notificationsSideModal">
    <div class="side-modal-header">
        <h3><i class="fas fa-bell"></i> الإشعارات</h3>
        <button class="close-side-modal" onclick="closeSideModal('notificationsSideModal')">&times;</button>
    </div>
    <div class="side-modal-body">
        <?php if (empty($notifications)): ?>
            <div class="text-center" style="padding: 2rem;">
                <i class="fas fa-bell-slash" style="font-size: 3rem; color: var(--gray);"></i>
                <p style="margin-top: 1rem;">لا توجد إشعارات</p>
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $n): ?>
                <div class="notification-item" style="padding: 1rem; border-bottom: 1px solid var(--light-gray); <?= $n['is_read'] ? '' : 'background: var(--primary-soft);' ?>" onclick="markNotificationRead(<?= $n['id'] ?>)">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--primary); display: flex; align-items: center; justify-content: center; color: white;">
                            <i class="fas fa-<?= $n['type'] == 'appointment' ? 'calendar-check' : ($n['type'] == 'message' ? 'comment' : ($n['type'] == 'order' ? 'shopping-cart' : 'bell')) ?>"></i>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600;"><?= htmlspecialchars($n['title']) ?></div>
                            <div style="font-size: 0.9rem; color: var(--gray);"><?= htmlspecialchars($n['content']) ?></div>
                            <div style="font-size: 0.8rem; color: var(--gray);"><?= timeAgo($n['created_at']) ?></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <button class="btn btn-outline btn-sm" onclick="markAllNotificationsRead()" style="width: 100%; margin-top: 1rem;">
                <i class="fas fa-check-double"></i> تعليم الكل كمقروء
            </button>
        <?php endif; ?>
    </div>
    <div class="side-modal-footer">
        <button class="btn btn-outline btn-sm" onclick="closeSideModal('notificationsSideModal')">إغلاق</button>
    </div>
</div>

<!-- ============================================================
     النافذة الجانبية لرمز QR
     ============================================================ -->
<div class="side-modal" id="qrSideModal">
    <div class="side-modal-header">
        <h3><i class="fas fa-qrcode"></i> رمز QR الخاص بك</h3>
        <button class="close-side-modal" onclick="closeSideModal('qrSideModal')">&times;</button>
    </div>
    <div class="side-modal-body text-center">
        <!-- رمز QR وهمي (سيتم تفعيله لاحقاً) -->
        <div style="width: 200px; height: 200px; background: var(--light); margin: 0 auto 1.5rem; display: flex; align-items: center; justify-content: center; border-radius: 20px; border: 2px dashed var(--primary);">
            <i class="fas fa-qrcode" style="font-size: 80px; color: var(--primary);"></i>
        </div>
        
        <h3><?= htmlspecialchars($user['full_name']) ?></h3>
        <p class="user-code" style="margin: 0.5rem 0; padding: 0.3rem 1rem; background: var(--primary-soft); border-radius: 30px; display: inline-block;">
            <?= $user['user_code'] ?>
        </p>
        
        <p style="color: var(--gray); margin: 1rem 0;">
            امسح الرمز لمشاركة ملفك الطبي مع الطبيب
        </p>
        
        <div class="dev-badge" style="margin: 1rem 0;">
            <i class="fas fa-tools"></i> تحت التطوير
        </div>
        
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button class="btn btn-outline btn-sm" onclick="copyToClipboard('<?= $user['user_code'] ?>')">
                <i class="fas fa-copy"></i> نسخ الكود
            </button>
            <button class="btn btn-primary btn-sm dev-btn" onclick="showDevMessage('تحميل QR')">
                <i class="fas fa-download"></i> تحميل
            </button>
        </div>
    </div>
</div>

<!-- ============================================================
     النافذة المنبثقة لرسائل تحت التطوير
     ============================================================ -->
<div class="modal" id="devModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-tools"></i> تحت التطوير</h3>
            <button class="close-modal" onclick="closeModal('devModal')">&times;</button>
        </div>
        <div class="modal-body text-center">
            <i class="fas fa-code-branch" style="font-size: 60px; color: var(--warning); margin: 20px;"></i>
            <h2 id="devFeature">هذه الميزة</h2>
            <p style="color: var(--gray); margin: 1rem 0;">
                قيد التطوير وستتوفر قريباً في التحديث القادم
            </p>
            <button class="btn btn-primary" onclick="closeModal('devModal')">موافق</button>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

function openSideModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeSideModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = 'auto';
}

function markNotificationRead(id) {
    fetch('../api/mark_notification_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // تحديث الشاشة
            location.reload();
        }
    });
}

function markAllNotificationsRead() {
    fetch('../api/mark_all_notifications_read.php', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('تم تعليم الكل كمقروء', 'success');
            setTimeout(() => location.reload(), 1500);
        }
    });
}

function showDevMessage(feature) {
    document.getElementById('devFeature').textContent = feature;
    openModal('devModal');
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}
</script>