<?php
/**
 * pharmacist/medicines.php - إدارة الأدوية العامة
 * CONNECT+ - الإصدار 2.0
 */

require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// التحقق من أن المستخدم صيدلي
requirePharmacist($pdo);

// جلب بيانات المستخدم الحالي
$user = getCurrentUser($pdo);

// ============================================================
// إضافة دواء جديد
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = cleanInput($_POST['name']);
    $scientific = cleanInput($_POST['scientific_name']);
    $category = cleanInput($_POST['category']);
    $price = (float)$_POST['default_price'];
    $prescription = isset($_POST['requires_prescription']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO medicines (name, scientific_name, category, default_price, requires_prescription) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$name, $scientific, $category, $price, $prescription]);
    
    setToast('تم إضافة الدواء بنجاح', 'success');
    redirect('medicines.php');
}

// ============================================================
// البحث عن الأدوية
// ============================================================
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

$sql = "SELECT m.*, 
               (SELECT COUNT(*) FROM pharmacy_medicines pm JOIN pharmacies p ON pm.pharmacy_id = p.id WHERE p.pharmacist_id = ? AND pm.medicine_id = m.id) as in_my_pharmacy,
               (SELECT price FROM pharmacy_medicines pm JOIN pharmacies p ON pm.pharmacy_id = p.id WHERE p.pharmacist_id = ? AND pm.medicine_id = m.id) as my_price,
               (SELECT quantity FROM pharmacy_medicines pm JOIN pharmacies p ON pm.pharmacy_id = p.id WHERE p.pharmacist_id = ? AND pm.medicine_id = m.id) as my_quantity
        FROM medicines m";

$params = [$user['id'], $user['id'], $user['id']];

