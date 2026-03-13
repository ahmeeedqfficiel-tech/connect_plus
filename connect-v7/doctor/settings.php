<?php
/**
 * doctor/settings.php - إعدادات الطبيب
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

// جلب إعدادات المستخدم
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user['id']]);
$settings = $stmt->fetch();

if (!$settings) {
    $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$user['id']]);
    $settings = [
        'dark_mode' => 0,
        'language' => 'ar',
        'appointment_notifications' => 1,
        'message_notifications' => 1,
        'email_notifications' => 1
    ];
}

// ============================================================
// معالجة حفظ الإعدادات
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
    $language = $_POST['language'];
    $appointment = isset($_POST['appointment_notifications']) ? 1 : 0;
    $message = isset($_POST['message_notifications']) ? 1 : 0;
    $email = isset($_POST['email_notifications']) ? 1 : 0;
    
    $pdo->prepare("UPDATE user_settings SET dark_mode = ?, language = ?, appointment_notifications = ?, message_notifications = ?, email_notifications = ? WHERE user_id = ?")
        ->execute([$dark_mode, $language, $appointment, $message, $email, $user['id']]);
    
    setToast('تم حفظ الإعدادات', 'success');
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
        setToast('كلمة المرور قصيرة جداً', 'error');
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$new, $user['id']]);
        setToast('تم تغيير كلمة المرور', 'success');
    }
    redirect('settings.php');
}

// ============================================================
// معالجة تحديث ساعات العمل
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_working_hours'])) {
    $from = $_POST['available_from'] ?: null;
    $to = $_POST['available_to'] ?: null;
    $days = cleanInput($_POST['working_days']);
    
    $pdo->prepare("UPDATE doctors SET available_from = ?, available_to = ?, working_days = ? WHERE user_id = ?")
        ->execute([$from, $to, $days, $user['id']]);
    
    setToast('تم تحديث ساعات العمل', 'success');
    redirect('settings.php');
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
        }
        
        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
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
        
        .working-hours-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
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
            
            <h1 class="page-title" style="margin-bottom: 2rem;">
                <i class="fas fa-cog"></i> الإعدادات
            </h1>
            
            <!-- إعدادات المظهر -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-palette"></i> المظهر
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div>
                            <strong>الوضع الداكن</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">تفعيل الألوان الداكنة</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="dark_mode" <?= $settings['dark_mode'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>اللغة</label>
                        <select name="language" class="form-control">
                            <option value="ar" <?= $settings['language'] == 'ar' ? 'selected' : '' ?>>العربية</option>
                            <option value="fr" <?= $settings['language'] == 'fr' ? 'selected' : '' ?>>Français</option>
                            <option value="en" <?= $settings['language'] == 'en' ? 'selected' : '' ?>>English</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary">حفظ</button>
                </form>
            </div>
            
            <!-- ساعات العمل -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-clock"></i> ساعات العمل
                </h3>
                
                <form method="post">
                    <div class="working-hours-grid">
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
                        <input type="text" name="working_days" class="form-control" value="<?= htmlspecialchars($doctor['working_days'] ?? '') ?>" placeholder="مثال: الأحد - الخميس">
                    </div>
                    
                    <button type="submit" name="update_working_hours" class="btn btn-primary">تحديث ساعات العمل</button>
                </form>
            </div>
            
            <!-- إعدادات الإشعارات -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-bell"></i> الإشعارات
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div>
                            <strong>إشعارات المواعيد</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">تنبيه عند حجز موعد جديد</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="appointment_notifications" <?= $settings['appointment_notifications'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="settings-item">
                        <div>
                            <strong>إشعارات الرسائل</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">تنبيه عند وصول رسالة جديدة</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="message_notifications" <?= $settings['message_notifications'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="settings-item">
                        <div>
                            <strong>إشعارات البريد</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">استلام التحديثات عبر البريد</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="email_notifications" <?= $settings['email_notifications'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn btn-primary">حفظ</button>
                </form>
            </div>
            
            <!-- تغيير كلمة المرور -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-key"></i> تغيير كلمة المرور
                </h3>
                
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
                    
                    <button type="submit" name="change_password" class="btn btn-primary">
                        تغيير كلمة المرور
                    </button>
                </form>
            </div>
            
            <!-- معلومات -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-info-circle"></i> معلومات
                </h3>
                
                <p><strong>التخصص:</strong> <?= htmlspecialchars($doctor['specialties'] ?: 'غير محدد') ?></p>
                <p><strong>رقم الترخيص:</strong> <?= $doctor['license_number'] ?: 'غير محدد' ?></p>
                <p><strong>رسوم الكشف:</strong> <?= $doctor['consultation_fees'] ?: 'غير محدد' ?> دج</p>
                <p><strong>عدد المرضى:</strong> <?= $pdo->query("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = {$user['id']}")->fetchColumn() ?></p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>