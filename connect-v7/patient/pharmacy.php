<?php
/**
 * patient/pharmacy.php - الصيدلية مع نوافذ جانبية
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم مريض
requirePatient($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// جلب الصيدليات
// ============================================================
$pharmacies = $pdo->query("
    SELECT p.*, u.full_name as pharmacist_name, u.phone, u.profile_image,
           (SELECT COUNT(*) FROM pharmacy_medicines WHERE pharmacy_id = p.id) as medicines_count
    FROM pharmacies p
    JOIN pharmacists ph ON p.pharmacist_id = ph.user_id
    JOIN users u ON ph.user_id = u.id
    WHERE u.is_verified = 1
    ORDER BY p.name
")->fetchAll();

// ============================================================
// جلب الأدوية
// ============================================================
$medicines = $pdo->query("
    SELECT m.*, 
           (SELECT MIN(price) FROM pharmacy_medicines WHERE medicine_id = m.id) as min_price,
           (SELECT MAX(price) FROM pharmacy_medicines WHERE medicine_id = m.id) as max_price,
           (SELECT COUNT(*) FROM pharmacy_medicines WHERE medicine_id = m.id) as available_pharmacies
    FROM medicines m
    ORDER BY m.name
")->fetchAll();

// تحويل البيانات إلى JSON
$pharmaciesJson = json_encode($pharmacies);
$medicinesJson = json_encode($medicines);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الصيدلية - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .pharmacy-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: white;
            padding: 1rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        body.dark-mode .pharmacy-tabs {
            background: #1E1E1E;
        }
        
        .pharmacy-tab {
            flex: 1;
            padding: 0.8rem;
            background: var(--light-gray);
            border: none;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            text-decoration: none;
            color: var(--dark);
        }
        
        body.dark-mode .pharmacy-tab {
            background: #333;
            color: white;
        }
        
        .pharmacy-tab:hover {
            background: var(--primary-soft);
        }
        
        .pharmacy-tab.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .pharmacy-card, .medicine-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        body.dark-mode .pharmacy-card,
        body.dark-mode .medicine-card {
            background: #1E1E1E;
        }
        
        .pharmacy-card:hover,
        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .pharmacy-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .pharmacy-address {
            color: var(--gray);
            margin-bottom: 0.5rem;
        }
        
        .badge-24h {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
        }
        
        .medicine-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .medicine-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .prescription-badge {
            background: var(--warning);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
            display: inline-block;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .dev-map {
            background: rgba(244, 162, 97, 0.1);
            border: 2px dashed var(--warning);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            margin-top: 2rem;
            cursor: pointer;
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
        
        .pharmacy-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .pharmacy-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            overflow: hidden;
        }
        
        .pharmacy-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .medicine-list {
            max-height: 300px;
            overflow-y: auto;
            margin: 1rem 0;
        }
        
        .medicine-list-item {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem;
            border-bottom: 1px solid var(--light-gray);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .medicine-list-item:hover {
            background: var(--primary-soft);
        }
        
        .floating-order-btn {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: var(--shadow-xl);
            cursor: pointer;
            transition: var(--transition);
            z-index: 100;
            border: none;
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
                <i class="fas fa-store"></i> الصيدلية
            </h1>
            
            <!-- تبويبات -->
            <div class="pharmacy-tabs">
                <a href="#pharmacies" class="pharmacy-tab active" onclick="showTab('pharmacies')">
                    <i class="fas fa-store"></i> صيدليات
                </a>
                <a href="#medicines" class="pharmacy-tab" onclick="showTab('medicines')">
                    <i class="fas fa-pills"></i> أدوية
                </a>
            </div>
            
            <!-- قائمة الصيدليات -->
            <div id="pharmaciesList" class="tab-content active">
                <?php if (empty($pharmacies)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-store" style="font-size: 3rem; color: var(--gray);"></i>
                        <p>لا توجد صيدليات مسجلة</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($pharmacies as $p): ?>
                        <div class="pharmacy-card" onclick="viewPharmacyDetails(<?= $p['id'] ?>)">
                            <div class="pharmacy-name"><?= htmlspecialchars($p['name'] ?? $p['pharmacy_name']) ?></div>
                            <div class="pharmacy-address">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($p['address']) ?> - <?= htmlspecialchars($p['city']) ?>
                            </div>
                            <div style="display: flex; gap: 1rem; color: var(--gray);">
                                <span><i class="fas fa-phone"></i> <?= $p['phone'] ?></span>
                                <span><i class="fas fa-pills"></i> <?= $p['medicines_count'] ?> دواء</span>
                                <?php if ($p['is_24h']): ?>
                                    <span class="badge-24h">24 ساعة</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- قائمة الأدوية -->
            <div id="medicinesList" class="tab-content">
                <?php if (empty($medicines)): ?>
                    <div class="info-card text-center" style="padding: 3rem;">
                        <i class="fas fa-pills" style="font-size: 3rem; color: var(--gray);"></i>
                        <p>لا توجد أدوية</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($medicines as $m): ?>
                        <div class="medicine-card" onclick="viewMedicineDetails(<?= $m['id'] ?>)">
                            <div class="medicine-name"><?= htmlspecialchars($m['name'] ?: $m['medicine_name']) ?></div>
                            <p style="color: var(--gray);"><?= htmlspecialchars($m['scientific_name']) ?></p>
                            <?php if ($m['min_price']): ?>
                                <div class="medicine-price">
                                    <?= number_format($m['min_price'], 2) ?> - <?= number_format($m['max_price'], 2) ?> دج
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; gap: 1rem; color: var(--gray);">
                                <span><i class="fas fa-store"></i> <?= $m['available_pharmacies'] ?> صيدلية</span>
                                <?php if ($m['requires_prescription']): ?>
                                    <span class="prescription-badge">يحتاج وصفة</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- خريطة تحت التطوير -->
            <div class="dev-map" onclick="showDevMessage('الخريطة')">
                <i class="fas fa-map-marked-alt" style="font-size: 3rem; color: var(--warning);"></i>
                <h3>الخريطة التفاعلية</h3>
                <p style="color: var(--gray);">اضغط لعرض الصيدليات القريبة منك</p>
                <span class="dev-badge">قيد التطوير</span>
            </div>
        </div>
    </div>
    
    <!-- زر الطلب العائم -->
    <button class="floating-order-btn" onclick="openOrderModal()">
        <i class="fas fa-shopping-cart"></i>
    </button>
    
    <!-- ============================================================
         النوافذ الجانبية
         ============================================================ -->
    
    <!-- نافذة تفاصيل الصيدلية -->
    <div class="side-modal" id="pharmacyDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-store"></i> تفاصيل الصيدلية</h3>
            <button class="close-side-modal" onclick="closeSideModal('pharmacyDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="pharmacyDetailsContent"></div>
    </div>
    
    <!-- نافذة تفاصيل الدواء -->
    <div class="side-modal" id="medicineDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-pills"></i> تفاصيل الدواء</h3>
            <button class="close-side-modal" onclick="closeSideModal('medicineDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="medicineDetailsContent"></div>
    </div>
    
    <!-- نافذة طلب دواء -->
    <div class="side-modal" id="orderModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-shopping-cart"></i> طلب دواء</h3>
            <button class="close-side-modal" onclick="closeSideModal('orderModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post" action="order.php">
                <div class="form-group">
                    <label>اختر الصيدلية</label>
                    <select name="pharmacy_id" id="orderPharmacy" class="form-control" required>
                        <option value="">اختر الصيدلية</option>
                        <?php foreach ($pharmacies as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> - <?= htmlspecialchars($p['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>اختر الدواء</label>
                    <select name="medicine_id" id="orderMedicine" class="form-control" required>
                        <option value="">اختر الدواء</option>
                        <?php foreach ($medicines as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-check"></i> تأكيد الطلب
                </button>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    const pharmacies = <?= $pharmaciesJson ?>;
    const medicines = <?= $medicinesJson ?>;
    
    // ============================================================
    // تبديل التبويبات
    // ============================================================
    function showTab(tab) {
        document.querySelectorAll('.pharmacy-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        if (tab === 'pharmacies') {
            document.querySelector('.pharmacy-tab').classList.add('active');
            document.getElementById('pharmaciesList').classList.add('active');
        } else {
            document.querySelectorAll('.pharmacy-tab')[1].classList.add('active');
            document.getElementById('medicinesList').classList.add('active');
        }
    }
    
    // ============================================================
    // عرض تفاصيل الصيدلية
    // ============================================================
    function viewPharmacyDetails(id) {
        const p = pharmacies.find(ph => ph.id == id);
        
        if (p) {
            openSideModal('pharmacyDetailsModal');
            
            document.getElementById('pharmacyDetailsContent').innerHTML = `
                <div class="pharmacy-header">
                    <div class="pharmacy-avatar">
                        ${p.profile_image ? `<img src="../${p.profile_image}">` : '<i class="fas fa-store"></i>'}
                    </div>
                    <div>
                        <h2 style="color: var(--primary);">${p.name}</h2>
                        <p style="color: var(--gray);">${p.pharmacist_name}</p>
                    </div>
                </div>
                
                <hr>
                
                <div class="info-row">
                    <span class="info-label">العنوان:</span>
                    <span class="info-value">${p.address} - ${p.city}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">الهاتف:</span>
                    <span class="info-value">${p.phone}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">ساعات العمل:</span>
                    <span class="info-value">${p.is_24h ? '24 ساعة' : 'ساعات عمل عادية'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">عدد الأدوية:</span>
                    <span class="info-value">${p.medicines_count}</span>
                </div>
            `;
        }
    }
    
    // ============================================================
    // عرض تفاصيل الدواء
    // ============================================================
    function viewMedicineDetails(id) {
        const m = medicines.find(med => med.id == id);
        
        if (m) {
            openSideModal('medicineDetailsModal');
            
            document.getElementById('medicineDetailsContent').innerHTML = `
                <h2 style="color: var(--primary);">${m.name}</h2>
                <p style="color: var(--gray);">${m.scientific_name || ''}</p>
                
                <hr>
                
                <div class="info-row">
                    <span class="info-label">التصنيف:</span>
                    <span class="info-value">${m.category || '-'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">السعر:</span>
                    <span class="info-value">${m.min_price ? m.min_price + ' - ' + m.max_price + ' دج' : 'غير محدد'}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">الصيدليات:</span>
                    <span class="info-value">${m.available_pharmacies}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">وصفة طبية:</span>
                    <span class="info-value">${m.requires_prescription ? 'نعم' : 'لا'}</span>
                </div>
            `;
        }
    }
    
    // ============================================================
    // فتح نافذة الطلب
    // ============================================================
    function openOrderModal() {
        openSideModal('orderModal');
    }
    </script>
</body>
</html>