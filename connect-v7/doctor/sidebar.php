<?php
/**
 * doctor/sidebar.php - القائمة الجانبية للطبيب
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
    $doctor = getDoctorData($pdo, $user['id']);
}

// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);

// جلب عدد طلبات المواعيد المعلقة
$pendingAppointments = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status = 'pending'");
    $stmt->execute([$user['id']]);
    $pendingAppointments = $stmt->fetchColumn();
}

// جلب عدد المرضى
$patientsCount = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ?");
    $stmt->execute([$user['id']]);
    $patientsCount = $stmt->fetchColumn();
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
        <!-- صورة الطبيب -->
        <div class="user-avatar" onclick="openAvatarModal()" style="cursor: pointer;">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة الطبيب">
            <?php else: ?>
                <i class="fas fa-user-md"></i>
            <?php endif; ?>
        </div>
        
        <!-- معلومات الطبيب -->
        <div class="user-info">
            <h3>د. <?= htmlspecialchars($user['full_name']) ?></h3>
            <p style="font-size: 0.9rem; opacity: 0.9;">
                <i class="fas fa-stethoscope"></i> <?= htmlspecialchars($doctor['specialties'] ?: 'طبيب') ?>
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
        
        <!-- المرضى -->
        <li>
            <a href="patients.php" class="<?= $current_page == 'patients.php' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span>المرضى</span>
                <?php if ($patientsCount > 0): ?>
                    <span class="badge"><?= $patientsCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- الأجندة (المواعيد) -->
        <li>
            <a href="agenda.php" class="<?= $current_page == 'agenda.php' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i>
                <span>الأجندة</span>
                <?php if ($pendingAppointments > 0): ?>
                    <span class="badge badge-danger"><?= $pendingAppointments ?></span>
                <?php endif; ?>
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
        
        <!-- الوصفات -->
        <li>
            <a href="prescriptions.php" class="<?= $current_page == 'prescriptions.php' ? 'active' : '' ?>">
                <i class="fas fa-prescription"></i>
                <span>الوصفات</span>
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
    
    <!-- حالة التوفر -->
    <div style="padding: 1rem; margin: 1rem; background: rgba(255,255,255,0.1); border-radius: 12px; text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; margin-bottom: 0.5rem;">
            <i class="fas fa-<?= $doctor['is_available'] ? 'check-circle' : 'clock' ?>" style="color: <?= $doctor['is_available'] ? 'var(--success)' : 'var(--warning)' ?>;"></i>
            <span><?= $doctor['is_available'] ? 'متاح الآن' : 'غير متاح' ?></span>
        </div>
        
        <button class="btn btn-sm" onclick="toggleAvailability()" style="background: rgba(255,255,255,0.2); color: white; width: 100%;">
            <i class="fas fa-<?= $doctor['is_available'] ? 'toggle-on' : 'toggle-off' ?>"></i>
            تغيير الحالة
        </button>
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
// معاينة الصورة قبل الرفع
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
    
    fetch('../api/doctor/update_avatar.php', {
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

function toggleAvailability() {
    fetch('../api/doctor/toggle_availability.php', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showToast('تم تحديث الحالة', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    });
}
</script>