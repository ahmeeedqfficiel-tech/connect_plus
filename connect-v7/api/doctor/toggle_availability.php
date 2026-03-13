<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$doctor_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$is_available = isset($data['is_available']) ? (int)$data['is_available'] : null;

if ($is_available === null) {
    // تبديل الحالة
    $stmt = $pdo->prepare("SELECT is_available FROM users WHERE id = ?");
    $stmt->execute([$doctor_id]);
    $current = $stmt->fetchColumn();
    $is_available = $current ? 0 : 1;
}

try {
    $stmt = $pdo->prepare("UPDATE users SET is_available = ? WHERE id = ?");
    $stmt->execute([$is_available, $doctor_id]);
    
    echo json_encode(['success' => true, 'is_available' => $is_available]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>