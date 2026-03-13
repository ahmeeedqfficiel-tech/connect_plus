<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$medication_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$medication_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT pm.*, p.prescription_date, u.full_name as doctor_name
        FROM prescription_medicines pm
        JOIN prescriptions p ON pm.prescription_id = p.id
        LEFT JOIN users u ON p.doctor_id = u.id
        WHERE pm.id = ? AND p.patient_id = ?
    ");
    $stmt->execute([$medication_id, $user_id]);
    $medication = $stmt->fetch();
    
    if ($medication) {
        echo json_encode(['success' => true, 'data' => $medication]);
    } else {
        echo json_encode(['success' => false, 'message' => 'الدواء غير موجود']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>