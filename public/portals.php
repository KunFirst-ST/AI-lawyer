<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

$pageTitle = 'เลือกพอร์ทัล';
require_once __DIR__ . '/../includes/header.php';

$portals = [
    [
        'title' => 'ผู้ใช้',
        'text' => 'ถาม AI บันทึกเคส Match ทนาย จองปรึกษา ชำระเงิน และรีวิวหลังปิดงาน',
        'icon' => 'person',
        'login' => url('/user/login.php'),
        'register' => url('/public/register.php'),
        'register_label' => 'สมัครสมาชิก',
        'requirements' => ['อีเมลและรหัสผ่าน', 'เบอร์โทรสำหรับนัดหมาย', 'ข้อมูลเคสที่ต้องการปรึกษา'],
    ],
    [
        'title' => 'ทนาย',
        'text' => 'จัดการโปรไฟล์ ความเชี่ยวชาญ เคสที่ถูกเสนอ Booking แชต รายได้ และรีวิว',
        'icon' => 'person-badge',
        'login' => url('/lawyer/login.php'),
        'register' => url('/lawyer/register-lawyer.php'),
        'register_label' => 'สมัครเป็นทนาย',
        'requirements' => ['ใบอนุญาตทนาย', 'เอกสารยืนยันตัวตน', 'หมวดกฎหมายและค่าปรึกษา'],
    ],
    [
        'title' => 'แอดมิน',
        'text' => 'ดูแลผู้ใช้ อนุมัติทนาย เคส Payment Commission รายงาน ข้อความติดต่อ และตั้งค่าระบบ',
        'icon' => 'shield-lock',
        'login' => url('/admin/login.php'),
        'register' => null,
        'register_label' => '',
        'requirements' => ['บัญชีที่ผู้ดูแลสร้างให้', 'สิทธิ์ role แอดมิน', 'ไม่มีหน้าสมัครสาธารณะ'],
    ],
];
?>
<section class="section-band">
    <div class="container">
        <div class="row justify-content-center text-center mb-4">
            <div class="col-lg-7">
                <span class="legal-badge mb-3"><i class="bi bi-grid"></i> Portal Selection</span>
                <h1 class="fw-bold">เลือกพอร์ทัลสำหรับเข้าใช้งาน</h1>
                <p class="text-muted mb-0">ระบบแยกพื้นที่ทำงานของผู้ใช้ ทนาย และแอดมิน เพื่อให้ข้อมูล สิทธิ์ และขั้นตอนการทำงานไม่ปนกัน</p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach ($portals as $portal): ?>
                <div class="col-md-4">
                    <div class="app-card portal-card p-4 h-100">
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
                            <a class="btn btn-primary" href="<?= e($portal['login']) ?>">เข้าสู่พอร์ทัล<?= e($portal['title']) ?></a>
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
