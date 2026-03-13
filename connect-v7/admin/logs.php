<?php
/**
 * admin/logs.php - سجلات النظام
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

// ============================================================
// جلب السجلات
// ============================================================
$action = $_GET['action'] ?? '';
$user_id = $_GET['user_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

$sql = "SELECT al.*, u.full_name, u.user_code, u.role, u.profile_image
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE 1=1";

$params = [];

if ($action) {
    $sql .= " AND al.action = ?";
    $params[] = $action;
}

if ($user_id) {
    $sql .= " AND al.user_id = ?";
    $params[] = $user_id;
}

if ($date_from) {
    $sql .= " AND DATE(al.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(al.created_at) <= ?";
    $params[] = $date_to;
}

// حساب العدد الإجمالي
$count_sql = str_replace("SELECT al.*, u.full_name, u.user_code, u.role, u.profile_image", "SELECT COUNT(*)", $sql);
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_logs = $count_stmt->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// جلب السجلات مع التقسيم
$sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// جلب قائمة الإجراءات للفلترة
$actions = $pdo->query("SELECT DISTINCT action FROM activity_log ORDER BY action")->fetchAll();

// جلب قائمة المستخدمين للفلترة
$users = $pdo->query("SELECT id, full_name, user_code FROM users ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سجلات النظام - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .filter-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        body.dark-mode .filter-section {
            background: #1E1E1E;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .logs-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .logs-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .logs-table tr:hover {
            background: var(--primary-soft);
        }
        
        .user-avatar-mini {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.8rem;
            overflow: hidden;
        }
        
        .user-avatar-mini img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .page-btn {
            padding: 0.5rem 1rem;
            border: 1px solid var(--light-gray);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--dark);
        }
        
        .page-btn:hover {
            background: var(--primary-soft);
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .export-btn {
            margin-top: 1rem;
            display: flex;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div class="content-wrapper">
        <!-- تضمين القائمة الجانبية -->
        <?php include 'sidebar.php'; ?>
        
        <div class="main-content">
            <!-- تضمين الشريط العلوي -->
            <?php include 'header.php'; ?>
            
            <!-- رسائل Toast -->
            <?php displayToast(); ?>
            
            <div class="card-header">
                <h1 class="page-title"><i class="fas fa-history"></i> سجلات النظام</h1>
                <button class="btn btn-outline" onclick="exportLogs()">
                    <i class="fas fa-download"></i> تصدير
                </button>
            </div>
            
            <!-- فلترة -->
            <div class="filter-section">
                <form method="get" class="filter-grid">
                    <select name="action" class="form-control">
                        <option value="">كل الإجراءات</option>
                        <?php foreach ($actions as $a): ?>
                            <option value="<?= $a['action'] ?>" <?= $action == $a['action'] ? 'selected' : '' ?>>
                                <?= $a['action'] ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="user_id" class="form-control">
                        <option value="">كل المستخدمين</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $user_id == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['full_name']) ?> (<?= $u['user_code'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>" placeholder="من تاريخ">
                    
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>" placeholder="إلى تاريخ">
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i> تصفية
                    </button>
                </form>
            </div>
            
            <!-- معلومات العدد -->
            <div style="margin-bottom: 1rem; color: var(--gray);">
                إجمالي السجلات: <strong><?= $total_logs ?></strong>
            </div>
            
            <!-- جدول السجلات -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="logs-table">
                        <thead>
                            <tr>
                                <th>التاريخ</th>
                                <th>المستخدم</th>
                                <th>الإجراء</th>
                                <th>الوصف</th>
                                <th>عنوان IP</th>
                                <th>الجهاز</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center" style="padding: 3rem;">
                                        لا توجد سجلات
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                <div class="user-avatar-mini">
                                                    <?php if (!empty($log['profile_image'])): ?>
                                                        <img src="../<?= $log['profile_image'] ?>" alt="">
                                                    <?php else: ?>
                                                        <i class="fas fa-user"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <?php if ($log['full_name']): ?>
                                                        <?= htmlspecialchars($log['full_name']) ?><br>
                                                        <small><?= $log['user_code'] ?> (<?= getRoleText($log['role'] ?? '') ?>)</small>
                                                    <?php else: ?>
                                                        <span style="color: var(--gray);">نظام</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-primary"><?= $log['action'] ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($log['description'] ?: '-') ?></td>
                                        <td><?= $log['ip_address'] ?: '-' ?></td>
                                        <td>
                                            <?php 
                                            if ($log['device_info']) {
                                                $device = json_decode($log['device_info'], true);
                                                echo $device['browser'] ?? $log['device_info'];
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- ترقيم الصفحات -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function exportLogs() {
        // إعادة التوجيه لتصدير السجلات
        window.location.href = 'export_logs.php?' + window.location.search.substring(1);
    }
    </script>
</body>
</html>