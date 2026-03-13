<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$doctor_id = (int)($_GET['id'] ?? 0);

if (!$doctor_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT u.*, d.*,
               (SELECT AVG(rating) FROM ratings WHERE doctor_id = u.id) as avg_rating,
               (SELECT COUNT(*) FROM ratings WHERE doctor_id = u.id) as ratings_count,
               (SELECT COUNT(*) FROM appointments WHERE doctor_id = u.id) as appointments_count
        FROM users u
        JOIN doctors d ON u.id = d.user_id
        WHERE u.id = ? AND u.role = 'doctor'
    ");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch();
    
    if ($doctor) {
        echo json_encode(['success' => true, 'doctor' => $doctor]);
    } else {
        echo json_encode(['success' => false, 'message' => 'الطبيب غير موجود']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>