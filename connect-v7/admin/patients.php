<?php
/**
 * admin/patients.php - إدارة المرضى مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

// ============================================================
// حذف مريض
// ============================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'")->execute([$id]);
    setToast('تم حذف المريض', 'success');
    redirect('patients.php');
}

// ============================================================
// جلب المرضى
// ============================================================
$search = $_GET['search'] ?? '';
$blood_type = $_GET['blood_type'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "
    SELECT u.*, p.blood_type, p.emergency_name, p.emergency_phone,
           p.chronic_diseases, p.allergies,
           (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id) as appointments_count,
           (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id) as prescriptions_count,
           (SELECT COUNT(*) FROM medicine_orders WHERE patient_id = u.id) as orders_count
    FROM users u
    JOIN patients p ON u.id = p.user_id
    WHERE u.role = 'patient'
";

$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($blood_type) {
    $sql .= " AND p.blood_type = ?";
    $params[] = $blood_type;
}

if ($status === 'verified') {
    $sql .= " AND u.is_verified = 1";
} elseif ($status === 'unverified') {
    $sql .= " AND u.is_verified = 0";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المرضى - CONNECT+</title>
    
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
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .patients-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patients-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .patients-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .patients-table tr:hover {
            background: var(--primary-soft);
            cursor: pointer;
        }
        
        .patient-avatar-small {
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
        
        .patient-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .blood-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            background: var(--danger);
            color: white;
            font-weight: 600;
            display: inline-block;
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
        
        /* تنسيق النافذة الجانبية */
        .info-row {
            display: flex;
            margin-bottom: 1rem;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .info-label {
            width: 140px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .emergency-box {
            background: rgba(231, 111, 81, 0.1);
            border-right: 4px solid var(--danger);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 1rem 0;
        }
        
        .medical-box {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin: 1rem 0;
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
                <i class="fas fa-user"></i> إدارة المرضى
            </h1>
            
            <!-- فلترة وبحث -->
            <div class="filter-section">
                <form method="get" class="filter-grid">
                    <input type="text" name="search" class="form-control" placeholder="بحث بالاسم أو البريد أو الهاتف..." value="<?= htmlspecialchars($search) ?>">
                    
                    <select name="blood_type" class="form-control">
                        <option value="">كل الفصائل</option>
                        <?php foreach ($bloodTypes as $bt): ?>
                            <option value="<?= $bt ?>" <?= $blood_type == $bt ? 'selected' : '' ?>><?= $bt ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="form-control">
                        <option value="">كل الحالات</option>
                        <option value="verified" <?= $status == 'verified' ? 'selected' : '' ?>>موثق</option>
                        <option value="unverified" <?= $status == 'unverified' ? 'selected' : '' ?>>غير موثق</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </form>
            </div>
            
            <!-- جدول المرضى -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="patients-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>الاسم</th>
                                <th>البريد</th>
                                <th>الهاتف</th>
                                <th>المدينة</th>
                                <th>فصيلة الدم</th>
                                <th>جهة اتصال</th>
                                <th>الحالة</th>
                                <th>المواعيد</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patients)): ?>
                                <tr>
                                    <td colspan="10" class="text-center" style="padding: 3rem;">
                                        لا يوجد مرضى
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($patients as $p): ?>
                                    <tr onclick="viewPatientDetails(<?= $p['id'] ?>)">
                                        <td onclick="event.stopPropagation()">
                                            <div class="patient-avatar-small">
                                                <?php if (!empty($p['profile_image'])): ?>
                                                    <img src="../<?= $p['profile_image'] ?>" alt="صورة">
                                                <?php else: ?>
                                                    <i class="fas fa-user"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($p['full_name']) ?></strong></td>
                                        <td><?= $p['email'] ?></td>
                                        <td><?= $p['phone'] ?: '-' ?></td>
                                        <td><?= $p['city'] ?: '-' ?></td>
                                        <td>
                                            <?php if ($p['blood_type']): ?>
                                                <span class="blood-badge"><?= $p['blood_type'] ?></span>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['emergency_name']): ?>
                                                <?= htmlspecialchars($p['emergency_name']) ?><br>
                                                <small><?= $p['emergency_phone'] ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($p['is_verified']): ?>
                                                <span class="badge badge-success">موثق</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">غير موثق</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $p['appointments_count'] ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <button class="action-btn view" onclick="viewPatientDetails(<?= $p['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if (!$p['is_verified']): ?>
                                                <button class="action-btn verify" onclick="verifyPatient(<?= $p['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn delete" onclick="deletePatient(<?= $p['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
         النافذة الجانبية لتفاصيل المريض
         ============================================================ -->
    <div class="side-modal" id="patientDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user"></i> تفاصيل المريض</h3>
            <button class="close-side-modal" onclick="closeSideModal('patientDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="patientDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    function viewPatientDetails(id) {
        event?.stopPropagation();
        
        // جلب بيانات المرضى من قاعدة البيانات
        <?php
        $patientsData = [];
        foreach ($patients as $p) {
            $patientsData[$p['id']] = $p;
        }
        ?>
        
        const patientsData = <?= json_encode($patientsData) ?>;
        const p = patientsData[id];
        
        if (p) {
            openSideModal('patientDetailsModal');
            
            let verifiedStatus = p.is_verified ? 
                '<span class="badge badge-success">موثق</span>' : 
                '<span class="badge badge-warning">غير موثق</span>';
            
            let emergencyInfo = '';
            if (p.emergency_name) {
                emergencyInfo = `
                    <div class="emergency-box">
                        <div class="info-row">
                            <span class="info-label">اسم جهة الاتصال:</span>
                            <span class="info-value"><strong>${p.emergency_name}</strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">هاتف الطوارئ:</span>
                            <span class="info-value">${p.emergency_phone || '-'}</span>
                        </div>
                    </div>
                `;
            }
            
            let medicalInfo = '';
            if (p.chronic_diseases || p.allergies) {
                medicalInfo = `
                    <div class="medical-box">
                        ${p.chronic_diseases ? `
                            <div class="info-row">
                                <span class="info-label">الأمراض المزمنة:</span>
                                <span class="info-value">${p.chronic_diseases}</span>
                            </div>
                        ` : ''}
                        ${p.allergies ? `
                            <div class="info-row">
                                <span class="info-label">الحساسيات:</span>
                                <span class="info-value">${p.allergies}</span>
                            </div>
                        ` : ''}
                    </div>
                `;
            }
            
            document.getElementById('patientDetailsContent').innerHTML = `
                <div style="text-align: center;">
                    <div class="patient-avatar-small" style="width: 80px; height: 80px; margin: 0 auto;">
                        ${p.profile_image ? `<img src="../${p.profile_image}">` : '<i class="fas fa-user"></i>'}
                    </div>
                    <h3 style="margin-top: 1rem;">${p.full_name}</h3>
                    <p style="color: var(--gray);">${p.user_code}</p>
                    <div style="margin: 0.5rem 0;">${verifiedStatus}</div>
                    ${p.blood_type ? `<span class="blood-badge">${p.blood_type}</span>` : ''}
                </div>
                
                <hr>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">معلومات الاتصال</h4>
                    
                    <div class="info-row">
                        <span class="info-label">البريد:</span>
                        <span class="info-value">${p.email}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value">${p.phone || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">العنوان:</span>
                        <span class="info-value">${p.address || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">المدينة:</span>
                        <span class="info-value">${p.city || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">تاريخ الميلاد:</span>
                        <span class="info-value">${p.birth_date || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">الجنس:</span>
                        <span class="info-value">${p.gender == 'male' ? 'ذكر' : (p.gender == 'female' ? 'أنثى' : '-')}</span>
                    </div>
                </div>
                
                ${emergencyInfo}
                ${medicalInfo}
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">إحصائيات</h4>
                    
                    <div class="info-row">
                        <span class="info-label">عدد المواعيد:</span>
                        <span class="info-value">${p.appointments_count || 0}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">عدد الوصفات:</span>
                        <span class="info-value">${p.prescriptions_count || 0}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">عدد الطلبات:</span>
                        <span class="info-value">${p.orders_count || 0}</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button class="btn btn-primary" onclick="window.location.href='mailto:${p.email}'" style="flex: 1;">
                        <i class="fas fa-envelope"></i> مراسلة
                    </button>
                    <button class="btn btn-outline" onclick="window.location.href='users.php?edit=${p.id}'" style="flex: 1;">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                </div>
            `;
        } else {
            showToast('لم يتم العثور على المريض', 'error');
        }
    }
    
    function verifyPatient(id) {
        event?.stopPropagation();
        if (confirm('تأكيد توثيق هذا المريض؟')) {
            window.location.href = `users.php?verify=${id}`;
        }
    }
    
    function deletePatient(id) {
        event?.stopPropagation();
        if (confirm('هل أنت متأكد من حذف هذا المريض؟')) {
            window.location.href = `?delete=${id}`;
        }
    }
    </script>
</body>
</html>