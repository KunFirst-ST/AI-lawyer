<?php
require_once __DIR__ . '/../includes/auth.php';

if (currentUser()) {
    redirect(dashboardPathForRole(currentUser()['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $admin = authenticateAccount((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''), 'admin');
    if ($admin) {
        loginUser($admin);
        redirect(url('/admin/dashboard.php'));
    }
    flash('danger', 'อีเมลหรือรหัสผ่านแอดมินไม่ถูกต้อง');
}

$pageTitle = 'เข้าสู่ระบบแอดมิน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="auth-section auth-admin">
    <div class="container">
        <div class="auth-shell">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-6 order-2 order-lg-1">
                    <div class="auth-side h-100">
                        <span class="legal-badge auth-badge mb-3"><i class="bi bi-shield-lock"></i> Admin Portal</span>
                        <h1 class="auth-title">ศูนย์ควบคุมระบบสำหรับผู้ดูแลเท่านั้น</h1>
                        <p class="auth-copy">พอร์ทัลนี้ใช้ตรวจสอบทนาย จัดการผู้ใช้ เคส การชำระเงิน ค่าคอมมิชชั่น รายงาน และตั้งค่าระบบ AI</p>

                        <div class="auth-stage">
                            <div class="auth-stage-top">
                                <span>Control Center</span>
                                <strong>ระบบรวมศูนย์</strong>
                            </div>
                            <div class="auth-stage-row is-active">
                                <i class="bi bi-person-vcard"></i>
                                <div>
                                    <strong>ตรวจสอบทนาย</strong>
                                    <span>อนุมัติเอกสารและสถานะโปรไฟล์</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-credit-card"></i>
                                <div>
                                    <strong>การชำระเงิน</strong>
                                    <span>ตรวจหลักฐานและค่าคอมมิชชั่น</span>
                                </div>
                            </div>
                            <div class="auth-stage-row">
                                <i class="bi bi-graph-up-arrow"></i>
                                <div>
                                    <strong>รายงานระบบ</strong>
                                    <span>ดูภาพรวมผู้ใช้ เคส และรายได้</span>
                                </div>
                            </div>
                        </div>

                        <div class="auth-meta-grid">
                            <div>
                                <span>ใช้สำหรับ</span>
                                <strong>ดูแลระบบทั้งหมด</strong>
                            </div>
                            <div>
                                <span>การสมัคร</span>
                                <strong>สร้างบัญชีโดยผู้ดูแลเท่านั้น</strong>
                            </div>
                        </div>

                        <div class="auth-requirements">
                            <h2>สิทธิ์และข้อกำหนดแอดมิน</h2>
                            <ul class="auth-check-list">
                                <li><i class="bi bi-check2-circle"></i> อนุมัติหรือระงับบัญชีทนาย</li>
                                <li><i class="bi bi-check2-circle"></i> ตรวจสอบการชำระเงินและค่าคอมมิชชั่น</li>
                                <li><i class="bi bi-check2-circle"></i> ดูแลหมวดกฎหมาย รายงาน และข้อความติดต่อ</li>
                                <li><i class="bi bi-check2-circle"></i> ไม่มีหน้าสมัครสาธารณะสำหรับแอดมิน</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 order-1 order-lg-2">
                    <div class="auth-card h-100">
                        <div class="auth-switch mb-4" aria-label="เลือกประเภทบัญชี">
                            <a href="<?= e(url('/user/login.php')) ?>"><i class="bi bi-person"></i> ผู้ใช้</a>
                            <a href="<?= e(url('/lawyer/login.php')) ?>"><i class="bi bi-person-badge"></i> ทนาย</a>
                            <a class="active" href="<?= e(url('/admin/login.php')) ?>"><i class="bi bi-shield-lock"></i> แอดมิน</a>
                        </div>

                        <div class="mb-4">
                            <span class="auth-eyebrow">Secure Login</span>
                            <h2 class="h3 fw-bold mb-2">เข้าสู่ระบบแอดมิน</h2>
                            <p class="text-muted mb-0">เฉพาะบัญชี role แอดมินเท่านั้น ระบบจะแยกออกจากบัญชีผู้ใช้และทนายโดยสมบูรณ์</p>
                        </div>

                        <div class="auth-lock-note mb-3">
                            <i class="bi bi-lock-fill"></i>
                            <span>แนะนำให้ออกจากระบบทุกครั้งหลังดูแลข้อมูลสำคัญ</span>
                        </div>

                        <form method="post" class="auth-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <div class="auth-input-icon">
                                <i class="bi bi-envelope"></i>
                                <div class="form-floating auth-field">
                                    <input class="form-control" id="adminEmail" type="email" name="email" autocomplete="email" placeholder="admin@example.com" required>
                                    <label for="adminEmail">อีเมลแอดมิน</label>
                                </div>
                            </div>
                            <div class="auth-input-icon auth-password-wrap">
                                <i class="bi bi-lock"></i>
                                <div class="form-floating auth-field">
                                    <input class="form-control" id="adminPassword" type="password" name="password" autocomplete="current-password" placeholder="รหัสผ่าน" data-password-watch required>
                                    <label for="adminPassword">รหัสผ่าน</label>
                                </div>
                                <button class="auth-password-toggle" type="button" data-password-toggle="adminPassword" aria-label="แสดงรหัสผ่าน"><i class="bi bi-eye"></i></button>
                                <div class="auth-caps-warning" data-caps-warning="adminPassword" hidden><i class="bi bi-exclamation-triangle"></i> Caps Lock เปิดอยู่</div>
                            </div>
                            <button class="btn btn-primary btn-lg w-100 auth-submit" type="submit">
                                <i class="bi bi-shield-check me-2"></i>เข้าสู่ระบบแอดมิน
                            </button>
                        </form>

                        <div class="auth-footnote">
                            <a href="<?= e(url('/public/portals.php')) ?>">กลับไปเลือกพอร์ทัล</a>
                        </div>
                        <div class="auth-demo-card">
                            <div>
                                <i class="bi bi-key"></i>
                                <span>
                                    <strong>บัญชีตัวอย่าง</strong>
                                    <small>admin@example.com / Admin@1234</small>
                                </span>
                            </div>
                            <button class="btn btn-outline-primary btn-sm" type="button" data-demo-fill data-demo-email="admin@example.com" data-demo-password="Admin@1234">เติมข้อมูล</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
