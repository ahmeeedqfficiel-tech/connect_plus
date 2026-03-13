<?php
/**
 * doctor/patients.php - قائمة المرضى
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم طبيب
requireDoctor($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// البحث والفلترة
// ============================================================
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'recent';

// ============================================================
// جلب مرضى الطبيب
// ============================================================
$sql = "
    SELECT DISTINCT u.id, u.full_name, u.phone, u.email, u.city, u.profile_image,
           p.blood_type, p.emergency_name, p.chronic_diseases,
           (SELECT COUNT(*) FROM appointments WHERE patient_id = u.id AND doctor_id = ?) as visit_count,
           (SELECT MAX(appointment_date) FROM appointments WHERE patient_id = u.id AND doctor_id = ?) as last_visit,
           (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id AND doctor_id = ?) as prescription_count,
           (SELECT COUNT(*) FROM prescriptions WHERE patient_id = u.id AND doctor_id = ? AND status = 'active') as active_prescriptions
    FROM users u
    JOIN patients p ON u.id = p.user_id
    JOIN appointments a ON u.id = a.patient_id
    WHERE a.doctor_id = ? AND u.role = 'patient'
";

$params = [$user['id'], $user['id'], $user['id'], $user['id'], $user['id']];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($sort == 'recent') {
    $sql .= " ORDER BY last_visit DESC";
} elseif ($sort == 'name') {
    $sql .= " ORDER BY u.full_name";
} elseif ($sort == 'visits') {
    $sql .= " ORDER BY visit_count DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

// ============================================================
// إحصائيات سريعة
// ============================================================
$totalPatients = count($patients);
$patientsWithActiveMeds = 0;
foreach ($patients as $p) {
    if ($p['active_prescriptions'] > 0) $patientsWithActiveMeds++;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المرضى - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-mini-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1rem;
            box-shadow: var(--shadow-sm);
            text-align: center;
        }
        
        body.dark-mode .stat-mini-card {
            background: #1E1E1E;
        }
        
        .stat-mini-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .search-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        body.dark-mode .search-section {
            background: #1E1E1E;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box i {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .search-box input {
            width: 100%;
            padding: 1rem 3rem 1rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 50px;
            font-size: 1rem;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .filter-bar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .sort-select {
            padding: 0.5rem 1rem;
            border: 2px solid var(--light-gray);
            border-radius: 30px;
            background: white;
            color: var(--dark);
        }
        
        body.dark-mode .sort-select {
            background: #2D2D2D;
            color: white;
        }
        
        .patients-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .patient-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
        }
        
        body.dark-mode .patient-card {
            background: #1E1E1E;
        }
        
        .patient-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .patient-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .patient-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .patient-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .patient-info {
            flex: 1;
        }
        
        .patient-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.3rem;
        }
        
        .patient-contact {
            font-size: 0.9rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .patient-stats {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            padding: 1rem 0;
            border-top: 1px solid var(--light-gray);
            border-bottom: 1px solid var(--light-gray);
        }
        
        .stat-item {
            text-align: center;
            flex: 1;
        }
        
        .stat-value {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        .stat-label {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .patient-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .meta-tag {
            background: var(--light-gray);
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }
        
        .blood-tag {
            background: var(--danger);
            color: white;
        }
        
        .active-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--success);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.7rem;
        }
        
        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 3rem;
            background: white;
            border-radius: var(--radius-lg);
        }
        
        body.dark-mode .empty-state {
            background: #1E1E1E;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 1rem;
        }
        
        .loading {
            text-align: center;
            padding: 2rem;
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
                <h1 class="page-title"><i class="fas fa-users"></i> المرضى</h1>
                <button class="btn btn-primary" onclick="openSideModal('addPatientSideModal')">
                    <i class="fas fa-user-plus"></i> إضافة مريض
                </button>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $totalPatients ?></div>
                    <div style="color: var(--gray);">إجمالي المرضى</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $patientsWithActiveMeds ?></div>
                    <div style="color: var(--gray);">لديهم وصفات نشطة</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number">
                        <?php 
                        $today = date('Y-m-d');
                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = ? AND DATE(appointment_date) = ?");
                        $stmt->execute([$user['id'], $today]);
                        echo $stmt->fetchColumn();
                        ?>
                    </div>
                    <div style="color: var(--gray);">مواعيد اليوم</div>
                </div>
            </div>
            
            <!-- قسم البحث والفلترة -->
            <div class="search-section">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="ابحث باسم المريض، رقم الهاتف أو البريد..." value="<?= htmlspecialchars($search) ?>">
                </div>
                
                <div class="filter-bar">
                    <select id="sortSelect" class="sort-select" onchange="applyFilters()">
                        <option value="recent" <?= $sort == 'recent' ? 'selected' : '' ?>>الأحدث</option>
                        <option value="name" <?= $sort == 'name' ? 'selected' : '' ?>>حسب الاسم</option>
                        <option value="visits" <?= $sort == 'visits' ? 'selected' : '' ?>>الأكثر زيارة</option>
                    </select>
                    
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> تطبيق
                    </button>
                    
                    <button class="btn btn-outline" onclick="clearFilters()">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
            
            <!-- قائمة المرضى -->
            <?php if (empty($patients)): ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>لا يوجد مرضى</h3>
                    <p style="color: var(--gray); margin: 1rem 0;">لم تقم بإضافة أي مرضى بعد</p>
                    <button class="btn btn-primary" onclick="openSideModal('addPatientSideModal')">
                        <i class="fas fa-user-plus"></i> إضافة مريض
                    </button>
                </div>
            <?php else: ?>
                <div class="patients-grid" id="patientsGrid">
                    <?php foreach ($patients as $p): ?>
                        <div class="patient-card" onclick="window.location.href='patient_view.php?id=<?= $p['id'] ?>'">
                            <?php if ($p['active_prescriptions'] > 0): ?>
                                <span class="active-badge">
                                    <i class="fas fa-prescription"></i> وصفة نشطة
                                </span>
                            <?php endif; ?>
                            
                            <div class="patient-header">
                                <div class="patient-avatar">
                                    <?php if (!empty($p['profile_image'])): ?>
                                        <img src="../<?= $p['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <?= mb_substr($p['full_name'], 0, 1) ?>
                                    <?php endif; ?>
                                </div>
                                <div class="patient-info">
                                    <div class="patient-name"><?= htmlspecialchars($p['full_name']) ?></div>
                                    <div class="patient-contact">
                                        <i class="fas fa-phone"></i> <?= $p['phone'] ?: 'غير محدد' ?>
                                    </div>
                                    <div class="patient-contact">
                                        <i class="fas fa-map-marker-alt"></i> <?= $p['city'] ?: 'غير محدد' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="patient-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?= $p['visit_count'] ?></div>
                                    <div class="stat-label">زيارة</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?= $p['prescription_count'] ?></div>
                                    <div class="stat-label">وصفة</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value">
                                        <?= $p['last_visit'] ? date('d/m', strtotime($p['last_visit'])) : '--' ?>
                                    </div>
                                    <div class="stat-label">آخر زيارة</div>
                                </div>
                            </div>
                            
                            <div class="patient-meta">
                                <?php if (!empty($p['blood_type'])): ?>
                                    <span class="meta-tag blood-tag">
                                        <i class="fas fa-tint"></i> <?= $p['blood_type'] ?>
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($p['chronic_diseases'])): ?>
                                    <span class="meta-tag" title="<?= htmlspecialchars($p['chronic_diseases']) ?>">
                                        <i class="fas fa-heartbeat"></i> أمراض مزمنة
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($p['emergency_name'])): ?>
                                    <span class="meta-tag">
                                        <i class="fas fa-phone-alt"></i> طوارئ
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لإضافة مريض
         ============================================================ -->
    <div class="side-modal" id="addPatientSideModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user-plus"></i> إضافة مريض</h3>
            <button class="close-side-modal" onclick="closeSideModal('addPatientSideModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <div style="margin-bottom: 1.5rem;">
                <button class="btn btn-outline" onclick="showManualSearch()" style="width: 100%;">
                    <i class="fas fa-keyboard"></i> بحث يدوي
                </button>
            </div>
            
            <div id="manualSearchSection">
                <form method="post" action="add_patient.php">
                    <div class="form-group">
                        <label>كود المريض أو رقم البطاقة</label>
                        <input type="text" name="patient_identifier" class="form-control" placeholder="أدخل كود المريض أو رقم البطاقة" required>
                    </div>
                    
                    <div class="form-group">
                        <label>ملاحظات (اختياري)</label>
                        <textarea name="notes" class="form-control" rows="2" placeholderسبب الإضافة..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        سيتم البحث عن المريض في قاعدة البيانات وإضافته إلى قائمتك.
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-search"></i> بحث وإضافة
                    </button>
                </form>
            </div>
            
            <hr style="margin: 2rem 0;">
            
            <div style="text-align: center;">
                <p style="color: var(--gray); margin-bottom: 1rem;">طرق إضافة أخرى (قيد التطوير)</p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <button class="btn btn-outline btn-sm dev-btn" onclick="showDevMessage('NFC')">
                        <i class="fas fa-id-card"></i> NFC
                    </button>
                    <button class="btn btn-outline btn-sm dev-btn" onclick="showDevMessage('QR')">
                        <i class="fas fa-qrcode"></i> QR
                    </button>
                    <button class="btn btn-outline btn-sm dev-btn" onclick="showDevMessage('بصمة')">
                        <i class="fas fa-fingerprint"></i> بصمة
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // تطبيق الفلاتر
    // ============================================================
    function applyFilters() {
        const search = document.getElementById('searchInput').value;
        const sort = document.getElementById('sortSelect').value;
        
        window.location.href = `patients.php?search=${encodeURIComponent(search)}&sort=${sort}`;
    }
    
    // ============================================================
    // إلغاء الفلاتر
    // ============================================================
    function clearFilters() {
        window.location.href = 'patients.php';
    }
    
    // ============================================================
    // بحث فوري (اختياري)
    // ============================================================
    let searchTimeout;
    document.getElementById('searchInput')?.addEventListener('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500);
    });
    
    // ============================================================
    // إظهار البحث اليدوي
    // ============================================================
    function showManualSearch() {
        document.getElementById('manualSearchSection').style.display = 'block';
    }
    
    // ============================================================
    // عرض تفاصيل المريض (توجيه)
    // ============================================================
    function viewPatient(id) {
        window.location.href = `patient_view.php?id=${id}`;
    }
    </script>
</body>
</html>