<?php
/**
 * patient/sidebar.php - القائمة الجانبية للمريض
 * CONNECT+ - الإصدار 2.0
 */

// التحقق من وجود المستخدم
if (!isset($user)) {
    require_once __DIR__ . '/../includes/functions.php';
    $user = getCurrentUser($pdo);
    $patient = getPatientData($pdo, $user['id']);
}

// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF']);

// جلب عدد الأدوية النشطة للعرض في الشارة
$activeMedsCount = 0;
if (isset($pdo) && isset($user)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM prescription_medicines pm 
                           JOIN prescriptions p ON pm.prescription_id = p.id 
                           WHERE p.patient_id = ? AND pm.status = 'active'");
    $stmt->execute([$user['id']]);
    $activeMedsCount = $stmt->fetchColumn();
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
        <!-- صورة المستخدم -->
        <div class="user-avatar" onclick="openAvatarModal()" style="cursor: pointer;">
            <?php if (!empty($user['profile_image'])): ?>
                <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة المريض">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
        
        <!-- معلومات المستخدم -->
        <div class="user-info">
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <?php if (!empty($patient['city'])): ?>
                <p style="font-size: 0.9rem; opacity: 0.9;">
                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($patient['city']) ?>
                </p>
            <?php endif; ?>
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
        
        <!-- المتابعات (الأدوية، الوصفات، المواعيد، السجل الطبي) -->
        <li>
            <a href="medications.php" class="<?= $current_page == 'medications.php' ? 'active' : '' ?>">
                <i class="fas fa-chart-line"></i>
                <span>المتابعات</span>
                <?php if ($activeMedsCount > 0): ?>
                    <span class="badge"><?= $activeMedsCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- البحث عن طبيب -->
        <li>
            <a href="find_doctor.php" class="<?= $current_page == 'find_doctor.php' ? 'active' : '' ?>">
                <i class="fas fa-search"></i>
                <span>البحث عن طبيب</span>
            </a>
        </li>
        
        <!-- الصيدلية -->
        <li>
            <a href="pharmacy.php" class="<?= $current_page == 'pharmacy.php' ? 'active' : '' ?>">
                <i class="fas fa-store"></i>
                <span>الصيدلية</span>
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
    
    <!-- زر الطوارئ -->
    <a href="emergency.php" class="emergency-btn">
        <i class="fas fa-ambulance"></i>
        الطوارئ
    </a>
</div>

<!-- نافذة تغيير الصورة الشخصية (تظهر عند النقر على الصورة) -->
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
            
            <!-- معاينة الصورة -->
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
    
    fetch('../api/patient/update_avatar.php', {
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
    })
    .catch(() => {
        showToast('حدث خطأ في الاتصال', 'error');
    });
}
</script>