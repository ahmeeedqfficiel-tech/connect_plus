<?php
/**
 * signup.php - صفحة إنشاء حساب جديد
 * CONNECT+ - الإصدار 2.0 النهائي
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// إذا كان المستخدم مسجلاً بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$role = $_GET['role'] ?? 'patient';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = cleanInput($_POST['full_name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $national_id = cleanInput($_POST['national_id'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $birth_date = $_POST['birth_date'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $role = $_POST['role'] ?? 'patient';
    $terms = isset($_POST['terms']);

    if (!$terms) {
        $error = 'يجب الموافقة على شروط الاستخدام';
    } elseif (empty($full_name) || empty($email) || empty($password)) {
        $error = 'الاسم والبريد وكلمة المرور حقول إجبارية';
    } elseif ($password !== $confirm) {
        $error = 'كلمة المرور غير متطابقة';
    } elseif (strlen($password) < 4) {
        $error = 'كلمة المرور قصيرة جداً (4 أحرف على الأقل)';
    } else {
        try {
            // التحقق من عدم تكرار البريد
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $check->execute([$email]);
            if ($check->fetch()) {
                $error = 'البريد الإلكتروني مستخدم بالفعل';
            } else {
                // إنشاء كود المستخدم
                $user_code = generateUserCode($pdo, $role);
                
                // الأدمن يتم توثيقه تلقائياً
                $is_verified = ($role === 'admin') ? 1 : 0;
                
                $pdo->beginTransaction();
                
                // إدراج المستخدم
                $stmt = $pdo->prepare("INSERT INTO users (user_code, full_name, email, phone, password, national_id, role, birth_date, gender, address, city, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_code, $full_name, $email, $phone, $password, $national_id ?: null, $role, $birth_date, $gender, $address, $city, $is_verified]);
                
                $user_id = $pdo->lastInsertId();
                
                // رفع الصور
                if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                    $result = uploadProfileImage($_FILES['profile_image'], $user_id);
                    if ($result['success']) {
                        $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?")->execute([$result['path'], $user_id]);
                    }
                }
                
                // بطاقة التعريف
                if (isset($_FILES['id_card']) && $_FILES['id_card']['error'] === UPLOAD_ERR_OK) {
                    $result = uploadDocument($_FILES['id_card'], 'id', $user_id);
                    if ($result['success']) {
                        $pdo->prepare("UPDATE users SET id_card_image = ? WHERE id = ?")->execute([$result['path'], $user_id]);
                    }
                }
                
                // بيانات خاصة حسب الدور
                switch ($role) {
                    case 'patient':
                        if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
                            $result = uploadDocument($_FILES['birth_certificate'], 'birth', $user_id);
                            if ($result['success']) {
                                $pdo->prepare("UPDATE users SET birth_certificate = ? WHERE id = ?")->execute([$result['path'], $user_id]);
                            }
                        }
                        
                        if (isset($_FILES['postal_receipt']) && $_FILES['postal_receipt']['error'] === UPLOAD_ERR_OK) {
                            $result = uploadDocument($_FILES['postal_receipt'], 'postal', $user_id);
                            if ($result['success']) {
                                $pdo->prepare("UPDATE users SET postal_receipt = ? WHERE id = ?")->execute([$result['path'], $user_id]);
                            }
                        }
                        
                        $emergency_name = cleanInput($_POST['emergency_name'] ?? '');
                        $emergency_phone = cleanInput($_POST['emergency_phone'] ?? '');
                        $blood_type = $_POST['blood_type'] ?? null;
                        $chronic = cleanInput($_POST['chronic_diseases'] ?? '');
                        $allergies = cleanInput($_POST['allergies'] ?? '');
                        
                        $pdo->prepare("INSERT INTO patients (user_id, emergency_name, emergency_phone, blood_type, chronic_diseases, allergies) VALUES (?, ?, ?, ?, ?, ?)")
                            ->execute([$user_id, $emergency_name, $emergency_phone, $blood_type, $chronic, $allergies]);
                        break;
                        
                    case 'doctor':
                        if (isset($_FILES['license_image']) && $_FILES['license_image']['error'] === UPLOAD_ERR_OK) {
                            $result = uploadDocument($_FILES['license_image'], 'license', $user_id);
                            if ($result['success']) {
                                $pdo->prepare("UPDATE users SET license_image = ? WHERE id = ?")->execute([$result['path'], $user_id]);
                            }
                        }
                        
                        if (isset($_FILES['degree_certificate']) && $_FILES['degree_certificate']['error'] === UPLOAD_ERR_OK) {
                            $result = uploadDocument($_FILES['degree_certificate'], 'degree', $user_id);
                            if ($result['success']) {
                                $pdo->prepare("UPDATE users SET degree_certificate = ? WHERE id = ?")->execute([$result['path'], $user_id]);
                            }
                        }
                        
                        $degree = cleanInput($_POST['degree'] ?? '');
                        $specialties = cleanInput($_POST['specialties'] ?? '');
                        $license_number = cleanInput($_POST['license_number'] ?? '');
                        $workplace = cleanInput($_POST['workplace_name'] ?? '');
                        $fees = (float)($_POST['consultation_fees'] ?? 0);
                        
                        $pdo->prepare("INSERT INTO doctors (user_id, degree, specialties, license_number, workplace_name, consultation_fees) VALUES (?, ?, ?, ?, ?, ?)")
                            ->execute([$user_id, $degree, $specialties, $license_number, $workplace, $fees]);
                        break;
                        
                    case 'pharmacist':
                        if (isset($_FILES['pharmacy_license']) && $_FILES['pharmacy_license']['error'] === UPLOAD_ERR_OK) {
                            $result = uploadDocument($_FILES['pharmacy_license'], 'pharmacy', $user_id);
                            if ($result['success']) {
                                $pdo->prepare("UPDATE pharmacists SET pharmacy_license = ? WHERE user_id = ?")->execute([$result['path'], $user_id]);
                            }
                        }
                        
                        $pharmacy_name = cleanInput($_POST['pharmacy_name'] ?? '');
                        $pharmacy_license = cleanInput($_POST['license_number'] ?? '');
                        $pharmacy_address = cleanInput($_POST['pharmacy_address'] ?? '');
                        $pharmacy_city = cleanInput($_POST['pharmacy_city'] ?? '');
                        
                        $pdo->prepare("INSERT INTO pharmacists (user_id, pharmacy_name, license_number, city, address) VALUES (?, ?, ?, ?, ?)")
                            ->execute([$user_id, $pharmacy_name, $pharmacy_license, $pharmacy_city, $pharmacy_address]);
                        break;
                        
                    case 'admin':
                        $pdo->prepare("INSERT INTO admins (user_id, department) VALUES (?, 'الإدارة العامة')")->execute([$user_id]);
                        break;
                }
                
                // إعدادات افتراضية
                $pdo->prepare("INSERT INTO user_settings (user_id) VALUES (?)")->execute([$user_id]);
                
                // طلب تأكيد لغير الأدمن
                if ($role !== 'admin') {
                    $pdo->prepare("INSERT INTO approval_requests (user_id) VALUES (?)")->execute([$user_id]);
                }
                
                // تسجيل النشاط
                logActivity($pdo, $user_id, 'تسجيل', 'تم إنشاء حساب جديد');
                
                $pdo->commit();
                
                if ($role === 'admin') {
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['user_role'] = 'admin';
                    $_SESSION['user_name'] = $full_name;
                    header('Location: admin/dashboard.php');
                    exit;
                } else {
                    $_SESSION['pending_user_id'] = $user_id;
                    header('Location: pending_approval.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #2A9D8F, #264653);
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        
        .signup-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 30px;
            padding: 2.5rem;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .back-home {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            color: #666;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: color 0.3s;
        }
        
        .back-home:hover {
            color: var(--primary);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo i {
            font-size: 4rem;
            color: var(--primary);
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 2rem;
        }
        
        .role-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: #9ee4e4;
            padding: 0.5rem;
            border-radius: 50px;
            flex-wrap: wrap;
        }
        
        .role-btn {
            flex: 1;
            text-align: center;
            padding: 0.8rem;
            border-radius: 30px;
            text-decoration: none;
            color: #333;
            font-weight: 600;
            transition: all 0.3s;
            min-width: 100px;
        }
        
        .role-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin: 2rem 0 1rem;
            border-bottom: 2px solid #1100ff;
            padding-bottom: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.3rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #d7d7d7;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 2rem;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
        }
        
        .alert {
            background: #ffebee;
            color: var(--danger);
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border-right: 4px solid var(--danger);
        }
        
        .upload-area {
            border: 2px dashed var(--primary);
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: background 0.3s;
            margin-bottom: 1rem;
        }
        
        .upload-area:hover {
            background: rgba(42, 157, 143, 0.05);
        }
        
        .upload-area i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .dev-note {
            background: rgba(244, 162, 97, 0.1);
            border-right: 4px solid var(--warning);
            padding: 1rem;
            border-radius: 10px;
            margin: 1rem 0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .dev-note i {
            font-size: 2rem;
            color: var(--warning);
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .signup-container {
                padding: 1.5rem;
            }
        }
        
        /* نافذة الشروط */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 30px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 2rem;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
        }
        
        .modal-header h3 {
            color: var(--primary);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <!-- زر العودة للصفحة الرئيسية -->
        <a href="index.php" class="back-home">
            <i class="fas fa-home"></i> العودة للرئيسية
        </a>
        
        <div class="logo">
            <i class="fas fa-plus-circle"></i>
            <h1>CONNECT+</h1>
        </div>
        
        <h2 style="text-align: center; margin-bottom: 2rem;">إنشاء حساب جديد</h2>
        
        <!-- اختيار الدور -->
        <div class="role-selector">
            <a href="?role=patient" class="role-btn <?= $role == 'patient' ? 'active' : '' ?>">
                <i class="fas fa-user"></i> مريض
            </a>
            <a href="?role=doctor" class="role-btn <?= $role == 'doctor' ? 'active' : '' ?>">
                <i class="fas fa-user-md"></i> طبيب
            </a>
            <a href="?role=pharmacist" class="role-btn <?= $role == 'pharmacist' ? 'active' : '' ?>">
                <i class="fas fa-store"></i> صيدلي
            </a>
            <a href="?role=admin" class="role-btn <?= $role == 'admin' ? 'active' : '' ?>">
                <i class="fas fa-crown"></i> أدمن
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="role" value="<?= $role ?>">
            
            <!-- المعلومات الأساسية -->
            <div class="section-title">
                <i class="fas fa-user-circle"></i> المعلومات الشخصية
            </div>
            
            <div class="form-group">
                <label>الاسم الكامل <span style="color: var(--danger);">*</span></label>
                <input type="text" name="full_name" class="form-control" placeholder="الاسم واللقب" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>تاريخ الميلاد</label>
                    <input type="date" name="birth_date" class="form-control">
                </div>
                <div class="form-group">
                    <label>الجنس</label>
                    <select name="gender" class="form-control">
                        <option value="">اختر</option>
                        <option value="male">ذكر</option>
                        <option value="female">أنثى</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>العنوان</label>
                <input type="text" name="address" class="form-control" placeholder="العنوان الكامل">
            </div>
            
            <div class="form-group">
                <label>الولاية</label>
                <input type="text" name="city" class="form-control" placeholder="مثال: الجزائر، وهران...">
            </div>
            
            <!-- معلومات الاتصال -->
            <div class="section-title">
                <i class="fas fa-address-card"></i> معلومات الاتصال
            </div>
            
            <div class="form-group">
                <label>رقم بطاقة التعريف الوطنية</label>
                <input type="text" name="national_id" class="form-control" placeholder="18 رقم">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="tel" name="phone" class="form-control" placeholder="05XX XX XX XX">
                </div>
                <div class="form-group">
                    <label>البريد الإلكتروني <span style="color: var(--danger);">*</span></label>
                    <input type="email" name="email" class="form-control" placeholder="example@domain.com" required>
                </div>
            </div>
            
            <!-- المستندات المطلوبة -->
            <div class="section-title">
                <i class="fas fa-file-upload"></i> المستندات المطلوبة
            </div>
            
            <div class="form-group">
                <label>صورة شخصية</label>
                <div class="upload-area" onclick="document.getElementById('profile_image').click()">
                    <i class="fas fa-camera"></i>
                    <p>انقر لرفع الصورة الشخصية</p>
                    <input type="file" id="profile_image" name="profile_image" accept="image/*" style="display:none;">
                </div>
            </div>
            
            <div class="form-group">
                <label>صورة بطاقة التعريف <span style="color: var(--danger);">*</span></label>
                <div class="upload-area" onclick="document.getElementById('id_card').click()">
                    <i class="fas fa-id-card"></i>
                    <p>انقر لرفع صورة البطاقة</p>
                    <input type="file" id="id_card" name="id_card" accept="image/*" style="display:none;" required>
                </div>
            </div>
            
            <!-- حقول خاصة بالمريض -->
            <?php if ($role == 'patient'): ?>
                <div class="form-group">
                    <label>شهادة الميلاد <span style="color: var(--danger);">*</span></label>
                    <div class="upload-area" onclick="document.getElementById('birth_certificate').click()">
                        <i class="fas fa-birthday-cake"></i>
                        <p>انقر لرفع شهادة الميلاد</p>
                        <input type="file" id="birth_certificate" name="birth_certificate" accept="image/*,.pdf" style="display:none;" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>الوصل البريدي <span style="color: var(--danger);">*</span></label>
                    <div class="upload-area" onclick="document.getElementById('postal_receipt').click()">
                        <i class="fas fa-receipt"></i>
                        <p>انقر لرفع الوصل البريدي</p>
                        <input type="file" id="postal_receipt" name="postal_receipt" accept="image/*,.pdf" style="display:none;" required>
                    </div>
                </div>
                
                <div class="section-title">
                    <i class="fas fa-heartbeat"></i> معلومات طبية إضافية (اختياري)
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>جهة اتصال الطوارئ (الاسم)</label>
                        <input type="text" name="emergency_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>رقم هاتف الطوارئ</label>
                        <input type="text" name="emergency_phone" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>فصيلة الدم</label>
                    <select name="blood_type" class="form-control">
                        <option value="">اختر</option>
                        <?php foreach ($blood_types as $bt): ?>
                            <option value="<?= $bt ?>"><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>الأمراض المزمنة</label>
                    <textarea name="chronic_diseases" class="form-control" rows="2" placeholder="مثال: سكري، ضغط دم..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>الحساسيات</label>
                    <textarea name="allergies" class="form-control" rows="2" placeholder="مثال: بنسلين، غبار..."></textarea>
                </div>
                
            <!-- حقول خاصة بالطبيب -->
            <?php elseif ($role == 'doctor'): ?>
                <div class="form-group">
                    <label>رخصة مزاولة المهنة <span style="color: var(--danger);">*</span></label>
                    <div class="upload-area" onclick="document.getElementById('license_image').click()">
                        <i class="fas fa-file-contract"></i>
                        <p>انقر لرفع الرخصة</p>
                        <input type="file" id="license_image" name="license_image" accept="image/*,.pdf" style="display:none;" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>شهادة التخرج</label>
                    <div class="upload-area" onclick="document.getElementById('degree_certificate').click()">
                        <i class="fas fa-graduation-cap"></i>
                        <p>انقر لرفع الشهادة</p>
                        <input type="file" id="degree_certificate" name="degree_certificate" accept="image/*,.pdf" style="display:none;">
                    </div>
                </div>
                
                <div class="section-title">
                    <i class="fas fa-briefcase"></i> المعلومات المهنية
                </div>
                
                <div class="form-group">
                    <label>الدرجة العلمية</label>
                    <input type="text" name="degree" class="form-control" placeholder="مثال: دكتوراه في الطب">
                </div>
                
                <div class="form-group">
                    <label>التخصصات <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="specialties" class="form-control" placeholder="مثال: قلب، عظام، جراحة...">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>رقم الترخيص</label>
                        <input type="text" name="license_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>اسم العيادة</label>
                        <input type="text" name="workplace_name" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>رسوم الكشف (دج)</label>
                    <input type="number" name="consultation_fees" class="form-control" step="0.01" min="0">
                </div>
                
            <!-- حقول خاصة بالصيدلي -->
            <?php elseif ($role == 'pharmacist'): ?>
                <div class="form-group">
                    <label>رخصة الصيدلية <span style="color: var(--danger);">*</span></label>
                    <div class="upload-area" onclick="document.getElementById('pharmacy_license').click()">
                        <i class="fas fa-store"></i>
                        <p>انقر لرفع الرخصة</p>
                        <input type="file" id="pharmacy_license" name="pharmacy_license" accept="image/*,.pdf" style="display:none;" required>
                    </div>
                </div>
                
                <div class="section-title">
                    <i class="fas fa-store"></i> معلومات الصيدلية
                </div>
                
                <div class="form-group">
                    <label>اسم الصيدلية <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="pharmacy_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>رقم الترخيص <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="license_number" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>عنوان الصيدلية <span style="color: var(--danger);">*</span></label>
                    <input type="text" name="pharmacy_address" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>الولاية</label>
                    <input type="text" name="pharmacy_city" class="form-control" required>
                </div>
            <?php endif; ?>
            
            <!-- كلمة المرور -->
            <div class="section-title">
                <i class="fas fa-lock"></i> أمان الحساب
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>كلمة المرور <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>تأكيد كلمة المرور <span style="color: var(--danger);">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
            </div>
            
            <!-- الموافقة على الشروط -->
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="terms" required>
                    <span>أوافق على <span style="color: var(--primary); cursor: pointer;" onclick="openModal('termsModal')">شروط الاستخدام</span></span>
                </label>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i> 
                <?= $role == 'admin' ? 'إنشاء حساب أدمن' : 'إنشاء الحساب' ?>
            </button>
        </form>
        
        <div style="text-align: center; margin-top: 2rem;">
            <p>لديك حساب بالفعل؟ <a href="login.php" style="color: var(--primary); font-weight: 600;">تسجيل الدخول</a></p>
        </div>
    </div>
    
    <!-- نافذة الشروط -->
    <div class="modal" id="termsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>شروط الاستخدام</h3>
                <button class="close-modal" onclick="closeModal('termsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <h4>مرحباً بك في CONNECT+</h4>
                <p>باستخدامك لهذه المنصة، فإنك توافق على:</p>
                <ul style="margin-right: 1.5rem;">
                    <li>تقديم معلومات صحيحة وكاملة</li>
                    <li>الاحتفاظ بسرية بيانات الدخول</li>
                    <li>الاستخدام الشخصي فقط للمنصة</li>
                    <li>عدم مشاركة حسابك مع الآخرين</li>
                    <li>الالتزام بقوانين وأنظمة الجمهورية الجزائرية</li>
                </ul>
                
                <h4 style="margin-top: 1rem;">المسؤولية</h4>
                <p>المنصة وسيط تقني فقط. الاستشارات الطبية والتشخيصات هي مسؤولية الأطباء والمختصين.</p>
                
                <h4 style="margin-top: 1rem;">الخصوصية</h4>
                <p>نحن نلتزم بحماية معلوماتك الشخصية والطبية وفقاً لسياسة الخصوصية.</p>
            </div>
        </div>
    </div>
    
    <script>
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    </script>
</body>
</html>