<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'pharmacist') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$order_id = (int)($_GET['id'] ?? 0);
$pharmacist_id = $_SESSION['user_id'];

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT mo.*, u.full_name as patient_name, u.phone
        FROM medicine_orders mo
        JOIN users u ON mo.patient_id = u.id
        JOIN pharmacies p ON mo.pharmacy_id = p.id
        WHERE mo.id = ? AND p.pharmacist_id = ?
    ");
    $stmt->execute([$order_id, $pharmacist_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'الطلب غير موجود']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as medicine_name
        FROM order_items oi
        JOIN medicines m ON oi.medicine_id = m.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order['items'] = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $order]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>