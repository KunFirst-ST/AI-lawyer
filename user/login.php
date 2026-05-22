<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

if (isset($_GET['registered'])) {
    flash('success', 'สมัครสมาชิกสำเร็จ กรุณาเข้าสู่ระบบผู้ใช้');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $account = authenticateAccount((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), 'user');
    if ($account) {
        loginUser($account);
        redirect(url('/user/ai-chat.php'));
    }
    flash('danger', 'อีเมลหรือรหัสผ่านผู้ใช้ไม่ถูกต้อง');
}

$pageTitle = 'เข้าสู่ระบบผู้ใช้';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-section auth-user">
    <div class="container">
        <div class="auth-shell">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-6 order-2 order-lg-1">
                    <div class="auth-side h-100">
                        <span class="legal-badge auth-badge mb-3"><i class="bi bi-person"></i> User Portal</span>
                        <h1 class="auth-title">พื้นที่สำหรับผู้ใช้ที่ต้องการเริ่มเคสกฎหมาย</h1>
                        <p class="auth-copy">เข้าสู่ระบบเพื่อคุยกับ AI, บันทึกเคส, ค้นหาทนายที่เหมาะกับปัญหา และติดตามงานทั้งหมดในที่เดียว</p>

                        <div class="auth-stage">
                            <div class="auth-stage-top">
                                <span>Case Flow</span>
                                <strong>พร้อมเริ่ม</strong>
                            </div>
                            <div class="auth-stage-row is-active">
                                <i class="bi bi-stars"></i>
                                <div>
                                    <strong>AI วิเคราะห์เบื้องต้น</strong>
                                    <span>สรุปประเด็นและระดับความเร่งด่วน</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-person-check"></i>
                                <div>
                                    <strong>Match ทนาย</strong>
                                    <span>เทียบหมวดกฎหมาย จังหวัด และประสบการณ์</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-calendar2-check"></i>
                                <div>
                                    <strong>ติดตามงาน</strong>
                                    <span>จองนัด แชต ชำระเงิน และรีวิว</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-meta-grid">
                            <div>
                                <span>ใช้สำหรับ</span>
                                <strong>ปรึกษาและจองทนาย</strong>
                            </div>
                            <div>
                                <span>สิทธิ์เข้าถึง</span>
                                <strong>เฉพาะเคสของตนเอง</strong>
                            </div>
                        </div>

                        <div class="auth-requirements">
                            <h2>สิ่งที่ผู้ใช้ทำได้</h2>
                            <ul class="auth-check-list">
                                <li><i class="bi bi-check2-circle"></i> ถาม AI เพื่อประเมินปัญหาเบื้องต้น</li>
                                <li><i class="bi bi-check2-circle"></i> สร้างเคสและรับการ Match ทนาย</li>
                                <li><i class="bi bi-check2-circle"></i> จองนัดหมาย อัปโหลดสลิป และติดตามสถานะ</li>
                                <li><i class="bi bi-check2-circle"></i> แชตกับทนายและรีวิวหลังจบงาน</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 order-1 order-lg-2">
                    <div class="auth-card h-100">
                        <div class="auth-switch mb-4" aria-label="เลือกประเภทบัญชี">
                            <a class="active" href="<?= e(url('/user/login.php')) ?>"><i class="bi bi-person"></i> ผู้ใช้</a>
                            <a href="<?= e(url('/lawyer/login.php')) ?>"><i class="bi bi-person-badge"></i> ทนาย</a>
                            <a href="<?= e(url('/admin/login.php')) ?>"><i class="bi bi-shield-lock"></i> แอดมิน</a>
                        </div>

                        <div class="mb-4">
                            <span class="auth-eyebrow">Login</span>
                            <h2 class="h3 fw-bold mb-2">เข้าสู่ระบบผู้ใช้</h2>
                            <p class="text-muted mb-0">ใช้บัญชีผู้ใช้ทั่วไปเท่านั้น หากเป็นทนายหรือแอดมินให้เลือกพอร์ทัลด้านบน</p>
                        </div>

                        <form method="post" class="auth-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="auth-input-icon">
                                <i class="bi bi-envelope"></i>
                                <div class="form-floating auth-field">
                                    <input class="form-control" id="userEmail" type="email" name="email" autocomplete="email" placeholder="name@example.com" required>
                                    <label for="userEmail">อีเมลผู้ใช้</label>
                                </div>
                            </div>
                            <div class="auth-input-icon auth-password-wrap">
                                <i class="bi bi-lock"></i>
                                <div class="form-floating auth-field">
                                    <input class="form-control" id="userPassword" type="password" name="password" autocomplete="current-password" placeholder="รหัสผ่าน" data-password-watch required>
                                    <label for="userPassword">รหัสผ่าน</label>
                                </div>
                                <button class="auth-password-toggle" type="button" data-password-toggle="userPassword" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                <div class="auth-caps-warning" data-caps-warning="userPassword" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                            </div>
                            <button class="btn btn-primary btn-lg w-100 auth-submit" type="submit">
                                <i class="bi bi-box-arrow-in-right me-2"></i>เข้าสู่ระบบผู้ใช้
                            </button>
                        </form>

                        <div class="auth-footnote">
                            <span>ยังไม่มีบัญชีผู้ใช้?</span>
                            <a href="<?= e(url('/public/register.php')) ?>">สมัครสมาชิกผู้ใช้</a>
                        </div>
                        <div class="auth-demo-card">
                            <div>
                                <i class="bi bi-key"></i>
                                <span>
                                    <strong>บัญชีตัวอย่าง</strong>
                                    <small>user@example.com / User@1234</small>
                                </span>
                            </div>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-demo-fill data-demo-email="user@example.com" data-demo-password="User@1234">เติมข้อมูล</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
