<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($data['id'] ?? 0);
$status = $data['status'] ?? '';
$pharmacist_id = $_SESSION['user_id'];

$allowed_status = ['confirmed', 'ready', 'delivered', 'cancelled'];

if (!$order_id || !in_array($status, $allowed_status)) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE medicine_orders mo
        JOIN pharmacies p ON mo.pharmacy_id = p.id
        SET mo.status = ?
        WHERE mo.id = ? AND p.pharmacist_id = ?
    ");
    $result = $stmt->execute([$status, $order_id, $pharmacist_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل التحديث']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>