<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$bookingId = (int) ($_GET['booking_id'] ?? 0);
$stmt = db()->prepare(
    'SELECT b.*, p.id AS payment_id, p.amount, p.status AS payment_status, p.slip_image, u.name AS lawyer_name
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
$pageTitle = 'ชำระเงิน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <h1 class="h3 fw-bold mb-3">ชำระเงิน</h1>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="small-muted">ยอดที่ต้องจ่าย</div>
                                <div class="h4 fw-bold"><?= e(formatMoney($booking['amount'])) ?></div>
                                <div class="small-muted">ทนาย: <?= e($booking['lawyer_name']) ?></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="small-muted">โอนเงิน</div>
                                <div class="fw-bold"><?= e($bank['bank_name']) ?></div>
                                <div><?= e($bank['account_number']) ?> · <?= e($bank['account_name']) ?></div>
                                <div>PromptPay: <?= e($bank['promptpay_id']) ?></div>
                            </div>
                        </div>
                    </div>
                    <form id="paymentForm" enctype="multipart/form-data" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                        <div class="col-12"><label class="form-label">อัปโหลดสลิป</label><input class="form-control" type="file" name="slip_image" accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
                        <div class="col-12"><button class="btn btn-primary">ส่งสลิปให้แอดมินตรวจสอบ</button></div>
                    </form>
                    <div class="mt-3">สถานะ: <span class="badge text-bg-light text-dark"><?= e($booking['payment_status']) ?></span></div>
                    <div id="paymentResult" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
document.querySelector('#paymentForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    const result = document.querySelector('#paymentResult');
    const response = await fetch('/api/payment-upload.php', {
        method: 'POST',
        headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
        body: new FormData(this)
    });
    const json = await response.json();
    result.innerHTML = `<div class="alert alert-${json.success ? 'success' : 'danger'}">${json.message}</div>`;
    if (json.success && json.data.redirect) window.location.href = json.data.redirect;
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
