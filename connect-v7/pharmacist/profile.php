<?php
/**
 * pharmacist/profile.php - الملف الشخصي للصيدلي
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم صيدلي
requirePharmacist($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);
$pharmacist = getPharmacistData($pdo, $user['id']);

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

// قائمة المدن (يمكن جلبها من قاعدة البيانات)
$cities = ['الجزائر', 'وهران', 'قسنطينة', 'عنابة', 'بجاية', 'سطيف', 'البليدة', 'تيبازة', 'بومرداس'];
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
        
        .working-hours-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-md);
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
                <h1 class="page-title"><i class="fas fa-user-circle"></i> الملف الشخصي</h1>
                <button class="btn btn-primary" onclick="openSideModal('editProfileSideModal')">
                    <i class="fas fa-edit"></i> تعديل الملف
                </button>
            </div>
            
            <!-- رأس الملف الشخصي -->
            <div class="profile-header">
                <div class="profile-avatar-large" onclick="openSideModal('avatarModal')">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="../<?= htmlspecialchars($user['profile_image']) ?>" alt="صورة الصيدلي">
                    <?php else: ?>
                        <i class="fas fa-store"></i>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <div class="profile-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="profile-code"><?= $user['user_code'] ?></div>
                    <div class="profile-badges">
                        <span class="badge badge-primary">صيدلي</span>
                        <?php if ($user['is_verified']): ?>
                            <span class="badge badge-success">موثق</span>
                        <?php else: ?>
                            <span class="badge badge-warning">غير موثق</span>
                        <?php endif; ?>
                        <?php if ($pharmacist['is_24h']): ?>
                            <span class="badge badge-success">24 ساعة</span>
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
                </div>
            </div>
            
            <!-- معلومات الصيدلية -->
            <div class="profile-section">
                <h3 class="section-title">
                    <i class="fas fa-store"></i> معلومات الصيدلية
                </h3>
                
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">اسم الصيدلية</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($pharmacist['pharmacy_name']) ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('pharmacy_name', '<?= htmlspecialchars($pharmacist['pharmacy_name']) ?>', 'اسم الصيدلية')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">رقم الترخيص</span>
                        <div class="info-value">
                            <span><?= $pharmacist['license_number'] ?: 'غير محدد' ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('license_number', '<?= $pharmacist['license_number'] ?>', 'رقم الترخيص')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">المدينة</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($pharmacist['city'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openCityEdit()">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="info-item">
                        <span class="info-label">العنوان</span>
                        <div class="info-value">
                            <span><?= htmlspecialchars($pharmacist['address'] ?: 'غير محدد') ?></span>
                            <button class="edit-field-btn" onclick="openFieldEdit('address', '<?= htmlspecialchars($pharmacist['address']) ?>', 'العنوان', 'textarea')">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="working-hours-toggle">
                    <span>
                        <strong>مفتوح 24 ساعة</strong>
                        <span style="color: var(--gray); font-size: 0.9rem; display: block;">
                            تفعيل إذا كانت الصيدلية تعمل 24 ساعة
                        </span>
                    </span>
                    <label class="switch">
                        <input type="checkbox" id="is24hToggle" <?= $pharmacist['is_24h'] ? 'checked' : '' ?> onchange="toggle24h()">
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
                    
                    <?php if ($pharmacist['pharmacy_license']): ?>
                        <a href="../<?= $pharmacist['pharmacy_license'] ?>" target="_blank" class="document-card">
                            <i class="fas fa-file-contract"></i>
                            <div>رخصة الصيدلية</div>
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
    
    <!-- نافذة تعديل الملف الشخصي -->
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
                
                <h4 style="margin: 1.5rem 0 1rem; color: var(--primary);">معلومات الصيدلية</h4>
                
                <div class="form-group">
                    <label>اسم الصيدلية</label>
                    <input type="text" name="pharmacy_name" class="form-control" value="<?= htmlspecialchars($pharmacist['pharmacy_name']) ?>">
                </div>
                
                <div class="form-group">
                    <label>رقم الترخيص</label>
                    <input type="text" name="license_number" class="form-control" value="<?= $pharmacist['license_number'] ?>">
                </div>
                
                <div class="form-group">
                    <label>المدينة</label>
                    <select name="city" class="form-control">
                        <option value="">اختر المدينة</option>
                        <?php foreach ($cities as $city): ?>
                            <option value="<?= $city ?>" <?= $pharmacist['city'] == $city ? 'selected' : '' ?>><?= $city ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>العنوان</label>
                    <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($pharmacist['address']) ?></textarea>
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
    
    <!-- نافذة تعديل حقل واحد -->
    <div class="side-modal" id="editFieldSideModal">
        <div class="side-modal-header">
            <h3 id="editFieldTitle">تعديل</h3>
            <button class="close-side-modal" onclick="closeSideModal('editFieldSideModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form id="editFieldForm" onsubmit="submitFieldChange(event)">
                <input type="hidden" name="field_name" id="editFieldName">
                
                <div class="form-group" id="editFieldContainer"></div>
                
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
    
    <script>
    function openFieldEdit(fieldName, currentValue, displayName, type = 'text') {
        document.getElementById('editFieldTitle').textContent = `تعديل ${displayName}`;
        document.getElementById('editFieldName').value = fieldName;
        
        let inputHtml = '';
        
        if (type === 'textarea') {
            inputHtml = `
                <label>${displayName}</label>
                <textarea name="new_value" class="form-control" rows="4">${currentValue || ''}</textarea>
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
    
    function openCityEdit() {
        const cities = <?= json_encode($cities) ?>;
        let options = '<option value="">اختر المدينة</option>';
        cities.forEach(c => {
            options += `<option value="${c}" ${c == '<?= $pharmacist['city'] ?>' ? 'selected' : ''}>${c}</option>`;
        });
        
        document.getElementById('editFieldTitle').textContent = 'تعديل المدينة';
        document.getElementById('editFieldName').value = 'city';
        document.getElementById('editFieldContainer').innerHTML = `
            <label>المدينة</label>
            <select name="new_value" class="form-control">
                ${options}
            </select>
        `;
        openSideModal('editFieldSideModal');
    }
    
    function submitFieldChange(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('editFieldForm'));
        
        fetch('../api/pharmacist/request_field_change.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم إرسال طلب التعديل', 'success');
                closeSideModal('editFieldSideModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    }
    
    function submitProfileChanges(event) {
        event.preventDefault();
        
        const formData = new FormData(document.getElementById('profileEditForm'));
        
        fetch('../api/pharmacist/request_profile_changes.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم إرسال طلب التعديل', 'success');
                closeSideModal('editProfileSideModal');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast(data.message || 'حدث خطأ', 'error');
            }
        });
    }
    
    function toggle24h() {
        const is24h = document.getElementById('is24hToggle').checked;
        
        fetch('../api/pharmacist/toggle_24h.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({is_24h: is24h})
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('تم تحديث الحالة', 'success');
            } else {
                showToast('فشل التحديث', 'error');
                document.getElementById('is24hToggle').checked = !is24h;
            }
        });
    }
    </script>
</body>
</html>