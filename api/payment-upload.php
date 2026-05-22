<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('payment_upload', 10, 60);

    $user = currentUser();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $stmt = db()->prepare('SELECT b.id, p.id AS payment_id FROM bookings b JOIN payments p ON p.booking_id = b.id WHERE b.id = ? AND b.user_id = ? LIMIT 1');
    $stmt->execute([$bookingId, (int) $user['id']]);
    $booking = $stmt->fetch();
    if (!$booking) {
        jsonResponse(false, 'ไม่พบรายการชำระเงิน', [], [], 404);
    }

    if (empty($_FILES['slip_image'])) {
        jsonResponse(false, 'กรุณาอัปโหลดสลิป', [], [], 422);
    }

    $path = uploadFile($_FILES['slip_image'], 'slips');
    $update = db()->prepare('UPDATE payments SET slip_image = ?, status = "pending" WHERE id = ?');
    $update->execute([$path, (int) $booking['payment_id']]);

    $doc = db()->prepare('INSERT INTO documents (user_id, document_type, file_path, original_name) VALUES (?, "payment_slip", ?, ?)');
    $doc->execute([(int) $user['id'], $path, $_FILES['slip_image']['name'] ?? null]);

    $notify = new NotificationService();
    $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
    foreach ($admins as $admin) {
        $notify->create((int) $admin['id'], 'มีสลิปรอตรวจสอบ', $user['name'] . ' อัปโหลดสลิปสำหรับ Booking #' . $bookingId, 'payment');
    }

    jsonResponse(true, 'อัปโหลดสลิปแล้ว รอแอดมินตรวจสอบ', [
        'redirect' => url('/user/bookings.php'),
    ]);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการอัปโหลดสลิป', [], ['detail' => $exception->getMessage()], 500);
}
