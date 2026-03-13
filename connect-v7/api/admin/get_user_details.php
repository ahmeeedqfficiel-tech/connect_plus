<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$user_id = (int)($_GET['id'] ?? 0);

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'المستخدم غير موجود']);
        exit;
    }
    
    if ($user['role'] == 'doctor') {
        $stmt = $pdo->prepare("SELECT * FROM doctors WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user['doctor'] = $stmt->fetch();
    } elseif ($user['role'] == 'patient') {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user['patient'] = $stmt->fetch();
    } elseif ($user['role'] == 'pharmacist') {
        $stmt = $pdo->prepare("SELECT * FROM pharmacists WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user['pharmacist'] = $stmt->fetch();
    }
    
    echo json_encode(['success' => true, 'user' => $user]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>