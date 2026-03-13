<?php
/**
 * login.php - صفحة تسجيل الدخول
 * CONNECT+ - الإصدار 2.0 النهائي
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// إذا كان المستخدم مسجلاً بالفعل
if (isset($_SESSION['user_id'])) {
    $user = getCurrentUser($pdo);
    if ($user) {
        switch ($user['role']) {
            case 'patient':
                header('Location: patient/dashboard.php');
                exit;
            case 'doctor':
                header('Location: doctor/dashboard.php');
                exit;
            case 'pharmacist':
                header('Location: pharmacist/dashboard.php');
                exit;
            case 'admin':
                header('Location: admin/dashboard.php');
                exit;
        }
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'patient';

    if (empty($login) || empty($password)) {
        $error = 'يرجى إدخال البريد/رقم البطاقة وكلمة المرور';
    } else {
        // البحث عن المستخدم
        $stmt = $pdo->prepare("SELECT * FROM users WHERE (email = ? OR national_id = ? OR user_code = ?) AND role = ?");
        $stmt->execute([$login, $login, $login, $role]);
        $user = $stmt->fetch();

        if ($user && $password === $user['password']) {
            if (!$user['is_verified'] && $user['role'] !== 'admin') {
                $_SESSION['pending_user_id'] = $user['id'];
                header('Location: pending_approval.php');
                exit;
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['full_name'];

            // تسجيل النشاط
            logActivity($pdo, $user['id'], 'تسجيل دخول', 'تسجيل دخول ناجح');

            // تحديث آخر دخول
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);

            // التوجيه حسب الدور
            switch ($user['role']) {
                case 'patient':
                    header('Location: patient/dashboard.php');
                    break;
                case 'doctor':
                    header('Location: doctor/dashboard.php');
                    break;
                case 'pharmacist':
                    header('Location: pharmacist/dashboard.php');
                    break;
                case 'admin':
                    header('Location: admin/dashboard.php');
                    break;
            }
            exit;
        } else {
            $error = 'بيانات الدخول غير صحيحة';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - CONNECT+</title>
    
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-container {
            background: white;
            border-radius: 30px;
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .back-home {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            color: #686868;
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
            margin-bottom: 0.5rem;
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 2rem;
        }
        
        .role-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            background: #56d1c2;
            padding: 0.5rem;
            border-radius: 50px;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 0.8rem;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }
        
        .role-option.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        
        .form-control {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #5dadfc;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
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
            margin-top: 1rem;
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
        
        .links {
            text-align: center;
            margin-top: 1.5rem;
        }
        
        .links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 0.5rem;
            transition: opacity 0.3s;
        }
        
        .links a:hover {
            opacity: 0.8;
            text-decoration: underline;
        }
        
        .divider {
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #00d2b6;
            z-index: 1;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #666;
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- زر العودة للصفحة الرئيسية -->
        <a href="index.php" class="back-home">
            <i class="fas fa-arrow-right"></i> العودة للرئيسية
        </a>
        
        <div class="logo">
            <i class="fas fa-plus-circle"></i>
            <h1>CONNECT+</h1>
        </div>
        
        <h2 style="text-align: center; margin-bottom: 2rem;">تسجيل الدخول</h2>
        
        <?php if ($error): ?>
            <div class="alert"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="role-selector">
                <div class="role-option active" data-role="patient">مريض</div>
                <div class="role-option" data-role="doctor">طبيب</div>
                <div class="role-option" data-role="pharmacist">صيدلي</div>
                <div class="role-option" data-role="admin">مسؤول</div>
            </div>
            <input type="hidden" name="role" id="selectedRole" value="patient">
            
            <div class="form-group">
                <label>البريد الإلكتروني / رقم البطاقة / معرف المستخدم</label>
                <input type="text" name="login" class="form-control" placeholder="أدخل بريدك أو رقم البطاقة" required>
            </div>
            
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" class="form-control" placeholder="أدخل كلمة المرور" required>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </button>
        </form>
        
        <div class="links">
            <a href="forgot_password.php">نسيت كلمة المرور؟</a>
        </div>
        
        <div class="divider">
            <span>أو</span>
        </div>
        
        <div style="text-align: center;">
            <p>ليس لديك حساب؟ <a href="signup.php" style="color: var(--primary); font-weight: 600;">إنشاء حساب جديد</a></p>
        </div>
    </div>
    
    <script>
    document.querySelectorAll('.role-option').forEach(opt => {
        opt.addEventListener('click', function() {
            document.querySelectorAll('.role-option').forEach(o => o.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selectedRole').value = this.dataset.role;
        });
    });
    </script>
</body>
</html>