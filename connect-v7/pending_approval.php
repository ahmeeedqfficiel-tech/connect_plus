<?php
/**
 * pending_approval.php - صفحة انتظار التأكيد
 * CONNECT+ - الإصدار 2.0 النهائي
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// التحقق من وجود معرف المستخدم المعلق
$pending_id = $_SESSION['pending_user_id'] ?? 0;

if (!$pending_id) {
    header('Location: index.php');
    exit;
}

// جلب معلومات المستخدم
$stmt = $pdo->prepare("SELECT user_code, full_name, email, role, created_at FROM users WHERE id = ?");
$stmt->execute([$pending_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بانتظار التأكيد - CONNECT+</title>
    
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
        
        .pending-container {
            background: white;
            border-radius: 30px;
            padding: 3rem;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        
        .pending-icon {
            font-size: 5rem;
            color: var(--warning);
            margin-bottom: 2rem;
        }
        
        h1 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .user-info {
            background: #f8f9fa;
            border-radius: 20px;
            padding: 1.5rem;
            margin: 2rem 0;
            text-align: right;
        }
        
        .user-info p {
            margin: 0.5rem 0;
        }
        
        .info-label {
            font-weight: 600;
            color: #6c757d;
            display: inline-block;
            width: 100px;
        }
        
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(42, 157, 143, 0.3);
        }
        
        .note {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 1.5rem 0;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: #e9ecef;
            border-radius: 10px;
            margin: 2rem 0;
            overflow: hidden;
        }
        
        .progress-fill {
            width: 50%;
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            animation: progress 2s ease-in-out infinite;
        }
        
        @keyframes progress {
            0% { width: 30%; }
            50% { width: 70%; }
            100% { width: 30%; }
        }
    </style>
</head>
<body>
    <div class="pending-container">
        <div class="pending-icon">
            <i class="fas fa-hourglass-half"></i>
        </div>
        
        <h1>بانتظار التأكيد</h1>
        
        <p style="color: #6c757d; margin-bottom: 2rem;">
            شكراً لتسجيلك في منصة <strong>CONNECT+</strong>
        </p>
        
        <div class="progress-bar">
            <div class="progress-fill"></div>
        </div>
        
        <div class="user-info">
            <p>
                <span class="info-label">الكود:</span>
                <span><?= $user['user_code'] ?></span>
            </p>
            <p>
                <span class="info-label">الاسم:</span>
                <span><?= htmlspecialchars($user['full_name']) ?></span>
            </p>
            <p>
                <span class="info-label">البريد:</span>
                <span><?= $user['email'] ?></span>
            </p>
            <p>
                <span class="info-label">الدور:</span>
                <span><?= getRoleText($user['role']) ?></span>
            </p>
            <p>
                <span class="info-label">تاريخ التسجيل:</span>
                <span><?= date('Y-m-d', strtotime($user['created_at'])) ?></span>
            </p>
        </div>
        
        <p class="note">
            <i class="fas fa-info-circle"></i>
            حسابك قيد المراجعة من قبل الإدارة. سيتم إشعارك عبر البريد الإلكتروني عند التأكيد.
            <br><br>
            <strong>ملاحظة:</strong> تستغرق عملية المراجعة عادة 24 ساعة كحد أقصى.
        </p>
        
        <a href="logout.php" class="btn">
            <i class="fas fa-home"></i> العودة للرئيسية
        </a>
    </div>
</body>
</html>