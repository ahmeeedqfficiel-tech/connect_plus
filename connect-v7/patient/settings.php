<?php
/**
 * patient/settings.php - إعدادات المريض
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم مريض
requirePatient($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// جلب إعدادات المستخدم
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user['id']]);
$settings = $stmt->fetch();

if (!$settings) {
    // إنشاء إعدادات افتراضية
    $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$user['id']]);
    $settings = [
        'dark_mode' => 0,
        'language' => 'ar',
        'appointment_notifications' => 1,
        'medication_notifications' => 1,
        'message_notifications' => 1,
        'email_notifications' => 1
    ];
}

// ============================================================
// معالجة حفظ الإعدادات العامة
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
    $language = $_POST['language'];
    $appointment = isset($_POST['appointment_notifications']) ? 1 : 0;
    $medication = isset($_POST['medication_notifications']) ? 1 : 0;
    $message = isset($_POST['message_notifications']) ? 1 : 0;
    $email = isset($_POST['email_notifications']) ? 1 : 0;
    
    $pdo->prepare("UPDATE user_settings SET dark_mode = ?, language = ?, appointment_notifications = ?, medication_notifications = ?, message_notifications = ?, email_notifications = ? WHERE user_id = ?")
        ->execute([$dark_mode, $language, $appointment, $medication, $message, $email, $user['id']]);
    
    // تطبيق الوضع الداكن فوراً
    if ($dark_mode) {
        echo "<script>localStorage.setItem('darkMode', 'true');</script>";
    } else {
        echo "<script>localStorage.setItem('darkMode', 'false');</script>";
    }
    
    setToast('تم حفظ الإعدادات بنجاح', 'success');
    redirect('settings.php');
}

// ============================================================
// معالجة تغيير كلمة المرور
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    if ($current !== $user['password']) {
        setToast('كلمة المرور الحالية غير صحيحة', 'error');
    } elseif ($new !== $confirm) {
        setToast('كلمة المرور الجديدة غير متطابقة', 'error');
    } elseif (strlen($new) < 4) {
        setToast('كلمة المرور قصيرة جداً (4 أحرف على الأقل)', 'error');
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new, $user['id']]);
        setToast('تم تغيير كلمة المرور بنجاح', 'success');
    }
    redirect('settings.php');
}

// ============================================================
// معالجة حذف الحساب
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $confirm_text = $_POST['confirm_delete'] ?? '';
    
    if ($confirm_text === 'حذف حسابي') {
        try {
            $pdo->beginTransaction();
            
            // تسجيل الخروج أولاً
            session_destroy();
            
            // حذف المستخدم (الجدول الأخرى ستحذف تلقائياً بسبب CASCADE)
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user['id']]);
            
            $pdo->commit();
            
            setToast('تم حذف الحساب بنجاح', 'success');
            redirect('../index.php');
        } catch (Exception $e) {
            $pdo->rollBack();
            setToast('حدث خطأ: ' . $e->getMessage(), 'error');
        }
    } else {
        setToast('الرجاء كتابة "حذف حسابي" للتأكيد', 'error');
    }
    redirect('settings.php');
}

// ============================================================
// معالجة تصدير البيانات
// ============================================================
if (isset($_GET['export_data'])) {
    // جلب جميع بيانات المستخدم
    $data = [
        'user' => $user,
        'patient' => getPatientData($pdo, $user['id']),
        'appointments' => $pdo->prepare("SELECT * FROM appointments WHERE patient_id = ?")->execute([$user['id']]) ? 
                         $pdo->query("SELECT * FROM appointments WHERE patient_id = {$user['id']}")->fetchAll() : [],
        'prescriptions' => $pdo->prepare("SELECT p.*, GROUP_CONCAT(pm.medicine_name) as medicines FROM prescriptions p LEFT JOIN prescription_medicines pm ON p.id = pm.prescription_id WHERE p.patient_id = ? GROUP BY p.id")->execute([$user['id']]) ? 
                          $pdo->query("SELECT p.*, GROUP_CONCAT(pm.medicine_name) as medicines FROM prescriptions p LEFT JOIN prescription_medicines pm ON p.id = pm.prescription_id WHERE p.patient_id = {$user['id']} GROUP BY p.id")->fetchAll() : [],
        'medical_records' => $pdo->prepare("SELECT * FROM medical_records WHERE patient_id = ?")->execute([$user['id']]) ? 
                            $pdo->query("SELECT * FROM medical_records WHERE patient_id = {$user['id']}")->fetchAll() : []
    ];
    
    // تصدير كـ JSON
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="my_health_data_' . date('Y-m-d') . '.json"');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .settings-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .settings-group {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
        }
        
        body.dark-mode .settings-group {
            background: #1E1E1E;
        }
        
        .settings-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--light-gray);
            padding-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .settings-item:last-child {
            border-bottom: none;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-title {
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        
        .item-description {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* ===== Toggle Switch ===== */
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
            margin-right: 1rem;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary);
        }
        
        input:checked + .slider:before {
            transform: translateX(30px);
        }
        
        .danger-zone {
            border: 2px solid var(--danger);
            position: relative;
        }
        
        .danger-zone::before {
            content: "منطقة خطر";
            position: absolute;
            top: -12px;
            right: 20px;
            background: var(--danger);
            color: white;
            padding: 0.2rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .danger-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            margin-top: 0.5rem;
        }
        
        .danger-btn:hover {
            background: #d55;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 111, 81, 0.3);
        }
        
        .info-card {
            background: var(--primary-soft);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .info-card i {
            font-size: 2rem;
            color: var(--primary);
        }
        
        .language-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 30px;
            background: white;
            color: var(--dark);
            font-size: 0.95rem;
            cursor: pointer;
        }
        
        body.dark-mode .language-select {
            background: #2D2D2D;
            color: white;
        }
        
        .btn-save {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            font-size: 1.1rem;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
        }
        
        .btn-outline-danger {
            background: transparent;
            color: var(--danger);
            border: 2px solid var(--danger);
            padding: 0.5rem 1rem;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .btn-outline-danger:hover {
            background: var(--danger);
            color: white;
        }
        
        .btn-outline-primary {
            background: transparent;
            color: var(--primary);
            border: 2px solid var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 30px;
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
        }
        
        .delete-confirm {
            background: #ffebee;
            border-radius: var(--radius-md);
            padding: 1rem;
            margin: 1rem 0;
            display: none;
        }
        
        body.dark-mode .delete-confirm {
            background: #2d1a1a;
        }
        
        .delete-confirm.show {
            display: block;
        }
        
        .delete-confirm input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid var(--danger);
            border-radius: 10px;
            margin: 1rem 0;
        }
        
        .version-info {
            text-align: center;
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--light-gray);
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
            
            <div class="settings-container">
                <h1 class="page-title" style="margin-bottom: 2rem;">
                    <i class="fas fa-cog"></i> الإعدادات
                </h1>
                
                <!-- ================================================
                     إعدادات المظهر
                     ================================================ -->
                <div class="settings-group">
                    <h3 class="settings-title">
                        <i class="fas fa-palette"></i>
                        المظهر
                    </h3>
                    
                    <form method="post">
                        <div class="settings-item">
                            <div class="item-info">
                                <div class="item-title">الوضع الداكن</div>
                                <div class="item-description">تفعيل الألوان الداكنة لتخفيف إجهاد العين</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="dark_mode" <?= $settings['dark_mode'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="settings-item">
                            <div class="item-info">
                                <div class="item-title">اللغة</div>
                                <div class="item-description">اختر اللغة المناسبة</div>
                            </div>
                            <select name="language" class="language-select">
                                <option value="ar" <?= $settings['language'] == 'ar' ? 'selected' : '' ?>>العربية</option>
                                <option value="fr" <?= $settings['language'] == 'fr' ? 'selected' : '' ?>>Français</option>
                                <option value="en" <?= $settings['language'] == 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn-save">
                            <i class="fas fa-save"></i> حفظ إعدادات المظهر
                        </button>
                    </form>
                </div>
                
                <!-- ================================================
                     إعدادات الإشعارات
                     ================================================ -->
                <div class="settings-group">
                    <h3 class="settings-title">
                        <i class="fas fa-bell"></i>
                        الإشعارات
                    </h3>
                    
                    <form method="post">
                        <div class="settings-item">
                            <div class="item-info">
                                <div class="item-title">إشعارات المواعيد</div>
                                <div class="item-description">تذكير قبل الموعد بوقت كاف</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="appointment_notifications" <?= $settings['appointment_notifications'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="settings-item">
                            <div class="item-info">
                                <div class="item-title">إشعارات الأدوية</div>
                                <div class="item-description">تذكير بمواعيد تناول الأدوية</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="medication_notifications" <?= $settings['medication_notifications'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="settings-item">
                            <div class="item-info">
                                <div class="item-title">إشعارات الرسائل</div>
                                <div class="item-description">تنبيه عند وصول رسائل جديدة</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="message_notifications" <?= $settings['message_notifications'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <div class="settings-item">
                            <div class="item-info">
                                <div class="item-title">إشعارات البريد الإلكتروني</div>
                                <div class="item-description">استلام التحديثات عبر البريد</div>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="email_notifications" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                        
                        <button type="submit" name="save_settings" class="btn-save">
                            <i class="fas fa-save"></i> حفظ إعدادات الإشعارات
                        </button>
                    </form>
                </div>
                
                <!-- ================================================
                     الأمان
                     ================================================ -->
                <div class="settings-group">
                    <h3 class="settings-title">
                        <i class="fas fa-shield-alt"></i>
                        الأمان
                    </h3>
                    
                    <!-- تغيير كلمة المرور -->
                    <form method="post">
                        <div class="form-group">
                            <label>كلمة المرور الحالية</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>كلمة المرور الجديدة</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label>تأكيد كلمة المرور</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-save">
                            <i class="fas fa-key"></i> تغيير كلمة المرور
                        </button>
                    </form>
                    
                    <hr style="margin: 2rem 0;">
                    
                    <!-- المصادقة الثنائية (قيد التطوير) -->
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">المصادقة الثنائية</div>
                            <div class="item-description">تفعيل طبقة أمان إضافية للحساب</div>
                        </div>
                        <button class="btn-outline-primary dev-btn" onclick="showDevMessage('المصادقة الثنائية')">
                            <i class="fas fa-lock"></i> قيد التطوير
                        </button>
                    </div>
                    
                    <!-- جلساتي النشطة (قيد التطوير) -->
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">جلساتي النشطة</div>
                            <div class="item-description">عرض وإدارة الأجهزة المتصلة بحسابك</div>
                        </div>
                        <button class="btn-outline-primary dev-btn" onclick="showDevMessage('الجلسات النشطة')">
                            <i class="fas fa-laptop"></i> قيد التطوير
                        </button>
                    </div>
                </div>
                
                <!-- ================================================
                     البيانات
                     ================================================ -->
                <div class="settings-group">
                    <h3 class="settings-title">
                        <i class="fas fa-database"></i>
                        البيانات
                    </h3>
                    
                    <div class="info-card">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>حجم البيانات التقريبي:</strong> 2.5 ميجابايت
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="?export_data=1" class="btn-outline-primary" style="flex: 1; text-align: center; text-decoration: none;">
                            <i class="fas fa-download"></i> تصدير بياناتي
                        </a>
                        
                        <button class="btn-outline-primary" onclick="showDevMessage('النسخ الاحتياطي')" style="flex: 1;">
                            <i class="fas fa-cloud-upload-alt"></i> نسخ احتياطي
                        </button>
                    </div>
                </div>
                
                <!-- ================================================
                     منطقة الخطر (حذف الحساب)
                     ================================================ -->
                <div class="settings-group danger-zone">
                    <h3 class="settings-title" style="color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                        منطقة الخطر
                    </h3>
                    
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">حذف الحساب نهائياً</div>
                            <div class="item-description">
                                سيتم حذف جميع بياناتك بشكل نهائي ولا يمكن استرجاعها
                            </div>
                        </div>
                        <button class="danger-btn" onclick="toggleDeleteConfirm()">
                            <i class="fas fa-trash"></i> حذف الحساب
                        </button>
                    </div>
                    
                    <div id="deleteConfirm" class="delete-confirm">
                        <p><strong>تحذير:</strong> هذا الإجراء لا يمكن التراجع عنه.</p>
                        <p>يرجى كتابة "حذف حسابي" للتأكيد:</p>
                        
                        <form method="post">
                            <input type="text" name="confirm_delete" placeholder="اكتب: حذف حسابي" required>
                            <button type="submit" name="delete_account" class="danger-btn">
                                <i class="fas fa-trash"></i> نعم، أنا متأكد - احذف حسابي
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- ================================================
                     معلومات التطبيق
                     ================================================ -->
                <div class="settings-group">
                    <h3 class="settings-title">
                        <i class="fas fa-info-circle"></i>
                        معلومات التطبيق
                    </h3>
                    
                    <div class="settings-item">
                        <span>الإصدار</span>
                        <span class="badge badge-primary">2.0.0</span>
                    </div>
                    
                    <div class="settings-item">
                        <span>آخر تحديث</span>
                        <span><?= date('Y-m-d') ?></span>
                    </div>
                    
                    <div class="settings-item">
                        <span>عدد الزيارات</span>
                        <span>42 زيارة</span>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1rem;">
                        <a href="../terms.php" style="color: var(--primary);">شروط الاستخدام</a>
                        <span>|</span>
                        <a href="../privacy.php" style="color: var(--primary);">سياسة الخصوصية</a>
                        <span>|</span>
                        <a href="../contact.php" style="color: var(--primary);">اتصل بنا</a>
                    </div>
                    
                    <div class="version-info">
                        CONNECT+ © 2026 - جميع الحقوق محفوظة
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function toggleDeleteConfirm() {
        document.getElementById('deleteConfirm').classList.toggle('show');
    }
    
    function showDevMessage(feature) {
        showToast(`خدمة ${feature} قيد التطوير وستتوفر قريباً`, 'warning');
    }
    </script>
</body>
</html>