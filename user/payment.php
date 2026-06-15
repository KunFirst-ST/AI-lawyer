<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
requireRole('user');

$user = currentUser();
(new BookingWorkflowService())->ensureSchema();
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT b.*, b.status AS booking_status, p.id AS payment_id, p.amount, p.status AS payment_status, p.slip_image, p.admin_note, u.name AS lawyer_name
     FROM bookings b
     JOIN lawyers l ON l.id = b.lawyer_id
     JOIN users u ON u.id = l.user_id
     JOIN payments p ON p.booking_id = b.id
     WHERE b.id = ? AND b.user_id = ?
     LIMIT 1'
);
$stmt->execute([$bookingId, $user['id']]);
$booking = $stmt->fetch();
if (!$booking) {
    http_response_code(404);
    exit('ไม่พบรายการชำระเงิน');
}

$bankConfig = app_config('bank');
$bank = [
    'bank_name' => $bankConfig['bank_name'],
    'account_name' => setting('bank_account_name', $bankConfig['account_name']),
    'account_number' => setting('bank_account_number', $bankConfig['account_number']),
    'promptpay_id' => setting('promptpay_id', $bankConfig['promptpay_id']),
];
$bankReady = trim((string) $bank['bank_name']) !== ''
    && trim((string) $bank['account_name']) !== ''
    && trim((string) $bank['account_number']) !== '';
$paymentFlow = paymentWorkflowState($booking);
$paymentLabel = paymentStatusLabel(
    $booking['payment_status'] ?? null,
    $paymentFlow['has_slip'],
    $booking['lawyer_response_status'] ?? null,
    $booking['booking_status'] ?? null
);

$pageTitle = 'ชำระเงิน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="page-heading-row mb-3">
                    <div>
                        <span class="page-kicker">ชำระเงินอย่างปลอดภัย</span>
                        <h1 class="h3 fw-bold mb-1">ชำระเงินรายการจอง #<?= e((string) $booking['id']) ?></h1>
                        <p class="text-muted mb-0">โอนเงินหลังทนายรับงาน แล้วส่งสลิปให้แอดมินตรวจเพื่อยืนยันนัดหมาย</p>
                    </div>
                    <a class="btn btn-outline-secondary" href="<?= e(url('/user/bookings.php')) ?>"><i class="bi bi-arrow-left me-1"></i>กลับรายการจอง</a>
                </div>

                <div class="payment-panel">
                    <div class="payment-panel-head">
                        <div>
                            <span class="workflow-badge tone-<?= e($paymentFlow['tone']) ?>">
                                <i class="bi bi-<?= e($paymentFlow['icon']) ?>"></i>
                                <?= e($paymentFlow['title']) ?>
                            </span>
                            <h2><?= e(formatMoney($booking['amount'])) ?></h2>
                            <p>ทนาย: <?= e($booking['lawyer_name']) ?> · <?= e(formatDateThai($booking['booking_date'])) ?> <?= e(substr((string) $booking['booking_time'], 0, 5)) ?></p>
                        </div>
                        <div class="payment-status-chip">
                            <span>สถานะ</span>
                            <strong><?= e($paymentLabel) ?></strong>
                        </div>
                    </div>

                    <div class="payment-progress payment-progress-wide" aria-label="ขั้นตอนชำระเงิน">
                        <?php foreach ($paymentFlow['steps'] as $step): ?>
                            <div class="payment-step is-<?= e($step['state']) ?>">
                                <span class="payment-step-dot"><i class="bi bi-<?= e($step['icon']) ?>"></i></span>
                                <div>
                                    <strong><?= e($step['label']) ?></strong>
                                    <small><?= e($step['hint']) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="payment-bank-grid">
                        <div class="payment-bank-item">
                            <span>ธนาคาร</span>
                            <strong><?= e($bank['bank_name'] ?: 'รอแอดมินตั้งค่าบัญชีรับชำระ') ?></strong>
                        </div>
                        <div class="payment-bank-item">
                            <span>ชื่อบัญชี</span>
                            <strong><?= e($bank['account_name'] ?: '-') ?></strong>
                        </div>
                        <div class="payment-bank-item">
                            <span>เลขบัญชี</span>
                            <strong><?= e($bank['account_number'] ?: '-') ?></strong>
                        </div>
                        <div class="payment-bank-item">
                            <span>PromptPay</span>
                            <strong><?= e($bank['promptpay_id'] ?: '-') ?></strong>
                        </div>
                    </div>

                    <?php if (!$bankReady): ?>
                        <div class="payment-note is-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div>
                                <strong>ยังไม่พร้อมรับชำระเงิน</strong>
                                <span>แอดมินยังไม่ได้ตั้งค่าบัญชีรับชำระ กรุณารอการยืนยันหรือสอบถามทีมงานก่อนโอนเงิน</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($booking['admin_note']) && ($booking['payment_status'] ?? '') === 'rejected'): ?>
                        <div class="payment-note is-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <div><strong>เหตุผลที่สลิปไม่ผ่าน</strong><span><?= e($booking['admin_note']) ?></span></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($paymentFlow['can_upload'] && $bankReady): ?>
                        <form id="paymentForm" enctype="multipart/form-data" class="payment-upload-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                            <label class="payment-upload-box" for="slipImage">
                                <i class="bi bi-cloud-arrow-up"></i>
                                <strong><?= $paymentFlow['stage'] === 'slip_rejected' ? 'อัปโหลดสลิปใหม่' : 'อัปโหลดสลิปชำระเงิน' ?></strong>
                                <span>รองรับรูปภาพทุกชนิดที่ระบบอ่านได้ และ PDF ขนาดไม่เกิน 15MB</span>
                                <input id="slipImage" class="visually-hidden" type="file" name="slip_image" accept="image/*,.pdf" required>
                            </label>
                            <div id="slipPreview" class="payment-preview d-none"></div>
                            <button id="paymentSubmit" class="btn btn-primary" type="submit">
                                <i class="bi bi-send-check me-1"></i>ส่งสลิปให้แอดมินตรวจ
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="payment-locked-note tone-<?= e($paymentFlow['tone']) ?>">
                            <i class="bi bi-<?= e($paymentFlow['icon']) ?>"></i>
                            <div>
                                <strong><?= e($paymentFlow['title']) ?></strong>
                                <span><?= e($paymentFlow['description']) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div id="paymentResult" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
