<?php
/**
 * admin/users.php - إدارة المستخدمين مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

$user = getCurrentUser($pdo);

// ============================================================
// حذف مستخدم
// ============================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // التحقق من أن المستخدم ليس أدمن
    $role = $pdo->prepare("SELECT role FROM users WHERE id = ?")->execute([$id]) ? 
            $pdo->query("SELECT role FROM users WHERE id = $id")->fetchColumn() : '';
    
    if ($role !== 'admin') {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
        setToast('تم حذف المستخدم', 'success');
    } else {
        setToast('لا يمكن حذف حساب أدمن', 'error');
    }
    
    redirect('users.php');
}

// ============================================================
// توثيق مستخدم
// ============================================================
if (isset($_GET['verify'])) {
    $id = (int)$_GET['verify'];
    
    $pdo->prepare("UPDATE users SET is_verified = 1 WHERE id = ?")->execute([$id]);
    
    // تحديث طلب التأكيد إن وجد
    $pdo->prepare("UPDATE approval_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE user_id = ? AND status = 'pending'")
        ->execute([$user['id'], $id]);
    
    // إشعار للمستخدم
    createNotification($pdo, $id, 'approval', 'تم توثيق حسابك', 'يمكنك الآن استخدام المنصة بكامل مزاياها');
    
    setToast('تم توثيق المستخدم', 'success');
    redirect('users.php');
}

// ============================================================
// جلب جميع المستخدمين مع معلومات إضافية
// ============================================================
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$sql = "
    SELECT u.id, u.user_code, u.full_name, u.email, u.phone, u.role, u.is_verified, 
           u.created_at, u.profile_image, u.city, u.address, u.birth_date, u.gender,
           u.national_id, u.last_login,
           d.specialties, d.degree, d.license_number, d.workplace_name,
           ph.pharmacy_name, ph.city as pharmacy_city,
           p.blood_type, p.emergency_name, p.emergency_phone,
           (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id) as appointments_count,
           (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id) as prescriptions_count,
           (SELECT COUNT(*) FROM medicine_orders WHERE patient_id = u.id) as orders_count
    FROM users u
    LEFT JOIN doctors d ON u.id = d.user_id AND u.role = 'doctor'
    LEFT JOIN pharmacists ph ON u.id = ph.user_id AND u.role = 'pharmacist'
    LEFT JOIN patients p ON u.id = p.user_id AND u.role = 'patient'
    WHERE 1=1
";

$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.user_code LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'verified') {
    $sql .= " AND u.is_verified = 1";
} elseif ($status_filter === 'unverified') {
    $sql .= " AND u.is_verified = 0 AND u.role != 'admin'";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

// ============================================================
// إحصائيات سريعة
// ============================================================
$totalUsers = count($users);
$verifiedCount = 0;
$unverifiedCount = 0;
$adminCount = 0;
$doctorCount = 0;
$patientCount = 0;
$pharmacistCount = 0;

foreach ($users as $u) {
    if ($u['role'] == 'admin') $adminCount++;
    elseif ($u['role'] == 'doctor') $doctorCount++;
    elseif ($u['role'] == 'patient') $patientCount++;
    elseif ($u['role'] == 'pharmacist') $pharmacistCount++;
    
    if ($u['is_verified'] || $u['role'] == 'admin') {
        $verifiedCount++;
    } else {
        $unverifiedCount++;
    }
}

// تحويل بيانات المستخدمين إلى JSON لاستخدامها في JavaScript
$usersJson = json_encode($users);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-mini-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }
        
        body.dark-mode .stat-mini-card {
            background: #1E1E1E;
        }
        
        .stat-mini-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }
        
        .stat-mini-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
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
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .users-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .users-table tr:hover {
            background: var(--primary-soft);
            cursor: pointer;
        }
        
        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            overflow: hidden;
        }
        
        .user-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .action-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            margin: 0 0.2rem;
        }
        
        .action-btn.view {
            background: var(--info);
            color: white;
        }
        
        .action-btn.delete {
            background: var(--danger);
            color: white;
        }
        
        .action-btn.verify {
            background: var(--success);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .role-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .role-patient { background: rgba(42, 157, 143, 0.1); color: var(--primary); }
        .role-doctor { background: rgba(42, 157, 143, 0.1); color: var(--primary); }
        .role-pharmacist { background: rgba(42, 157, 143, 0.1); color: var(--primary); }
        .role-admin { background: rgba(231, 111, 81, 0.1); color: var(--danger); }
        
        /* تنسيق النافذة الجانبية */
        .info-row {
            display: flex;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-label {
            width: 120px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .section-title-small {
            color: var(--primary);
            font-size: 1.1rem;
            margin: 1.5rem 0 1rem 0;
            border-bottom: 1px solid var(--light-gray);
            padding-bottom: 0.5rem;
        }
        
        .documents-list {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        
        .doc-link {
            padding: 0.3rem 0.8rem;
            background: var(--light-gray);
            border-radius: 30px;
            text-decoration: none;
            color: var(--dark);
            font-size: 0.8rem;
            transition: var(--transition);
        }
        
        .doc-link:hover {
            background: var(--primary);
            color: white;
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
        }
        
        .page-btn:hover {
            background: var(--primary-soft);
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
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
                <h1 class="page-title"><i class="fas fa-users"></i> إدارة المستخدمين</h1>
                <button class="btn btn-primary" onclick="openSideModal('addUserSideModal')">
                    <i class="fas fa-user-plus"></i> إضافة مستخدم
                </button>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card" onclick="window.location.href='users.php'">
                    <div class="stat-mini-number"><?= $totalUsers ?></div>
                    <div style="color: var(--gray);">إجمالي</div>
                </div>
                <div class="stat-mini-card" onclick="window.location.href='users.php?status=verified'">
                    <div class="stat-mini-number"><?= $verifiedCount ?></div>
                    <div style="color: var(--gray);">موثق</div>
                </div>
                <div class="stat-mini-card" onclick="window.location.href='users.php?status=unverified'">
                    <div class="stat-mini-number"><?= $unverifiedCount ?></div>
                    <div style="color: var(--gray);">غير موثق</div>
                </div>
                <div class="stat-mini-card" onclick="window.location.href='users.php?role=patient'">
                    <div class="stat-mini-number"><?= $patientCount ?></div>
                    <div style="color: var(--gray);">مرضى</div>
                </div>
                <div class="stat-mini-card" onclick="window.location.href='users.php?role=doctor'">
                    <div class="stat-mini-number"><?= $doctorCount ?></div>
                    <div style="color: var(--gray);">أطباء</div>
                </div>
                <div class="stat-mini-card" onclick="window.location.href='users.php?role=pharmacist'">
                    <div class="stat-mini-number"><?= $pharmacistCount ?></div>
                    <div style="color: var(--gray);">صيادلة</div>
                </div>
                <div class="stat-mini-card" onclick="window.location.href='users.php?role=admin'">
                    <div class="stat-mini-number"><?= $adminCount ?></div>
                    <div style="color: var(--gray);">أدمن</div>
                </div>
            </div>
            
            <!-- فلترة وبحث -->
            <div class="filter-section">
                <form method="get" class="filter-grid">
                    <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو البريد أو الكود أو الهاتف..." value="<?= htmlspecialchars($search) ?>">
                    
                    <select name="role" class="form-control">
                        <option value="">كل الأدوار</option>
                        <option value="patient" <?= $role_filter == 'patient' ? 'selected' : '' ?>>مريض</option>
                        <option value="doctor" <?= $role_filter == 'doctor' ? 'selected' : '' ?>>طبيب</option>
                        <option value="pharmacist" <?= $role_filter == 'pharmacist' ? 'selected' : '' ?>>صيدلي</option>
                        <option value="admin" <?= $role_filter == 'admin' ? 'selected' : '' ?>>أدمن</option>
                    </select>
                    
                    <select name="status" class="form-control">
                        <option value="">كل الحالات</option>
                        <option value="verified" <?= $status_filter == 'verified' ? 'selected' : '' ?>>موثق</option>
                        <option value="unverified" <?= $status_filter == 'unverified' ? 'selected' : '' ?>>غير موثق</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </form>
            </div>
            
            <!-- جدول المستخدمين -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>الكود</th>
                                <th>الاسم</th>
                                <th>البريد</th>
                                <th>الهاتف</th>
                                <th>المدينة</th>
                                <th>الدور</th>
                                <th>الحالة</th>
                                <th>تاريخ التسجيل</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="10" class="text-center" style="padding: 3rem;">
                                        لا يوجد مستخدمين
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr onclick="viewUserDetails(<?= $u['id'] ?>)">
                                        <td onclick="event.stopPropagation()">
                                            <div class="user-avatar-small">
                                                <?php if (!empty($u['profile_image'])): ?>
                                                    <img src="../<?= $u['profile_image'] ?>" alt="صورة">
                                                <?php else: ?>
                                                    <i class="fas fa-<?= $u['role'] == 'doctor' ? 'user-md' : ($u['role'] == 'pharmacist' ? 'store' : ($u['role'] == 'admin' ? 'crown' : 'user')) ?>"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><strong><?= $u['user_code'] ?></strong></td>
                                        <td><?= htmlspecialchars($u['full_name']) ?></td>
                                        <td><?= $u['email'] ?></td>
                                        <td><?= $u['phone'] ?: '-' ?></td>
                                        <td><?= $u['city'] ?: '-' ?></td>
                                        <td>
                                            <span class="role-badge role-<?= $u['role'] ?>">
                                                <?= getRoleText($u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($u['role'] == 'admin'): ?>
                                                <span class="badge badge-primary">أدمن</span>
                                            <?php elseif ($u['is_verified']): ?>
                                                <span class="badge badge-success">موثق</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">غير موثق</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('Y-m-d', strtotime($u['created_at'])) ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <button class="action-btn view" onclick="viewUserDetails(<?= $u['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if (!$u['is_verified'] && $u['role'] != 'admin'): ?>
                                                <button class="action-btn verify" onclick="verifyUser(<?= $u['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($u['role'] != 'admin'): ?>
                                                <button class="action-btn delete" onclick="deleteUser(<?= $u['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لتفاصيل المستخدم
         ============================================================ -->
    <div class="side-modal" id="userDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user"></i> تفاصيل المستخدم</h3>
            <button class="close-side-modal" onclick="closeSideModal('userDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="userDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لإضافة مستخدم
         ============================================================ -->
    <div class="side-modal" id="addUserSideModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user-plus"></i> إضافة مستخدم جديد</h3>
            <button class="close-side-modal" onclick="closeSideModal('addUserSideModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post" action="add_user.php" enctype="multipart/form-data">
                <div class="form-group">
                    <label>الدور</label>
                    <select name="role" id="userRoleSelect" class="form-control" required onchange="toggleRoleFields()">
                        <option value="patient">مريض</option>
                        <option value="doctor">طبيب</option>
                        <option value="pharmacist">صيدلي</option>
                        <option value="admin">أدمن</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="full_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>رقم الهاتف</label>
                    <input type="text" name="phone" class="form-control">
                </div>
                
                <!-- حقول المريض -->
                <div id="patientFields" style="display: block;">
                    <h4 style="margin: 1rem 0;">معلومات إضافية للمريض</h4>
                    <div class="form-group">
                        <label>جهة اتصال الطوارئ (الاسم)</label>
                        <input type="text" name="emergency_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>هاتف الطوارئ</label>
                        <input type="text" name="emergency_phone" class="form-control">
                    </div>
                </div>
                
                <!-- حقول الطبيب -->
                <div id="doctorFields" style="display: none;">
                    <h4 style="margin: 1rem 0;">معلومات الطبيب</h4>
                    <div class="form-group">
                        <label>التخصص</label>
                        <input type="text" name="specialties" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>رقم الترخيص</label>
                        <input type="text" name="license_number" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>مكان العمل</label>
                        <input type="text" name="workplace_name" class="form-control">
                    </div>
                </div>
                
                <!-- حقول الصيدلي -->
                <div id="pharmacistFields" style="display: none;">
                    <h4 style="margin: 1rem 0;">معلومات الصيدلي</h4>
                    <div class="form-group">
                        <label>اسم الصيدلية</label>
                        <input type="text" name="pharmacy_name" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>رقم ترخيص الصيدلية</label>
                        <input type="text" name="pharmacy_license" class="form-control">
                    </div>
                </div>
                
                <button type="submit" name="add_user" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-user-plus"></i> إضافة المستخدم
                </button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // تحويل بيانات المستخدمين إلى JavaScript
    const usersData = <?= json_encode($users) ?>;
    
    // ============================================================
    // تبديل حقول إضافة المستخدم
    // ============================================================
    function toggleRoleFields() {
        const role = document.getElementById('userRoleSelect').value;
        
        document.getElementById('patientFields').style.display = role === 'patient' ? 'block' : 'none';
        document.getElementById('doctorFields').style.display = role === 'doctor' ? 'block' : 'none';
        document.getElementById('pharmacistFields').style.display = role === 'pharmacist' ? 'block' : 'none';
    }
    
    // ============================================================
    // عرض تفاصيل المستخدم
    // ============================================================
    function viewUserDetails(id) {
        event?.stopPropagation();
        
        // البحث عن المستخدم في المصفوفة
        const user = usersData.find(u => u.id == id);
        
        if (user) {
            openSideModal('userDetailsModal');
            
            let roleText = getRoleText(user.role);
            let verifiedText = user.is_verified ? 'موثق' : 'غير موثق';
            let verifiedClass = user.is_verified ? 'badge-success' : 'badge-warning';
            
            // معلومات إضافية حسب الدور
            let extraInfo = '';
            let documents = '';
            
            if (user.role == 'doctor') {
                extraInfo = `
                    <h4 class="section-title-small"><i class="fas fa-stethoscope"></i> معلومات مهنية</h4>
                    <div class="info-row"><span class="info-label">التخصص:</span><span class="info-value">${user.specialties || '-'}</span></div>
                    <div class="info-row"><span class="info-label">الدرجة:</span><span class="info-value">${user.degree || '-'}</span></div>
                    <div class="info-row"><span class="info-label">رقم الترخيص:</span><span class="info-value">${user.license_number || '-'}</span></div>
                    <div class="info-row"><span class="info-label">مكان العمل:</span><span class="info-value">${user.workplace_name || '-'}</span></div>
                `;
            } else if (user.role == 'pharmacist') {
                extraInfo = `
                    <h4 class="section-title-small"><i class="fas fa-store"></i> معلومات الصيدلية</h4>
                    <div class="info-row"><span class="info-label">اسم الصيدلية:</span><span class="info-value">${user.pharmacy_name || '-'}</span></div>
                    <div class="info-row"><span class="info-label">مدينة الصيدلية:</span><span class="info-value">${user.pharmacy_city || '-'}</span></div>
                `;
            } else if (user.role == 'patient') {
                extraInfo = `
                    <h4 class="section-title-small"><i class="fas fa-heartbeat"></i> معلومات طبية</h4>
                    <div class="info-row"><span class="info-label">فصيلة الدم:</span><span class="info-value">${user.blood_type || '-'}</span></div>
                    <div class="info-row"><span class="info-label">جهة اتصال:</span><span class="info-value">${user.emergency_name || '-'} (${user.emergency_phone || '-'})</span></div>
                `;
            }
            
            // إحصائيات
            let stats = '';
            if (user.role == 'patient') {
                stats = `
                    <h4 class="section-title-small"><i class="fas fa-chart-bar"></i> إحصائيات</h4>
                    <div class="info-row"><span class="info-label">المواعيد:</span><span class="info-value">${user.appointments_count || 0}</span></div>
                    <div class="info-row"><span class="info-label">الوصفات:</span><span class="info-value">${user.prescriptions_count || 0}</span></div>
                    <div class="info-row"><span class="info-label">الطلبات:</span><span class="info-value">${user.orders_count || 0}</span></div>
                `;
            }
            
            // قائمة المستندات (مثال)
            let docs = [];
            if (user.id_card_image) docs.push('بطاقة التعريف');
            if (user.birth_certificate) docs.push('شهادة الميلاد');
            if (user.license_image) docs.push('رخصة مزاولة');
            if (user.pharmacy_license) docs.push('رخصة صيدلية');
            
            if (docs.length > 0) {
                documents = `
                    <h4 class="section-title-small"><i class="fas fa-file-alt"></i> المستندات</h4>
                    <div class="documents-list">
                        ${docs.map(doc => `<span class="doc-link"><i class="fas fa-file"></i> ${doc}</span>`).join('')}
                    </div>
                `;
            }
            
            document.getElementById('userDetailsContent').innerHTML = `
                <div style="text-align: center;">
                    <div class="user-avatar-small" style="width: 80px; height: 80px; margin: 0 auto;">
                        ${user.profile_image ? `<img src="../${user.profile_image}">` : `<i class="fas fa-${user.role == 'doctor' ? 'user-md' : (user.role == 'pharmacist' ? 'store' : (user.role == 'admin' ? 'crown' : 'user'))}"></i>`}
                    </div>
                    <h3 style="margin-top: 1rem;">${user.full_name}</h3>
                    <p style="color: var(--gray);">${user.user_code}</p>
                    <div style="margin: 0.5rem 0;">
                        <span class="badge badge-primary">${roleText}</span>
                        <span class="badge ${verifiedClass}">${verifiedText}</span>
                    </div>
                </div>
                
                <hr>
                
                <h4 class="section-title-small"><i class="fas fa-address-card"></i> معلومات شخصية</h4>
                <div class="info-row"><span class="info-label">البريد:</span><span class="info-value">${user.email}</span></div>
                <div class="info-row"><span class="info-label">الهاتف:</span><span class="info-value">${user.phone || '-'}</span></div>
                <div class="info-row"><span class="info-label">المدينة:</span><span class="info-value">${user.city || '-'}</span></div>
                <div class="info-row"><span class="info-label">العنوان:</span><span class="info-value">${user.address || '-'}</span></div>
                <div class="info-row"><span class="info-label">تاريخ الميلاد:</span><span class="info-value">${user.birth_date || '-'}</span></div>
                <div class="info-row"><span class="info-label">الجنس:</span><span class="info-value">${user.gender == 'male' ? 'ذكر' : (user.gender == 'female' ? 'أنثى' : '-')}</span></div>
                <div class="info-row"><span class="info-label">رقم البطاقة:</span><span class="info-value">${user.national_id || '-'}</span></div>
                <div class="info-row"><span class="info-label">آخر دخول:</span><span class="info-value">${user.last_login ? user.last_login : '-'}</span></div>
                <div class="info-row"><span class="info-label">تاريخ التسجيل:</span><span class="info-value">${user.created_at}</span></div>
                
                ${extraInfo}
                ${stats}
                ${documents}
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button class="btn btn-primary" onclick="window.location.href='mailto:${user.email}'" style="flex: 1;">
                        <i class="fas fa-envelope"></i> مراسلة
                    </button>
                    ${user.role != 'admin' ? `
                        <button class="btn btn-outline" onclick="editUser(${user.id})" style="flex: 1;">
                            <i class="fas fa-edit"></i> تعديل
                        </button>
                    ` : ''}
                </div>
            `;
        } else {
            showToast('لم يتم العثور على المستخدم', 'error');
        }
    }
    
    // ============================================================
    // توثيق المستخدم
    // ============================================================
    function verifyUser(id) {
        event?.stopPropagation();
        if (confirm('تأكيد توثيق هذا المستخدم؟')) {
            window.location.href = `?verify=${id}`;
        }
    }
    
    // ============================================================
    // حذف المستخدم
    // ============================================================
    function deleteUser(id) {
        event?.stopPropagation();
        if (confirm('هل أنت متأكد من حذف هذا المستخدم؟')) {
            window.location.href = `?delete=${id}`;
        }
    }
    
    // ============================================================
    // تعديل المستخدم
    // ============================================================
    function editUser(id) {
        window.location.href = `edit_user.php?id=${id}`;
    }
    
    // ============================================================
    // دالة مساعدة لترجمة الأدوار
    // ============================================================
    function getRoleText(role) {
        const roles = {
            'patient': 'مريض',
            'doctor': 'طبيب',
            'pharmacist': 'صيدلي',
            'admin': 'أدمن'
        };
        return roles[role] || role;
    }
    </script>
</body>
</html>