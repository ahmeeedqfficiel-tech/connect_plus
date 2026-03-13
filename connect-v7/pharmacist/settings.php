<?php
/**
 * pharmacist/settings.php - إعدادات الصيدلي
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

// جلب إعدادات المستخدم
$stmt = $pdo->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->execute([$user['id']]);
$settings = $stmt->fetch();

if (!$settings) {
    $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$user['id']]);
    $settings = [
        'dark_mode' => 0,
        'language' => 'ar',
        'order_notifications' => 1,
        'message_notifications' => 1,
        'email_notifications' => 1,
        'low_stock_alert' => 1,
        'low_stock_threshold' => 10
    ];
}

// ============================================================
// معالجة حفظ الإعدادات
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $dark_mode = isset($_POST['dark_mode']) ? 1 : 0;
    $language = $_POST['language'];
    $order = isset($_POST['order_notifications']) ? 1 : 0;
    $message = isset($_POST['message_notifications']) ? 1 : 0;
    $email = isset($_POST['email_notifications']) ? 1 : 0;
    $low_stock = isset($_POST['low_stock_alert']) ? 1 : 0;
    $threshold = (int)$_POST['low_stock_threshold'];
    
    $pdo->prepare("UPDATE user_settings SET dark_mode = ?, language = ?, order_notifications = ?, message_notifications = ?, email_notifications = ?, low_stock_alert = ?, low_stock_threshold = ? WHERE user_id = ?")
        ->execute([$dark_mode, $language, $order, $message, $email, $low_stock, $threshold, $user['id']]);
    
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
// معالجة تحديث معلومات الصيدلية السريع
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_pharmacy_info'])) {
    $is_24h = isset($_POST['is_24h']) ? 1 : 0;
    
    $pdo->prepare("UPDATE pharmacists SET is_24h = ? WHERE user_id = ?")
        ->execute([$is_24h, $user['id']]);
    
    setToast('تم تحديث معلومات الصيدلية', 'success');
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
            margin-top: 1rem;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
        }
        
        .threshold-input {
            width: 80px;
            padding: 0.3rem;
            border: 2px solid var(--light-gray);
            border-radius: 10px;
            text-align: center;
        }
        
        .info-card {
            background: var(--primary-soft);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin: 1rem 0;
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
                        <div class="item-info">
                            <div class="item-title">الوضع الداكن</div>
                            <div class="item-description">تفعيل الألوان الداكنة</div>
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
            
            <!-- إعدادات الإشعارات -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-bell"></i> الإشعارات
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">إشعارات الطلبات</div>
                            <div class="item-description">تنبيه عند وصول طلب جديد</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="order_notifications" <?= $settings['order_notifications'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">إشعارات الرسائل</div>
                            <div class="item-description">تنبيه عند وصول رسالة جديدة</div>
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
            
            <!-- إعدادات المخزون -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-boxes"></i> إعدادات المخزون
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">تنبيه المخزون المنخفض</div>
                            <div class="item-description">إشعار عندما تنخفض كمية دواء عن الحد المحدد</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="low_stock_alert" id="lowStockAlert" <?= $settings['low_stock_alert'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">حد المخزون المنخفض</div>
                            <div class="item-description">تنبيه عندما تقل الكمية عن</div>
                        </div>
                        <div>
                            <input type="number" name="low_stock_threshold" class="threshold-input" min="1" max="100" value="<?= $settings['low_stock_threshold'] ?: 10 ?>">
                        </div>
                    </div>
                    
                    <button type="submit" name="save_settings" class="btn-save">
                        <i class="fas fa-save"></i> حفظ إعدادات المخزون
                    </button>
                </form>
            </div>
            
            <!-- معلومات الصيدلية -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-store"></i> معلومات الصيدلية
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div class="item-info">
                            <div class="item-title">مفتوح 24 ساعة</div>
                            <div class="item-description">تفعيل إذا كانت الصيدلية تعمل طوال اليوم</div>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="is_24h" <?= $pharmacist['is_24h'] ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="info-card">
                        <i class="fas fa-info-circle"></i>
                        <strong>للتعديلات الأخرى على معلومات الصيدلية،</strong> يرجى استخدام صفحة الملف الشخصي.
                    </div>
                    
                    <button type="submit" name="update_pharmacy_info" class="btn-save">
                        <i class="fas fa-save"></i> تحديث معلومات الصيدلية
                    </button>
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
                    
                    <button type="submit" name="change_password" class="btn-save">
                        <i class="fas fa-key"></i> تغيير كلمة المرور
                    </button>
                </form>
            </div>
            
            <!-- معلومات إضافية -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-info-circle"></i> معلومات
                </h3>
                
                <p><strong>اسم الصيدلية:</strong> <?= htmlspecialchars($pharmacist['pharmacy_name'] ?? 'غير محدد') ?></p>
                <p><strong>رقم الترخيص:</strong> <?= $pharmacist['license_number'] ?? 'غير محدد' ?></p>
                <p><strong>المدينة:</strong> <?= htmlspecialchars($pharmacist['city'] ?? 'غير محدد') ?></p>
                <p><strong>عدد الأدوية في المخزون:</strong> 
                    <?php
                    $pharmacy_id = $pdo->prepare("SELECT id FROM pharmacies WHERE pharmacist_id = ?")->execute([$user['id']]) ? 
                                  $pdo->query("SELECT id FROM pharmacies WHERE pharmacist_id = {$user['id']}")->fetchColumn() : 0;
                    if ($pharmacy_id) {
                        $count = $pdo->query("SELECT COUNT(*) FROM pharmacy_medicines WHERE pharmacy_id = $pharmacy_id")->fetchColumn();
                        echo $count;
                    } else {
                        echo 0;
                    }
                    ?>
                </p>
                <p><strong>عدد الطلبات:</strong> 
                    <?php
                    if ($pharmacy_id) {
                        $orders = $pdo->query("SELECT COUNT(*) FROM medicine_orders WHERE pharmacy_id = $pharmacy_id")->fetchColumn();
                        echo $orders;
                    } else {
                        echo 0;
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>