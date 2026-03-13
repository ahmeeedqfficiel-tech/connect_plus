<?php
/**
 * pharmacist/pharmacist_medicines.php - أدوية صيدلية محددة
 * CONNECT+ - الإصدار 2.0 النهائي
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم أدمن
requireAdmin($pdo);

$pharmacist_id = (int)($_GET['pharmacist_id'] ?? 0);

if (!$pharmacist_id) {
    setToast('معرف الصيدلي غير صحيح', 'error');
    redirect('pharmacists.php');
}

// جلب معلومات الصيدلي
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.phone, u.profile_image,
           ph.pharmacy_name, ph.license_number, ph.city, ph.address, ph.is_24h
    FROM users u 
    JOIN pharmacists ph ON u.id = ph.user_id 
    WHERE u.id = ? AND u.role = 'pharmacist'
");
$stmt->execute([$pharmacist_id]);
$pharmacist = $stmt->fetch();

if (!$pharmacist) {
    setToast('الصيدلي غير موجود', 'error');
    redirect('pharmacists.php');
}

// جلب معرف الصيدلية
$stmt = $pdo->prepare("SELECT id FROM pharmacies WHERE pharmacist_id = ?");
$stmt->execute([$pharmacist_id]);
$pharmacy_id = $stmt->fetchColumn();

if (!$pharmacy_id) {
    // إذا لم توجد صيدلية، نقوم بإنشاء واحدة
    $stmt = $pdo->prepare("
        INSERT INTO pharmacies (pharmacist_id, name, city, address, phone, license_number, is_24h) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $pharmacist_id,
        $pharmacist['pharmacy_name'] ?: 'صيدلية ' . $pharmacist['full_name'],
        $pharmacist['city'] ?: 'غير محدد',
        $pharmacist['address'] ?: 'غير محدد',
        $pharmacist['phone'] ?: '',
        $pharmacist['license_number'] ?: '',
        $pharmacist['is_24h'] ?: 0
    ]);
    $pharmacy_id = $pdo->lastInsertId();
}

// جلب أدوية الصيدلية
$medicines = $pdo->prepare("
    SELECT pm.*, m.name, m.scientific_name, m.category, m.requires_prescription,
           m.default_price
    FROM pharmacy_medicines pm
    JOIN medicines m ON pm.medicine_id = m.id
    WHERE pm.pharmacy_id = ?
    ORDER BY 
        CASE 
            WHEN pm.quantity = 0 THEN 1
            WHEN pm.quantity < 10 THEN 2
            ELSE 3
        END,
        m.name
");
$stmt->execute([$pharmacy_id]);
$medicines = $stmt->fetchAll();

// إحصائيات سريعة
$totalMedicines = count($medicines);
$lowStockCount = 0;
$outOfStockCount = 0;
$totalValue = 0;

foreach ($medicines as $m) {
    if ($m['quantity'] == 0) {
        $outOfStockCount++;
    } elseif ($m['quantity'] < 10) {
        $lowStockCount++;
    }
    $totalValue += $m['quantity'] * $m['price'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أدوية الصيدلية - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .pharmacy-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }
        
        body.dark-mode .pharmacy-header {
            background: #1E1E1E;
        }
        
        .pharmacy-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            overflow: hidden;
        }
        
        .pharmacy-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .pharmacy-info {
            flex: 1;
        }
        
        .pharmacy-name {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }
        
        .pharmacy-meta {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            color: var(--gray);
            margin: 0.5rem 0;
        }
        
        .badge-24h {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
            padding: 0.3rem 1rem;
            border-radius: 30px;
            display: inline-block;
        }
        
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
        
        .filter-buttons {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 30px;
            border: 1px solid var(--light-gray);
            background: white;
            cursor: pointer;
            text-decoration: none;
            color: var(--dark);
            transition: var(--transition);
        }
        
        body.dark-mode .filter-btn {
            background: #1E1E1E;
            color: white;
        }
        
        .filter-btn:hover {
            background: var(--primary-soft);
        }
        
        .filter-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: transparent;
        }
        
        .medicines-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .medicines-table th {
            background: var(--primary);
            color: white;
            padding: 1rem;
            text-align: right;
        }
        
        .medicines-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .medicines-table tr.low-stock {
            background: rgba(244, 162, 97, 0.1);
        }
        
        .medicines-table tr.out-stock {
            background: rgba(231, 111, 81, 0.1);
        }
        
        .quantity-badge {
            display: inline-block;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-weight: 600;
        }
        
        .quantity-normal {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .quantity-low {
            background: rgba(244, 162, 97, 0.1);
            color: var(--warning);
        }
        
        .quantity-out {
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
        
        .action-btn.edit {
            background: var(--primary);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .back-link:hover {
            color: var(--primary);
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
            
            <!-- رابط العودة -->
            <a href="pharmacists.php" class="back-link">
                <i class="fas fa-arrow-right"></i> العودة إلى قائمة الصيادلة
            </a>
            
            <!-- رأس الصيدلية -->
            <div class="pharmacy-header">
                <div class="pharmacy-avatar">
                    <?php if (!empty($pharmacist['profile_image'])): ?>
                        <img src="../<?= $pharmacist['profile_image'] ?>" alt="صورة">
                    <?php else: ?>
                        <i class="fas fa-store"></i>
                    <?php endif; ?>
                </div>
                
                <div class="pharmacy-info">
                    <div class="pharmacy-name"><?= htmlspecialchars($pharmacist['pharmacy_name']) ?></div>
                    
                    <div class="pharmacy-meta">
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($pharmacist['full_name']) ?></span>
                        <span><i class="fas fa-envelope"></i> <?= $pharmacist['email'] ?></span>
                        <span><i class="fas fa-phone"></i> <?= $pharmacist['phone'] ?: '-' ?></span>
                    </div>
                    
                    <div class="pharmacy-meta">
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($pharmacist['address'] ?: '-') ?> - <?= htmlspecialchars($pharmacist['city'] ?: '-') ?></span>
                        <span><i class="fas fa-id-card"></i> رخصة: <?= $pharmacist['license_number'] ?: '-' ?></span>
                        <?php if ($pharmacist['is_24h']): ?>
                            <span class="badge-24h"><i class="fas fa-clock"></i> 24 ساعة</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- إحصائيات سريعة -->
            <div class="stats-mini-grid">
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $totalMedicines ?></div>
                    <div style="color: var(--gray);">إجمالي الأدوية</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $lowStockCount ?></div>
                    <div style="color: var(--gray);">مخزون منخفض</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= $outOfStockCount ?></div>
                    <div style="color: var(--gray);">نافد من المخزون</div>
                </div>
                
                <div class="stat-mini-card">
                    <div class="stat-mini-number"><?= number_format($totalValue, 2) ?> دج</div>
                    <div style="color: var(--gray);">القيمة الإجمالية</div>
                </div>
            </div>
            
            <!-- شريط البحث والتصفية -->
            <div class="search-section">
                <div class="form-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="ابحث عن دواء...">
                </div>
                
                <div class="filter-buttons">
                    <button class="filter-btn active" onclick="filterMedicines('all')">الكل</button>
                    <button class="filter-btn" onclick="filterMedicines('low')">مخزون منخفض</button>
                    <button class="filter-btn" onclick="filterMedicines('out')">نافد</button>
                    <button class="filter-btn" onclick="filterMedicines('prescription')">يحتاج وصفة</button>
                </div>
            </div>
            
            <!-- جدول الأدوية -->
            <div class="info-card">
                <div style="overflow-x: auto;">
                    <table class="medicines-table">
                        <thead>
                            <tr>
                                <th>الدواء</th>
                                <th>الاسم العلمي</th>
                                <th>التصنيف</th>
                                <th>الكمية</th>
                                <th>السعر</th>
                                <th>القيمة</th>
                                <th>وصفة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="medicinesBody">
                            <?php if (empty($medicines)): ?>
                                <tr>
                                    <td colspan="8" class="text-center" style="padding: 3rem;">
                                        لا توجد أدوية في هذه الصيدلية
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($medicines as $m): 
                                    $rowClass = '';
                                    $quantityClass = 'quantity-normal';
                                    
                                    if ($m['quantity'] == 0) {
                                        $rowClass = 'out-stock';
                                        $quantityClass = 'quantity-out';
                                    } elseif ($m['quantity'] < 10) {
                                        $rowClass = 'low-stock';
                                        $quantityClass = 'quantity-low';
                                    }
                                    
                                    $totalPrice = $m['quantity'] * $m['price'];
                                ?>
                                    <tr class="<?= $rowClass ?>" 
                                        data-name="<?= strtolower(htmlspecialchars($m['name'])) ?>"
                                        data-category="<?= strtolower($m['category'] ?? '') ?>"
                                        data-prescription="<?= $m['requires_prescription'] ?>"
                                        data-quantity="<?= $m['quantity'] ?>">
                                        
                                        <td><strong><?= htmlspecialchars($m['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($m['scientific_name'] ?: '-') ?></td>
                                        <td><?= htmlspecialchars($m['category'] ?: '-') ?></td>
                                        <td>
                                            <span class="quantity-badge <?= $quantityClass ?>">
                                                <?= $m['quantity'] ?>
                                            </span>
                                        </td>
                                        <td><?= number_format($m['price'], 2) ?> دج</td>
                                        <td><?= number_format($totalPrice, 2) ?> دج</td>
                                        <td>
                                            <?php if ($m['requires_prescription']): ?>
                                                <span class="badge badge-warning">نعم</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">لا</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="action-btn view" onclick="viewMedicine(<?= $m['medicine_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="action-btn edit" onclick="editMedicine(<?= $m['medicine_id'] ?>, <?= $m['quantity'] ?>, <?= $m['price'] ?>)">
                                                <i class="fas fa-edit"></i>
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
    
    <!-- نافذة تعديل الدواء -->
    <div class="side-modal" id="editMedicineModal">
        <div class="side-modal-header">
            <h3>تحديث الدواء</h3>
            <button class="close-side-modal" onclick="closeSideModal('editMedicineModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post" action="inventory.php">
                <input type="hidden" name="medicine_id" id="editMedicineId">
                
                <div class="form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" id="editQuantity" class="form-control" min="0" required>
                </div>
                
                <div class="form-group">
                    <label>السعر (دج)</label>
                    <input type="number" name="price" id="editPrice" class="form-control" step="0.01" min="0" required>
                </div>
                
                <button type="submit" name="update" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> تحديث
                </button>
            </form>
        </div>
    </div>
    
    <!-- نافذة تفاصيل الدواء -->
    <div class="side-modal" id="viewMedicineModal">
        <div class="side-modal-header">
            <h3>تفاصيل الدواء</h3>
            <button class="close-side-modal" onclick="closeSideModal('viewMedicineModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="medicineDetails"></div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
    // ============================================================
    // فلترة الأدوية
    // ============================================================
    function filterMedicines(filter) {
        document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
        event.target.classList.add('active');
        
        const rows = document.querySelectorAll('#medicinesBody tr');
        
        rows.forEach(row => {
            if (filter === 'all') {
                row.style.display = '';
            } else if (filter === 'low') {
                const quantity = row.dataset.quantity;
                row.style.display = (quantity > 0 && quantity < 10) ? '' : 'none';
            } else if (filter === 'out') {
                const quantity = row.dataset.quantity;
                row.style.display = quantity == 0 ? '' : 'none';
            } else if (filter === 'prescription') {
                const prescription = row.dataset.prescription;
                row.style.display = prescription == '1' ? '' : 'none';
            }
        });
    }
    
    // ============================================================
    // بحث فوري
    // ============================================================
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const search = this.value.toLowerCase();
        const rows = document.querySelectorAll('#medicinesBody tr');
        
        rows.forEach(row => {
            const name = row.dataset.name || '';
            if (name.includes(search)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
    
    // ============================================================
    // تعديل الدواء
    // ============================================================
    function editMedicine(id, quantity, price) {
        document.getElementById('editMedicineId').value = id;
        document.getElementById('editQuantity').value = quantity;
        document.getElementById('editPrice').value = price;
        openSideModal('editMedicineModal');
    }
    
    // ============================================================
    // عرض تفاصيل الدواء
    // ============================================================
    function viewMedicine(id) {
        fetch(`../api/get_medicine_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const m = data.data;
                    
                    let prescriptionText = m.requires_prescription ? 'يحتاج وصفة طبية' : 'لا يحتاج وصفة';
                    let prescriptionClass = m.requires_prescription ? 'badge-warning' : 'badge-success';
                    
                    document.getElementById('medicineDetails').innerHTML = `
                        <h2 style="color: var(--primary);">${m.name}</h2>
                        <p style="color: var(--gray);">${m.scientific_name || 'الاسم العلمي غير محدد'}</p>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            ${m.category ? `<p><i class="fas fa-tag"></i> <strong>التصنيف:</strong> ${m.category}</p>` : ''}
                            <p><i class="fas fa-money-bill"></i> <strong>السعر التقريبي:</strong> ${m.default_price || 0} دج</p>
                            <p><i class="fas fa-prescription"></i> <strong>الوصفة الطبية:</strong> 
                                <span class="badge ${prescriptionClass}">${prescriptionText}</span>
                            </p>
                        </div>
                        
                        <h4 style="margin: 1rem 0;">معلومات إضافية</h4>
                        <p><i class="fas fa-box"></i> <strong>الكمية في هذه الصيدلية:</strong> 
                            <span class="${m.quantity < 10 ? 'text-warning' : ''}">${m.quantity || 0}</span>
                        </p>
                        <p><i class="fas fa-tag"></i> <strong>السعر في هذه الصيدلية:</strong> ${m.price || 0} دج</p>
                    `;
                    
                    openSideModal('viewMedicineModal');
                }
            });
    }
    </script>
</body>
</html>