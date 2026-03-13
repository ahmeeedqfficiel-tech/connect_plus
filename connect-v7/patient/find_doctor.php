<?php
/**
 * patient/find_doctor.php - البحث عن الأطباء مع نافذة واحدة شاملة
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
// جلب التخصصات والمدن للفلترة
// ============================================================
$specialties = $pdo->query("SELECT DISTINCT specialties FROM doctors WHERE specialties IS NOT NULL ORDER BY specialties")->fetchAll();
$cities = $pdo->query("SELECT DISTINCT city FROM users WHERE role = 'doctor' AND city IS NOT NULL ORDER BY city")->fetchAll();

// ============================================================
// جلب الأطباء المميزين مع جميع المعلومات
// ============================================================
$featuredDoctors = $pdo->query("
    SELECT 
        u.id, 
        u.full_name, 
        u.profile_image, 
        u.city,
        u.email,
        u.phone,
        u.is_available,
        u.is_verified,
        u.created_at,
        d.specialties, 
        d.consultation_fees, 
        d.workplace_name,
        d.workplace_address,
        d.degree,
        d.license_number,
        d.license_start_date,
        d.available_from, 
        d.available_to, 
        d.working_days,
        (SELECT AVG(rating) FROM ratings WHERE doctor_id = u.id) as avg_rating,
        (SELECT COUNT(*) FROM ratings WHERE doctor_id = u.id) as ratings_count,
        (SELECT COUNT(*) FROM appointments WHERE doctor_id = u.id AND status = 'completed') as completed_appointments,
        (SELECT COUNT(DISTINCT patient_id) FROM appointments WHERE doctor_id = u.id) as patients_count
    FROM users u
    JOIN doctors d ON u.id = d.user_id
    WHERE u.role = 'doctor' AND u.is_verified = 1
    ORDER BY avg_rating DESC, completed_appointments DESC
    LIMIT 12
")->fetchAll();

// ============================================================
// جلب التقييمات لجميع الأطباء
// ============================================================
$allRatings = [];
foreach ($featuredDoctors as $doctor) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as patient_name, u.profile_image as patient_image
        FROM ratings r
        JOIN users u ON r.patient_id = u.id
        WHERE r.doctor_id = ?
        ORDER BY r.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$doctor['id']]);
    $allRatings[$doctor['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البحث عن الأطباء - CONNECT+</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- ملفات CSS المحلية -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <style>
        .search-container {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
        }
        
        body.dark-mode .search-container {
            background: #1E1E1E;
        }
        
        .filter-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .doctor-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            position: relative;
        }
        
        body.dark-mode .doctor-card {
            background: #1E1E1E;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .doctor-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
            overflow: hidden;
            border: 3px solid var(--primary-light);
        }
        
        .doctor-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .doctor-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
            text-align: center;
            margin-bottom: 0.3rem;
        }
        
        .doctor-specialty {
            text-align: center;
            color: var(--gray);
            margin-bottom: 0.3rem;
            font-size: 1rem;
        }
        
        .doctor-degree {
            text-align: center;
            color: var(--secondary);
            margin-bottom: 0.8rem;
            font-size: 0.9rem;
            font-style: italic;
        }
        
        .doctor-rating {
            text-align: center;
            color: #F4A261;
            margin-bottom: 1rem;
            font-size: 1rem;
        }
        
        .rating-number {
            color: var(--gray);
            font-size: 0.9rem;
            margin-right: 0.3rem;
        }
        
        .availability-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .available {
            background: rgba(42, 157, 143, 0.1);
            color: var(--success);
        }
        
        .unavailable {
            background: rgba(231, 111, 81, 0.1);
            color: var(--danger);
        }
        
        .verified-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--success);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }
        
        .doctor-info-row {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-size: 0.9rem;
        }
        
        .doctor-info-row i {
            width: 18px;
            color: var(--primary);
        }
        
        .featured-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 2rem 0 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .featured-title i {
            color: #F4A261;
        }
        
        /* ===== النافذة الجانبية الوحيدة ===== */
        .side-modal {
            position: fixed;
            top: 0;
            left: -500px;
            width: 500px;
            height: 100vh;
            background: white;
            box-shadow: var(--shadow-xl);
            z-index: 2000;
            transition: left 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
        }
        
        body.dark-mode .side-modal {
            background: #1E1E1E;
        }
        
        .side-modal.active {
            left: 0;
        }
        
        .side-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 2px solid var(--light-gray);
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }
        
        body.dark-mode .side-modal-header {
            background: #1E1E1E;
        }
        
        .side-modal-header h3 {
            color: var(--primary);
            font-size: 1.2rem;
        }
        
        .close-side-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
        }
        
        .close-side-modal:hover {
            color: var(--danger);
        }
        
        .side-modal-body {
            padding: 1.5rem;
            flex: 1;
            overflow-y: auto;
        }
        
        /* تنسيقات محتوى النافذة */
        .doctor-profile-header {
            display: flex;
            gap: 1.5rem;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .doctor-profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            overflow: hidden;
            border: 3px solid var(--primary-light);
            flex-shrink: 0;
        }
        
        .doctor-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .doctor-profile-info h2 {
            color: var(--primary);
            margin-bottom: 0.3rem;
            font-size: 1.5rem;
        }
        
        .doctor-profile-info p {
            color: var(--gray);
            margin-bottom: 0.3rem;
        }
        
        .info-section {
            background: var(--light);
            border-radius: var(--radius-md);
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        
        .info-section-title {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 0.8rem;
            padding: 0.3rem 0;
            border-bottom: 1px dashed var(--light-gray);
        }
        
        .info-label {
            width: 120px;
            font-weight: 600;
            color: var(--gray);
        }
        
        .info-value {
            flex: 1;
        }
        
        .working-hours-box {
            background: white;
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 0.5rem;
        }
        
        .ratings-container {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 1rem;
        }
        
        .rating-item {
            background: white;
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--light-gray);
        }
        
        .rating-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .rating-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .rating-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .rating-name {
            font-weight: 600;
        }
        
        .rating-date {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .rating-stars {
            color: #F4A261;
            margin: 0.3rem 0;
        }
        
        .rating-comment {
            color: var(--dark);
            line-height: 1.6;
            margin-top: 0.5rem;
            font-size: 0.95rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .dev-map {
            background: rgba(244, 162, 97, 0.1);
            border: 2px dashed var(--warning);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            text-align: center;
            margin: 1.5rem 0;
            cursor: pointer;
        }
        
        .badge-group {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin: 0.5rem 0;
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
                <i class="fas fa-search"></i> البحث عن الأطباء
            </h1>
            
            <!-- قسم البحث -->
            <div class="search-container">
                <div class="form-group">
                    <input type="text" id="searchInput" class="form-control" placeholder="ابحث باسم الطبيب أو التخصص...">
                </div>
                
                <div class="filter-section">
                    <select id="specialtyFilter" class="form-control">
                        <option value="">كل التخصصات</option>
                        <?php foreach ($specialties as $s): ?>
                            <option value="<?= htmlspecialchars($s['specialties']) ?>"><?= htmlspecialchars($s['specialties']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="cityFilter" class="form-control">
                        <option value="">كل المدن</option>
                        <?php foreach ($cities as $c): ?>
                            <option value="<?= htmlspecialchars($c['city']) ?>"><?= htmlspecialchars($c['city']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select id="ratingFilter" class="form-control">
                        <option value="">كل التقييمات</option>
                        <option value="4">4 نجوم فأكثر</option>
                        <option value="3">3 نجوم فأكثر</option>
                        <option value="2">2 نجوم فأكثر</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="availableFilter"> متاح الآن فقط
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="checkbox" id="verifiedFilter"> موثق فقط
                    </label>
                </div>
                
                <button class="btn btn-primary" onclick="searchDoctors()" style="width: 100%; margin-top: 1.5rem;">
                    <i class="fas fa-search"></i> بحث
                </button>
            </div>
            
            <!-- خريطة تحت التطوير -->
            <div class="dev-map" onclick="showDevMessage('الخريطة')">
                <i class="fas fa-map-marked-alt" style="font-size: 2rem; color: var(--warning); margin-bottom: 0.5rem;"></i>
                <h3 style="margin: 0.5rem 0;">الخريطة التفاعلية</h3>
                <p style="color: var(--gray);">اضغط لعرض الأطباء القريبين منك على الخريطة</p>
                <span class="dev-badge">قيد التطوير</span>
            </div>
            
            <!-- عنوان الأطباء المميزين -->
            <div class="featured-title">
                <i class="fas fa-star"></i>
                <span>أطباء مميزون (<?= count($featuredDoctors) ?> طبيب)</span>
            </div>
            
            <!-- قائمة الأطباء -->
            <div class="doctors-grid">
                <?php foreach ($featuredDoctors as $doc): ?>
                    <div class="doctor-card" onclick='viewDoctorDetails(<?= json_encode($doc) ?>)'>
                        <!-- شارة التوثيق -->
                        <?php if ($doc['is_verified']): ?>
                            <div class="verified-badge" title="طبيب موثق">
                                <i class="fas fa-check"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- شارة التوفر -->
                        <div class="availability-badge <?= $doc['is_available'] ? 'available' : 'unavailable' ?>">
                            <i class="fas fa-<?= $doc['is_available'] ? 'check-circle' : 'clock' ?>"></i>
                            <?= $doc['is_available'] ? 'متاح' : 'غير متاح' ?>
                        </div>
                        
                        <div class="doctor-avatar">
                            <?php if (!empty($doc['profile_image'])): ?>
                                <img src="../<?= $doc['profile_image'] ?>" alt="صورة الطبيب">
                            <?php else: ?>
                                <i class="fas fa-user-md"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="doctor-name">د. <?= htmlspecialchars($doc['full_name']) ?></div>
                        <div class="doctor-specialty"><?= htmlspecialchars($doc['specialties']) ?></div>
                        
                        <?php if (!empty($doc['degree'])): ?>
                            <div class="doctor-degree"><?= htmlspecialchars($doc['degree']) ?></div>
                        <?php endif; ?>
                        
                        <div class="doctor-rating">
                            <?php 
                            $rating = round($doc['avg_rating'] ?: 0);
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $rating):
                            ?>
                                <i class="fas fa-star"></i>
                            <?php elseif ($i - 0.5 <= $rating): ?>
                                <i class="fas fa-star-half-alt"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; endfor; ?>
                            <span class="rating-number">(<?= $doc['ratings_count'] ?>)</span>
                        </div>
                        
                        <div class="doctor-info-row">
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?= $doc['city'] ?: 'غير محدد' ?></span>
                        </div>
                        
                        <?php if (!empty($doc['workplace_name'])): ?>
                            <div class="doctor-info-row">
                                <i class="fas fa-hospital"></i>
                                <span><?= htmlspecialchars($doc['workplace_name']) ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($doc['consultation_fees'])): ?>
                            <div class="doctor-info-row">
                                <i class="fas fa-money-bill"></i>
                                <span><?= number_format($doc['consultation_fees'], 2) ?> دج</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- ============================================================
         النافذة الجانبية الوحيدة (تحتوي على كل المعلومات + التقييمات)
         ============================================================ -->
    <div class="side-modal" id="doctorDetailsModal">
        <div class="side-modal-header">
            <h3><i class="fas fa-user-md"></i> الملف الشخصي للطبيب</h3>
            <button class="close-side-modal" onclick="closeSideModal('doctorDetailsModal')">&times;</button>
        </div>
        <div class="side-modal-body" id="doctorDetailsContent">
            <div class="text-center">
                <i class="fas fa-spinner fa-spin fa-3x"></i>
                <p>جاري تحميل معلومات الطبيب...</p>
            </div>
        </div>
    </div>
    
    <script>
    // جميع التقييمات (من PHP)
    const allRatings = <?= json_encode($allRatings) ?>;
    
    // ============================================================
    // فتح وإغلاق النافذة الجانبية
    // ============================================================
    function openSideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    function closeSideModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }
    
    // ============================================================
    // عرض تفاصيل الطبيب (نافذة واحدة شاملة)
    // ============================================================
    function viewDoctorDetails(doctor) {
        openSideModal('doctorDetailsModal');
        
        // حساب التقييم
        let rating = Math.round(doctor.avg_rating || 0);
        let stars = '';
        for (let i = 1; i <= 5; i++) {
            if (i <= rating) {
                stars += '<i class="fas fa-star" style="color: #F4A261;"></i>';
            } else if (i - 0.5 <= rating) {
                stars += '<i class="fas fa-star-half-alt" style="color: #F4A261;"></i>';
            } else {
                stars += '<i class="far fa-star" style="color: #F4A261;"></i>';
            }
        }
        
        // تنسيق ساعات العمل
        let workingHours = (doctor.available_from && doctor.available_to) 
            ? `من ${doctor.available_from} إلى ${doctor.available_to}`
            : 'غير محدد';
        
        // تاريخ الانضمام
        let joinDate = new Date(doctor.created_at).toLocaleDateString('ar-EG');
        
        // الشارات
        let verifiedBadge = doctor.is_verified 
            ? '<span class="badge badge-success"><i class="fas fa-check-circle"></i> طبيب موثق</span>'
            : '<span class="badge badge-warning"><i class="fas fa-clock"></i> غير موثق</span>';
        
        let availableBadge = doctor.is_available
            ? '<span class="badge badge-success"><i class="fas fa-check-circle"></i> متاح الآن</span>'
            : '<span class="badge badge-danger"><i class="fas fa-clock"></i> غير متاح</span>';
        
        // جلب تقييمات هذا الطبيب
        const ratings = allRatings[doctor.id] || [];
        
        // بناء HTML للتقييمات
        let ratingsHtml = '';
        if (ratings.length > 0) {
            ratings.forEach(r => {
                let ratingStars = '';
                for (let i = 1; i <= 5; i++) {
                    ratingStars += i <= r.rating 
                        ? '<i class="fas fa-star" style="color: #F4A261;"></i>'
                        : '<i class="far fa-star" style="color: #F4A261;"></i>';
                }
                
                let patientImage = r.patient_image 
                    ? `<img src="../${r.patient_image}" style="width:100%; height:100%; object-fit:cover;">`
                    : '<i class="fas fa-user"></i>';
                
                let comment = r.comment ? `<p class="rating-comment">${r.comment}</p>` : '';
                let date = new Date(r.created_at).toLocaleDateString('ar-EG');
                
                ratingsHtml += `
                    <div class="rating-item">
                        <div class="rating-header">
                            <div class="rating-avatar">${patientImage}</div>
                            <div style="flex:1;">
                                <div class="rating-name">${r.patient_name}</div>
                                <div class="rating-date">${date}</div>
                            </div>
                        </div>
                        <div class="rating-stars">${ratingStars}</div>
                        ${comment}
                    </div>
                `;
            });
        } else {
            ratingsHtml = '<p style="text-align: center; color: var(--gray);">لا توجد تقييمات بعد</p>';
        }
        
        // المحتوى الكامل في نافذة واحدة
        document.getElementById('doctorDetailsContent').innerHTML = `
            <!-- رأس الملف الشخصي -->
            <div class="doctor-profile-header">
                <div class="doctor-profile-avatar">
                    ${doctor.profile_image 
                        ? `<img src="../${doctor.profile_image}" style="width:100%; height:100%; object-fit:cover;">`
                        : '<i class="fas fa-user-md"></i>'}
                </div>
                <div class="doctor-profile-info">
                    <h2>د. ${doctor.full_name}</h2>
                    <p><i class="fas fa-stethoscope"></i> ${doctor.specialties || 'طبيب'}</p>
                    <div class="badge-group">
                        ${verifiedBadge}
                        ${availableBadge}
                    </div>
                </div>
            </div>
            
            <!-- معلومات الاتصال -->
            <div class="info-section">
                <div class="info-section-title">
                    <i class="fas fa-address-card"></i> معلومات الاتصال
                </div>
                <div class="info-row"><span class="info-label">البريد:</span><span class="info-value">${doctor.email}</span></div>
                <div class="info-row"><span class="info-label">الهاتف:</span><span class="info-value">${doctor.phone || 'غير محدد'}</span></div>
                <div class="info-row"><span class="info-label">المدينة:</span><span class="info-value">${doctor.city || 'غير محدد'}</span></div>
            </div>
            
            <!-- المعلومات المهنية -->
            <div class="info-section">
                <div class="info-section-title"><i class="fas fa-briefcase"></i> المعلومات المهنية</div>
                <div class="info-row"><span class="info-label">التخصص:</span><span class="info-value">${doctor.specialties || 'غير محدد'}</span></div>
                ${doctor.degree ? `<div class="info-row"><span class="info-label">الدرجة:</span><span class="info-value">${doctor.degree}</span></div>` : ''}
                ${doctor.license_number ? `<div class="info-row"><span class="info-label">رقم الترخيص:</span><span class="info-value">${doctor.license_number}</span></div>` : ''}
                ${doctor.workplace_name ? `<div class="info-row"><span class="info-label">مكان العمل:</span><span class="info-value">${doctor.workplace_name}</span></div>` : ''}
                ${doctor.consultation_fees ? `<div class="info-row"><span class="info-label">رسوم الكشف:</span><span class="info-value">${doctor.consultation_fees} دج</span></div>` : ''}
            </div>
            
            <!-- ساعات العمل -->
            <div class="info-section">
                <div class="info-section-title"><i class="fas fa-clock"></i> ساعات العمل</div>
                <div class="working-hours-box">
                    <div class="info-row"><span class="info-label">أيام العمل:</span><span class="info-value">${doctor.working_days || 'غير محدد'}</span></div>
                    <div class="info-row"><span class="info-label">ساعات العمل:</span><span class="info-value">${workingHours}</span></div>
                </div>
            </div>
            
            <!-- الإحصائيات -->
            <div class="info-section">
                <div class="info-section-title"><i class="fas fa-chart-bar"></i> الإحصائيات</div>
                <div class="info-row"><span class="info-label">التقييم:</span><span class="info-value">${stars} (${doctor.avg_rating ? doctor.avg_rating.toFixed(1) : '0'} من 5)</span></div>
                <div class="info-row"><span class="info-label">عدد التقييمات:</span><span class="info-value">${doctor.ratings_count || 0}</span></div>
                <div class="info-row"><span class="info-label">عدد المرضى:</span><span class="info-value">${doctor.patients_count || 0}</span></div>
                <div class="info-row"><span class="info-label">المواعيد:</span><span class="info-value">${doctor.completed_appointments || 0}</span></div>
                <div class="info-row"><span class="info-label">انضم في:</span><span class="info-value">${joinDate}</span></div>
            </div>
            
            <!-- التقييمات -->
            <div class="info-section">
                <div class="info-section-title"><i class="fas fa-star"></i> التقييمات (${doctor.ratings_count || 0})</div>
                <div class="ratings-container">${ratingsHtml}</div>
            </div>
            
            <!-- الخريطة تحت التطوير -->
            <div class="dev-map" onclick="showDevMessage('الخريطة')">
                <i class="fas fa-map-marked-alt" style="font-size: 2rem; color: var(--warning);"></i>
                <p style="margin: 0.5rem 0;">عرض موقع العيادة على الخريطة</p>
                <span class="dev-badge">قيد التطوير</span>
            </div>
            
            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openBookAppointment(${doctor.id})" style="flex: 1;">
                    <i class="fas fa-calendar-plus"></i> حجز موعد
                </button>
                <button class="btn btn-outline" onclick="openRateDoctor(${doctor.id})" style="flex: 1;">
                    <i class="fas fa-star"></i> تقييم
                </button>
            </div>
        `;
    }
    
    // ============================================================
    // فتح نافذة حجز موعد (سيتم تنفيذها لاحقاً)
    // ============================================================
    function openBookAppointment(id) {
        showToast('سيتم إضافة خاصية حجز الموعد قريباً', 'info');
    }
    
    // ============================================================
    // فتح نافذة تقييم الطبيب (سيتم تنفيذها لاحقاً)
    // ============================================================
    function openRateDoctor(id) {
        showToast('سيتم إضافة خاصية التقييم قريباً', 'info');
    }
    
    // ============================================================
    // البحث عن الأطباء
    // ============================================================
    function searchDoctors() {
        showToast('جاري تطبيق البحث...', 'info');
    }
    
    // ============================================================
    // رسائل التطوير
    // ============================================================
    function showDevMessage(message) {
        showToast(`${message} - قيد التطوير`, 'warning');
    }
    
    // ============================================================
    // دالة showToast
    // ============================================================
    function showToast(message, type) {
        alert(message);
    }
    </script>
</body>
</html>