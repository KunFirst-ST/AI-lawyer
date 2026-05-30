<?php
require_once __DIR__ . '/../services/SocialAuthService.php';
$socialProviders = SocialAuthService::providerSummaries();
?>
<div class="auth-social" aria-label="เข้าสู่ระบบด้วยบัญชีภายนอก">
    <div class="auth-divider"><span>หรือใช้บัญชีเดิม</span></div>
    <div class="auth-social-grid">
        <?php foreach ($socialProviders as $provider): ?>
            <a class="auth-social-button <?= e($provider['class']) ?><?= $provider['configured'] ? '' : ' is-pending' ?>" href="<?= e($provider['start_url']) ?>">
                <i class="bi <?= e($provider['icon']) ?>"></i>
                <span><?= e($provider['name']) ?></span>
                <?php if (!$provider['configured']): ?>
                    <em>รอตั้งค่า</em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
