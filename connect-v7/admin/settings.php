<?php
/**
 * admin/settings.php - إعدادات النظام
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

$user = getCurrentUser($pdo);

// ============================================================
// حفظ الإعدادات العامة
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general'])) {
    $site_name = cleanInput($_POST['site_name']);
    $site_email = cleanInput($_POST['site_email']);
    $pagination = (int)$_POST['pagination_limit'];
    
    // هنا يمكن حفظ الإعدادات في ملف config أو جدول settings
    // للتبسيط، سنستخدم الجلسة مؤقتاً
    $_SESSION['site_name'] = $site_name;
    $_SESSION['site_email'] = $site_email;
    $_SESSION['pagination_limit'] = $pagination;
    
    setToast('تم حفظ الإعدادات العامة', 'success');
    redirect('settings.php');
}

// ============================================================
// حفظ إعدادات التسجيل
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_registration'])) {
    $registration = isset($_POST['registration_enabled']) ? 1 : 0;
    $require_approval = isset($_POST['require_approval']) ? 1 : 0;
    $email_notify = isset($_POST['email_notification']) ? 1 : 0;
    
    $_SESSION['registration_enabled'] = $registration;
    $_SESSION['require_approval'] = $require_approval;
    $_SESSION['email_notification'] = $email_notify;
    
    setToast('تم حفظ إعدادات التسجيل', 'success');
    redirect('settings.php');
}

// ============================================================
// حفظ إعدادات الأمان
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_security'])) {
    $maintenance = isset($_POST['maintenance_mode']) ? 1 : 0;
    $max_attempts = (int)$_POST['max_login_attempts'];
    $session_lifetime = (int)$_POST['session_lifetime'];
    
    $_SESSION['maintenance_mode'] = $maintenance;
    $_SESSION['max_login_attempts'] = $max_attempts;
    $_SESSION['session_lifetime'] = $session_lifetime;
    
    setToast('تم حفظ إعدادات الأمان', 'success');
    redirect('settings.php');
}

// ============================================================
// تنظيف قاعدة البيانات
// ============================================================
if (isset($_GET['cleanup'])) {
    // حذف الإشعارات القديمة (أكثر من 30 يوم)
    $pdo->exec("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    
    // حذف سجلات النشاط القديمة (أكثر من 90 يوم)
    $pdo->exec("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    
    setToast('تم تنظيف قاعدة البيانات', 'success');
    redirect('settings.php');
}

// ============================================================
// إحصائيات النظام
// ============================================================
$stats = [
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'patients' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn(),
    'doctors' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn(),
    'pharmacists' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pharmacist'")->fetchColumn(),
    'appointments' => $pdo->query("SELECT COUNT(*) FROM appointments")->fetchColumn(),
    'prescriptions' => $pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn(),
    'orders' => $pdo->query("SELECT COUNT(*) FROM medicine_orders")->fetchColumn(),
    'messages' => $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn(),
    'logs' => $pdo->query("SELECT COUNT(*) FROM activity_log")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات النظام - CONNECT+</title>
    
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-card {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 1rem;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
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
        }
        
        .danger-btn:hover {
            background: #d55;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 111, 81, 0.3);
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
            margin-top: 1rem;
        }
        
        .btn-save:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
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
                <i class="fas fa-cog"></i> إعدادات النظام
            </h1>
            
            <!-- إحصائيات سريعة -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-chart-pie"></i> إحصائيات النظام
                </h3>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['users'] ?></div>
                        <div>مستخدم</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['patients'] ?></div>
                        <div>مريض</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['doctors'] ?></div>
                        <div>طبيب</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['pharmacists'] ?></div>
                        <div>صيدلي</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['appointments'] ?></div>
                        <div>موعد</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['prescriptions'] ?></div>
                        <div>وصفة</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['orders'] ?></div>
                        <div>طلب</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['messages'] ?></div>
                        <div>رسالة</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $stats['logs'] ?></div>
                        <div>سجل</div>
                    </div>
                </div>
                
                <div style="margin-top: 1rem;">
                    <strong>حجم قاعدة البيانات:</strong> 
                    <?php
                    $result = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size FROM information_schema.tables WHERE table_schema = '" . DB_NAME . "'");
                    $size = $result->fetchColumn();
                    echo $size ? "$size ميجابايت" : "غير معروف";
                    ?>
                </div>
            </div>
            
            <!-- إعدادات عامة -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-globe"></i> إعدادات عامة
                </h3>
                
                <form method="post">
                    <div class="form-group">
                        <label>اسم الموقع</label>
                        <input type="text" name="site_name" class="form-control" value="<?= $_SESSION['site_name'] ?? APP_NAME ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>البريد الإلكتروني الرسمي</label>
                        <input type="email" name="site_email" class="form-control" value="<?= $_SESSION['site_email'] ?? 'admin@connectplus.dz' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>عدد العناصر في الصفحة</label>
                        <input type="number" name="pagination_limit" class="form-control" value="<?= $_SESSION['pagination_limit'] ?? 20 ?>" min="5" max="100">
                    </div>
                    
                    <button type="submit" name="save_general" class="btn-save">
                        <i class="fas fa-save"></i> حفظ الإعدادات العامة
                    </button>
                </form>
            </div>
            
            <!-- إعدادات التسجيل -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-user-plus"></i> إعدادات التسجيل
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div>
                            <strong>فتح التسجيل</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">السماح للمستخدمين الجدد بالتسجيل</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="registration_enabled" <?= ($_SESSION['registration_enabled'] ?? 1) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="settings-item">
                        <div>
                            <strong>طلب موافقة الأدمن</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">تأكيد الحسابات الجديدة من قبل الأدمن</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="require_approval" <?= ($_SESSION['require_approval'] ?? 1) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="settings-item">
                        <div>
                            <strong>إشعار بريد إلكتروني</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">إرسال بريد ترحيبي للمستخدمين الجدد</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="email_notification" <?= ($_SESSION['email_notification'] ?? 1) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <button type="submit" name="save_registration" class="btn-save">
                        <i class="fas fa-save"></i> حفظ إعدادات التسجيل
                    </button>
                </form>
            </div>
            
            <!-- إعدادات الأمان -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-shield-alt"></i> إعدادات الأمان
                </h3>
                
                <form method="post">
                    <div class="settings-item">
                        <div>
                            <strong>وضع الصيانة</strong>
                            <p style="font-size: 0.9rem; color: var(--gray);">تعطيل الموقع للصيانة</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="maintenance_mode" <?= ($_SESSION['maintenance_mode'] ?? 0) ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label>الحد الأقصى لمحاولات تسجيل الدخول</label>
                        <input type="number" name="max_login_attempts" class="form-control" value="<?= $_SESSION['max_login_attempts'] ?? 5 ?>" min="3" max="20">
                    </div>
                    
                    <div class="form-group">
                        <label>مدة الجلسة (بالدقائق)</label>
                        <input type="number" name="session_lifetime" class="form-control" value="<?= $_SESSION['session_lifetime'] ?? 120 ?>" min="30" max="1440">
                    </div>
                    
                    <button type="submit" name="save_security" class="btn-save">
                        <i class="fas fa-save"></i> حفظ إعدادات الأمان
                    </button>
                </form>
            </div>
            
            <!-- صيانة قاعدة البيانات -->
            <div class="settings-group danger-zone">
                <h3 class="settings-title" style="color: var(--danger);">
                    <i class="fas fa-database"></i> صيانة قاعدة البيانات
                </h3>
                
                <div style="margin-bottom: 1rem;">
                    <p><strong>تنظيف قاعدة البيانات:</strong> حذف الإشعارات القديمة (أكثر من 30 يوم) وسجلات النشاط القديمة (أكثر من 90 يوم)</p>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <a href="?cleanup=logs" class="danger-btn" style="flex: 1;" onclick="return confirm('هل أنت متأكد من تنظيف قاعدة البيانات؟')">
                        <i class="fas fa-broom"></i> تنظيف البيانات القديمة
                    </a>
                    
                    <button class="btn btn-outline" style="flex: 1;" onclick="showDevMessage('النسخ الاحتياطي')">
                        <i class="fas fa-download"></i> نسخ احتياطي
                    </button>
                </div>
            </div>
            
            <!-- معلومات النظام -->
            <div class="settings-group">
                <h3 class="settings-title">
                    <i class="fas fa-info-circle"></i> معلومات النظام
                </h3>
                
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;">
                    <div>
                        <strong>إصدار PHP:</strong> <?= phpversion() ?>
                    </div>
                    <div>
                        <strong>خادم قاعدة البيانات:</strong> MySQL
                    </div>
                    <div>
                        <strong>إصدار CONNECT+:</strong> 2.0.0
                    </div>
                    <div>
                        <strong>آخر تحديث:</strong> <?= date('Y-m-d') ?>
                    </div>
                    <div>
                        <strong>المسار الجذر:</strong> <?= $_SERVER['DOCUMENT_ROOT'] ?>
                    </div>
                    <div>
                        <strong>الذاكرة المستخدمة:</strong> <?= round(memory_get_usage() / 1024 / 1024, 2) ?> ميجابايت
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>