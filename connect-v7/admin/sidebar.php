<?php
/**
 * admin/sidebar.php - القائمة الجانبية للأدمن
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
}

// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);

// جلب الإحصائيات للشارات
$pendingApprovals = 0;
$pendingChanges = 0;
$pendingOrders = 0;
$lowStock = 0;

if (isset($pdo)) {
    $pendingApprovals = $pdo->query("SELECT COUNT(*) FROM approval_requests WHERE status = 'pending'")->fetchColumn();
    $pendingChanges = $pdo->query("SELECT COUNT(*) FROM profile_changes WHERE status = 'pending'")->fetchColumn();
    
    // إحصائيات إضافية
    $totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalDoctors = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();
    $totalPharmacists = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pharmacist'")->fetchColumn();
    $totalPatients = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <!-- صورة الأدمن -->
        <div class="user-avatar" onclick="openAvatarModal()" style="cursor: pointer;">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة الأدمن">
            <?php else: ?>
                <i class="fas fa-crown"></i>
            <?php endif; ?>
        </div>
        
        <!-- معلومات الأدمن -->
        <div class="user-info">
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <p style="font-size: 0.9rem; opacity: 0.9;">مدير النظام</p>
            <div class="user-code" onclick="copyToClipboard('<?= $user['user_code'] ?>')" style="cursor: pointer;" title="انسخ الكود">
                <?= $user['user_code'] ?> <i class="fas fa-copy" style="font-size: 0.7rem;"></i>
            </div>
        </div>
    </div>
    
    <!-- روابط التنقل -->
    <ul class="nav-links">
        <!-- لوحة التحكم -->
        <li>
            <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>لوحة التحكم</span>
            </a>
        </li>
        
        <!-- المستخدمين -->
        <li>
            <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>المستخدمين</span>
                <span class="badge"><?= $totalUsers ?? 0 ?></span>
            </a>
        </li>
        
        <!-- طلبات التأكيد -->
        <li>
            <a href="pending_approvals.php" class="<?= $current_page == 'pending_approvals.php' ? 'active' : '' ?>">
                <i class="fas fa-clock"></i>
                <span>طلبات التأكيد</span>
                <?php if ($pendingApprovals > 0): ?>
                    <span class="badge badge-danger"><?= $pendingApprovals ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- طلبات التعديل -->
        <li>
            <a href="profile_changes.php" class="<?= $current_page == 'profile_changes.php' ? 'active' : '' ?>">
                <i class="fas fa-edit"></i>
                <span>طلبات التعديل</span>
                <?php if ($pendingChanges > 0): ?>
                    <span class="badge badge-warning"><?= $pendingChanges ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- الأطباء -->
        <li>
            <a href="doctors.php" class="<?= $current_page == 'doctors.php' ? 'active' : '' ?>">
                <i class="fas fa-user-md"></i>
                <span>الأطباء</span>
                <span class="badge"><?= $totalDoctors ?? 0 ?></span>
            </a>
        </li>
        
        <!-- المرضى -->
        <li>
            <a href="patients.php" class="<?= $current_page == 'patients.php' ? 'active' : '' ?>">
                <i class="fas fa-user"></i>
                <span>المرضى</span>
                <span class="badge"><?= $totalPatients ?? 0 ?></span>
            </a>
        </li>
        
        <!-- الصيادلة -->
        <li>
            <a href="pharmacists.php" class="<?= $current_page == 'pharmacists.php' ? 'active' : '' ?>">
                <i class="fas fa-store"></i>
                <span>الصيادلة</span>
                <span class="badge"><?= $totalPharmacists ?? 0 ?></span>
            </a>
        </li>
        
        <!-- سجلات النظام -->
        <li>
            <a href="logs.php" class="<?= $current_page == 'logs.php' ? 'active' : '' ?>">
                <i class="fas fa-history"></i>
                <span>سجلات النظام</span>
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
    
    <!-- معلومات النظام -->
    <div style="padding: 1rem; margin: 1rem; background: rgba(255,255,255,0.1); border-radius: 12px; text-align: center; font-size: 0.8rem;">
        <div>CONNECT+ v2.0</div>
        <div style="margin-top: 0.3rem;">© 2026 جميع الحقوق محفوظة</div>
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
    
    fetch('../api/admin/update_avatar.php', {
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