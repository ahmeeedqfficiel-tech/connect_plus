<?php
/**
 * CONNECT+ - ملف الإعدادات الرئيسي
 * الإصدار: 2.0 (معاد برمجته بالكامل)
 */

// ============================================================
// بدء الجلسة (مرة واحدة فقط)
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// إعدادات قاعدة البيانات
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'connect_plus');
define('DB_USER', 'root');
define('DB_PASS', '');

// ============================================================
// إعدادات التطبيق
// ============================================================
define('APP_NAME', 'CONNECT+');
define('APP_URL', 'http://localhost/connect-plus');
define('APP_VERSION', '2.0');

// ============================================================
// الاتصال بقاعدة البيانات
// ============================================================
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("عذراً، حدث خطأ في الاتصال بقاعدة البيانات. الرجاء المحاولة لاحقاً.");
}
?>