<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'doctor') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$prescription_id = (int)($_GET['id'] ?? 0);
$doctor_id = $_SESSION['user_id'];

if (!$prescription_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as patient_name, u.phone
        FROM prescriptions p
        JOIN users u ON p.patient_id = u.id
        WHERE p.id = ? AND p.doctor_id = ?
    ");
    $stmt->execute([$prescription_id, $doctor_id]);
    $prescription = $stmt->fetch();
    
    if (!$prescription) {
        echo json_encode(['success' => false, 'message' => 'الوصفة غير موجودة']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM prescription_medicines WHERE prescription_id = ? ORDER BY id");
    $stmt->execute([$prescription_id]);
    $prescription['medicines'] = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $prescription]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>