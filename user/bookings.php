<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
requireRole('user');

$user = currentUser();
$workflow = new BookingWorkflowService();
$workflow->ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    try {
        $workflow->cancelByUser((int) $user['id'], (int) ($_POST['booking_id'] ?? 0));
        flash('success', 'ยกเลิกคำขอนัดหมายแล้ว');
    } catch (DomainException $exception) {
        flash('danger', $exception->getMessage());
    }
    redirect(url('/user/bookings.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'review') {
    verify_csrf();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    $stmt = db()->prepare(
        'SELECT b.*, l.user_id AS lawyer_user_id
         FROM bookings b
         JOIN lawyers l ON l.id = b.lawyer_id
         WHERE b.id = ? AND b.user_id = ?
         LIMIT 1'
    );
    $stmt->execute([$bookingId, (int) $user['id']]);
    $booking = $stmt->fetch();

    if (!$booking || $booking['status'] !== 'completed') {
        flash('danger', 'รีวิวได้หลัง Booking เสร็จสิ้นเท่านั้น');
    } elseif ($rating < 1 || $rating > 5) {
        flash('danger', 'กรุณาให้คะแนน 1-5');
    } else {
        $exists = db()->prepare('SELECT id FROM reviews WHERE booking_id = ? LIMIT 1');
        $exists->execute([$bookingId]);
        if ($exists->fetch()) {
            flash('warning', 'Booking นี้มีรีวิวแล้ว');
        } else {
            $insert = db()->prepare('INSERT INTO reviews (booking_id, user_id, lawyer_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$bookingId, (int) $user['id'], (int) $booking['lawyer_id'], $rating, $comment]);

            (new NotificationService())->create(
                (int) $booking['lawyer_user_id'],
                'มีรีวิวใหม่',
                $user['name'] . ' ให้คะแนน ' . $rating . '/5 สำหรับ Booking #' . $bookingId,
                'review'
            );
            flash('success', 'ส่งรีวิวเรียบร้อย');
        }
    }

    redirect(url('/user/bookings.php'));
}

$stmt = db()->prepare(
    'SELECT b.*, p.id AS payment_id, p.status AS payment_status, p.amount, u.name AS lawyer_name,
            r.id AS review_id, r.rating AS review_rating
     FROM bookings b
     JOIN lawyers l ON l.id = b.lawyer_id
     JOIN users u ON u.id = l.user_id
     LEFT JOIN payments p ON p.booking_id = b.id
     LEFT JOIN reviews r ON r.booking_id = b.id
     WHERE b.user_id = ?
     ORDER BY b.created_at DESC'
);
$stmt->execute([(int) $user['id']]);
$bookings = $stmt->fetchAll();

$statusLabels = [
    'pending' => 'รอชำระเงิน',
    'confirmed' => 'ยืนยันแล้ว',
    'completed' => 'เสร็จสิ้น',
    'cancelled' => 'ยกเลิก',
];

$pageTitle = 'การจอง';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">การจอง</h1>
                <div class="app-card p-3">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ทนาย</th>
                                    <th>วันเวลา</th>
                                    <th>รูปแบบ</th>
                                    <th>ราคา</th>
                                    <th>Booking</th>
                                    <th>Payment</th>
                                    <th>ดำเนินการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?= e($booking['lawyer_name']) ?></td>
                                    <td><?= e(formatDateThai($booking['booking_date'])) ?> <?= e(substr((string) $booking['booking_time'], 0, 5)) ?></td>
                                    <td><?= e($booking['consultation_type']) ?></td>
                                    <td><?= e(formatMoney($booking['price'])) ?></td>
                                    <td><span class="badge text-bg-light text-dark"><?= e($statusLabels[$booking['status']] ?? $booking['status']) ?></span></td>
                                    <td><span class="badge text-bg-light text-dark"><?= e($booking['payment_status'] ?? '-') ?></span></td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php if (($booking['payment_status'] ?? '') !== 'approved'): ?>
                                                <?php if ($booking['status'] === 'pending' && $booking['lawyer_response_status'] === 'accepted'): ?>
                                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/user/payment.php?booking_id=' . $booking['id'])) ?>">ชำระเงิน</a>
                                                <?php elseif ($booking['status'] === 'pending' && $booking['lawyer_response_status'] === 'pending'): ?>
                                                    <span class="badge text-bg-warning align-self-center">รอทนายตอบรับ</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge text-bg-success align-self-center">ชำระแล้ว</span>
                                            <?php endif; ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/user/messages.php?lawyer_id=' . $booking['lawyer_id'] . '&case_id=' . $booking['case_id'])) ?>"><i class="bi bi-chat-dots me-1"></i>แชต</a>
                                            <?php if ($booking['status'] === 'pending' && ($booking['payment_status'] ?? '') !== 'approved'): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                                    <button class="btn btn-sm btn-outline-danger" name="action" value="cancel">ยกเลิก</button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($booking['review_id']): ?>
                                                <span class="badge text-bg-warning align-self-center">รีวิว <?= e($booking['review_rating']) ?>/5</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php if ($booking['status'] === 'completed' && !$booking['review_id']): ?>
                                    <tr class="review-row">
                                        <td colspan="7">
                                            <form method="post" class="row g-2 align-items-end">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                                <div class="col-md-2">
                                                    <label class="form-label small-muted">คะแนน</label>
                                                    <select class="form-select form-select-sm" name="rating" required>
                                                        <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                                                            <option value="<?= $rating ?>"><?= $rating ?>/5</option>
                                                        <?php endfor; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-8">
                                                    <label class="form-label small-muted">รีวิว</label>
                                                    <input class="form-control form-control-sm" name="comment" placeholder="เล่าประสบการณ์สั้น ๆ เพื่อช่วยผู้ใช้คนอื่น">
                                                </div>
                                                <div class="col-md-2">
                                                    <button class="btn btn-sm btn-primary w-100" name="action" value="review">ส่งรีวิว</button>
                                                </div>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if (!$bookings): ?><tr><td colspan="7" class="text-muted">ยังไม่มีการจอง</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
