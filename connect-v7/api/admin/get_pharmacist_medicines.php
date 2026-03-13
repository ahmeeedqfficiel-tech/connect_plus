<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$pharmacist_id = (int)($_GET['pharmacist_id'] ?? 0);

if (!$pharmacist_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM pharmacies WHERE pharmacist_id = ?");
    $stmt->execute([$pharmacist_id]);
    $pharmacy = $stmt->fetch();
    
    if (!$pharmacy) {
        echo json_encode(['success' => false, 'message' => 'الصيدلية غير موجودة']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT pm.*, m.name, m.scientific_name
        FROM pharmacy_medicines pm
        JOIN medicines m ON pm.medicine_id = m.id
        WHERE pm.pharmacy_id = ?
    ");
    $stmt->execute([$pharmacy['id']]);
    $medicines = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'pharmacy_name' => $pharmacy['name'],
        'medicines' => $medicines
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>