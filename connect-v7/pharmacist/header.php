<?php
/**
 * pharmacist/header.php - الشريط العلوي للصيدلي
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
    $pharmacist = getPharmacistData($pdo, $user['id']);
}

// جلب عدد الإشعارات غير المقروءة
$unreadNotifications = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unreadNotifications = $stmt->fetchColumn();
}

// جلب آخر الإشعارات
$notifications = [];
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$user['id']]);
    $notifications = $stmt->fetchAll();
}

// جلب عدد الطلبات المعلقة
$pendingOrders = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medicine_orders mo JOIN pharmacies p ON mo.pharmacy_id = p.id WHERE p.pharmacist_id = ? AND mo.status = 'pending'");
    $stmt->execute([$user['id']]);
    $pendingOrders = $stmt->fetchColumn();
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
        <!-- زر الطلبات المعلقة -->
        <a href="orders.php?status=pending" class="top-btn">
            <i class="fas fa-shopping-cart"></i>
            <span>الطلبات</span>
            <?php if ($pendingOrders > 0): ?>
                <span class="badge badge-danger"><?= $pendingOrders ?></span>
            <?php endif; ?>
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
        
        <!-- NFC (تحت التطوير) -->
        <button class="top-btn dev-btn" onclick="showDevMessage('NFC')">
            <i class="fas fa-id-card"></i>
            <span>NFC</span>
        </button>
    </div>
</div>

<!-- النافذة الجانبية للإشعارات -->
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
                            <i class="fas fa-<?= $n['type'] == 'order' ? 'shopping-cart' : 'bell' ?>"></i>
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
</div>

<!-- نافذة تحت التطوير -->
<div class="modal" id="devModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fas fa-tools"></i> تحت التطوير</h3>
            <button class="close-modal" onclick="closeModal('devModal')">&times;</button>
        </div>
        <div class="modal-body text-center">
            <i class="fas fa-code-branch" style="font-size: 60px; color: var(--warning); margin: 20px;"></i>
            <h2 id="devFeature">هذه الميزة</h2>
            <p style="color: var(--gray); margin: 1rem 0;">قيد التطوير وستتوفر قريباً</p>
            <button class="btn btn-primary" onclick="closeModal('devModal')">موافق</button>
        </div>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('active');
}

function markNotificationRead(id) {
    fetch('../api/mark_notification_read.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({id: id})
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

function markAllNotificationsRead() {
    fetch('../api/mark_all_notifications_read.php', {method: 'POST'})
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
</script>