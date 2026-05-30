<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SocialAuthService.php';
requireRole('admin');

$socialProviders = SocialAuthService::providerSummaries();
$pageTitle = 'Social Login';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Social Login</h1>
                        <p class="small-muted mb-0">สถานะ OAuth สำหรับบัญชีผู้ใช้ทั่วไป</p>
                    </div>
                    <span class="legal-badge"><i class="bi bi-shield-lock"></i> Environment Secrets</span>
                </div>

                <div class="social-admin-grid mb-3">
                    <?php foreach ($socialProviders as $provider): ?>
                        <div class="app-card social-admin-card">
                            <div class="social-admin-head">
                                <i class="bi <?= e($provider['icon']) ?> <?= e($provider['class']) ?>"></i>
                                <div>
                                    <h2><?= e($provider['name']) ?></h2>
                                    <span>OAuth 2.0</span>
                                </div>
                                <em class="<?= $provider['configured'] ? 'is-ready' : '' ?>"><?= $provider['configured'] ? 'พร้อมใช้งาน' : 'รอตั้งค่า' ?></em>
                            </div>
                            <label>Callback URL</label>
                            <code><?= e($provider['callback_url']) ?></code>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-card p-4">
                    <h2 class="h5 fw-bold mb-3">Environment Variables</h2>
                    <div class="social-env-list">
                        <code>GOOGLE_LOGIN_ENABLED=true</code>
                        <code>GOOGLE_CLIENT_ID=...</code>
                        <code>GOOGLE_CLIENT_SECRET=...</code>
                        <code>FACEBOOK_LOGIN_ENABLED=true</code>
                        <code>FACEBOOK_CLIENT_ID=...</code>
                        <code>FACEBOOK_CLIENT_SECRET=...</code>
                        <code>FACEBOOK_GRAPH_VERSION=v24.0</code>
                    </div>
                    <p class="small-muted mt-3 mb-0">เก็บ Client Secret ในไฟล์ .env บนเซิร์ฟเวอร์เท่านั้น ห้ามใส่ Secret ลง GitHub</p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
