<?php
/**
 * admin/profile_changes.php - طلبات تعديل الملفات
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

$admin = getCurrentUser($pdo);

// ============================================================
// قبول طلب تعديل
// ============================================================
if (isset($_GET['approve'])) {
    $change_id = (int)$_GET['approve'];
    
    try {
        $pdo->beginTransaction();
        
        // جلب تفاصيل التغيير
        $stmt = $pdo->prepare("SELECT * FROM profile_changes WHERE id = ? AND status = 'pending'");
        $stmt->execute([$change_id]);
        $change = $stmt->fetch();
        
        if ($change) {
            // تحديث الحقل في جدول users
            $table = 'users';
            if (in_array($change['field_name'], ['specialties', 'license_number', 'workplace_name', 'workplace_address', 'consultation_fees', 'degree'])) {
                $table = 'doctors';
            } elseif (in_array($change['field_name'], ['pharmacy_name', 'license_number', 'city', 'address', 'is_24h'])) {
                $table = 'pharmacists';
            } elseif (in_array($change['field_name'], ['emergency_name', 'emergency_phone', 'blood_type', 'chronic_diseases', 'allergies'])) {
                $table = 'patients';
            }
            
            if ($table == 'users') {
                $pdo->prepare("UPDATE users SET {$change['field_name']} = ? WHERE id = ?")
                    ->execute([$change['new_value'], $change['user_id']]);
            } else {
                $pdo->prepare("UPDATE $table SET {$change['field_name']} = ? WHERE user_id = ?")
                    ->execute([$change['new_value'], $change['user_id']]);
            }
            
            // تحديث حالة الطلب
            $pdo->prepare("UPDATE profile_changes SET status = 'approved', reviewed_at = NOW() WHERE id = ?")
                ->execute([$change_id]);
            
            // إشعار للمستخدم
            createNotification($pdo, $change['user_id'], 'approval', 'تم قبول طلب التعديل', "تم تحديث {$change['field_name']} بنجاح");
            
            $pdo->commit();
            setToast('تم قبول التعديل', 'success');
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        setToast('حدث خطأ: ' . $e->getMessage(), 'error');
    }
    
    redirect('profile_changes.php');
}

// ============================================================
// رفض طلب تعديل
// ============================================================
if (isset($_GET['reject'])) {
    $change_id = (int)$_GET['reject'];
    
    $pdo->prepare("UPDATE profile_changes SET status = 'rejected', reviewed_at = NOW() WHERE id = ?")->execute([$change_id]);
    setToast('تم رفض التعديل', 'success');
    redirect('profile_changes.php');
}

// ============================================================
// جلب طلبات التعديل المعلقة
// ============================================================
$pendingChanges = [];
$pendingStmt = $pdo->query("
    SELECT pc.*, u.user_code, u.full_name, u.role, u.profile_image
    FROM profile_changes pc
    JOIN users u ON pc.user_id = u.id
    WHERE pc.status = 'pending'
    ORDER BY pc.created_at ASC
");
if ($pendingStmt) {
    $pendingChanges = $pendingStmt->fetchAll();
}

// ============================================================
// جلب التعديلات المؤرشفة
// ============================================================
$archive = [];
$archiveStmt = $pdo->query("
    SELECT pc.*, u.user_code, u.full_name, u.role, u.profile_image
    FROM profile_changes pc
    JOIN users u ON pc.user_id = u.id
    WHERE pc.status != 'pending'
    ORDER BY pc.reviewed_at DESC
    LIMIT 50
");
if ($archiveStmt) {
    $archive = $archiveStmt->fetchAll();
}

// ============================================================
// أسماء الحقول للعرض
// ============================================================
$fieldNames = [
    'full_name' => 'الاسم الكامل',
    'email' => 'البريد الإلكتروني',
    'phone' => 'رقم الهاتف',
    'address' => 'العنوان',
    'city' => 'الولاية',
    'emergency_name' => 'اسم جهة اتصال الطوارئ',
    'emergency_phone' => 'هاتف الطوارئ',
    'blood_type' => 'فصيلة الدم',
    'chronic_diseases' => 'الأمراض المزمنة',
    'allergies' => 'الحساسيات',
    'degree' => 'الدرجة العلمية',
    'specialties' => 'التخصصات',
    'license_number' => 'رقم الترخيص',
    'workplace_name' => 'مكان العمل',
    'workplace_address' => 'عنوان العمل',
    'consultation_fees' => 'رسوم الكشف',
    'pharmacy_name' => 'اسم الصيدلية',
    'is_24h' => '24 ساعة'
];

// حساب الأعداد بأمان
$pendingCount = is_array($pendingChanges) ? count($pendingChanges) : 0;
$archiveCount = is_array($archive) ? count($archive) : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلبات تعديل الملفات - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        body.dark-mode .tabs {
            background: #1E1E1E;
        }
        
        .tab {
            flex: 1;
            padding: 0.8rem;
            background: var(--light-gray);
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
        }
        
        body.dark-mode .tab {
            background: #333;
            color: white;
        }
        
        .tab:hover {
            background: var(--primary-soft);
        }
        
        .tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .change-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-right: 5px solid var(--warning);
            box-shadow: var(--shadow-md);
        }
        
        body.dark-mode .change-card {
            background: #1E1E1E;
        }
        
        .change-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-avatar-small {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            overflow: hidden;
        }
        
        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .field-badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-weight: 600;
            display: inline-block;
        }
        
        .value-box {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 0.5rem 0;
        }
        
        body.dark-mode .value-box {
            background: #2D2D2D;
        }
        
        .old-value {
            border-right: 4px solid var(--danger);
        }
        
        .new-value {
            border-right: 4px solid var(--success);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .archive-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .archive-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .archive-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
            
            <h1 class="page-title" style="margin-bottom: 2rem;">
                <i class="fas fa-edit"></i> طلبات تعديل الملفات
            </h1>
            
            <!-- تبويبات -->
            <div class="tabs">
                <a href="#pending" class="tab active" onclick="showTab('pending')">طلبات جديدة (<?= $pendingCount ?>)</a>
                <a href="#archive" class="tab" onclick="showTab('archive')">الأرشيف (<?= $archiveCount ?>)</a>
            </div>
            
            <!-- الطلبات المعلقة -->
            <div id="pendingTab" class="tab-content active">
                <?php if (empty($pendingChanges)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success);"></i>
                        <h3 style="margin: 1rem 0;">لا توجد طلبات تعديل</h3>
                        <p style="color: var(--gray);">كل الطلبات تمت معالجتها</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pendingChanges as $change): ?>
                        <div class="change-card">
                            <div class="change-header">
                                <div class="user-info">
                                    <div class="user-avatar-small">
                                        <?php if (!empty($change['profile_image'])): ?>
                                            <img src="../<?= $change['profile_image'] ?>" alt="صورة">
                                        <?php else: ?>
                                            <i class="fas fa-<?= $change['role'] == 'doctor' ? 'user-md' : ($change['role'] == 'pharmacist' ? 'store' : 'user') ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h3><?= htmlspecialchars($change['full_name']) ?></h3>
                                        <p style="color: var(--gray);"><?= $change['user_code'] ?> • <?= getRoleText($change['role']) ?></p>
                                    </div>
                                </div>
                                <span class="field-badge"><?= $fieldNames[$change['field_name']] ?? $change['field_name'] ?></span>
                            </div>
                            
                            <div style="margin: 1rem 0;">
                                <div class="value-box old-value">
                                    <strong style="color: var(--danger);">القيمة القديمة:</strong>
                                    <p style="margin-top: 0.5rem;"><?= htmlspecialchars($change['old_value'] ?: 'فارغ') ?></p>
                                </div>
                                
                                <div class="value-box new-value">
                                    <strong style="color: var(--success);">القيمة الجديدة:</strong>
                                    <p style="margin-top: 0.5rem;"><?= htmlspecialchars($change['new_value'] ?: 'فارغ') ?></p>
                                </div>
                            </div>
                            
                            <div class="action-buttons">
                                <a href="?approve=<?= $change['id'] ?>" class="btn btn-success" style="flex: 1;" onclick="return confirm('قبول هذا التعديل؟')">
                                    <i class="fas fa-check"></i> قبول
                                </a>
                                <a href="?reject=<?= $change['id'] ?>" class="btn btn-danger" style="flex: 1;" onclick="return confirm('رفض هذا التعديل؟')">
                                    <i class="fas fa-times"></i> رفض
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- الأرشيف -->
            <div id="archiveTab" class="tab-content">
                <?php if (empty($archive)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <p>لا توجد تعديلات سابقة</p>
                    </div>
                <?php else: ?>
                    <div class="info-card">
                        <div style="overflow-x: auto;">
                            <table class="archive-table">
                                <thead>
                                    <tr>
                                        <th>المستخدم</th>
                                        <th>الحقل</th>
                                        <th>القيمة القديمة</th>
                                        <th>القيمة الجديدة</th>
                                        <th>الحالة</th>
                                        <th>تاريخ الطلب</th>
                                        <th>تاريخ المراجعة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archive as $arc): ?>
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <div class="user-avatar-small" style="width: 30px; height: 30px;">
                                                        <?php if (!empty($arc['profile_image'])): ?>
                                                            <img src="../<?= $arc['profile_image'] ?>" style="width:100%; height:100%;">
                                                        <?php else: ?>
                                                            <i class="fas fa-user" style="font-size: 0.8rem;"></i>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <?= htmlspecialchars($arc['full_name']) ?><br>
                                                        <small><?= $arc['user_code'] ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= $fieldNames[$arc['field_name']] ?? $arc['field_name'] ?></td>
                                            <td><?= htmlspecialchars(substr($arc['old_value'] ?: 'فارغ', 0, 30)) ?></td>
                                            <td><?= htmlspecialchars(substr($arc['new_value'], 0, 30)) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $arc['status'] ?>">
                                                    <?= $arc['status'] == 'approved' ? 'مقبول' : 'مرفوض' ?>
                                                </span>
                                            </td>
                                            <td><?= date('Y-m-d', strtotime($arc['created_at'])) ?></td>
                                            <td><?= $arc['reviewed_at'] ? date('Y-m-d', strtotime($arc['reviewed_at'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function showTab(tab) {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        if (tab === 'pending') {
            document.querySelector('.tab').classList.add('active');
            document.getElementById('pendingTab').classList.add('active');
        } else {
            document.querySelectorAll('.tab')[1].classList.add('active');
            document.getElementById('archiveTab').classList.add('active');
        }
    }
    </script>
</body>
</html>