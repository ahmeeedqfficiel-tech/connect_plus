<?php
/**
 * pharmacist/sidebar.php - القائمة الجانبية للصيدلي
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
    $pharmacist = getPharmacistData($pdo, $user['id']);
}

// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);

// جلب معرف الصيدلية
$pharmacy_id = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT id FROM pharmacies WHERE pharmacist_id = ?");
    $stmt->execute([$user['id']]);
    $pharmacy_id = $stmt->fetchColumn();
}

// جلب عدد الطلبات المعلقة
$pendingOrders = 0;
if ($pharmacy_id && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = ? AND status = 'pending'");
    $stmt->execute([$pharmacy_id]);
    $pendingOrders = $stmt->fetchColumn();
}

// جلب عدد الأدوية منخفضة المخزون
$lowStock = 0;
if ($pharmacy_id && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM pharmacy_medicines WHERE pharmacy_id = ? AND quantity < 10");
    $stmt->execute([$pharmacy_id]);
    $lowStock = $stmt->fetchColumn();
}

// جلب عدد الرسائل غير المقروءة
$unreadMessages = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->execute([$user['id']]);
    $unreadMessages = $stmt->fetchColumn();
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <!-- صورة الصيدلي -->
        <div class="user-avatar" onclick="openAvatarModal()" style="cursor: pointer;">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة الصيدلي">
            <?php else: ?>
                <i class="fas fa-store"></i>
            <?php endif; ?>
        </div>
        
        <!-- معلومات الصيدلي -->
        <div class="user-info">
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <p style="font-size: 0.9rem; opacity: 0.9;">
                <i class="fas fa-store"></i> <?= htmlspecialchars($pharmacist['pharmacy_name'] ?: 'صيدلي') ?>
            </p>
            <div class="user-code" onclick="copyToClipboard('<?= $user['user_code'] ?>')" style="cursor: pointer;" title="انسخ الكود">
                <?= $user['user_code'] ?> <i class="fas fa-copy" style="font-size: 0.7rem;"></i>
            </div>
        </div>
    </div>
    
    <!-- روابط التنقل -->
    <ul class="nav-links">
        <!-- الرئيسية -->
        <li>
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-home"></i>
                <span>الرئيسية</span>
            </a>
        </li>
        
        <!-- الملف الشخصي -->
        <li>
            <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>">
                <i class="fas fa-user-circle"></i>
                <span>الملف الشخصي</span>
            </a>
        </li>
        
        <!-- المخزون -->
        <li>
            <a href="inventory.php" class="<?= $current_page == 'inventory.php' ? 'active' : '' ?>">
                <i class="fas fa-pills"></i>
                <span>المخزون</span>
                <?php if ($lowStock > 0): ?>
                    <span class="badge badge-warning"><?= $lowStock ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- الطلبات -->
        <li>
            <a href="orders.php" class="<?= $current_page == 'orders.php' ? 'active' : '' ?>">
                <i class="fas fa-shopping-cart"></i>
                <span>الطلبات</span>
                <?php if ($pendingOrders > 0): ?>
                    <span class="badge badge-danger"><?= $pendingOrders ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- الأدوية العامة -->
        <li>
            <a href="medicines.php" class="<?= $current_page == 'medicines.php' ? 'active' : '' ?>">
                <i class="fas fa-database"></i>
                <span>الأدوية</span>
            </a>
        </li>
        
        <!-- الاستشارات (الرسائل) -->
        <li>
            <a href="consultations.php" class="<?= $current_page == 'consultations.php' ? 'active' : '' ?>">
                <i class="fas fa-comments"></i>
                <span>الاستشارات</span>
                <?php if ($unreadMessages > 0): ?>
                    <span class="badge badge-danger"><?= $unreadMessages ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- الإعدادات -->
        <li>
            <a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span>الإعدادات</span>
            </a>
        </li>
        
        <!-- تسجيل الخروج -->
        <li>
            <a href="#" onclick="logout()">
                <i class="fas fa-sign-out-alt"></i>
                <span>تسجيل الخروج</span>
            </a>
        </li>
    </ul>
    
    <!-- معلومات الصيدلية -->
    <div style="padding: 1rem; margin: 1rem; background: rgba(255,255,255,0.1); border-radius: 12px;">
        <div style="font-weight: 600; margin-bottom: 0.5rem;"><?= htmlspecialchars($pharmacist['pharmacy_name']) ?></div>
        <div style="font-size: 0.9rem; opacity: 0.9;">
            <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pharmacist['city'] ?: 'غير محدد') ?>
        </div>
        <?php if ($pharmacist['is_24h']): ?>
            <div style="margin-top: 0.5rem;">
                <span class="badge badge-success">24 ساعة</span>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- نافذة تغيير الصورة الشخصية -->
<div class="side-modal" id="avatarModal">
    <div class="side-modal-header">
        <h3><i class="fas fa-camera"></i> تغيير الصورة الشخصية</h3>
        <button class="close-side-modal" onclick="closeSideModal('avatarModal')">&times;</button>
    </div>
    <div class="side-modal-body">
        <form id="avatarForm" enctype="multipart/form-data">
            <div class="upload-area" onclick="document.getElementById('avatarInput').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>انقر لاختيار صورة جديدة</p>
                <p style="font-size: 0.8rem; color: var(--gray);">أقصى حجم: 5 ميجابايت</p>
                <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display:none;">
            </div>
            
            <div id="avatarPreview" style="text-align: center; margin: 1rem 0; display: none;">
                <img src="" alt="معاينة" style="max-width: 200px; max-height: 200px; border-radius: 10px;">
            </div>
            
            <button type="button" class="btn btn-primary" onclick="uploadAvatar()" style="width: 100%;">
                <i class="fas fa-upload"></i> رفع الصورة
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('avatarInput')?.addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.querySelector('img').src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(this.files[0]);
    }
});

function openAvatarModal() {
    openSideModal('avatarModal');
}

function uploadAvatar() {
    const input = document.getElementById('avatarInput');
    if (!input.files || input.files.length === 0) {
        showToast('اختر صورة أولاً', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('avatar', input.files[0]);
    
    fetch('../api/pharmacist/update_avatar.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('تم تحديث الصورة بنجاح', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.message || 'فشل تحديث الصورة', 'error');
        }
    });
}
</script>