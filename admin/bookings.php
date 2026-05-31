<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
requireRole('admin');
(new BookingWorkflowService())->ensureSchema();
$bookings = db()->query(
    'SELECT b.*, uu.name AS user_name, lu.name AS lawyer_name, p.status AS payment_status
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
<section class="dashboard-shell"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div><div class="col-lg-9">
<h1 class="h3 fw-bold mb-3">Booking</h1>
<div class="app-card p-3 table-responsive"><table class="table"><thead><tr><th>ผู้ใช้</th><th>ทนาย</th><th>วันเวลา</th><th>ราคา</th><th>ทนายตอบรับ</th><th>Payment</th><th>Status</th></tr></thead><tbody>
<?php foreach ($bookings as $booking): ?><tr><td><?= e($booking['user_name']) ?></td><td><?= e($booking['lawyer_name']) ?></td><td><?= e(formatDateThai($booking['booking_date'])) ?> <?= e(substr((string) $booking['booking_time'], 0, 5)) ?></td><td><?= e(formatMoney($booking['price'])) ?></td><td><div><?= e($booking['lawyer_response_status']) ?></div><?php if (!empty($booking['lawyer_note'])): ?><small class="text-muted"><?= e($booking['lawyer_note']) ?></small><?php endif; ?></td><td><?= e($booking['payment_status'] ?? '-') ?></td><td><?= e($booking['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$bookings): ?><tr><td colspan="7" class="text-muted">ยังไม่มี Booking</td></tr><?php endif; ?>
</tbody></table></div>
</div></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
