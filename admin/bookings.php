<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
requireRole('admin');
(new BookingWorkflowService())->ensureSchema();

$bookings = db()->query(
    'SELECT b.*, b.status AS booking_status, uu.name AS user_name, lu.name AS lawyer_name, p.status AS payment_status, p.slip_image, p.admin_note
     FROM bookings b
     JOIN users uu ON uu.id = b.user_id
     JOIN lawyers l ON l.id = b.lawyer_id
     JOIN users lu ON lu.id = l.user_id
     LEFT JOIN payments p ON p.booking_id = b.id
     ORDER BY b.created_at DESC'
)->fetchAll();

$pageTitle = 'จัดการ Booking';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="page-heading-row mb-3">
                    <div>
                        <span class="page-kicker">Booking Monitor</span>
                        <h1 class="h3 fw-bold mb-1">Booking</h1>
                        <p class="text-muted mb-0">ติดตามนัดหมายและขั้นตอนชำระเงินของทุกเคส</p>
                    </div>
                </div>

                <div class="app-card p-3 table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>ผู้ใช้</th>
                                <th>ทนาย</th>
                                <th>วันเวลา</th>
                                <th>ราคา</th>
                                <th>การตอบรับ</th>
                                <th>Payment</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
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
                            <tr>
                                <td><?= e($booking['user_name']) ?></td>
                                <td><?= e($booking['lawyer_name']) ?></td>
                                <td><?= e(formatDateThai($booking['booking_date'])) ?> <?= e(substr((string) $booking['booking_time'], 0, 5)) ?></td>
                                <td><?= e(formatMoney($booking['price'])) ?></td>
                                <td>
                                    <strong><?= e(lawyerResponseStatusLabel($booking['lawyer_response_status'] ?? null)) ?></strong>
                                    <?php if (!empty($booking['lawyer_note'])): ?><div class="small text-muted"><?= e($booking['lawyer_note']) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <span class="workflow-badge tone-<?= e($paymentFlow['tone']) ?>">
                                        <i class="bi bi-<?= e($paymentFlow['icon']) ?>"></i>
                                        <?= e($paymentLabel) ?>
                                    </span>
                                    <?php if (!empty($booking['admin_note'])): ?><div class="small text-muted mt-1"><?= e($booking['admin_note']) ?></div><?php endif; ?>
                                </td>
                                <td><?= e(bookingStatusLabel($booking['booking_status'] ?? null)) ?></td>
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
