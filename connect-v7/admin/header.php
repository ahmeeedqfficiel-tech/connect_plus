<?php
/**
 * admin/header.php - الشريط العلوي للأدمن
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
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
$pendingApprovals = isset($pdo) ? $pdo->query("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'")->fetchColumn() : 0;
$pendingChanges = isset($pdo) ? $pdo->query("SELECT COUNT(*) FROM profile_changes WHERE status = 'pending'")->fetchColumn() : 0;
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
        <!-- طلبات التأكيد -->
        <a href="pending_approvals.php" class="top-btn">
            <i class="fas fa-user-check"></i>
            <span>طلبات التأكيد</span>
            <?php if ($pendingApprovals > 0): ?>
                <span class="badge badge-danger"><?= $pendingApprovals ?></span>
            <?php endif; ?>
        </a>
        
        <!-- طلبات التعديل -->
        <a href="profile_changes.php" class="top-btn">
            <i class="fas fa-edit"></i>
            <span>طلبات التعديل</span>
            <?php if ($pendingChanges > 0): ?>
                <span class="badge badge-warning"><?= $pendingChanges ?></span>
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
        
        <!-- إضافة مستخدم -->
        <button class="top-btn" onclick="openSideModal('addUserSideModal')">
            <i class="fas fa-user-plus"></i>
            <span>إضافة مستخدم</span>
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
                            <i class="fas fa-<?= $n['type'] == 'approval' ? 'check-circle' : 'bell' ?>"></i>
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

<!-- النافذة الجانبية لإضافة مستخدم -->
<div class="side-modal" id="addUserSideModal">
    <div class="side-modal-header">
        <h3><i class="fas fa-user-plus"></i> إضافة مستخدم جديد</h3>
        <button class="close-side-modal" onclick="closeSideModal('addUserSideModal')">&times;</button>
    </div>
    <div class="side-modal-body">
        <form method="post" action="add_user.php" enctype="multipart/form-data">
            <div class="form-group">
                <label>الدور</label>
                <select name="role" id="userRoleSelect" class="form-control" required onchange="toggleRoleFields()">
                    <option value="patient">مريض</option>
                    <option value="doctor">طبيب</option>
                    <option value="pharmacist">صيدلي</option>
                    <option value="admin">أدمن</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>الاسم الكامل</label>
                <input type="text" name="full_name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label>رقم الهاتف</label>
                <input type="text" name="phone" class="form-control">
            </div>
            
            <!-- حقول المريض -->
            <div id="patientFields" style="display: block;">
                <h4 style="margin: 1rem 0;">معلومات إضافية للمريض</h4>
                <div class="form-group">
                    <label>جهة اتصال الطوارئ (الاسم)</label>
                    <input type="text" name="emergency_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>هاتف الطوارئ</label>
                    <input type="text" name="emergency_phone" class="form-control">
                </div>
            </div>
            
            <!-- حقول الطبيب -->
            <div id="doctorFields" style="display: none;">
                <h4 style="margin: 1rem 0;">معلومات الطبيب</h4>
                <div class="form-group">
                    <label>التخصص</label>
                    <input type="text" name="specialties" class="form-control">
                </div>
                <div class="form-group">
                    <label>رقم الترخيص</label>
                    <input type="text" name="license_number" class="form-control">
                </div>
                <div class="form-group">
                    <label>مكان العمل</label>
                    <input type="text" name="workplace_name" class="form-control">
                </div>
            </div>
            
            <!-- حقول الصيدلي -->
            <div id="pharmacistFields" style="display: none;">
                <h4 style="margin: 1rem 0;">معلومات الصيدلي</h4>
                <div class="form-group">
                    <label>اسم الصيدلية</label>
                    <input type="text" name="pharmacy_name" class="form-control">
                </div>
                <div class="form-group">
                    <label>رقم ترخيص الصيدلية</label>
                    <input type="text" name="pharmacy_license" class="form-control">
                </div>
            </div>
            
            <button type="submit" name="add_user" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                <i class="fas fa-user-plus"></i> إضافة المستخدم
            </button>
        </form>
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

function toggleRoleFields() {
    const role = document.getElementById('userRoleSelect').value;
    
    document.getElementById('patientFields').style.display = role === 'patient' ? 'block' : 'none';
    document.getElementById('doctorFields').style.display = role === 'doctor' ? 'block' : 'none';
    document.getElementById('pharmacistFields').style.display = role === 'pharmacist' ? 'block' : 'none';
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