$where = [];
if ($search) {
    $where[] = "(m.name LIKE ? OR m.scientific_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where[] = "m.category = ?";
    $params[] = $category_filter;
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY m.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

// ============================================================
// جلب التصنيفات للفلترة
// ============================================================
$categories = $pdo->query("SELECT DISTINCT category FROM medicines WHERE category IS NOT NULL ORDER BY category")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الأدوية - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
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
            grid-template-columns: 2fr 1fr auto;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
            }
        }
        
        .medicines-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .medicine-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
        }
        
        body.dark-mode .medicine-card {
            background: #1E1E1E;
        }
        
        .medicine-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .medicine-card.in-my-pharmacy {
            border-color: var(--success);
            background: rgba(42, 157, 143, 0.05);
        }
        
        .medicine-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .medicine-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary);
        }
        
        .prescription-badge {
            background: var(--warning);
            color: white;
            padding: 0.2rem 0.8rem;
            border-radius: 30px;
            font-size: 0.8rem;
        }
        
        .medicine-scientific {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .medicine-category {
            display: inline-block;
            padding: 0.2rem 0.8rem;
            background: var(--light-gray);
            border-radius: 30px;
            font-size: 0.8rem;
            margin-bottom: 1rem;
        }
        
        .medicine-price {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            margin: 0.5rem 0;
        }
        
        .my-stock-info {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 0.8rem;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
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
        
        .floating-add-btn {
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
            
            <div class="card-header">
                <h1 class="page-title"><i class="fas fa-database"></i> إدارة الأدوية</h1>
                <button class="btn btn-primary" onclick="openSideModal('addModal')">
                    <i class="fas fa-plus"></i> إضافة دواء
                </button>
            </div>
            
            <!-- شريط البحث والتصفية -->
            <div class="search-section">
                <form method="get" id="filterForm">
                    <div class="filter-row">
                        <input type="text" name="search" class="form-control" placeholder="ابحث باسم الدواء أو الاسم العلمي..." value="<?= htmlspecialchars($search) ?>">
                        
                        <select name="category" class="form-control">
                            <option value="">كل التصنيفات</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= htmlspecialchars($c['category']) ?>" <?= $category_filter == $c['category'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['category']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> بحث
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- قائمة الأدوية -->
            <?php if (empty($medicines)): ?>
                <div class="empty-state">
                    <i class="fas fa-pills" style="font-size: 4rem; color: var(--gray);"></i>
                    <h3 style="margin-top: 1rem;">لا توجد أدوية</h3>
                    <p style="color: var(--gray);">لم يتم العثور على أي أدوية مطابقة للبحث</p>
                </div>
            <?php else: ?>
                <div class="medicines-grid">
                    <?php foreach ($medicines as $m): ?>
                        <div class="medicine-card <?= $m['in_my_pharmacy'] ? 'in-my-pharmacy' : '' ?>">
                            <div class="medicine-header">
                                <span class="medicine-name"><?= htmlspecialchars($m['name']) ?></span>
                                <?php if ($m['requires_prescription']): ?>
                                    <span class="prescription-badge">وصفة</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="medicine-scientific">
                                <i class="fas fa-flask"></i> <?= htmlspecialchars($m['scientific_name'] ?: 'الاسم العلمي غير محدد') ?>
                            </div>
                            
                            <?php if ($m['category']): ?>
                                <div class="medicine-category">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($m['category']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="medicine-price">
                                <?= number_format($m['default_price'] ?: 0, 2) ?> دج
                                <span style="font-size: 0.9rem; color: var(--gray);">(سعر تقريبي)</span>
                            </div>
                            
                            <?php if ($m['in_my_pharmacy']): ?>
                                <div class="my-stock-info">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span><i class="fas fa-box"></i> في مخزوني:</span>
                                        <span class="<?= $m['my_quantity'] < 10 ? 'text-warning' : '' ?>">
                                            <strong><?= $m['my_quantity'] ?></strong> وحدة
                                        </span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; margin-top: 0.3rem;">
                                        <span><i class="fas fa-tag"></i> سعري:</span>
                                        <span><strong><?= number_format($m['my_price'], 2) ?></strong> دج</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="action-buttons">
                                <?php if ($m['in_my_pharmacy']): ?>
                                    <button class="btn btn-outline btn-sm" onclick="editInInventory(<?= $m['id'] ?>, <?= $m['my_quantity'] ?>, <?= $m['my_price'] ?>)">
                                        <i class="fas fa-edit"></i> تحديث
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-primary btn-sm" onclick="addToInventory(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name']) ?>')">
                                        <i class="fas fa-cart-plus"></i> أضف لمخزوني
                                    </button>
                                <?php endif; ?>
                                
                                <button class="btn btn-outline btn-sm" onclick="viewMedicineDetails(<?= $m['id'] ?>)">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- زر الإضافة العائم -->
    <button class="floating-add-btn" onclick="openSideModal('addModal')">
        <i class="fas fa-plus"></i>
    </button>
    
    <!-- نافذة إضافة دواء جديد -->
    <div class="side-modal" id="addModal">
        <div class="side-modal-header">
            <h3>إضافة دواء جديد</h3>
            <button class="close-side-modal" onclick="closeSideModal('addModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post">
                <div class="form-group">
                    <label>الاسم التجاري</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>الاسم العلمي</label>
                    <input type="text" name="scientific_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>التصنيف</label>
                    <input type="text" name="category" class="form-control" placeholder="مثال: مسكن، مضاد حيوي">
                </div>
                
                <div class="form-group">
                    <label>السعر التقريبي (دج)</label>
                    <input type="number" name="default_price" class="form-control" step="0.01" min="0">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="requires_prescription"> يحتاج وصفة طبية
                    </label>
                </div>
                
                <button type="submit" name="add" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-save"></i> إضافة الدواء
                </button>
            </form>
        </div>
    </div>
    
    <!-- نافذة إضافة للمخزون -->
    <div class="side-modal" id="addToInventoryModal">
        <div class="side-modal-header">
            <h3>إضافة للمخزون</h3>
            <button class="close-side-modal" onclick="closeSideModal('addToInventoryModal')">&times;</button>
        </div>
        <div class="side-modal-body">
            <form method="post" action="inventory.php">
                <input type="hidden" name="medicine_id" id="invMedicineId">
                
                <div class="form-group">
                    <label>الدواء</label>
                    <input type="text" id="invMedicineName" class="form-control" disabled>
                </div>
                
                <div class="form-group">
                    <label>الكمية</label>
                    <input type="number" name="quantity" class="form-control" min="1" required>
                </div>
                
                <div class="form-group">
                    <label>السعر (دج)</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <button type="submit" name="add" class="btn btn-primary" style="width: 100%;">
                    <i class="fas fa-cart-plus"></i> إضافة للمخزون
                </button>
            </form>
        </div>
    </div>
    
    <!-- نافذة تعديل في المخزون -->
    <div class="side-modal" id="editInventoryModal">
        <div class="side-modal-header">
            <h3>تحديث المخزون</h3>
            <button class="close-side-modal" onclick="closeSideModal('editInventoryModal')">&times;</button>
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
    <div class="side-modal" id="medicineDetailsModal">
        <div class="side-modal-header">
            <h3>تفاصيل الدواء</h3>
            <button class="close-side-modal" onclick="closeSideModal('medicineDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="medicineDetailsContent"></div>
    </div>
    
    <script>
    function addToInventory(id, name) {
        document.getElementById('invMedicineId').value = id;
        document.getElementById('invMedicineName').value = name;
        openSideModal('addToInventoryModal');
    }
    
    function editInInventory(id, quantity, price) {
        document.getElementById('editMedicineId').value = id;
        document.getElementById('editQuantity').value = quantity;
        document.getElementById('editPrice').value = price;
        openSideModal('editInventoryModal');
    }
    
    function viewMedicineDetails(id) {
        fetch(`../api/get_medicine_details.php?id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const m = data.data;
                    document.getElementById('medicineDetailsContent').innerHTML = `
                        <h2 style="color: var(--primary);">${m.name}</h2>
                        <p style="color: var(--gray);">${m.scientific_name || 'الاسم العلمي غير محدد'}</p>
                        
                        <hr>
                        
                        <div style="margin: 1.5rem 0;">
                            ${m.category ? `<p><i class="fas fa-tag"></i> <strong>التصنيف:</strong> ${m.category}</p>` : ''}
                            <p><i class="fas fa-money-bill"></i> <strong>السعر التقريبي:</strong> ${m.default_price || 0} دج</p>
                            <p><i class="fas fa-prescription"></i> <strong>الوصفة الطبية:</strong> 
                                <span class="badge ${m.requires_prescription ? 'badge-warning' : 'badge-success'}">
                                    ${m.requires_prescription ? 'يحتاج وصفة' : 'لا يحتاج وصفة'}
                                </span>
                            </p>
                        </div>
                        
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="addToInventory(${m.id}, '${m.name}')" style="flex: 1;">
                                <i class="fas fa-cart-plus"></i> أضف لمخزوني
                            </button>
                        </div>
                    `;
                    openSideModal('medicineDetailsModal');
                }
            });
    }
    </script>
</body>
</html>