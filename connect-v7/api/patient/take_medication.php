<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$medication_id = (int)($data['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$medication_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT pm.id FROM prescription_medicines pm
        JOIN prescriptions p ON pm.prescription_id = p.id
        WHERE pm.id = ? AND p.patient_id = ?
    ");
    $stmt->execute([$medication_id, $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح']);
        exit;
    }
    
    echo json_encode(['success' => true, 'message' => 'تم التسجيل']);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>