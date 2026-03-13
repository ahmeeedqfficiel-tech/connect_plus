<?php
session_start();
require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$activity_id = (int)($_GET['id'] ?? 0);

if (!$activity_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.full_name
        FROM activity_log a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$activity_id]);
    $activity = $stmt->fetch();
    
    if ($activity) {
        echo json_encode(['success' => true, 'activity' => $activity]);
    } else {
        echo json_encode(['success' => false, 'message' => 'النشاط غير موجود']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>