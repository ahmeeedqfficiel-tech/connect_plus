<?php
/**
 * doctor/profile.php - الملف الشخصي للطبيب
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم طبيب
requireDoctor($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$doctor = getDoctorData($pdo, $user['id']);

// ============================================================
// جلب طلبات التعديل المعلقة
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM profile_changes WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$pendingChanges = $stmt->fetchAll();

// ============================================================
// جلب آخر النشاطات
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$activities = $stmt->fetchAll();

// قائمة التخصصات المقترحة
$specialties = [
    'قلب وأوعية دموية',
    'أمراض جلدية',
    'جراحة عامة',
    'أطفال',
    'نساء وتوليد',
    'عظام',
    'أنف وأذن وحنجرة',
    'عيون',
    'أسنان',
    'نفسية',
    'أعصاب',
    'مسالك بولية',
    'غدد صماء',
    'جهاز هضمي'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .profile-header {
            display: flex;
            gap: 2rem;
            align-items: center;
            margin-bottom: 2rem;
            background: white;
            padding: 2rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            flex-wrap: wrap;
        }
        
        body.dark-mode .profile-header {
            background: #1E1E1E;
        }
        
        .profile-avatar-large {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            flex-shrink: 0;
            border: 4px solid var(--primary-light);
            overflow: hidden;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .profile-avatar-large:hover {
            transform: scale(1.05);
            border-color: var(--accent);
        }
        
        .profile-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .profile-code {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .profile-badges {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .profile-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-md);
            transition: var(--transition);
        }
        
        .info-item:hover {
            background: var(--primary-soft);
            transform: translateY(-2px);
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--gray);
            margin-bottom: 0.3rem;
        }
        
        .info-value {
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .edit-field-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 0.9rem;
            opacity: 0.5;
            transition: var(--transition);
        }
        
        .info-item:hover .edit-field-btn {
            opacity: 1;
        }
        
        .pending-change {
            background: rgba(244, 162, 97, 0.1);
            border-right: 4px solid var(--warning);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .document-card {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 1rem;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
        }
        
        .document-card:hover {
            background: var(--primary-soft);
            transform: translateY(-5px);
        }
        
        .document-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .availability-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        
        .working-hours {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <!-- تضمين القائمة الجانبية -->
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- تضمين الشريط العلوي -->
            <?php include 'header.php'; ?>
            
            <!-- رسائل Toast -->
            <?php displayToast(); ?>
            
            <div class="card-header">
                <h1 class="page-title"><i class="fas fa-user-md"></i> الملف الشخصي</h1>
                <button class="btn btn-primary" onclick="openSideModal('editProfileSideModal')">
                    <i class="fas fa-edit"></i> تعديل الملف
                </button>
            </div>
            
            <!-- رأس الملف الشخصي -->
            <div class="profile-header">
                <div class="profile-avatar-large" onclick="openSideModal('avatarModal')">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة الطبيب">
                    <?php else: ?>
                        <i class="fas fa-user-md"></i>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <div class="profile-name">د. <?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="profile-code"><?= $user['user_code'] ?></div>
                    <div class="profile-badges">
                        <span class="badge badge-primary">طبيب</span>
                        <?php if ($user['is_verified']): ?>
                            <span class="badge badge-success">موثق</span>
                        <?php else: ?>
                            <span class="badge badge-warning">غير موثق</span>
                        <?php endif; ?>
                        <span class="badge <?= $doctor['is_available'] ? 'badge-success' : 'badge-danger' ?>">
                            <?= $doctor['is_available'] ? 'متاح' : 'غير متاح' ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- طلبات التعديل المعلقة -->
            <?php if (!empty($pendingChanges)): ?>
                <div class="info-card">
                    <h3 class="card-title"><i class="fas fa-clock"></i> طلبات تعديل معلقة</h3>
                    <?php foreach ($pendingChanges as $change): ?>
                        <div class="pending-change">
                            <div style="display: flex; justify-content: space-between;">
                                <strong><?= $change['field_name'] ?></strong>
                                <small><?= timeAgo($change['created_at']) ?></small>
                            </div>
                            <p style="margin-top: 0.5rem;">
                                من: "<?= htmlspecialchars($change['old_value'] ?: 'فارغ') ?>" ← 
                                إلى: "<?= htmlspecialchars($change['new_value']) ?>"
                            </p>
                            <span class="badge badge-warning">قيد المراجعة</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- المعلومات الشخصية -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-user"></i> المعلومات الشخصية
                </h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">الاسم الكامل</span>
                        <div class="info-value">
                            <span>د. <?= htmlspecialchars($user['full_name']) ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('full_name', '<?= htmlspecialchars($user['full_name']) ?>', 'الاسم الكامل')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">البريد الإلكتروني</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($user['email']) ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('email', '<?= htmlspecialchars($user['email']) ?>', 'البريد الإلكتروني', 'email')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">رقم الهاتف</span>
                        <div class="info-value">
                            <span><?= $user['phone'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('phone', '<?= $user['phone'] ?>', 'رقم الهاتف', 'tel')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">المدينة</span>
                        <div class="info-value">
                            <span><?= $user['city'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('city', '<?= htmlspecialchars($user['city']) ?>', 'المدينة')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- المعلومات المهنية -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-briefcase"></i> المعلومات المهنية
                </h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">التخصص</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($doctor['specialties'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('specialties', '<?= htmlspecialchars($doctor['specialties']) ?>', 'التخصص')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الدرجة العلمية</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($doctor['degree'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('degree', '<?= htmlspecialchars($doctor['degree']) ?>', 'الدرجة العلمية')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">رقم الترخيص</span>
                        <div class="info-value">
                            <span><?= $doctor['license_number'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('license_number', '<?= $doctor['license_number'] ?>', 'رقم الترخيص')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">مكان العمل</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($doctor['workplace_name'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('workplace_name', '<?= htmlspecialchars($doctor['workplace_name']) ?>', 'مكان العمل')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">عنوان العمل</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($doctor['workplace_address'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('workplace_address', '<?= htmlspecialchars($doctor['workplace_address']) ?>', 'عنوان العمل', 'textarea')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">رسوم الكشف</span>
                        <div class="info-value">
                            <span><?= $doctor['consultation_fees'] ?: 'غير محدد' ?> دج</span>
                            <button class="edit-field-btn" onclick="openFieldEdit('consultation_fees', '<?= $doctor['consultation_fees'] ?>', 'رسوم الكشف', 'number')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- ساعات العمل -->
                <div style="margin-top: 1.5rem;">
                    <h4 style="margin-bottom: 1rem;">ساعات العمل</h4>
                    
                    <div class="working-hours">
                        <div class="info-item">
                            <span class="info-label">من الساعة</span>
                            <div class="info-value">
                                <span><?= $doctor['available_from'] ?: 'غير محدد' ?></span>
                                <button class="edit-field-btn" onclick="openFieldEdit('available_from', '<?= $doctor['available_from'] ?>', 'من الساعة', 'time')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <span class="info-label">إلى الساعة</span>
                            <div class="info-value">
                                <span><?= $doctor['available_to'] ?: 'غير محدد' ?></span>
                                <button class="edit-field-btn" onclick="openFieldEdit('available_to', '<?= $doctor['available_to'] ?>', 'إلى الساعة', 'time')">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-item" style="margin-top: 1rem;">
                        <span class="info-label">أيام العمل</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($doctor['working_days'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('working_days', '<?= htmlspecialchars($doctor['working_days']) ?>', 'أيام العمل', 'textarea')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- حالة التوفر -->
                <div class="availability-toggle">
                    <span>
                        <strong>حالة التوفر</strong>
                        <span style="color: var(--gray); font-size: 0.9rem; display: block;">
                            عند تفعيل "متاح"، ستظهر في نتائج بحث المرضى
                        </span>
                    </span>
                    <label class="switch">
                        <input type="checkbox" id="availabilityToggle" <?= $doctor['is_available'] ? 'checked' : '' ?> onchange="toggleAvailability()">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>
            
            <!-- المستندات -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-file-alt"></i> المستندات
                </h3>
                
                <div class="documents-grid">
                    <?php if ($user['id_card_image']): ?>
                        <a href="../<?= $user['id_card_image'] ?>" target="_blank" class="document-card">
                            <i class="fas fa-id-card"></i>
                            <div>بطاقة التعريف</div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($user['license_image']): ?>
                        <a href="../<?= $user['license_image'] ?>" target="_blank" class="document-card">
                            <i class="fas fa-file-contract"></i>
                            <div>رخصة المزاولة</div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($user['degree_certificate']): ?>
                        <a href="../<?= $user['degree_certificate'] ?>" target="_blank" class="document-card">
                            <i class="fas fa-graduation-cap"></i>
                            <div>شهادة التخرج</div>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- آخر النشاطات -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-history"></i> آخر النشاطات
                </h3>
                
                <?php if (empty($activities)): ?>
                    <p class="text-center" style="padding: 2rem;">لا توجد نشاطات</p>
                <?php else: ?>
                    <?php foreach ($activities as $act): ?>
                        <div class="info-item" style="margin-bottom: 0.5rem;">
                            <div>
                                <strong><?= $act['action'] ?></strong>
                                <div style="font-size: 0.9rem; color: var(--gray);"><?= $act['description'] ?></div>
                            </div>
                            <small><?= timeAgo($act['created_at']) ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتعديل الملف الشخصي
         ============================================================ -->
    <div class="side-modal" id="editProfileSideModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-edit"></i> تعديل الملف الشخصي</h3>
            <button class="close-side-modal" onclick="closeSideModal('editProfileSideModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form id="profileEditForm" onsubmit="submitProfileChanges(event)">
                <h4 style="margin-bottom: 1rem; color: var(--primary);">معلومات شخصية</h4>
                
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>">
                </div>
                
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" value="<?= $user['phone'] ?>">
                </div>
                
                <div class="form-group">
                    <label>المدينة</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city']) ?>">
                </div>
                
                <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">معلومات مهنية</h4>
                
                <div class="form-group">
                    <label>التخصص</label>
                    <select name="specialties" class="form-control">
                        <option value="">اختر التخصص</option>
                        <?php foreach ($specialties as $spec): ?>
                            <option value="<?= $spec ?>" <?= $doctor['specialties'] == $spec ? 'selected' : '' ?>><?= $spec ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>الدرجة العلمية</label>
                    <input type="text" name="degree" class="form-control" value="<?= htmlspecialchars($doctor['degree']) ?>">
                </div>
                
                <div class="form-group">
                    <label>رقم الترخيص</label>
                    <input type="text" name="license_number" class="form-control" value="<?= $doctor['license_number'] ?>">
                </div>
                
                <div class="form-group">
                    <label>مكان العمل</label>
                    <input type="text" name="workplace_name" class="form-control" value="<?= htmlspecialchars($doctor['workplace_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label>عنوان العمل</label>
                    <textarea name="workplace_address" class="form-control" rows="2"><?= htmlspecialchars($doctor['workplace_address']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>رسوم الكشف (دج)</label>
                    <input type="number" name="consultation_fees" class="form-control" value="<?= $doctor['consultation_fees'] ?>" step="0.01">
                </div>
                
                <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">ساعات العمل</h4>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>من الساعة</label>
                        <input type="time" name="available_from" class="form-control" value="<?= $doctor['available_from'] ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>إلى الساعة</label>
                        <input type="time" name="available_to" class="form-control" value="<?= $doctor['available_to'] ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>أيام العمل</label>
                    <textarea name="working_days" class="form-control" rows="2" placeholder="مثال: الأحد - الخميس"><?= htmlspecialchars($doctor['working_days']) ?></textarea>
                </div>
                
                <div class="alert alert-info" style="margin: 1rem 0;">
                    <i class="fas fa-info-circle"></i>
                    سيتم إرسال طلب التعديل إلى الإدارة للموافقة عليه.
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> إرسال طلب التعديل
                </button>
            </form>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتعديل حقل واحد
         ============================================================ -->
    <div class="side-modal" id="editFieldSideModal">
        <div class="side-modal-header">
            <h3 id="editFieldTitle">تعديل</h3>
            <button class="close-side-modal" onclick="closeSideModal('editFieldSideModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form id="editFieldForm" onsubmit="submitFieldChange(event)">
                <input type="hidden" name="field_name" id="editFieldName">
                
                <div class="form-group" id="editFieldContainer">
                    <!-- يتم ملؤها بالجافاسكريبت -->
                </div>
                
                <div class="alert alert-info" style="margin: 1rem 0;">
                    <i class="fas fa-info-circle"></i>
                    سيتم إرسال طلب التعديل إلى الإدارة للموافقة عليه.
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> إرسال طلب التعديل
                </button>
            </form>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتغيير الصورة
         ============================================================ -->
    <div class="side-modal" id="avatarModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-camera"></i> تغيير الصورة الشخصية</h3>
            <button class="close-side-modal" onclick="closeSideModal('avatarModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form id="avatarForm" enctype="multipart/form-data">
                <div class="upload-area" onclick="document.getElementById('profileAvatarInput').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>انقر لاختيار صورة جديدة</p>
                    <p style="font-size: 0.8rem; color: var(--gray);">أقصى حجم: 5 ميجابايت</p>
                    <input type="file" id="profileAvatarInput" name="avatar" accept="image/*" style="display:none;">
                </div>
                
                <div id="avatarPreview" style="text-align: center; margin: 1rem 0; display: none;">
                    <img src="" alt="معاينة" style="max-width: 200px; max-height: 200px; border-radius: 10px;">
                </div>
                
                <button type="button" class="btn btn-primary" onclick="uploadProfileAvatar()" style="width: 100%;">
                    <i class="fas fa-upload"></i> رفع الصورة
                </button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // فتح تعديل حقل معين
    // ============================================================
    function openFieldEdit(fieldName, currentValue, displayName, type = 'text') {
        document.getElementById('editFieldTitle').textContent = `تعديل ${displayName}`;
        document.getElementById('editFieldName').value = fieldName;
        
        let inputHtml = '';
        
        if (type === 'textarea') {
            inputHtml = `
                <label>${displayName}</label>
                <textarea name="new_value" class="form-control" rows="4">${currentValue || ''}</textarea>
            `;
        } else if (type === 'time') {
            inputHtml = `
                <label>${displayName}</label>
                <input type="time" name="new_value" class="form-control" value="${currentValue || ''}">
            `;
        } else if (type === 'number') {
            inputHtml = `
                <label>${displayName}</label>
                <input type="number" name="new_value" class="form-control" step="0.01" value="${currentValue || ''}">
            `;
        } else {
            inputHtml = `
                <label>${displayName}</label>
                <input type="${type}" name="new_value" class="form-control" value="${currentValue || ''}">
            `;
        }
        
        document.getElementById('editFieldContainer').innerHTML = inputHtml;
        openSideModal('editFieldSideModal');
    }
    
    // ============================================================
    // إرسال تعديل حقل واحد
    // ============================================================
    function submitFieldChange(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editFieldForm'));
        
        fetch('../api/doctor/request_field_change.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم إرسال طلب التعديل بنجاح', 'success');
                closeSideModal('editFieldSideModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    }
    
    // ============================================================
    // إرسال تعديل الملف الكامل
    // ============================================================
    function submitProfileChanges(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('profileEditForm'));
        
        fetch('../api/doctor/request_profile_changes.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم إرسال طلب التعديل بنجاح', 'success');
                closeSideModal('editProfileSideModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    }
    
    // ============================================================
    // رفع الصورة الشخصية
    // ============================================================
    function uploadProfileAvatar() {
        const input = document.getElementById('profileAvatarInput');
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
                closeSideModal('avatarModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'فشل تحديث الصورة', 'error');
            }
        });
    }
    
    // ============================================================
    // تبديل حالة التوفر
    // ============================================================
    function toggleAvailability() {
        const isAvailable = document.getElementById('availabilityToggle').checked;
        
        fetch('../api/doctor/toggle_availability.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({is_available: isAvailable})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم تحديث الحالة', 'success');
            } else {
                showToast('فشل التحديث', 'error');
                document.getElementById('availabilityToggle').checked = !isAvailable;
            }
        });
    }
    
    // معاينة الصورة
    document.getElementById('profileAvatarInput')?.addEventListener('change', function(e) {
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
    </script>
</body>
</html>