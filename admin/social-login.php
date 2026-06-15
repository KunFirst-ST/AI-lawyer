<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SocialAuthService.php';
requireRole('admin');

$socialProviders = SocialAuthService::providerSummaries();
$pageTitle = 'เข้าสู่ระบบด้วย Google';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">เข้าสู่ระบบด้วย Google</h1>
                        <p class="small-muted mb-0">สถานะการเชื่อมต่อบัญชี Google สำหรับสมาชิกทั่วไป</p>
                    </div>
                    <span class="legal-badge"><i class="bi bi-shield-lock"></i> ตั้งค่าจากไฟล์ .env</span>
                </div>

                <div class="social-admin-grid mb-3">
                    <?php foreach ($socialProviders as $provider): ?>
                        <div class="app-card social-admin-card">
                            <div class="social-admin-head">
                                <i class="bi <?= e($provider['icon']) ?> <?= e($provider['class']) ?>"></i>
                                <div>
                                    <h2><?= e($provider['name']) ?></h2>
                                    <span>เข้าสู่ระบบอย่างปลอดภัย</span>
                                </div>
                                <em class="<?= $provider['configured'] ? 'is-ready' : '' ?>"><?= $provider['configured'] ? 'พร้อมใช้งาน' : 'รอตั้งค่า' ?></em>
                            </div>
                            <label>ลิงก์เชื่อมต่อกลับ</label>
                            <code><?= e($provider['callback_url']) ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-card p-4">
                    <h2 class="h5 fw-bold mb-3">ค่าที่ต้องตั้งบนเซิร์ฟเวอร์</h2>
                    <div class="social-env-list">
                        <code>GOOGLE_LOGIN_ENABLED=true</code>
                        <code>GOOGLE_CLIENT_ID=...</code>
                        <code>GOOGLE_CLIENT_SECRET=...</code>
                    </div>
                    <p class="small-muted mt-3 mb-0">เก็บรหัสลับไว้ในไฟล์ .env บนเซิร์ฟเวอร์เท่านั้น และไม่ควรบันทึกลง GitHub</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
