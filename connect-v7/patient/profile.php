<?php
/**
 * patient/profile.php - الملف الشخصي للمريض
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم مريض
requirePatient($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$patient = getPatientData($pdo, $user['id']);

// ============================================================
// جلب طلبات التعديل المعلقة
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM profile_changes WHERE user_id = ? AND status = 'pending' ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$pendingChanges = $stmt->fetchAll();

// ============================================================
// جلب آخر نشاط
// ============================================================
$stmt = $pdo->prepare("SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$user['id']]);
$activities = $stmt->fetchAll();

// قائمة فصائل الدم
$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
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
            margin-bottom: 1rem;
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
                <h1 class="page-title"><i class="fas fa-user-circle"></i> الملف الشخصي</h1>
                <button class="btn btn-primary" onclick="openSideModal('editProfileSideModal')">
                    <i class="fas fa-edit"></i> تعديل الملف
                </button>
            </div>
            
            <!-- رأس الملف الشخصي -->
            <div class="profile-header">
                <div class="profile-avatar-large" onclick="openSideModal('avatarModal')">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة المستخدم">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="profile-code"><?= $user['user_code'] ?></div>
                    <div class="profile-badges">
                        <span class="badge badge-primary">مريض</span>
                        <?php if ($user['is_verified']): ?>
                            <span class="badge badge-success">موثق</span>
                        <?php else: ?>
                            <span class="badge badge-warning">غير موثق</span>
                        <?php endif; ?>
                        <?php if (!empty($patient['blood_type'])): ?>
                            <span class="badge" style="background: var(--danger); color: white;">فصيلة <?= $patient['blood_type'] ?></span>
                        <?php endif; ?>
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
                            <span><?= htmlspecialchars($user['full_name']) ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('full_name', '<?= htmlspecialchars($user['full_name']) ?>', 'الاسم الكامل')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">تاريخ الميلاد</span>
                        <div class="info-value">
                            <span><?= $user['birth_date'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('birth_date', '<?= $user['birth_date'] ?>', 'تاريخ الميلاد', 'date')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الجنس</span>
                        <div class="info-value">
                            <span><?= $user['gender'] == 'male' ? 'ذكر' : ($user['gender'] == 'female' ? 'أنثى' : 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openGenderEdit()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">رقم البطاقة</span>
                        <div class="info-value">
                            <span><?= $user['national_id'] ?: 'غير محدد' ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- معلومات الاتصال -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-address-card"></i> معلومات الاتصال
                </h3>
                
                <div class="info-grid">
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
                        <span class="info-label">العنوان</span>
                        <div class="info-value">
                            <span><?= $user['address'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('address', '<?= htmlspecialchars($user['address']) ?>', 'العنوان', 'textarea')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الولاية</span>
                        <div class="info-value">
                            <span><?= $user['city'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('city', '<?= htmlspecialchars($user['city']) ?>', 'الولاية')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- جهة اتصال الطوارئ -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-phone-alt" style="color: var(--danger);"></i> جهة اتصال الطوارئ
                </h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">الاسم</span>
                        <div class="info-value">
                            <span><?= $patient['emergency_name'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('emergency_name', '<?= htmlspecialchars($patient['emergency_name']) ?>', 'اسم جهة الاتصال')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">رقم الهاتف</span>
                        <div class="info-value">
                            <span><?= $patient['emergency_phone'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('emergency_phone', '<?= $patient['emergency_phone'] ?>', 'رقم هاتف الطوارئ', 'tel')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- معلومات طبية -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-heartbeat"></i> معلومات طبية
                </h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">فصيلة الدم</span>
                        <div class="info-value">
                            <span><?= $patient['blood_type'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openBloodTypeEdit()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الأمراض المزمنة</span>
                        <div class="info-value">
                            <span><?= $patient['chronic_diseases'] ?: 'لا يوجد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('chronic_diseases', '<?= htmlspecialchars($patient['chronic_diseases']) ?>', 'الأمراض المزمنة', 'textarea')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">الحساسيات</span>
                        <div class="info-value">
                            <span><?= $patient['allergies'] ?: 'لا توجد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('allergies', '<?= htmlspecialchars($patient['allergies']) ?>', 'الحساسيات', 'textarea')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
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
                    
                    <?php if ($user['birth_certificate']): ?>
                        <a href="../<?= $user['birth_certificate'] ?>" target="_blank" class="document-card">
                            <i class="fas fa-birthday-cake"></i>
                            <div>شهادة الميلاد</div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($user['postal_receipt']): ?>
                        <a href="../<?= $user['postal_receipt'] ?>" target="_blank" class="document-card">
                            <i class="fas fa-receipt"></i>
                            <div>الوصل البريدي</div>
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
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label>تاريخ الميلاد</label>
                    <input type="date" name="birth_date" class="form-control" value="<?= $user['birth_date'] ?>">
                </div>
                
                <div class="form-group">
                    <label>الجنس</label>
                    <select name="gender" class="form-control">
                        <option value="">اختر</option>
                        <option value="male" <?= $user['gender'] == 'male' ? 'selected' : '' ?>>ذكر</option>
                        <option value="female" <?= $user['gender'] == 'female' ? 'selected' : '' ?>>أنثى</option>
                    </select>
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
                    <label>العنوان</label>
                    <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($user['address']) ?>">
                </div>
                
                <div class="form-group">
                    <label>الولاية</label>
                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city']) ?>">
                </div>
                
                <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">جهة اتصال الطوارئ</h4>
                
                <div class="form-group">
                    <label>الاسم</label>
                    <input type="text" name="emergency_name" class="form-control" value="<?= htmlspecialchars($patient['emergency_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="tel" name="emergency_phone" class="form-control" value="<?= $patient['emergency_phone'] ?>">
                </div>
                
                <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">معلومات طبية</h4>
                
                <div class="form-group">
                    <label>فصيلة الدم</label>
                    <select name="blood_type" class="form-control">
                        <option value="">اختر</option>
                        <?php foreach ($bloodTypes as $bt): ?>
                            <option value="<?= $bt ?>" <?= $patient['blood_type'] == $bt ? 'selected' : '' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>الأمراض المزمنة</label>
                    <textarea name="chronic_diseases" class="form-control" rows="3"><?= htmlspecialchars($patient['chronic_diseases']) ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>الحساسيات</label>
                    <textarea name="allergies" class="form-control" rows="3"><?= htmlspecialchars($patient['allergies']) ?></textarea>
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
         النافذة الجانبية لتغيير الصورة (مكررة من sidebar لكن نضيفها هنا للاستخدام)
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
        } else if (type === 'date') {
            inputHtml = `
                <label>${displayName}</label>
                <input type="date" name="new_value" class="form-control" value="${currentValue || ''}">
            `;
        } else if (type === 'email') {
            inputHtml = `
                <label>${displayName}</label>
                <input type="email" name="new_value" class="form-control" value="${currentValue || ''}">
            `;
        } else if (type === 'tel') {
            inputHtml = `
                <label>${displayName}</label>
                <input type="tel" name="new_value" class="form-control" value="${currentValue || ''}">
            `;
        } else {
            inputHtml = `
                <label>${displayName}</label>
                <input type="text" name="new_value" class="form-control" value="${currentValue || ''}">
            `;
        }
        
        document.getElementById('editFieldContainer').innerHTML = inputHtml;
        openSideModal('editFieldSideModal');
    }
    
    // ============================================================
    // فتح تعديل الجنس
    // ============================================================
    function openGenderEdit() {
        document.getElementById('editFieldTitle').textContent = 'تعديل الجنس';
        document.getElementById('editFieldName').value = 'gender';
        
        const currentGender = '<?= $user['gender'] ?>';
        
        document.getElementById('editFieldContainer').innerHTML = `
            <label>الجنس</label>
            <select name="new_value" class="form-control">
                <option value="">اختر</option>
                <option value="male" ${currentGender === 'male' ? 'selected' : ''}>ذكر</option>
                <option value="female" ${currentGender === 'female' ? 'selected' : ''}>أنثى</option>
            </select>
        `;
        
        openSideModal('editFieldSideModal');
    }
    
    // ============================================================
    // فتح تعديل فصيلة الدم
    // ============================================================
    function openBloodTypeEdit() {
        document.getElementById('editFieldTitle').textContent = 'تعديل فصيلة الدم';
        document.getElementById('editFieldName').value = 'blood_type';
        
        const currentBlood = '<?= $patient['blood_type'] ?>';
        const bloodTypes = <?= json_encode($bloodTypes) ?>;
        
        let options = '<option value="">اختر</option>';
        bloodTypes.forEach(bt => {
            options += `<option value="${bt}" ${currentBlood === bt ? 'selected' : ''}>${bt}</option>`;
        });
        
        document.getElementById('editFieldContainer').innerHTML = `
            <label>فصيلة الدم</label>
            <select name="new_value" class="form-control">
                ${options}
            </select>
        `;
        
        openSideModal('editFieldSideModal');
    }
    
    // ============================================================
    // إرسال تعديل حقل واحد
    // ============================================================
    function submitFieldChange(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editFieldForm'));
        
        fetch('../api/patient/request_field_change.php', {
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
        
        fetch('../api/patient/request_profile_changes.php', {
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
        
        fetch('../api/patient/update_avatar.php', {
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