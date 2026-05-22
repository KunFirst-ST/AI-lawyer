<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';

$user = currentUser();
$subjectOptions = [
    'general' => 'สอบถามทั่วไป',
    'case_support' => 'ช่วยเหลือผู้ใช้/เคส',
    'lawyer_onboarding' => 'สมัครหรือยืนยันตัวตนทนาย',
    'payment' => 'การชำระเงินและใบเสร็จ',
    'privacy' => 'ข้อมูลส่วนบุคคลและความปลอดภัย',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $subjectKey = $_POST['subject'] ?? 'general';
    $subject = $subjectOptions[$subjectKey] ?? $subjectOptions['general'];
    $message = trim($_POST['message'] ?? '');

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10) {
        flash('danger', 'กรุณากรอกชื่อ อีเมล และรายละเอียดอย่างน้อย 10 ตัวอักษร');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $phone, $subject, $message]);
        $contactId = (int) db()->lastInsertId();

        $notify = new NotificationService();
        $adminStmt = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"');
        foreach ($adminStmt->fetchAll() as $admin) {
            $notify->create((int) $admin['id'], 'มีข้อความติดต่อใหม่', $subject . ' จาก ' . $name, 'contact');
        }

        flash('success', 'ส่งข้อความเรียบร้อย ทีมงานจะติดต่อกลับตามช่องทางที่ให้ไว้');
        redirect(url('/public/contact.php?sent=' . $contactId));
    }
}

$pageTitle = 'ติดต่อทีมงาน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="section-band">
    <div class="container">
        <div class="row g-4 align-items-start">
            <div class="col-lg-5">
                <span class="legal-badge mb-3"><i class="bi bi-headset"></i> Support</span>
                <h1 class="fw-bold mb-3">ติดต่อทีมงาน AI Lawyer</h1>
                <p class="lead text-muted">ส่งคำถามเรื่องการใช้งาน สมัครทนาย การชำระเงิน หรือประเด็นข้อมูลส่วนบุคคล ทีมงานจะตรวจในแอดมินและตอบกลับตามความเร่งด่วน</p>
                <div class="row g-3 mt-2">
                    <div class="col-sm-6">
                        <div class="stat-card h-100">
                            <div class="small-muted">เวลาทำการ</div>
                            <div class="fw-bold">จันทร์-ศุกร์ 09:00-18:00</div>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="stat-card h-100">
                            <div class="small-muted">เคสด่วน</div>
                            <div class="fw-bold">ระบุเส้นตายในข้อความ</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="app-card p-4">
                    <h2 class="h4 fw-bold mb-3">ส่งข้อความ</h2>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-md-6">
                            <label class="form-label">ชื่อ</label>
                            <input class="form-control" name="name" value="<?= e($user['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">อีเมล</label>
                            <input class="form-control" type="email" name="email" value="<?= e($user['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เบอร์โทร</label>
                            <input class="form-control" name="phone" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">หัวข้อ</label>
                            <select class="form-select" name="subject">
                                <?php foreach ($subjectOptions as $key => $label): ?>
                                    <option value="<?= e($key) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">รายละเอียด</label>
                            <textarea class="form-control" name="message" rows="6" minlength="10" required></textarea>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2 align-items-center">
                            <button class="btn btn-primary"><i class="bi bi-send me-2"></i>ส่งข้อความ</button>
                            <a class="btn btn-outline-primary" href="<?= e(url('/public/faq.php')) ?>">อ่าน FAQ</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
