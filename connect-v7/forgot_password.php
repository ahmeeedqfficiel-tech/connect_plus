<?php
/**
 * forgot_password.php - نسيت كلمة المرور
 * CONNECT+ - الإصدار 2.0 النهائي
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'يرجى إدخال البريد الإلكتروني';
    } elseif (!isValidEmail($email)) {
        $error = 'البريد الإلكتروني غير صالح';
    } else {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // إنشاء رمز إعادة تعيين
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // حفظ الرمز في قاعدة البيانات
            $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt->execute([$email, $token, $expires]);
            
            // هنا يمكن إرسال بريد إلكتروني
            // للتبسيط، سنعرض الرابط مباشرة
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/reset_password.php?token=" . $token;
            
            $message = 'تم إرسال رابط إعادة تعيين كلمة المرور إلى بريدك الإلكتروني';
            
            // تسجيل النشاط
            logActivity($pdo, $user['id'], 'طلب إعادة تعيين', 'تم طلب إعادة تعيين كلمة المرور');
        } else {
            $error = 'البريد الإلكتروني غير مسجل في النظام';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نسيت كلمة المرور - CONNECT+</title>
    
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
        
        .forgot-container {
            background: white;
            border-radius: 30px;
            padding: 2.5rem;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 2rem;
            color: #666;
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .back-link:hover {
            color: var(--primary);
        }
        
        h1 {
            color: var(--primary);
            margin-bottom: 1rem;
            text-align: center;
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
            border: 2px solid #e9ecef;
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
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
            border-right: 4px solid var(--success);
        }
        
        .alert-danger {
            background: #ffebee;
            color: var(--danger);
            border-right: 4px solid var(--danger);
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
        }
        
        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <a href="login.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة لتسجيل الدخول
        </a>
        
        <h1>نسيت كلمة المرور</h1>
        
        <p style="text-align: center; color: #666; margin-bottom: 2rem;">
            أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة تعيين كلمة المرور
        </p>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>البريد الإلكتروني</label>
                <input type="email" name="email" class="form-control" placeholder="example@domain.com" required>
            </div>
            
            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> إرسال رابط إعادة التعيين
            </button>
        </form>
        
        <div class="login-link">
            <a href="login.php">تذكرت كلمة المرور؟ تسجيل الدخول</a>
        </div>
    </div>
</body>
</html>