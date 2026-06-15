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
        flash('danger', 'รีวิวได้หลังรายการจองเสร็จสิ้นเท่านั้น');
    } elseif ($rating < 1 || $rating > 5) {
        flash('danger', 'กรุณาให้คะแนน 1-5');
    } else {
        $exists = db()->prepare('SELECT id FROM reviews WHERE booking_id = ? LIMIT 1');
        $exists->execute([$bookingId]);
        if ($exists->fetch()) {
            flash('warning', 'รายการจองนี้มีรีวิวแล้ว');
        } else {
            $insert = db()->prepare('INSERT INTO reviews (booking_id, user_id, lawyer_id, rating, comment) VALUES (?, ?, ?, ?, ?)');
            $insert->execute([$bookingId, (int) $user['id'], (int) $booking['lawyer_id'], $rating, $comment]);

            (new NotificationService())->create(
                (int) $booking['lawyer_user_id'],
                'มีรีวิวใหม่',
                $user['name'] . ' ให้คะแนน ' . $rating . '/5 สำหรับรายการจอง #' . $bookingId,
                'review'
            );
            flash('success', 'ส่งรีวิวเรียบร้อย');
        }
    }

    redirect(url('/user/bookings.php'));
}

$stmt = db()->prepare(
    'SELECT b.*, b.status AS booking_status, p.id AS payment_id, p.status AS payment_status, p.amount, p.slip_image, p.admin_note, u.name AS lawyer_name,
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

$pageTitle = 'การจอง';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="page-heading-row mb-3">
                    <div>
                        <span class="page-kicker">ขั้นตอนชำระเงิน</span>
                        <h1 class="h3 fw-bold mb-1">การจองและชำระเงิน</h1>
                        <p class="text-muted mb-0">ดูขั้นตอนตั้งแต่ทนายรับงาน ไปจนถึงแอดมินยืนยันสลิป</p>
                    </div>
                    <a class="btn btn-outline-primary" href="<?= e(url('/user/matches.php')) ?>"><i class="bi bi-search me-1"></i>หาทนายเพิ่ม</a>
                </div>

                <?php if (!$bookings): ?>
                    <div class="empty-state app-card">
                        <i class="bi bi-calendar2-plus"></i>
                        <h2>ยังไม่มีการจอง</h2>
                        <p>เมื่อคุณเลือกทนายและส่งคำขอนัดหมาย รายการจะมาแสดงที่นี่พร้อมขั้นตอนชำระเงิน</p>
                        <a class="btn btn-primary" href="<?= e(url('/user/matches.php')) ?>">เลือกทนาย</a>
                    </div>
                <?php else: ?>
                    <div class="booking-list">
                        <?php foreach ($bookings as $booking): ?>
                            <?php
                            $paymentFlow = paymentWorkflowState($booking);
                            $paymentLabel = paymentStatusLabel(
                                $booking['payment_status'] ?? null,
                                $paymentFlow['has_slip'],
                                $booking['lawyer_response_status'] ?? null,
                                $booking['booking_status'] ?? null
                            );
                            ?>
                            <article class="booking-card">
                                <div class="booking-card-head">
                                    <div class="booking-title">
                                        <span class="booking-id">รายการจอง #<?= e((string) $booking['id']) ?></span>
                                        <h2><?= e($booking['lawyer_name']) ?></h2>
                                        <p>
                                            <i class="bi bi-calendar-event"></i>
                                            <?= e(formatDateThai($booking['booking_date'])) ?>
                                            <?= e(substr((string) $booking['booking_time'], 0, 5)) ?>
                                            <span>·</span>
                                            <?= e(consultationTypeLabel($booking['consultation_type'])) ?>
                                        </p>
                                    </div>
                                    <div class="booking-price">
                                        <span class="workflow-badge tone-<?= e($paymentFlow['tone']) ?>">
                                            <i class="bi bi-<?= e($paymentFlow['icon']) ?>"></i>
                                            <?= e($paymentFlow['title']) ?>
                                        </span>
                                        <strong><?= e(formatMoney($booking['amount'] ?? $booking['price'])) ?></strong>
                                        <small><?= e($paymentLabel) ?></small>
                                    </div>
                                </div>

                                <div class="payment-progress" aria-label="ขั้นตอนชำระเงิน">
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

                                <div class="booking-meta-grid">
                                    <div>
                                        <span>สถานะนัดหมาย</span>
                                        <strong><?= e(bookingStatusLabel($booking['booking_status'] ?? null)) ?></strong>
                                    </div>
                                    <div>
                                        <span>การตอบรับของทนาย</span>
                                        <strong><?= e(lawyerResponseStatusLabel($booking['lawyer_response_status'] ?? null)) ?></strong>
                                    </div>
                                    <div>
                                        <span>สถานะสลิป</span>
                                        <strong><?= e($paymentLabel) ?></strong>
                                    </div>
                                </div>

                                <?php if (!empty($booking['lawyer_note'])): ?>
                                    <div class="payment-note">
                                        <i class="bi bi-chat-left-text"></i>
                                        <div><strong>หมายเหตุจากทนาย</strong><span><?= e($booking['lawyer_note']) ?></span></div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($booking['admin_note']) && ($booking['payment_status'] ?? '') === 'rejected'): ?>
                                    <div class="payment-note is-danger">
                                        <i class="bi bi-exclamation-triangle"></i>
                                        <div><strong>เหตุผลที่สลิปไม่ผ่าน</strong><span><?= e($booking['admin_note']) ?></span></div>
                                    </div>
                                <?php endif; ?>

                                <p class="booking-flow-copy"><?= e($paymentFlow['description']) ?></p>

                                <div class="booking-card-actions">
                                    <?php if ($paymentFlow['can_upload']): ?>
                                        <a class="btn btn-primary" href="<?= e(url('/user/payment.php?booking_id=' . $booking['id'])) ?>">
                                            <i class="bi bi-upload me-1"></i><?= $paymentFlow['stage'] === 'slip_rejected' ? 'อัปโหลดสลิปใหม่' : 'ชำระเงิน' ?>
                                        </a>
                                    <?php elseif ($paymentFlow['stage'] === 'waiting_admin'): ?>
                                        <span class="workflow-badge tone-info"><i class="bi bi-hourglass-split"></i>รอแอดมินตรวจ</span>
                                    <?php elseif (in_array($paymentFlow['stage'], ['confirmed', 'completed'], true)): ?>
                                        <span class="workflow-badge tone-success"><i class="bi bi-check2-circle"></i>พร้อมใช้งาน</span>
                                    <?php endif; ?>

                                    <a class="btn btn-outline-secondary" href="<?= e(url('/user/messages.php?lawyer_id=' . $booking['lawyer_id'] . '&case_id=' . $booking['case_id'])) ?>">
                                        <i class="bi bi-chat-dots me-1"></i>แชตทนาย
                                    </a>

                                    <?php if ($paymentFlow['can_cancel']): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                            <button class="btn btn-outline-danger" name="action" value="cancel">
                                                <i class="bi bi-x-circle me-1"></i>ยกเลิก
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($booking['review_id']): ?>
                                        <span class="workflow-badge tone-warning"><i class="bi bi-star-fill"></i>รีวิว <?= e((string) $booking['review_rating']) ?>/5</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($booking['booking_status'] === 'completed' && !$booking['review_id']): ?>
                                    <form method="post" class="review-form">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                                        <div>
                                            <label class="form-label small-muted">คะแนน</label>
                                            <select class="form-select form-select-sm" name="rating" required>
                                                <?php for ($rating = 5; $rating >= 1; $rating--): ?>
                                                    <option value="<?= $rating ?>"><?= $rating ?>/5</option>
                                                <?php endfor; ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label small-muted">รีวิว</label>
                                            <input class="form-control form-control-sm" name="comment" placeholder="เล่าประสบการณ์สั้น ๆ เพื่อช่วยผู้ใช้คนอื่น">
                                        </div>
                                        <button class="btn btn-sm btn-primary" name="action" value="review">ส่งรีวิว</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
