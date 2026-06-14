<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
requireRole('lawyer');

$user = currentUser();
$workflow = new BookingWorkflowService();
$workflow->ensureSchema();
$lawyerStmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyerId = (int) ($lawyerStmt->fetchColumn() ?: 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    try {
        if (in_array($action, ['accept', 'reject'], true)) {
            $workflow->respond((int) $user['id'], $bookingId, $action, trim((string) ($_POST['lawyer_note'] ?? '')));
            flash('success', $action === 'accept' ? 'ตอบรับนัดหมายแล้ว ระบบจะแจ้งให้ลูกความชำระเงิน' : 'ปฏิเสธนัดหมายแล้ว');
        } elseif ($action === 'complete') {
            $workflow->complete((int) $user['id'], $bookingId);
            flash('success', 'ปิดงานเรียบร้อย ผู้ใช้สามารถให้รีวิวได้แล้ว');
        }
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        flash('danger', 'ไม่สามารถอัปเดตรายการจองได้');
    }

    redirect(url('/lawyer/bookings.php'));
}

$stmt = db()->prepare(
    'SELECT b.*, b.status AS booking_status, u.name AS user_name, c.title AS case_title, p.status AS payment_status, p.slip_image, p.admin_note
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN cases c ON c.id = b.case_id
     LEFT JOIN payments p ON p.booking_id = b.id
     WHERE b.lawyer_id = ?
     ORDER BY b.booking_date DESC, b.booking_time DESC'
);
$stmt->execute([$lawyerId]);
$bookings = $stmt->fetchAll();

$pageTitle = 'การจองของทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="page-heading-row mb-3">
                    <div>
                        <span class="page-kicker">ตารางนัดหมาย</span>
                        <h1 class="h3 fw-bold mb-1">การจองของลูกความ</h1>
                        <p class="text-muted mb-0">ตอบรับนัดหมายก่อน แล้วระบบจะเปิดให้ลูกความชำระเงิน</p>
                    </div>
                </div>

                <div class="app-card p-3 table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>ลูกความ</th>
                                <th>เคส</th>
                                <th>วันเวลา</th>
                                <th>รูปแบบ</th>
                                <th>ราคา</th>
                                <th>ขั้นตอน</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <?php $paymentFlow = paymentWorkflowState($booking); ?>
                            <tr>
                                <td>
                                    <strong><?= e($booking['user_name']) ?></strong>
                                    <div class="small text-muted">รายการจอง #<?= e((string) $booking['id']) ?></div>
                                </td>
                                <td><?= e($booking['case_title']) ?></td>
                                <td><?= e(formatDateThai($booking['booking_date'])) ?> <?= e(substr((string) $booking['booking_time'], 0, 5)) ?></td>
                                <td><?= e($booking['consultation_type']) ?></td>
                                <td><?= e(formatMoney($booking['price'])) ?></td>
                                <td>
                                    <span class="workflow-badge tone-<?= e($paymentFlow['tone']) ?>">
                                        <i class="bi bi-<?= e($paymentFlow['icon']) ?>"></i>
                                        <?= e($paymentFlow['title']) ?>
                                    </span>
                                    <div class="small text-muted mt-1"><?= e(paymentStatusLabel($booking['payment_status'] ?? null, $paymentFlow['has_slip'], $booking['lawyer_response_status'] ?? null, $booking['booking_status'] ?? null)) ?></div>
                                    <?php if (!empty($booking['admin_note']) && ($booking['payment_status'] ?? '') === 'rejected'): ?>
                                        <div class="small text-danger mt-1">หมายเหตุแอดมิน: <?= e($booking['admin_note']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking['booking_status'] === 'pending' && $booking['lawyer_response_status'] === 'pending'): ?>
                                        <form method="post" class="d-flex flex-column gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                            <input class="form-control form-control-sm" name="lawyer_note" placeholder="หมายเหตุถึงลูกความ">
                                            <div class="d-flex flex-wrap gap-2">
                                                <button class="btn btn-sm btn-success" name="action" value="accept"><i class="bi bi-check2 me-1"></i>ตอบรับ</button>
                                                <button class="btn btn-sm btn-outline-danger" name="action" value="reject"><i class="bi bi-x-lg me-1"></i>ปฏิเสธ</button>
                                            </div>
                                        </form>
                                    <?php elseif ($booking['booking_status'] === 'confirmed'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                            <button class="btn btn-sm btn-success" name="action" value="complete"><i class="bi bi-check2-circle me-1"></i>ปิดงาน</button>
                                        </form>
                                    <?php elseif ($booking['booking_status'] === 'completed'): ?>
                                        <span class="text-success small">ปิดงานแล้ว</span>
                                    <?php elseif ($paymentFlow['stage'] === 'waiting_admin'): ?>
                                        <span class="text-muted small">ลูกความส่งสลิปแล้ว รอแอดมินตรวจ</span>
                                    <?php elseif ($paymentFlow['stage'] === 'ready_to_pay'): ?>
                                        <span class="text-muted small">ตอบรับแล้ว รอลูกความชำระเงิน</span>
                                    <?php elseif ($booking['lawyer_response_status'] === 'rejected'): ?>
                                        <span class="text-danger small">ปฏิเสธแล้ว</span>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= e($paymentFlow['description']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bookings): ?><tr><td colspan="7" class="text-muted">ยังไม่มีรายการจอง</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
