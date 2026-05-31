<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('booking_create', 10, 60);

    $user = currentUser();
    $caseId = (int) ($_POST['case_id'] ?? 0);
    $lawyerId = (int) ($_POST['lawyer_id'] ?? 0);
    $bookingDate = $_POST['booking_date'] ?? '';
    $bookingTime = $_POST['booking_time'] ?? '';
    $consultationType = $_POST['consultation_type'] ?? '';

    $bookingId = (new BookingService())->create((int) $user['id'], [
        'case_id' => $caseId,
        'lawyer_id' => $lawyerId,
        'booking_date' => $bookingDate,
        'booking_time' => $bookingTime,
        'consultation_type' => $consultationType,
    ]);

    if (!empty($_FILES['case_document'])) {
        $path = uploadFile($_FILES['case_document'], 'case_documents');
        if ($path) {
            $stmt = db()->prepare('INSERT INTO documents (user_id, case_id, document_type, file_path, original_name) VALUES (?, ?, "case_document", ?, ?)');
            $stmt->execute([(int) $user['id'], $caseId, $path, $_FILES['case_document']['name'] ?? null]);
        }
    }

    jsonResponse(true, 'สร้าง Booking แล้ว กรุณาชำระเงินและอัปโหลดสลิป', [
        'booking_id' => $bookingId,
        'redirect' => url('/user/bookings.php'),
    ]);
} catch (DomainException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['booking' => 'invalid'], 422);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการสร้าง Booking', [], ['detail' => $exception->getMessage()], 500);
}
