<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/ActivityService.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('payment_upload', 10, 60);

    $user = currentUser();
    (new BookingWorkflowService())->ensureSchema();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $stmt = db()->prepare('SELECT b.id, b.case_id, b.status AS booking_status, b.lawyer_response_status, p.id AS payment_id, p.status AS payment_status FROM bookings b JOIN payments p ON p.booking_id = b.id WHERE b.id = ? AND b.user_id = ? LIMIT 1');
    $stmt->execute([$bookingId, (int) $user['id']]);
    $booking = $stmt->fetch();
    if (!$booking) {
        jsonResponse(false, 'ไม่พบรายการชำระเงิน', [], [], 404);
    }
    if ($booking['booking_status'] !== 'pending' || $booking['lawyer_response_status'] !== 'accepted' || !in_array($booking['payment_status'], ['pending', 'rejected'], true)) {
        jsonResponse(false, 'รายการนี้ไม่สามารถอัปโหลดสลิปเพิ่มได้', [], ['payment' => 'locked'], 422);
    }

    if (empty($_FILES['slip_image'])) {
        jsonResponse(false, 'กรุณาอัปโหลดสลิป', [], [], 422);
    }

    $path = uploadFile($_FILES['slip_image'], 'slips');
    $update = db()->prepare('UPDATE payments SET slip_image = ?, status = "pending" WHERE id = ?');
    $update->execute([$path, (int) $booking['payment_id']]);

    $doc = db()->prepare('INSERT INTO documents (user_id, document_type, file_path, original_name) VALUES (?, "payment_slip", ?, ?)');
    $doc->execute([(int) $user['id'], $path, $_FILES['slip_image']['name'] ?? null]);
    $activity = new ActivityService();
    $activity->caseEvent((int) $booking['case_id'], (int) $user['id'], 'payment_slip_uploaded', 'ผู้ใช้อัปโหลดสลิปเพื่อยืนยันนัดหมาย', ['booking_id' => $bookingId, 'payment_id' => (int) $booking['payment_id']]);
    $activity->audit((int) $user['id'], 'payment.upload_slip', 'payment', (int) $booking['payment_id'], ['booking_id' => $bookingId]);

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
