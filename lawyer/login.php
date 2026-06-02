<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    rateLimit('login_lawyer', 8, 300);
    $account = authenticateAccount((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), 'lawyer');
    if ($account) {
        loginUser($account);
        redirect(url('/lawyer/dashboard.php'));
    }
    flash('danger', 'อีเมลหรือรหัสผ่านทนายไม่ถูกต้อง หรือบัญชียังไม่ใช่บัญชีทนาย');
}

$pageTitle = 'เข้าสู่ระบบทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-section auth-lawyer">
    <div class="container">
        <div class="auth-shell">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-6 order-2 order-lg-1">
                    <div class="auth-side h-100">
                        <span class="legal-badge auth-badge mb-3"><i class="bi bi-person-badge"></i> Lawyer Portal</span>
                        <h1 class="auth-title">พื้นที่ทำงานสำหรับทนายที่รับเคสผ่านระบบ</h1>
                        <p class="auth-copy">เข้าสู่ระบบเพื่อจัดการโปรไฟล์ ความเชี่ยวชาญ เคสที่ถูกเสนอ นัดหมาย แชต รายได้ และรีวิวจากลูกความ</p>

                        <div class="auth-stage">
                            <div class="auth-stage-top">
                                <span>Work Queue</span>
                                <strong>จัดการงาน</strong>
                            </div>
                            <div class="auth-stage-row is-active">
                                <i class="bi bi-inboxes"></i>
                                <div>
                                    <strong>เคสที่ถูกเสนอ</strong>
                                    <span>ดูรายละเอียดและตอบรับงานใหม่</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-chat-dots"></i>
                                <div>
                                    <strong>สื่อสารกับลูกความ</strong>
                                    <span>แชตและแลกเปลี่ยนเอกสารในระบบ</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-wallet2"></i>
                                <div>
                                    <strong>รายได้และรีวิว</strong>
                                    <span>ติดตามค่าปรึกษาและคะแนนบริการ</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-meta-grid">
                            <div>
                                <span>ใช้สำหรับ</span>
                                <strong>รับงานและดูแลลูกความ</strong>
                            </div>
                            <div>
                                <span>สถานะบัญชี</span>
                                <strong>ต้องผ่านแอดมินตรวจสอบ</strong>
                            </div>
                        </div>

                        <div class="auth-requirements">
                            <h2>สิ่งที่ทนายต้องเตรียม</h2>
                            <ul class="auth-check-list">
                                <li><i class="bi bi-check2-circle"></i> เลขใบอนุญาตทนายและเอกสารยืนยัน</li>
                                <li><i class="bi bi-check2-circle"></i> หมวดกฎหมายที่เชี่ยวชาญและจังหวัดหลัก</li>
                                <li><i class="bi bi-check2-circle"></i> ค่าปรึกษา ประสบการณ์ และประวัติย่อ</li>
                                <li><i class="bi bi-check2-circle"></i> รอแอดมินอนุมัติก่อนแสดงในหน้าค้นหา</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 order-1 order-lg-2">
                    <div class="auth-card h-100">
                        <div class="auth-switch mb-4" aria-label="เลือกประเภทบัญชี">
                            <a href="<?= e(url('/user/login.php')) ?>"><i class="bi bi-person"></i> ผู้ใช้</a>
                            <a class="active" href="<?= e(url('/lawyer/login.php')) ?>"><i class="bi bi-person-badge"></i> ทนาย</a>
                        </div>

                        <div class="mb-4">
                            <span class="auth-eyebrow">Login</span>
                            <h2 class="h3 fw-bold mb-2">เข้าสู่ระบบทนาย</h2>
                            <p class="text-muted mb-0">ใช้บัญชีทนายเท่านั้น บัญชีผู้ใช้ทั่วไปจะไม่สามารถเข้าพื้นที่นี้ได้</p>
                        </div>

                        <form method="post" class="auth-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="auth-input-icon">
                                <i class="bi bi-envelope"></i>
                                <div class="form-floating auth-field">
                                    <input class="form-control" id="lawyerEmail" type="email" name="email" autocomplete="email" placeholder="lawyer@example.com" required>
                                    <label for="lawyerEmail">อีเมลทนาย</label>
                                </div>
                            </div>
                            <div class="auth-input-icon auth-password-wrap">
                                <i class="bi bi-lock"></i>
                                <div class="form-floating auth-field">
                                    <input class="form-control" id="lawyerPassword" type="password" name="password" autocomplete="current-password" placeholder="รหัสผ่าน" data-password-watch required>
                                    <label for="lawyerPassword">รหัสผ่าน</label>
                                </div>
                                <button class="auth-password-toggle" type="button" data-password-toggle="lawyerPassword" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                <div class="auth-caps-warning" data-caps-warning="lawyerPassword" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                            </div>
                            <button class="btn btn-primary btn-lg w-100 auth-submit" type="submit">
                                <i class="bi bi-briefcase me-2"></i>เข้าสู่พอร์ทัลทนาย
                            </button>
                        </form>

                        <div class="auth-footnote">
                            <span>ยังไม่มีบัญชีทนาย?</span>
                            <a href="<?= e(url('/lawyer/register-lawyer.php')) ?>">สมัครเป็นทนาย</a>
                        </div>
                        <?php if (app_config('show_demo_accounts', false)): ?>
                        <div class="auth-demo-card">
                            <div>
                                <i class="bi bi-key"></i>
                                <span>
                                    <strong>บัญชีตัวอย่าง</strong>
                                    <small>criminal.lawyer@example.com / Lawyer@1234</small>
                                </span>
                            </div>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-demo-fill data-demo-email="criminal.lawyer@example.com" data-demo-password="Lawyer@1234">เติมข้อมูล</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
