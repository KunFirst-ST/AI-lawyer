<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

$pageTitle = 'เลือกพื้นที่ใช้งาน';
require_once __DIR__ . '/../includes/header.php';

$portals = [
    [
        'title' => 'ผู้ใช้',
        'tone' => 'member',
        'text' => 'เล่าเรื่องให้ผู้ช่วย AI จัดประเด็น บันทึกเคส ค้นหาทนาย จองปรึกษา ชำระเงิน และติดตามงานจนจบ',
        'icon' => 'person',
        'login' => url('/user/login.php'),
        'register' => url('/public/register.php'),
        'register_label' => 'สมัครสมาชิก',
        'requirements' => ['อีเมลและรหัสผ่าน', 'เบอร์โทรสำหรับนัดหมาย', 'ข้อมูลเคสที่ต้องการปรึกษา'],
    ],
    [
        'title' => 'ทนาย',
        'tone' => 'lawyer',
        'text' => 'ดูแลโปรไฟล์ รับคำขอนัดหมาย แชตกับลูกความ ติดตามรายได้ และจัดการรีวิวหลังให้คำปรึกษา',
        'icon' => 'person-badge',
        'login' => url('/lawyer/login.php'),
        'register' => url('/lawyer/register-lawyer.php'),
        'register_label' => 'สมัครเป็นทนาย',
        'requirements' => ['ใบอนุญาตทนาย', 'เอกสารยืนยันตัวตน', 'หมวดกฎหมายและค่าปรึกษา'],
    ],
];
?>
<section class="section-band portal-page">
    <div class="container">
        <div class="row justify-content-center text-center mb-4">
            <div class="col-lg-7">
                <span class="legal-badge mb-3"><i class="bi bi-grid"></i> เลือกพื้นที่ใช้งาน</span>
                <h1 class="fw-bold">เข้าสู่ระบบตามบทบาทของคุณ</h1>
                <p class="text-muted mb-0">ผู้ใช้และทนายมีขั้นตอนทำงานต่างกัน ระบบจึงแยกพื้นที่ใช้งานให้ชัดเจน เพื่อให้ข้อมูลและสิทธิ์เข้าถึงถูกต้อง</p>
            </div>
        </div>
        <div class="row g-3 portal-grid">
            <?php foreach ($portals as $portal): ?>
                <div class="col-md-6">
                    <div class="app-card portal-card portal-card-<?= e($portal['tone']) ?> p-4 h-100">
                        <div class="portal-icon mb-3"><i class="bi bi-<?= e($portal['icon']) ?>"></i></div>
                        <h2 class="h4 fw-bold"><?= e($portal['title']) ?></h2>
                        <p class="text-muted"><?= e($portal['text']) ?></p>
                        <div class="portal-requirements">
                            <div class="fw-semibold mb-2">สิ่งที่จำเป็น</div>
                            <?php foreach ($portal['requirements'] as $requirement): ?>
                                <div><i class="bi bi-check2-circle"></i> <?= e($requirement) ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="d-grid gap-2 mt-auto">
                            <a class="btn btn-primary" href="<?= e($portal['login']) ?>">เข้าสู่ระบบ<?= e($portal['title']) ?></a>
                            <?php if ($portal['register']): ?>
                                <a class="btn btn-outline-primary" href="<?= e($portal['register']) ?>"><?= e($portal['register_label']) ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
