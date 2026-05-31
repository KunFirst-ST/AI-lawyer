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
            flash('success', $action === 'accept' ? 'ตอบรับนัดหมายแล้ว' : 'ปฏิเสธนัดหมายแล้ว');
        } elseif ($action === 'complete') {
            $workflow->complete((int) $user['id'], $bookingId);
            flash('success', 'ปิดงานเรียบร้อย ผู้ใช้สามารถให้รีวิวได้แล้ว');
        }
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
    } catch (Throwable $exception) {
        flash('danger', 'ไม่สามารถอัปเดต Booking ได้');
    }

    redirect(url('/lawyer/bookings.php'));
}

$stmt = db()->prepare(
    'SELECT b.*, u.name AS user_name, c.title AS case_title, p.status AS payment_status
     FROM bookings b
     JOIN users u ON u.id = b.user_id
     JOIN cases c ON c.id = b.case_id
     LEFT JOIN payments p ON p.booking_id = b.id
     WHERE b.lawyer_id = ?
     ORDER BY b.booking_date DESC, b.booking_time DESC'
);
$stmt->execute([$lawyerId]);
$bookings = $stmt->fetchAll();

$statusLabels = [
    'pending' => 'รอชำระเงิน',
    'confirmed' => 'ยืนยันแล้ว',
    'completed' => 'เสร็จสิ้น',
    'cancelled' => 'ยกเลิก',
];

$pageTitle = 'Booking ทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">Booking</h1>
                <div class="app-card p-3 table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ลูกค้า</th>
                                <th>เคส</th>
                                <th>วันเวลา</th>
                                <th>รูปแบบ</th>
                                <th>ราคา</th>
                                <th>Status</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= e($booking['user_name']) ?></td>
                                <td><?= e($booking['case_title']) ?></td>
                                <td><?= e(formatDateThai($booking['booking_date'])) ?> <?= e(substr((string) $booking['booking_time'], 0, 5)) ?></td>
                                <td><?= e($booking['consultation_type']) ?></td>
                                <td><?= e(formatMoney($booking['price'])) ?></td>
                                <td>
                                    <span class="badge text-bg-light text-dark"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span>
                                    <?php if ($booking['payment_status']): ?>
                                        <span class="badge text-bg-light text-dark">Payment: <?= e($booking['payment_status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($booking['status'] === 'pending' && $booking['lawyer_response_status'] === 'pending'): ?>
                                        <form method="post" class="d-flex flex-column gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                            <input class="form-control form-control-sm" name="lawyer_note" placeholder="หมายเหตุถึงลูกความ">
                                            <div class="d-flex gap-2">
                                                <button class="btn btn-sm btn-success" name="action" value="accept">ตอบรับ</button>
                                                <button class="btn btn-sm btn-outline-danger" name="action" value="reject">ปฏิเสธ</button>
                                            </div>
                                        </form>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                            <button class="btn btn-sm btn-success" name="action" value="complete">ปิดงาน</button>
                                        </form>
                                    <?php elseif ($booking['status'] === 'completed'): ?>
                                        <span class="text-success small">ปิดงานแล้ว</span>
                                    <?php elseif ($booking['lawyer_response_status'] === 'accepted'): ?>
                                        <span class="text-muted small">ตอบรับแล้ว รอลูกค้าชำระเงิน</span>
                                    <?php elseif ($booking['lawyer_response_status'] === 'rejected'): ?>
                                        <span class="text-danger small">ปฏิเสธแล้ว</span>
                                    <?php else: ?>
                                        <span class="text-muted small">รอขั้นตอนก่อนหน้า</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$bookings): ?><tr><td colspan="7" class="text-muted">ยังไม่มี Booking</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
