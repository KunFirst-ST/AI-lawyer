<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';
requireRole('lawyer');

$user = currentUser();
$lawyerStmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyerId = (int) ($lawyerStmt->fetchColumn() ?: 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($action === 'complete' && $lawyerId > 0) {
        $stmt = db()->prepare(
            'SELECT b.*, c.title AS case_title
             FROM bookings b
             JOIN cases c ON c.id = b.case_id
             WHERE b.id = ? AND b.lawyer_id = ?
             LIMIT 1'
        );
        $stmt->execute([$bookingId, $lawyerId]);
        $booking = $stmt->fetch();

        if (!$booking || $booking['status'] !== 'confirmed') {
            flash('danger', 'สามารถปิดงานได้เฉพาะ Booking ที่ยืนยันแล้วเท่านั้น');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE bookings SET status = "completed" WHERE id = ? AND lawyer_id = ?')->execute([$bookingId, $lawyerId]);
                $pdo->prepare('UPDATE cases SET status = "closed" WHERE id = ?')->execute([(int) $booking['case_id']]);
                $pdo->commit();

                (new NotificationService())->create(
                    (int) $booking['user_id'],
                    'การปรึกษาเสร็จสิ้นแล้ว',
                    'Booking สำหรับเคส "' . ($booking['case_title'] ?: 'ไม่ระบุชื่อเคส') . '" ถูกปิดงานแล้ว คุณสามารถให้รีวิวทนายได้',
                    'booking'
                );
                flash('success', 'ปิดงานเรียบร้อย ผู้ใช้สามารถให้รีวิวได้แล้ว');
            } catch (Throwable $exception) {
                $pdo->rollBack();
                flash('danger', 'ไม่สามารถปิดงานได้: ' . $exception->getMessage());
            }
        }
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
                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                            <button class="btn btn-sm btn-success" name="action" value="complete">ปิดงาน</button>
                                        </form>
                                    <?php elseif ($booking['status'] === 'completed'): ?>
                                        <span class="text-success small">ปิดงานแล้ว</span>
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
