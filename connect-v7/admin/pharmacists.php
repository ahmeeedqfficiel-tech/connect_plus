<?php
/**
 * admin/pharmacists.php - إدارة الصيادلة مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

// ============================================================
// حذف صيدلي
// ============================================================
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'pharmacist'")->execute([$id]);
    setToast('تم حذف الصيدلي', 'success');
    redirect('pharmacists.php');
}

// ============================================================
// جلب الصيادلة
// ============================================================
$search = $_GET['search'] ?? '';
$city = $_GET['city'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "
    SELECT u.*, ph.pharmacy_name, ph.license_number, ph.city as pharmacy_city, 
           ph.address, ph.is_24h,
           (SELECT COUNT(*) FROM medicine_orders mo JOIN pharmacies p ON mo.pharmacy_id = p.id WHERE p.pharmacist_id = u.id) as orders_count,
           (SELECT COUNT(*) FROM pharmacy_medicines pm JOIN pharmacies p ON pm.pharmacy_id = p.id WHERE p.pharmacist_id = u.id) as medicines_count
    FROM users u
    JOIN pharmacists ph ON u.id = ph.user_id
    WHERE u.role = 'pharmacist'
";

$params = [];

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR ph.pharmacy_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($city) {
    $sql .= " AND ph.city = ?";
    $params[] = $city;
}

if ($status === 'verified') {
    $sql .= " AND u.is_verified = 1";
} elseif ($status === 'unverified') {
    $sql .= " AND u.is_verified = 0";
} elseif ($status === '24h') {
    $sql .= " AND ph.is_24h = 1";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pharmacists = $stmt->fetchAll();

// جلب المدن للفلترة
$cities = $pdo->query("SELECT DISTINCT city FROM pharmacists WHERE city IS NOT NULL")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الصيادلة - CONNECT+</title>
    
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
        
        .pharmacists-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .pharmacists-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .pharmacists-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .pharmacists-table tr:hover {
            background: var(--primary-soft);
            cursor: pointer;
        }
        
        .pharmacist-avatar-small {
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
        
        .pharmacist-avatar-small img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .badge-24h {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
            padding: 0.3rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
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
        
        .action-btn.medicines {
            background: var(--primary);
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
            width: 120px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .medicines-list {
            margin-top: 1rem;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .medicine-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .medicine-item:hover {
            background: var(--primary-soft);
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
                <i class="fas fa-store"></i> إدارة الصيادلة
            </h1>
            
            <!-- فلترة وبحث -->
            <div class="filter-section">
                <form method="get" class="filter-grid">
                    <input type="text" name="search" class="form-control" placeholder="بحث باسم الصيدلي أو الصيدلية..." value="<?= htmlspecialchars($search) ?>">
                    
                    <select name="city" class="form-control">
                        <option value="">كل المدن</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c['city']) ?>" <?= $city == $c['city'] ? 'selected' : '' ?>><?= htmlspecialchars($c['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status" class="form-control">
                        <option value="">كل الحالات</option>
                        <option value="verified" <?= $status == 'verified' ? 'selected' : '' ?>>موثق</option>
                        <option value="unverified" <?= $status == 'unverified' ? 'selected' : '' ?>>غير موثق</option>
                        <option value="24h" <?= $status == '24h' ? 'selected' : '' ?>>24 ساعة</option>
                    </select>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </form>
            </div>
            
            <!-- جدول الصيادلة -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="pharmacists-table">
                        <thead>
                            <tr>
                                <th></th>
                                <th>الصيدلي</th>
                                <th>الصيدلية</th>
                                <th>البريد</th>
                                <th>الهاتف</th>
                                <th>المدينة</th>
                                <th>الحالة</th>
                                <th>الأدوية</th>
                                <th>الطلبات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pharmacists)): ?>
                                <tr>
                                    <td colspan="10" class="text-center" style="padding: 3rem;">
                                        لا يوجد صيادلة
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pharmacists as $ph): ?>
                                    <tr onclick="viewPharmacistDetails(<?= $ph['id'] ?>)">
                                        <td onclick="event.stopPropagation()">
                                            <div class="pharmacist-avatar-small">
                                                <?php if (!empty($ph['profile_image'])): ?>
                                                    <img src="../<?= $ph['profile_image'] ?>" alt="صورة">
                                                <?php else: ?>
                                                    <i class="fas fa-store"></i>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($ph['full_name']) ?></strong></td>
                                        <td><?= htmlspecialchars($ph['pharmacy_name']) ?></td>
                                        <td><?= $ph['email'] ?></td>
                                        <td><?= $ph['phone'] ?: '-' ?></td>
                                        <td><?= htmlspecialchars($ph['pharmacy_city'] ?: '-') ?></td>
                                        <td>
                                            <?php if ($ph['is_verified']): ?>
                                                <span class="badge badge-success">موثق</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">غير موثق</span>
                                            <?php endif; ?>
                                            <?php if ($ph['is_24h']): ?>
                                                <br><span class="badge-24h">24 ساعة</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $ph['medicines_count'] ?></td>
                                        <td><?= $ph['orders_count'] ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <button class="action-btn view" onclick="viewPharmacistDetails(<?= $ph['id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <?php if (!$ph['is_verified']): ?>
                                                <button class="action-btn verify" onclick="verifyPharmacist(<?= $ph['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button class="action-btn medicines" onclick="viewPharmacistMedicines(<?= $ph['id'] ?>)">
                                                <i class="fas fa-pills"></i>
                                            </button>
                                            
                                            <button class="action-btn delete" onclick="deletePharmacist(<?= $ph['id'] ?>)">
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
         النافذة الجانبية لتفاصيل الصيدلي
         ============================================================ -->
    <div class="side-modal" id="pharmacistDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-store"></i> تفاصيل الصيدلي</h3>
            <button class="close-side-modal" onclick="closeSideModal('pharmacistDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="pharmacistDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية لأدوية الصيدلي
         ============================================================ -->
    <div class="side-modal" id="pharmacistMedicinesModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-pills"></i> أدوية الصيدلية</h3>
            <button class="close-side-modal" onclick="closeSideModal('pharmacistMedicinesModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="pharmacistMedicinesContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري التحميل...</p>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // عرض تفاصيل الصيدلي
    // ============================================================
    function viewPharmacistDetails(id) {
        // منع النقر من الانتشار للصف
        event?.stopPropagation();
        
        // فتح النافذة الجانبية
        openSideModal('pharmacistDetailsModal');
        
        // جلب البيانات
        fetch(`../api/admin/get_pharmacist_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const ph = data.pharmacist;
                    
                    let workingHours = ph.is_24h ? '24 ساعة' : 'ساعات عمل عادية';
                    let verifiedStatus = ph.is_verified ? 
                        '<span class="badge badge-success">موثق</span>' : 
                        '<span class="badge badge-warning">غير موثق</span>';
                    
                    let documentsHtml = '';
                    if (ph.id_card_image) {
                        documentsHtml += `<a href="../${ph.id_card_image}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-id-card"></i> بطاقة التعريف</a> `;
                    }
                    if (ph.pharmacy_license) {
                        documentsHtml += `<a href="../${ph.pharmacy_license}" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-file-contract"></i> رخصة الصيدلية</a>`;
                    }
                    
                    document.getElementById('pharmacistDetailsContent').innerHTML = `
                        <div style="text-align: center;">
                            <div class="pharmacist-avatar-small" style="width: 80px; height: 80px; margin: 0 auto;">
                                ${ph.profile_image ? `<img src="../${ph.profile_image}">` : '<i class="fas fa-store"></i>'}
                            </div>
                            <h3 style="margin-top: 1rem;">${ph.full_name}</h3>
                            <p style="color: var(--gray);">${ph.user_code}</p>
                            <div style="margin: 0.5rem 0;">${verifiedStatus}</div>
                        </div>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--primary); margin-bottom: 1rem;">معلومات الصيدلية</h4>
                            
                            <div class="info-row">
                                <span class="info-label">اسم الصيدلية:</span>
                                <span class="info-value"><strong>${ph.pharmacy_name}</strong></span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">رقم الترخيص:</span>
                                <span class="info-value">${ph.license_number || '-'}</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">العنوان:</span>
                                <span class="info-value">${ph.address || '-'}</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">المدينة:</span>
                                <span class="info-value">${ph.city || '-'}</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">ساعات العمل:</span>
                                <span class="info-value">${workingHours}</span>
                            </div>
                        </div>
                        
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--primary); margin-bottom: 1rem;">معلومات الاتصال</h4>
                            
                            <div class="info-row">
                                <span class="info-label">البريد:</span>
                                <span class="info-value">${ph.email}</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">الهاتف:</span>
                                <span class="info-value">${ph.phone || '-'}</span>
                            </div>
                        </div>
                        
                        <div style="margin: 1.5rem 0;">
                            <h4 style="color: var(--primary); margin-bottom: 1rem;">إحصائيات</h4>
                            
                            <div class="info-row">
                                <span class="info-label">عدد الأدوية:</span>
                                <span class="info-value">${ph.medicines_count || 0}</span>
                            </div>
                            
                            <div class="info-row">
                                <span class="info-label">عدد الطلبات:</span>
                                <span class="info-value">${ph.orders_count || 0}</span>
                            </div>
                        </div>
                        
                        ${documentsHtml ? `
                            <div style="margin: 1.5rem 0;">
                                <h4 style="color: var(--primary); margin-bottom: 1rem;">المستندات</h4>
                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    ${documentsHtml}
                                </div>
                            </div>
                        ` : ''}
                        
                        <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                            <button class="btn btn-primary" onclick="viewPharmacistMedicines(${ph.id})" style="flex: 1;">
                                <i class="fas fa-pills"></i> عرض الأدوية
                            </button>
                            <button class="btn btn-outline" onclick="window.location.href='mailto:${ph.email}'" style="flex: 1;">
                                <i class="fas fa-envelope"></i> مراسلة
                            </button>
                        </div>
                    `;
                } else {
                    document.getElementById('pharmacistDetailsContent').innerHTML = `
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-exclamation-circle fa-3x" style="color: var(--danger);"></i>
                            <p style="margin-top: 1rem;">فشل تحميل البيانات</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                document.getElementById('pharmacistDetailsContent').innerHTML = `
                    <div class="text-center" style="padding: 2rem;">
                        <i class="fas fa-exclamation-circle fa-3x" style="color: var(--danger);"></i>
                        <p style="margin-top: 1rem;">حدث خطأ في الاتصال</p>
                    </div>
                `;
            });
    }
    
    // ============================================================
    // عرض أدوية الصيدلي
    // ============================================================
    function viewPharmacistMedicines(id) {
        // إغلاق النافذة الحالية إذا كانت مفتوحة
        closeSideModal('pharmacistDetailsModal');
        
        // فتح نافذة الأدوية
        openSideModal('pharmacistMedicinesModal');
        
        // جلب البيانات
        fetch(`../api/admin/get_pharmacist_medicines.php?pharmacist_id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let medicinesHtml = '';
                    
                    if (data.medicines && data.medicines.length > 0) {
                        medicinesHtml = '<div class="medicines-list">';
                        data.medicines.forEach(m => {
                            let stockClass = '';
                            if (m.quantity == 0) {
                                stockClass = 'text-danger';
                            } else if (m.quantity < 10) {
                                stockClass = 'text-warning';
                            } else {
                                stockClass = 'text-success';
                            }
                            
                            medicinesHtml += `
                                <div class="medicine-item" onclick="viewMedicineDetails(${m.medicine_id})">
                                    <div>
                                        <strong>${m.name}</strong>
                                        <br><small style="color: var(--gray);">${m.scientific_name || ''}</small>
                                    </div>
                                    <div style="text-align: left;">
                                        <span class="${stockClass}"><strong>${m.quantity}</strong></span>
                                        <br><small>${m.price} دج</small>
                                    </div>
                                </div>
                            `;
                        });
                        medicinesHtml += '</div>';
                    } else {
                        medicinesHtml = '<p class="text-center" style="padding: 2rem;">لا توجد أدوية في هذه الصيدلية</p>';
                    }
                    
                    document.getElementById('pharmacistMedicinesContent').innerHTML = `
                        <h3 style="color: var(--primary);">أدوية ${data.pharmacy_name}</h3>
                        <p style="color: var(--gray); margin-bottom: 1.5rem;">${data.city}</p>
                        
                        <div style="margin-bottom: 1rem;">
                            <input type="text" id="medicineSearch" class="form-control" placeholder="ابحث عن دواء...">
                        </div>
                        
                        ${medicinesHtml}
                    `;
                    
                    // إضافة خاصية البحث
                    document.getElementById('medicineSearch')?.addEventListener('keyup', function() {
                        const search = this.value.toLowerCase();
                        document.querySelectorAll('.medicine-item').forEach(item => {
                            const text = item.textContent.toLowerCase();
                            item.style.display = text.includes(search) ? '' : 'none';
                        });
                    });
                    
                } else {
                    document.getElementById('pharmacistMedicinesContent').innerHTML = `
                        <div class="text-center" style="padding: 2rem;">
                            <i class="fas fa-exclamation-circle fa-3x" style="color: var(--danger);"></i>
                            <p style="margin-top: 1rem;">فشل تحميل البيانات</p>
                        </div>
                    `;
                }
            });
    }
    
    // ============================================================
    // عرض تفاصيل الدواء
    // ============================================================
    function viewMedicineDetails(id) {
        // يمكن إضافة نافذة جانبية أخرى لتفاصيل الدواء
        window.location.href = `medicines.php?view=${id}`;
    }
    
    // ============================================================
    // توثيق الصيدلي
    // ============================================================
    function verifyPharmacist(id) {
        event?.stopPropagation();
        if (confirm('تأكيد توثيق هذا الصيدلي؟')) {
            window.location.href = `users.php?verify=${id}`;
        }
    }
    
    // ============================================================
    // حذف الصيدلي
    // ============================================================
    function deletePharmacist(id) {
        event?.stopPropagation();
        if (confirm('هل أنت متأكد من حذف هذا الصيدلي؟')) {
            window.location.href = `?delete=${id}`;
        }
    }
    </script>
</body>
</html>