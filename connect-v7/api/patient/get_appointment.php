<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$appointment_id = (int)($_GET['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if (!$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name as doctor_name, u.profile_image as doctor_image,
               d.specialties
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctors d ON a.doctor_id = d.user_id
        WHERE a.id = ? AND a.patient_id = ?
    ");
    $stmt->execute([$appointment_id, $user_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        echo json_encode(['success' => true, 'data' => $appointment]);
    } else {
        echo json_encode(['success' => false, 'message' => 'الموعد غير موجود']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>