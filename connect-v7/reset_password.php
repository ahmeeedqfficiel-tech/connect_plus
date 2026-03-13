<?php
/**
 * reset_password.php - إعادة تعيين كلمة المرور
 * CONNECT+ - الإصدار 2.0 النهائي
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

$message = '';
$error = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: forgot_password.php');
    exit;
}

// التحقق من صحة الرمز
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() AND used = 0");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) {
    $error = 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'يرجى إدخال كلمة المرور الجديدة';
    } elseif ($password !== $confirm) {
        $error = 'كلمة المرور غير متطابقة';
    } elseif (strlen($password) < 4) {
        $error = 'كلمة المرور قصيرة جداً (4 أحرف على الأقل)';
    } else {
        // تحديث كلمة المرور
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$password, $reset['email']]);
        
        // تعليم الرمز كمستخدم
        $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
        $stmt->execute([$token]);
        
        // جلب المستخدم للتسجيل
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$reset['email']]);
        $user_id = $stmt->fetchColumn();
        
        // تسجيل النشاط
        logActivity($pdo, $user_id, 'إعادة تعيين', 'تم إعادة تعيين كلمة المرور');
        
        setToast('تم تغيير كلمة المرور بنجاح', 'success');
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور - CONNECT+</title>
    
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
        
        .reset-container {
            background: white;
            border-radius: 30px;
            padding: 2.5rem;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
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
    </style>
</head>
<body>
    <div class="reset-container">
        <h1>إعادة تعيين كلمة المرور</h1>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <?php if (!$error): ?>
            <p style="text-align: center; color: #666; margin-bottom: 2rem;">
                أدخل كلمة المرور الجديدة
            </p>
            
            <form method="post">
                <div class="form-group">
                    <label>كلمة المرور الجديدة</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>تأكيد كلمة المرور</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> حفظ كلمة المرور الجديدة
                </button>
            </form>
        <?php endif; ?>
        
        <div class="login-link">
            <a href="login.php">العودة لتسجيل الدخول</a>
        </div>
    </div>
</body>
</html>