<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$pharmacist_id = (int)($_GET['id'] ?? 0);

if (!$pharmacist_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, ph.*
        FROM users u
        JOIN pharmacists ph ON u.id = ph.user_id
        WHERE u.id = ? AND u.role = 'pharmacist'
    ");
    $stmt->execute([$pharmacist_id]);
    $pharmacist = $stmt->fetch();
    
    if ($pharmacist) {
        echo json_encode(['success' => true, 'pharmacist' => $pharmacist]);
    } else {
        echo json_encode(['success' => false, 'message' => 'الصيدلي غير موجود']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>