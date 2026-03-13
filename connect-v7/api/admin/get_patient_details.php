<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$patient_id = (int)($_GET['id'] ?? 0);

if (!$patient_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, p.*,
               (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id) as appointments_count,
               (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id) as prescriptions_count
        FROM users u
        JOIN patients p ON u.id = p.user_id
        WHERE u.id = ? AND u.role = 'patient'
    ");
    $stmt->execute([$patient_id]);
    $patient = $stmt->fetch();
    
    if ($patient) {
        echo json_encode(['success' => true, 'patient' => $patient]);
    } else {
        echo json_encode(['success' => false, 'message' => 'المريض غير موجود']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>