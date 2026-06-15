<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/ActivityService.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
require_once __DIR__ . '/../services/N8nPaymentVerificationService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('payment_upload', 10, 60);

    $user = currentUser();
    (new BookingWorkflowService())->ensureSchema();
    $bookingId = (int) ($_POST['booking_id'] ?? 0);
    $stmt = db()->prepare('SELECT b.id, b.case_id, b.status AS booking_status, b.lawyer_response_status, p.id AS payment_id, p.status AS payment_status, p.slip_image FROM bookings b JOIN payments p ON p.booking_id = b.id WHERE b.id = ? AND b.user_id = ? LIMIT 1');
    $stmt->execute([$bookingId, (int) $user['id']]);
    $booking = $stmt->fetch();
    if (!$booking) {
        jsonResponse(false, 'ไม่พบรายการชำระเงิน', [], [], 404);
    }
    $workflow = paymentWorkflowState($booking);
    if (!$workflow['can_upload']) {
        jsonResponse(false, $workflow['description'], [], ['payment' => 'locked'], 422);
    }

    if (empty($_FILES['slip_image'])) {
        jsonResponse(false, 'กรุณาอัปโหลดสลิป', [], [], 422);
    }

    $path = uploadFile($_FILES['slip_image'], 'slips', [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'image/bmp',
        'image/x-bmp',
        'image/x-ms-bmp',
        'image/tiff',
        'image/heic',
        'image/heif',
        'image/heic-sequence',
        'image/heif-sequence',
        'image/x-icon',
        'image/vnd.microsoft.icon',
    ]);
    $update = db()->prepare('UPDATE payments SET slip_image = ?, status = "pending" WHERE id = ?');
    $update->execute([$path, (int) $booking['payment_id']]);

    $doc = db()->prepare('INSERT INTO documents (user_id, document_type, file_path, original_name) VALUES (?, "payment_slip", ?, ?)');
    $doc->execute([(int) $user['id'], $path, $_FILES['slip_image']['name'] ?? null]);
    $activity = new ActivityService();
    $activity->caseEvent((int) $booking['case_id'], (int) $user['id'], 'payment_slip_uploaded', 'ผู้ใช้อัปโหลดสลิปเพื่อยืนยันนัดหมาย', ['booking_id' => $bookingId, 'payment_id' => (int) $booking['payment_id']]);
    $activity->audit((int) $user['id'], 'payment.upload_slip', 'payment', (int) $booking['payment_id'], ['booking_id' => $bookingId]);

    $n8nResult = null;
    $n8nVerifier = new N8nPaymentVerificationService();
    if ($n8nVerifier->isConfigured()) {
        try {
            $n8nResult = $n8nVerifier->dispatchPaymentSlip((int) $booking['payment_id']);
            if (!empty($n8nResult['sent'])) {
                $activity->caseEvent((int) $booking['case_id'], null, 'payment_sent_to_n8n', 'ระบบส่งสลิปให้ n8n ตรวจอัตโนมัติ', ['booking_id' => $bookingId, 'payment_id' => (int) $booking['payment_id']]);
                $activity->audit(null, 'payment.n8n_dispatched', 'payment', (int) $booking['payment_id'], ['booking_id' => $bookingId]);
            }
        } catch (Throwable $n8nException) {
            error_log('Unable to send payment #' . (int) $booking['payment_id'] . ' to n8n: ' . $n8nException->getMessage());
            $n8nResult = ['sent' => false, 'message' => 'ระบบตรวจอัตโนมัติไม่พร้อมใช้งาน'];
        }
    }

    $notify = new NotificationService();
    $adminMessage = $user['name'] . ' อัปโหลดสลิปสำหรับนัดหมาย #' . $bookingId;
    if (!empty($n8nResult['sent'])) {
        $adminMessage .= ' และระบบส่งให้ n8n ตรวจอัตโนมัติแล้ว';
    }
    $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
    foreach ($admins as $admin) {
        $notify->create((int) $admin['id'], 'มีสลิปรอตรวจสอบ', $adminMessage, 'payment');
    }

    $message = !empty($n8nResult['sent'])
        ? 'ส่งสลิปแล้ว ระบบกำลังตรวจสอบอัตโนมัติและจะแจ้งผลให้ทราบ'
        : 'ส่งสลิปแล้ว รอแอดมินตรวจสอบเพื่อยืนยันนัดหมาย';

    jsonResponse(true, $message, [
        'redirect' => url('/user/bookings.php'),
        'n8n_verification' => [
            'enabled' => $n8nVerifier->isConfigured(),
            'sent' => !empty($n8nResult['sent']),
        ],
    ]);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการอัปโหลดสลิป', [], ['detail' => $exception->getMessage()], 500);
}
