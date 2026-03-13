<?php
/**
 * doctor/prescriptions.php - قائمة الوصفات
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
$status = $_GET['status'] ?? 'all';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

// ============================================================
// جلب الوصفات
// ============================================================
$sql = "
    SELECT p.*, u.full_name as patient_name, u.profile_image,
           (SELECT COUNT(*) FROM prescription_medicines WHERE prescription_id = p.id) as medicines_count,
           (SELECT GROUP_CONCAT(medicine_name) FROM prescription_medicines WHERE prescription_id = p.id) as medicines_list
    FROM prescriptions p
    JOIN users u ON p.patient_id = u.id
    WHERE p.doctor_id = ?
";

$params = [$user['id']];

if ($status != 'all') {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($from_date) {
    $sql .= " AND p.prescription_date >= ?";
    $params[] = $from_date;
}

if ($to_date) {
    $sql .= " AND p.prescription_date <= ?";
    $params[] = $to_date;
}

$sql .= " ORDER BY p.prescription_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prescriptions = $stmt->fetchAll();

// ============================================================
// إحصائيات
// ============================================================
$stats = [
    'total' => count($prescriptions),
    'active' => $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE doctor_id = ? AND status = 'active'")->execute([$user['id']]) ? 
                $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE doctor_id = {$user['id']} AND status = 'active'")->fetchColumn() : 0,
    'completed' => $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE doctor_id = ? AND status = 'completed'")->execute([$user['id']]) ? 
                   $pdo->query("SELECT COUNT(*) FROM prescriptions WHERE doctor_id = {$user['id']} AND status = 'completed'")->fetchColumn() : 0
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الوصفات - CONNECT+</title>
    
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
            font-size: 1.8rem;
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
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .prescription-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border-right: 4px solid var(--primary);
        }
        
        body.dark-mode .prescription-card {
            background: #1E1E1E;
        }
        
        .prescription-card:hover {
            transform: translateX(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .prescription-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .patient-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .patient-avatar-small {
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
        
        .patient-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .patient-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .prescription-date {
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .prescription-meta {
            display: flex;
            gap: 2rem;
            margin: 1rem 0;
            color: var(--gray);
            flex-wrap: wrap;
        }
        
        .medicines-preview {
            margin: 1rem 0;
            padding: 1rem;
            background: var(--light);
            border-radius: var(--radius-md);
        }
        
        .empty-state {
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
                <i class="fas fa-prescription"></i> الوصفات
            </h1>
            
            <!-- إحصائيات -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $stats['total'] ?></div>
                    <div style="color: var(--gray);">إجمالي الوصفات</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $stats['active'] ?></div>
                    <div style="color: var(--gray);">نشطة</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $stats['completed'] ?></div>
                    <div style="color: var(--gray);">مكتملة</div>
                </div>
            </div>
            
            <!-- بحث وتصفية -->
            <div class="search-section">
                <form method="get" id="filterForm">
                    <div class="filter-row">
                        <input type="text" name="search" class="form-control" placeholder="بحث باسم المريض..." value="<?= htmlspecialchars($search) ?>">
                        
                        <select name="status" class="form-control">
                            <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>كل الحالات</option>
                            <option value="active" <?= $status == 'active' ? 'selected' : '' ?>>نشطة</option>
                            <option value="completed" <?= $status == 'completed' ? 'selected' : '' ?>>مكتملة</option>
                        </select>
                        
                        <input type="date" name="from_date" class="form-control" placeholder="من تاريخ" value="<?= $from_date ?>">
                        
                        <input type="date" name="to_date" class="form-control" placeholder="إلى تاريخ" value="<?= $to_date ?>">
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <i class="fas fa-search"></i> بحث
                        </button>
                        <a href="prescriptions.php" class="btn btn-outline" style="flex: 1;">
                            <i class="fas fa-times"></i> إلغاء
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- قائمة الوصفات -->
            <?php if (empty($prescriptions)): ?>
                <div class="empty-state">
                    <i class="fas fa-prescription"></i>
                    <h3>لا توجد وصفات</h3>
                    <p style="color: var(--gray); margin-top: 0.5rem;">لم تقم بإصدار أي وصفات بعد</p>
                    <button class="btn btn-primary" onclick="window.location.href='patients.php'" style="margin-top: 1rem;">
                        <i class="fas fa-user"></i> اختر مريضاً
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($prescriptions as $p): ?>
                    <div class="prescription-card" onclick="showPrescriptionDetails(<?= $p['id'] ?>)">
                        <div class="prescription-header">
                            <div class="patient-info">
                                <div class="patient-avatar-small">
                                    <?php if (!empty($p['profile_image'])): ?>
                                        <img src="../<?= $p['profile_image'] ?>" alt="صورة">
                                    <?php else: ?>
                                        <?= mb_substr($p['patient_name'], 0, 1) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="patient-name"><?= htmlspecialchars($p['patient_name']) ?></div>
                                    <div class="prescription-date">
                                        <i class="fas fa-calendar"></i> <?= $p['prescription_date'] ?>
                                    </div>
                                </div>
                            </div>
                            
                            <span class="badge badge-<?= $p['status'] ?>">
                                <?= getStatusText($p['status']) ?>
                            </span>
                        </div>
                        
                        <?php if ($p['diagnosis']): ?>
                            <div class="prescription-meta">
                                <span><i class="fas fa-stethoscope"></i> <?= htmlspecialchars($p['diagnosis']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <div class="medicines-preview">
                            <strong>الأدوية (<?= $p['medicines_count'] ?>):</strong>
                            <p style="margin-top: 0.5rem; color: var(--gray);">
                                <?= htmlspecialchars(substr($p['medicines_list'] ?? '', 0, 100)) ?><?= strlen($p['medicines_list'] ?? '') > 100 ? '...' : '' ?>
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                            <button class="btn btn-outline btn-sm" onclick="event.stopPropagation(); window.location.href='patient_view.php?id=<?= $p['patient_id'] ?>'">
                                <i class="fas fa-user"></i> ملف المريض
                            </button>
                            <?php if ($p['status'] == 'active'): ?>
                                <button class="btn btn-success btn-sm" onclick="event.stopPropagation(); markPrescriptionCompleted(<?= $p['id'] ?>)">
                                    <i class="fas fa-check"></i> إنهاء
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- ============================================================
         نافذة تفاصيل الوصفة
         ============================================================ -->
    <div class="side-modal" id="prescriptionDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-prescription"></i> تفاصيل الوصفة</h3>
            <button class="close-side-modal" onclick="closeSideModal('prescriptionDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="prescriptionDetailsContent"></div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // عرض تفاصيل الوصفة
    // ============================================================
    function showPrescriptionDetails(id) {
        fetch(`../api/doctor/get_prescription_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const p = data.data;
                    let html = `
                        <h3>وصفة طبية</h3>
                        <p style="color: var(--gray);">للمريض: ${p.patient_name}</p>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <p><i class="fas fa-calendar"></i> <strong>التاريخ:</strong> ${p.prescription_date}</p>
                            ${p.diagnosis ? `<p><i class="fas fa-stethoscope"></i> <strong>التشخيص:</strong> ${p.diagnosis}</p>` : ''}
                            <p><i class="fas fa-tag"></i> <strong>الحالة:</strong> <span class="badge badge-${p.status}">${getStatusText(p.status)}</span></p>
                        </div>
                        
                        <h4 style="margin: 1rem 0;">الأدوية</h4>
                    `;
                    
                    if (p.medicines && p.medicines.length > 0) {
                        p.medicines.forEach(m => {
                            html += `
                                <div style="margin: 1rem 0; padding: 1rem; background: var(--light); border-radius: 10px;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <strong>${m.medicine_name}</strong>
                                        <span class="badge ${m.status === 'active' ? 'badge-success' : 'badge-secondary'}">
                                            ${m.status === 'active' ? 'نشط' : 'مكتمل'}
                                        </span>
                                    </div>
                                    <p style="margin-top: 0.5rem;">${m.dosage} - ${m.frequency}</p>
                                    ${m.instructions ? `<p style="color: var(--gray); font-size: 0.9rem;">${m.instructions}</p>` : ''}
                                </div>
                            `;
                        });
                    }
                    
                    html += `
                        ${p.notes ? `
                            <div style="margin: 1rem 0;">
                                <strong><i class="fas fa-sticky-note"></i> ملاحظات:</strong>
                                <p style="margin-top: 0.5rem; padding: 1rem; background: var(--light); border-radius: 10px;">${p.notes}</p>
                            </div>
                        ` : ''}
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button class="btn btn-primary" onclick="window.location.href='patient_view.php?id=${p.patient_id}'" style="flex: 1;">
                                <i class="fas fa-user"></i> ملف المريض
                            </button>
                            <button class="btn btn-outline" onclick="window.print()" style="flex: 1;">
                                <i class="fas fa-print"></i> طباعة
                            </button>
                        </div>
                    `;
                    
                    document.getElementById('prescriptionDetailsContent').innerHTML = html;
                    openSideModal('prescriptionDetailsModal');
                }
            });
    }
    
    // ============================================================
    // إنهاء وصفة
    // ============================================================
    function markPrescriptionCompleted(id) {
        if (confirm('تأكيد إنهاء هذه الوصفة؟')) {
            fetch('../api/doctor/update_prescription_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({id: id, status: 'completed'})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('تم إنهاء الوصفة', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('حدث خطأ', 'error');
                }
            });
        }
    }
    
    function getStatusText(status) {
        const statuses = {
            'active': 'نشط',
            'completed': 'مكتمل'
        };
        return statuses[status] || status;
    }
    </script>
</body>
</html>