const slipInput = document.querySelector('#slipImage');
const slipPreview = document.querySelector('#slipPreview');
slipInput?.addEventListener('change', () => {
    const file = slipInput.files?.[0];
    if (!file || !slipPreview) return;
    const sizeMb = (file.size / 1024 / 1024).toFixed(2);
    slipPreview.classList.remove('d-none');
    slipPreview.innerHTML = '';

    if (file.type.startsWith('image/')) {
        const image = document.createElement('img');
        image.alt = 'พรีวิวสลิป';
        image.src = URL.createObjectURL(file);
        slipPreview.appendChild(image);
    } else {
        const icon = document.createElement('i');
        icon.className = 'bi bi-file-earmark-pdf';
        slipPreview.appendChild(icon);
    }

    const meta = document.createElement('div');
    meta.innerHTML = `<strong>${file.name}</strong><span>${sizeMb} MB</span>`;
    slipPreview.appendChild(meta);
});

document.querySelector('#paymentForm')?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const result = document.querySelector('#paymentResult');
    const button = document.querySelector('#paymentSubmit');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังส่งสลิป';

    try {
        const response = await fetch('<?= e(url('/api/payment-upload.php')) ?>', {
            method: 'POST',
            headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
            body: new FormData(this)
        });
        const json = await response.json();
        result.innerHTML = `<div class="alert alert-${json.success ? 'success' : 'danger'}">${json.message}</div>`;
        if (json.success && json.data.redirect) window.location.href = json.data.redirect;
    } catch (error) {
        result.innerHTML = '<div class="alert alert-danger">ส่งสลิปไม่สำเร็จ กรุณาลองใหม่อีกครั้ง</div>';
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-send-check me-1"></i>ส่งสลิปให้แอดมินตรวจ';
    }
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
