<?php
/**
 * admin/doctors.php - إدارة الأطباء مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

$user = getCurrentUser($pdo);

// ============================================================
// حذف طبيب
// ============================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'doctor'")->execute([$id]);
    setToast('تم حذف الطبيب', 'success');
    redirect('doctors.php');
}

// ============================================================
// جلب الأطباء
// ============================================================
$search = $_GET['search'] ?? '';
$specialty = $_GET['specialty'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "
    SELECT u.*, d.specialties, d.license_number, d.consultation_fees, d.rating,
           d.workplace_name, d.degree, d.available_from, d.available_to, d.working_days,
           u.is_available,
           (SELECT AVG(rating) FROM ratings WHERE doctor_id = u.id) as avg_rating,
           (SELECT COUNT(*) FROM ratings WHERE doctor_id = u.id) as ratings_count,
           (SELECT COUNT(*) FROM appointments WHERE doctor_id = u.id) as appointments_count,
           (SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = u.id) as patients_count
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    WHERE u.role = 'doctor'
";

$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR d.specialties LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($specialty) {
    $sql .= " AND d.specialties LIKE ?";
    $params[] = "%$specialty%";
}

if ($status === 'verified') {
    $sql .= " AND u.is_verified = 1";
} elseif ($status === 'unverified') {
    $sql .= " AND u.is_verified = 0";
} elseif ($status === 'available') {
    $sql .= " AND u.is_available = 1";
} elseif ($status === 'unavailable') {
    $sql .= " AND u.is_available = 0";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$doctors = $stmt->fetchAll();

// جلب التخصصات للفلترة
$specialties = $pdo->query("SELECT DISTINCT specialties FROM doctors WHERE specialties IS NOT NULL")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأطباء - CONNECT+</title>
    
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
        
        .doctors-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .doctors-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .doctors-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .doctors-table tr:hover {
            background: var(--primary-soft);
            cursor: pointer;
        }
        
        .doctor-avatar-small {
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
        
        .doctor-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .rating-stars {
            color: #F4A261;
        }
        
        .availability-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
        }
        
        .available {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .unavailable {
            background: rgba(231, 111, 81, 0.1);
            color: var(--danger);
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
            width: 130px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .working-hours-box {
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
                <i class="fas fa-user-md"></i> إدارة الأطباء
            </h1>
            
            <!-- فلترة وبحث -->
            <div class="filter-section">
                <form method="get" class="filter-grid">
                    <input type="text" name="search" class="form-control" placeholder="بحث باسم الطبيب أو التخصص..." value="<?= htmlspecialchars($search) ?>">
                    
                    <select name="specialty" class="form-control">
                        <option value="">كل التخصصات</option>
                        <?php foreach ($specialties as $s): ?>
                            <option value="<?= htmlspecialchars($s['specialties']) ?>" <?= $specialty == $s['specialties'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['specialties']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="form-control">
                        <option value="">كل الحالات</option>
                        <option value="verified" <?= $status == 'verified' ? 'selected' : '' ?>>موثق</option>
                        <option value="unverified" <?= $status == 'unverified' ? 'selected' : '' ?>>غير موثق</option>
                        <option value="available" <?= $status == 'available' ? 'selected' : '' ?>>متاح</option>
                        <option value="unavailable" <?= $status == 'unavailable' ? 'selected' : '' ?>>غير متاح</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </form>
            </div>
            
            <!-- جدول الأطباء -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="doctors-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>الاسم</th>
                                <th>البريد</th>
                                <th>التخصص</th>
                                <th>مكان العمل</th>
                                <th>التقييم</th>
                                <th>الحالة</th>
                                <th>المرضى</th>
                                <th>المواعيد</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($doctors)): ?>
                                <tr>
                                    <td colspan="10" class="text-center" style="padding: 3rem;">
                                        لا يوجد أطباء
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($doctors as $d): ?>
                                    <tr onclick="viewDoctorDetails(<?= $d['id'] ?>)">
                                        <td onclick="event.stopPropagation()">
                                            <div class="doctor-avatar-small">
                                                <?php if (!empty($d['profile_image'])): ?>
                                                    <img src="../<?= $d['profile_image'] ?>" alt="صورة">
                                                <?php else: ?>
                                                    <i class="fas fa-user-md"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong>د. <?= htmlspecialchars($d['full_name']) ?></strong>
                                            <?php if ($d['degree']): ?>
                                                <br><small><?= htmlspecialchars($d['degree']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $d['email'] ?></td>
                                        <td><?= htmlspecialchars($d['specialties']) ?></td>
                                        <td><?= htmlspecialchars($d['workplace_name'] ?: '-') ?></td>
                                        <td>
                                            <span class="rating-stars"><?= renderStars($d['avg_rating'] ?: 0) ?></span>
                                            <br><small>(<?= $d['ratings_count'] ?>)</small>
                                        </td>
                                        <td>
                                            <?php if ($d['is_available']): ?>
                                                <span class="availability-badge available">متاح</span>
                                            <?php else: ?>
                                                <span class="availability-badge unavailable">غير متاح</span>
                                            <?php endif; ?>
                                            <?php if (!$d['is_verified']): ?>
                                                <br><span class="badge badge-warning">غير موثق</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $d['patients_count'] ?></td>
                                        <td><?= $d['appointments_count'] ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <button class="action-btn view" onclick="viewDoctorDetails(<?= $d['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if (!$d['is_verified']): ?>
                                                <button class="action-btn verify" onclick="verifyDoctor(<?= $d['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn delete" onclick="deleteDoctor(<?= $d['id'] ?>)">
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
         النافذة الجانبية لتفاصيل الطبيب
         ============================================================ -->
    <div class="side-modal" id="doctorDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user-md"></i> تفاصيل الطبيب</h3>
            <button class="close-side-modal" onclick="closeSideModal('doctorDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="doctorDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // عرض تفاصيل الطبيب - بدون API
    // ============================================================
    function viewDoctorDetails(id) {
        event?.stopPropagation();
        
        // جلب بيانات الطبيب من قاعدة البيانات مباشرة
        <?php
        // نقوم بإنشاء مصفوفة تحتوي على جميع بيانات الأطباء
        $doctorsData = [];
        foreach ($doctors as $d) {
            $doctorsData[$d['id']] = $d;
        }
        ?>
        
        // تحويل بيانات PHP إلى JavaScript
        const doctorsData = <?= json_encode($doctorsData) ?>;
        
        // الحصول على بيانات الطبيب المطلوب
        const d = doctorsData[id];
        
        if (d) {
            openSideModal('doctorDetailsModal');
            
            let workingHours = '';
            if (d.available_from && d.available_to) {
                workingHours = `${d.available_from} - ${d.available_to}`;
            } else {
                workingHours = 'غير محدد';
            }
            
            let verifiedStatus = d.is_verified ? 
                '<span class="badge badge-success">موثق</span>' : 
                '<span class="badge badge-warning">غير موثق</span>';
            
            let availabilityStatus = d.is_available ? 
                '<span class="availability-badge available">متاح</span>' : 
                '<span class="availability-badge unavailable">غير متاح</span>';
            
            let rating = Math.round(d.avg_rating || 0);
            let stars = '';
            for (let i = 1; i <= 5; i++) {
                stars += i <= rating ? '<i class="fas fa-star"></i>' : '<i class="far fa-star"></i>';
            }
            
            document.getElementById('doctorDetailsContent').innerHTML = `
                <div style="text-align: center;">
                    <div class="doctor-avatar-small" style="width: 80px; height: 80px; margin: 0 auto;">
                        ${d.profile_image ? `<img src="../${d.profile_image}">` : '<i class="fas fa-user-md"></i>'}
                    </div>
                    <h3 style="margin-top: 1rem;">د. ${d.full_name}</h3>
                    <p style="color: var(--gray);">${d.user_code}</p>
                    <div style="margin: 0.5rem 0;">
                        ${verifiedStatus} ${availabilityStatus}
                    </div>
                    <div class="rating-stars" style="margin: 0.5rem 0;">
                        ${stars} (${d.ratings_count || 0})
                    </div>
                </div>
                
                <hr>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">معلومات شخصية</h4>
                    
                    <div class="info-row">
                        <span class="info-label">البريد:</span>
                        <span class="info-value">${d.email}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">الهاتف:</span>
                        <span class="info-value">${d.phone || '-'}</span>
                    </div>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">معلومات مهنية</h4>
                    
                    <div class="info-row">
                        <span class="info-label">التخصص:</span>
                        <span class="info-value">${d.specialties || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">الدرجة:</span>
                        <span class="info-value">${d.degree || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">رقم الترخيص:</span>
                        <span class="info-value">${d.license_number || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">مكان العمل:</span>
                        <span class="info-value">${d.workplace_name || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">عنوان العمل:</span>
                        <span class="info-value">${d.workplace_address || '-'}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">رسوم الكشف:</span>
                        <span class="info-value">${d.consultation_fees ? d.consultation_fees + ' دج' : '-'}</span>
                    </div>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">ساعات العمل</h4>
                    
                    <div class="working-hours-box">
                        <div class="info-row">
                            <span class="info-label">أيام العمل:</span>
                            <span class="info-value">${d.working_days || 'غير محدد'}</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">ساعات العمل:</span>
                            <span class="info-value">${workingHours}</span>
                        </div>
                    </div>
                </div>
                
                <div style="margin: 1.5rem 0;">
                    <h4 style="color: var(--primary); margin-bottom: 1rem;">إحصائيات</h4>
                    
                    <div class="info-row">
                        <span class="info-label">عدد المرضى:</span>
                        <span class="info-value">${d.patients_count || 0}</span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">عدد المواعيد:</span>
                        <span class="info-value">${d.appointments_count || 0}</span>
                    </div>
                </div>
                
                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button class="btn btn-primary" onclick="window.location.href='mailto:${d.email}'" style="flex: 1;">
                        <i class="fas fa-envelope"></i> مراسلة
                    </button>
                    <button class="btn btn-outline" onclick="window.location.href='users.php?edit=${d.id}'" style="flex: 1;">
                        <i class="fas fa-edit"></i> تعديل
                    </button>
                </div>
            `;
        } else {
            showToast('لم يتم العثور على الطبيب', 'error');
        }
    }
    
    function verifyDoctor(id) {
        event?.stopPropagation();
        if (confirm('تأكيد توثيق هذا الطبيب؟')) {
            window.location.href = `users.php?verify=${id}`;
        }
    }
    
    function deleteDoctor(id) {
        event?.stopPropagation();
        if (confirm('هل أنت متأكد من حذف هذا الطبيب؟')) {
            window.location.href = `?delete=${id}`;
        }
    }
    </script>
</body>
</html>