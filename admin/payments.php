<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/N8nPaymentVerificationService.php';
requireRole('admin');

$n8nVerifier = new N8nPaymentVerificationService();
$n8nReady = $n8nVerifier->isConfigured();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? '');
    $service = new PaymentService();
    if (($_POST['action'] ?? '') === 'approve') {
        try {
            $service->approve($paymentId, $note, (int) currentUser()['id']);
            flash('success', 'อนุมัติสลิปแล้ว ระบบยืนยันนัดหมายให้ลูกความและทนายเรียบร้อย');
        } catch (DomainException $exception) {
            flash('danger', $exception->getMessage());
        }
    } elseif (($_POST['action'] ?? '') === 'reject') {
        try {
            $service->reject($paymentId, $note, (int) currentUser()['id']);
            flash('success', 'ปฏิเสธสลิปแล้ว ระบบแจ้งให้ผู้ใช้อัปโหลดใหม่');
        } catch (DomainException $exception) {
            flash('danger', $exception->getMessage());
        }
    }
    redirect(url('/admin/payments.php'));
}

$payments = db()->query(
    'SELECT p.*, b.id AS booking_id, b.user_id, b.status AS booking_status, b.lawyer_response_status, b.booking_date, b.booking_time,
            uu.name AS user_name, lu.name AS lawyer_name
     FROM payments p
     JOIN bookings b ON b.id = p.booking_id
     JOIN users uu ON uu.id = b.user_id
     JOIN lawyers l ON l.id = b.lawyer_id
     JOIN users lu ON lu.id = l.user_id
     ORDER BY
        CASE
            WHEN p.status = "pending" AND p.slip_image IS NOT NULL THEN 1
            WHEN p.status = "rejected" THEN 2
            WHEN p.status = "pending" THEN 3
            WHEN p.status = "approved" THEN 4
            ELSE 5
        END,
        p.created_at DESC'
)->fetchAll();

$stats = [
    'review' => 0,
    'waiting_user' => 0,
    'approved' => 0,
    'rejected' => 0,
];
foreach ($payments as $payment) {
    if (($payment['status'] ?? '') === 'pending' && !empty($payment['slip_image'])) {
        $stats['review']++;
    } elseif (($payment['status'] ?? '') === 'pending') {
        $stats['waiting_user']++;
    } elseif (($payment['status'] ?? '') === 'approved') {
        $stats['approved']++;
    } elseif (($payment['status'] ?? '') === 'rejected') {
        $stats['rejected']++;
    }
}

$pageTitle = 'ตรวจสอบการชำระเงิน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="page-heading-row mb-3">
                    <div>
                        <span class="page-kicker">ตรวจสอบการชำระเงิน</span>
                        <h1 class="h3 fw-bold mb-1">ตรวจสอบสลิป</h1>
                        <p class="text-muted mb-0">รองรับทั้งการตรวจโดยแอดมินและการตรวจอัตโนมัติผ่าน n8n เมื่อ workflow ส่งผลกลับมา</p>
                    </div>
                    <span class="legal-badge">
                        <i class="bi bi-diagram-3"></i>
                        <?= $n8nReady ? 'n8n ตรวจอัตโนมัติเปิดอยู่' : 'n8n ยังไม่เปิดใช้' ?>
                    </span>
                </div>

                <div class="payment-admin-stats mb-3">
                    <div class="payment-admin-stat tone-info"><span>รอตรวจ</span><strong><?= e((string) $stats['review']) ?></strong></div>
                    <div class="payment-admin-stat tone-warning"><span>รอผู้ใช้อัปโหลด</span><strong><?= e((string) $stats['waiting_user']) ?></strong></div>
                    <div class="payment-admin-stat tone-success"><span>อนุมัติแล้ว</span><strong><?= e((string) $stats['approved']) ?></strong></div>
                    <div class="payment-admin-stat tone-danger"><span>สลิปไม่ผ่าน</span><strong><?= e((string) $stats['rejected']) ?></strong></div>
                </div>

                <div class="app-card p-3 table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>รายการจอง</th>
                                <th>ลูกความ / ทนาย</th>
                                <th>ยอด</th>
                                <th>สลิป</th>
                                <th>สถานะ</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <?php
                            $paymentFlow = paymentWorkflowState($payment);
                            $paymentLabel = paymentStatusLabel(
                                $payment['status'] ?? null,
                                $paymentFlow['has_slip'],
                                $payment['lawyer_response_status'] ?? null,
                                $payment['booking_status'] ?? null
                            );
                            ?>
                            <tr>
                                <td>
                                    <strong>#<?= e((string) $payment['booking_id']) ?></strong>
                                    <div class="small text-muted"><?= e(formatDateThai($payment['booking_date'])) ?> <?= e(substr((string) $payment['booking_time'], 0, 5)) ?></div>
                                </td>
                                <td>
                                    <strong><?= e($payment['user_name']) ?></strong>
                                    <div class="small text-muted">ทนาย: <?= e($payment['lawyer_name']) ?></div>
                                </td>
                                <td><?= e(formatMoney($payment['amount'])) ?></td>
                                <td>
                                    <?php if ($payment['slip_image']): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/public/file.php?payment_id=' . $payment['id'])) ?>" target="_blank" rel="noopener">
                                            <i class="bi bi-image me-1"></i>ดูสลิป
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">ยังไม่มีสลิป</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="workflow-badge tone-<?= e($paymentFlow['tone']) ?>">
                                        <i class="bi bi-<?= e($paymentFlow['icon']) ?>"></i>
                                        <?= e($paymentLabel) ?>
                                    </span>
                                    <div class="small text-muted mt-1"><?= e($paymentFlow['description']) ?></div>
                                    <?php if (!empty($payment['admin_note'])): ?>
                                        <div class="small text-muted mt-1">หมายเหตุ: <?= e($payment['admin_note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($payment['status'] ?? '') === 'pending' && !empty($payment['slip_image'])): ?>
                                        <form method="post" class="payment-admin-action">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="payment_id" value="<?= e($payment['id']) ?>">
                                            <input class="form-control form-control-sm" name="admin_note" placeholder="หมายเหตุ เช่น ยอดถูกต้อง / ยอดไม่ตรง">
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-sm btn-success" name="action" value="approve">
                                                    <i class="bi bi-check2 me-1"></i>อนุมัติ
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" name="action" value="reject">
                                                    <i class="bi bi-x-lg me-1"></i>ปฏิเสธ
                                                </button>
                                            </div>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">ยังไม่มีงานที่ต้องตรวจ</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="6" class="text-muted">ยังไม่มีรายการชำระเงิน</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
