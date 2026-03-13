<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$user_id = $_SESSION['user_id'];
$other_id = (int)($_GET['other_id'] ?? 0);
$after = (int)($_GET['after'] ?? 0);

if (!$other_id) {
    echo json_encode(['success' => false, 'message' => 'معرف غير صحيح']);
    exit;
}

try {
    if ($after > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?))
            AND id > ?
            ORDER BY created_at ASC
        ");
        $stmt->execute([$user_id, $other_id, $other_id, $user_id, $after]);
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id, $other_id, $other_id, $user_id]);
        $messages = array_reverse($stmt->fetchAll());
    }
    
    $messages = $stmt->fetchAll();
    echo json_encode(['success' => true, 'messages' => $messages]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
?>