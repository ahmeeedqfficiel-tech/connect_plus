<?php
/**
 * logout.php - تسجيل الخروج
 * CONNECT+ - الإصدار 2.0 النهائي
 */

// بدء الجلسة
session_start();

// تضمين الملفات الأساسية (اختياري - للتسجيل)
require_once 'includes/config.php';
require_once 'includes/functions.php';

// تسجيل الخروج في سجل النشاطات (إذا كان هناك مستخدم مسجل)
if (isset($_SESSION['user_id']) && isset($pdo)) {
    logActivity($pdo, $_SESSION['user_id'], 'تسجيل خروج', 'تسجيل خروج من النظام');
}

// تنظيف الجلسة بالكامل
$_SESSION = array();

// حذف كوكي الجلسة إذا كان موجوداً
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// تدمير الجلسة
session_destroy();

// إعادة التوجيه إلى الصفحة الرئيسية مع رسالة
setToast('تم تسجيل الخروج بنجاح', 'success');
header('Location: index.php');
exit;
?>