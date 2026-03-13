<?php
/**
 * index.php - الصفحة الرئيسية
 * CONNECT+ - الإصدار 2.0 النهائي
 */

session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';

// إذا كان المستخدم مسجلاً بالفعل، نوجهه للصفحة المناسبة
if (isset($_SESSION['user_id'])) {
    $user = getCurrentUser($pdo);
    if ($user) {
        switch ($user['role']) {
            case 'patient':
                header('Location: patient/dashboard.php');
                exit;
            case 'doctor':
                header('Location: doctor/dashboard.php');
                exit;
            case 'pharmacist':
                header('Location: pharmacist/dashboard.php');
                exit;
            case 'admin':
                header('Location: admin/dashboard.php');
                exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CONNECT+ - المنصة الصحية المتكاملة</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Cairo -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }
        
        :root {
            --primary: #2A9D8F;
            --primary-dark: #21867A;
            --primary-light: #6CD4C5;
            --secondary: #264653;
            --accent: #E9C46A;
            --success: #2A9D8F;
            --warning: #F4A261;
            --danger: #E76F51;
            --light: #F8F9FA;
            --dark: #212529;
            --gray: #6C757D;
        }
        
        body {
            background: #f8f9fa;
            color: var(--dark);
            line-height: 1.6;
        }
        
        /* شريط التنقل */
        .navbar {
            background: white;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            padding: 1rem 5%;
            position: sticky;
            top: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            text-decoration: none;
        }
        
        .logo i {
            font-size: 2.2rem;
            color: var(--accent);
        }
        
        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: color 0.3s;
            position: relative;
        }
        
        .nav-links a:hover {
            color: var(--primary);
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
        }
        
        .nav-links a:hover::after {
            width: 100%;
        }
        
        .nav-buttons {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.6rem 1.5rem;
            border-radius: 30px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-outline {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }
        
        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 157, 143, 0.3);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: 2px solid var(--primary);
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 157, 143, 0.3);
        }
        
        .btn-light {
            background: white;
            color: var(--primary);
            border: 2px solid white;
        }
        
        .btn-light:hover {
            background: transparent;
            color: white;
            transform: translateY(-2px);
        }
        
        /* القسم الرئيسي */
        .hero {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            padding: 5rem 5%;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: rotate 60s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            font-weight: 800;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        /* المميزات */
        .features {
            padding: 5rem 5%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-title {
            text-align: center;
            font-size: 2.5rem;
            color: var(--secondary);
            margin-bottom: 3rem;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary);
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .feature-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: rgba(42, 157, 143, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2rem;
            color: var(--primary);
        }
        
        .feature-card h3 {
            margin-bottom: 1rem;
            color: var(--secondary);
        }
        
        /* إحصائيات */
        .stats {
            background: var(--secondary);
            color: white;
            padding: 4rem 5%;
            text-align: center;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 2rem auto 0;
        }
        
        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--accent);
        }
        
        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        /* كيف تعمل */
        .how-it-works {
            padding: 5rem 5%;
            background: var(--light);
        }
        
        .steps {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            max-width: 1200px;
            margin: 3rem auto 0;
        }
        
        .step {
            flex: 1;
            min-width: 250px;
            max-width: 300px;
            text-align: center;
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 1rem;
        }
        
        /* الأدوار */
        .roles {
            padding: 5rem 5%;
        }
        
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 3rem auto 0;
        }
        
        .role-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        
        .role-card:hover {
            transform: translateY(-10px);
        }
        
        .role-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 3rem;
            color: white;
        }
        
        .role-card h3 {
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .role-card ul {
            text-align: right;
            list-style-position: inside;
            margin: 1rem 0;
        }
        
        .role-card li {
            margin-bottom: 0.5rem;
            color: var(--gray);
        }
        
        /* تحت التطوير */
        .development {
            background: linear-gradient(135deg, var(--warning), var(--danger));
            color: white;
            padding: 4rem 5%;
            text-align: center;
        }
        
        .dev-grid {
            display: flex;
            justify-content: center;
            gap: 3rem;
            flex-wrap: wrap;
            margin-top: 3rem;
        }
        
        .dev-item {
            background: rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 2rem;
            width: 200px;
            backdrop-filter: blur(10px);
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .dev-item:hover {
            transform: translateY(-10px);
            background: rgba(255,255,255,0.2);
        }
        
        .dev-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .dev-badge {
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 0.3rem 1rem;
            border-radius: 30px;
            display: inline-block;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        /* تذييل الصفحة */
        .footer {
            background: var(--dark);
            color: white;
            padding: 3rem 5%;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }
        
        .footer-logo {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 0.5rem;
        }
        
        .footer-links a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .footer-bottom {
            max-width: 1200px;
            margin: 2rem auto 0;
            padding-top: 2rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            text-align: center;
            color: rgba(255,255,255,0.6);
        }
        
        /* نافذة تحت التطوير */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 30px;
            width: 90%;
            max-width: 400px;
            padding: 2rem;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            animation: slideDown 0.3s ease;
            text-align: center;
        }
        
        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .modal-header h3 {
            color: var(--primary);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
        }
        
        .modal-body i {
            font-size: 4rem;
            color: var(--warning);
            margin: 1rem 0;
        }
        
        /* تصميم متجاوب */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .steps {
                flex-direction: column;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <!-- شريط التنقل -->
    <nav class="navbar">
        <a href="index.php" class="logo">
            <i class="fas fa-plus-circle"></i>
            <span>CONNECT+</span>
        </a>
        
        <ul class="nav-links">
            <li><a href="#features">المميزات</a></li>
            <li><a href="#how">كيف تعمل</a></li>
            <li><a href="#roles">الأدوار</a></li>
            <li><a href="#development">تحت التطوير</a></li>
            <li><a href="#contact">اتصل بنا</a></li>
        </ul>
        
        <div class="nav-buttons">
            <a href="login.php" class="btn btn-outline">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </a>
            <a href="signup.php" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> إنشاء حساب
            </a>
        </div>
    </nav>
    
    <!-- القسم الرئيسي -->
    <section class="hero">
        <div class="hero-content">
            <h1>منصة CONNECT+ الصحية</h1>
            <p>أول منصة جزائرية متكاملة تربط المرضى بالأطباء والصيادلة بكل أمان وسهولة</p>
            <div class="hero-buttons">
                <a href="signup.php" class="btn btn-light">
                    <i class="fas fa-rocket"></i> ابدأ الآن مجاناً
                </a>
                <a href="#features" class="btn btn-outline" style="border-color: white; color: white;">
                    <i class="fas fa-play-circle"></i> تعرف أكثر
                </a>
            </div>
        </div>
    </section>
    
    <!-- المميزات -->
    <section id="features" class="features">
        <h2 class="section-title">مميزات المنصة</h2>
        
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>ربط مع الأطباء</h3>
                <p>تواصل مباشر مع أفضل الأطباء في جميع التخصصات، استشارات فورية وآمنة</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-pills"></i>
                </div>
                <h3>صيدليات متكاملة</h3>
                <p>ابحث عن الأدوية، تحقق من التوفر، واستلم طلباتك من أقرب صيدلية</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3>إدارة المواعيد</h3>
                <p>حجز وتنظيم المواعيد مع تذكيرات ذكية، وإشعارات قبل الموعد بوقت كاف</p>
            </div>
            
            <div class="feature-card">
                <div class="feature-icon">
                    <i class="fas fa-file-medical"></i>
                </div>
                <h3>ملف طبي موحد</h3>
                <p>جميع سجلاتك الصحية في مكان واحد، متاحة لك ولمن تسمح لهم من الأطباء</p>
            </div>
        </div>
    </section>
    
    <!-- إحصائيات -->
    <section class="stats">
        <h2 style="font-size: 2rem; margin-bottom: 2rem;">أرقام توثق نجاحنا</h2>
        <div class="stats-grid">
            <div>
                <div class="stat-number">15,000+</div>
                <div class="stat-label">مستخدم نشط</div>
            </div>
            <div>
                <div class="stat-number">500+</div>
                <div class="stat-label">طبيب</div>
            </div>
            <div>
                <div class="stat-number">200+</div>
                <div class="stat-label">صيدلية</div>
            </div>
            <div>
                <div class="stat-number">10,000+</div>
                <div class="stat-label">موعد</div>
            </div>
        </div>
    </section>
    
    <!-- كيف تعمل -->
    <section id="how" class="how-it-works">
        <h2 class="section-title">كيف تعمل المنصة</h2>
        
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <h3>إنشاء حساب</h3>
                <p>سجل كمريض، طبيب، أو صيدلي وأدخل بياناتك والمستندات المطلوبة</p>
            </div>
            
            <div class="step">
                <div class="step-number">2</div>
                <h3>توثيق الحساب</h3>
                <p>انتظر موافقة الإدارة على مستنداتك (يتم خلال 24 ساعة)</p>
            </div>
            
            <div class="step">
                <div class="step-number">3</div>
                <h3>استخدم الخدمات</h3>
                <p>احجز المواعيد، تواصل مع الأطباء، اطلب الأدوية، وتابع صحتك</p>
            </div>
        </div>
    </section>
    
    <!-- الأدوار -->
    <section id="roles" class="roles">
        <h2 class="section-title">لمن تناسب المنصة؟</h2>
        
        <div class="roles-grid">
            <div class="role-card">
                <div class="role-icon">
                    <i class="fas fa-user"></i>
                </div>
                <h3>للمرضى</h3>
                <ul>
                    <li>حجز مواعيد مع الأطباء</li>
                    <li>متابعة الأدوية والوصفات</li>
                    <li>طلب أدوية من الصيدليات</li>
                    <li>استشارات طبية عبر الرسائل</li>
                    <li>سجل طبي شامل</li>
                </ul>
            </div>
            
            <div class="role-card">
                <div class="role-icon">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3>للأطباء</h3>
                <ul>
                    <li>إدارة المواعيد والمرضى</li>
                    <li>إصدار وصفات إلكترونية</li>
                    <li>متابعة الحالات</li>
                    <li>سجل طبي للمرضى</li>
                    <li>تواصل مباشر مع المرضى</li>
                </ul>
            </div>
            
            <div class="role-card">
                <div class="role-icon">
                    <i class="fas fa-store"></i>
                </div>
                <h3>للصيادلة</h3>
                <ul>
                    <li>إدارة المخزون</li>
                    <li>معالجة طلبات الأدوية</li>
                    <li>تحديث الأسعار</li>
                    <li>تنبيهات نفاد المخزون</li>
                    <li>تواصل مع المرضى</li>
                </ul>
            </div>
        </div>
    </section>
    
    <!-- تحت التطوير -->
    <section id="development" class="development">
        <h2 style="font-size: 2rem; margin-bottom: 1rem;">ميزات قيد التطوير</h2>
        <p style="font-size: 1.2rem; opacity: 0.9;">قريباً في التحديثات القادمة</p>
        
        <div class="dev-grid">
            <div class="dev-item" onclick="showDevMessage('NFC')">
                <div class="dev-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <h3>NFC</h3>
                <p>مسح البطاقة الصحية</p>
                <span class="dev-badge">قريباً</span>
            </div>
            
            <div class="dev-item" onclick="showDevMessage('البصمة')">
                <div class="dev-icon">
                    <i class="fas fa-fingerprint"></i>
                </div>
                <h3>بصمة</h3>
                <p>تسجيل الدخول ببصمة الإصبع</p>
                <span class="dev-badge">قريباً</span>
            </div>
            
            <div class="dev-item" onclick="showDevMessage('الخريطة')">
                <div class="dev-icon">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <h3>خريطة</h3>
                <p>تحديد موقع الأطباء والصيدليات</p>
                <span class="dev-badge">قريباً</span>
            </div>
            
            <div class="dev-item" onclick="showDevMessage('الذكاء الاصطناعي')">
                <div class="dev-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <h3>AI</h3>
                <p>مساعد ذكي للاستشارات</p>
                <span class="dev-badge">قريباً</span>
            </div>
        </div>
    </section>
    
    <!-- تذييل الصفحة -->
    <footer id="contact" class="footer">
        <div class="footer-content">
            <div>
                <div class="footer-logo">CONNECT+</div>
                <p style="margin: 1rem 0; color: rgba(255,255,255,0.8);">منصة صحية متكاملة تربط المرضى بالأطباء والصيادلة في الجزائر.</p>
                <div style="display: flex; gap: 1rem;">
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-facebook"></i></a>
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-twitter"></i></a>
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-instagram"></i></a>
                    <a href="#" style="color: white; font-size: 1.5rem;"><i class="fab fa-linkedin"></i></a>
                </div>
            </div>
            
            <div>
                <h4 style="margin-bottom: 1rem;">روابط سريعة</h4>
                <ul class="footer-links">
                    <li><a href="#features">المميزات</a></li>
                    <li><a href="#how">كيف تعمل</a></li>
                    <li><a href="#roles">الأدوار</a></li>
                    <li><a href="#development">تحت التطوير</a></li>
                </ul>
            </div>
            
            <div>
                <h4 style="margin-bottom: 1rem;">الدعم</h4>
                <ul class="footer-links">
                    <li><a href="faq.php">الأسئلة الشائعة</a></li>
                    <li><a href="contact.php">اتصل بنا</a></li>
                    <li><a href="terms.php">شروط الاستخدام</a></li>
                    <li><a href="privacy.php">سياسة الخصوصية</a></li>
                </ul>
            </div>
            
            <div>
                <h4 style="margin-bottom: 1rem;">معلومات الاتصال</h4>
                <ul class="footer-links">
                    <li><i class="fas fa-phone"></i> 0550 00 00 00</li>
                    <li><i class="fas fa-envelope"></i> contact@connectplus.dz</li>
                    <li><i class="fas fa-map-marker-alt"></i> الجزائر العاصمة، الجزائر</li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <p>© 2026 CONNECT+ - جميع الحقوق محفوظة</p>
        </div>
    </footer>
    
    <!-- نافذة تحت التطوير -->
    <div class="modal" id="devModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tools" style="color: var(--warning);"></i> تحت التطوير</h3>
                <button class="close-modal" onclick="closeModal('devModal')">&times;</button>
            </div>
            <div class="modal-body">
                <i class="fas fa-code-branch"></i>
                <h2 id="devFeature">هذه الميزة</h2>
                <p style="color: var(--gray); margin: 1rem 0;">قيد التطوير وستتوفر قريباً في التحديث القادم</p>
                <button class="btn btn-primary" onclick="closeModal('devModal')" style="width: 100%;">موافق</button>
            </div>
        </div>
    </div>
    
    <script>
    function showDevMessage(feature) {
        document.getElementById('devFeature').textContent = feature;
        openModal('devModal');
    }
    
    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    </script>
</body>
</html>