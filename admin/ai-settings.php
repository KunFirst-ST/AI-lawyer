<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$aiConfig = require __DIR__ . '/../config/ai.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    upsertSetting('legal_disclaimer', trim($_POST['legal_disclaimer'] ?? ''));
    upsertSetting('auto_match_after_consent', isset($_POST['auto_match_after_consent']) ? '1' : '0');
    upsertSetting('commission_percent', (string) (float) ($_POST['commission_percent'] ?? 20));
    upsertSetting('bank_account_name', trim($_POST['bank_account_name'] ?? ''));
    upsertSetting('bank_account_number', trim($_POST['bank_account_number'] ?? ''));
    upsertSetting('promptpay_id', trim($_POST['promptpay_id'] ?? ''));
    flash('success', 'บันทึก AI Settings แล้ว');
    redirect(url('/admin/ai-settings.php'));
}

$pageTitle = 'AI Settings';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">AI Settings</h1>
                <div class="app-card p-4 mb-3">
                    <h2 class="h5 fw-bold">System Prompt</h2>
                    <textarea class="form-control" rows="14" readonly><?= e($aiConfig['system_prompt']) ?></textarea>
                    <div class="small-muted mt-2">API key แยกใน config/ai.php ผ่านตัวแปร OPENAI_API_KEY</div>
                </div>
                <div class="app-card p-4">
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-12"><label class="form-label">ข้อความเตือน</label><textarea class="form-control" name="legal_disclaimer" rows="3"><?= e(setting('legal_disclaimer', '')) ?></textarea></div>
                        <div class="col-md-4"><label class="form-label">Commission %</label><input class="form-control" type="number" step="0.01" name="commission_percent" value="<?= e(setting('commission_percent', '20')) ?>"></div>
                        <div class="col-md-8 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="auto_match_after_consent" id="autoMatch" <?= setting('auto_match_after_consent', '1') === '1' ? 'checked' : '' ?>><label class="form-check-label" for="autoMatch">เปิด Auto Match หลังผู้ใช้ยินยอม</label></div></div>
                        <div class="col-md-4"><label class="form-label">ชื่อบัญชี</label><input class="form-control" name="bank_account_name" value="<?= e(setting('bank_account_name', '')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">เลขบัญชี</label><input class="form-control" name="bank_account_number" value="<?= e(setting('bank_account_number', '')) ?>"></div>
                        <div class="col-md-4"><label class="form-label">PromptPay</label><input class="form-control" name="promptpay_id" value="<?= e(setting('promptpay_id', '')) ?>"></div>
                        <div class="col-12"><button class="btn btn-primary">บันทึก</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
