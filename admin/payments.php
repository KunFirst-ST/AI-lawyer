<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/PaymentService.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $paymentId = (int) ($_POST['payment_id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? '');
    $service = new PaymentService();
    if (($_POST['action'] ?? '') === 'approve') {
        $service->approve($paymentId, $note);
        flash('success', 'อนุมัติสลิปแล้ว');
    } elseif (($_POST['action'] ?? '') === 'reject') {
        $service->reject($paymentId, $note);
        flash('success', 'ปฏิเสธสลิปแล้ว');
    }
    redirect(url('/admin/payments.php'));
}

$payments = db()->query(
    'SELECT p.*, b.user_id, uu.name AS user_name, lu.name AS lawyer_name
     FROM payments p
     JOIN bookings b ON b.id = p.booking_id
     JOIN users uu ON uu.id = b.user_id
     JOIN lawyers l ON l.id = b.lawyer_id
     JOIN users lu ON lu.id = l.user_id
     ORDER BY FIELD(p.status, "pending", "approved", "rejected"), p.created_at DESC'
)->fetchAll();
$pageTitle = 'ตรวจสอบ Payment';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">ตรวจสอบสลิป</h1>
                <div class="app-card p-3 table-responsive">
                    <table class="table">
                        <thead><tr><th>ลูกค้า</th><th>ทนาย</th><th>ยอด</th><th>สลิป</th><th>Status</th><th>จัดการ</th></tr></thead>
                        <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= e($payment['user_name']) ?></td><td><?= e($payment['lawyer_name']) ?></td><td><?= e(formatMoney($payment['amount'])) ?></td>
                                <td><?= $payment['slip_image'] ? '<a href="' . e(url('/public/file.php?payment_id=' . $payment['id'])) . '" target="_blank">ดูสลิป</a>' : '<span class="text-muted">ยังไม่มี</span>' ?></td>
                                <td><span class="badge text-bg-light text-dark"><?= e($payment['status']) ?></span></td>
                                <td>
                                    <form method="post" class="d-flex gap-2">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="payment_id" value="<?= e($payment['id']) ?>">
                                        <input class="form-control form-control-sm" name="admin_note" placeholder="หมายเหตุ">
                                        <button class="btn btn-sm btn-success" name="action" value="approve">อนุมัติ</button>
                                        <button class="btn btn-sm btn-outline-danger" name="action" value="reject">ปฏิเสธ</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$payments): ?><tr><td colspan="6" class="text-muted">ยังไม่มี payment</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
