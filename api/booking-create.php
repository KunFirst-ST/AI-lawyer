<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingService.php';

try {
    requireRole('user');
    verify_csrf();

    $user = currentUser();
    $caseId = (int) ($_POST['case_id'] ?? 0);
    $lawyerId = (int) ($_POST['lawyer_id'] ?? 0);
    $bookingDate = $_POST['booking_date'] ?? '';
    $bookingTime = $_POST['booking_time'] ?? '';
    $consultationType = $_POST['consultation_type'] ?? '';

    $caseStmt = db()->prepare('SELECT id FROM cases WHERE id = ? AND user_id = ? LIMIT 1');
    $caseStmt->execute([$caseId, (int) $user['id']]);
    if (!$caseStmt->fetch()) {
        jsonResponse(false, 'กรุณาเลือกเคสของคุณ', [], ['case_id' => 'invalid'], 422);
    }

    $lawyerStmt = db()->prepare('SELECT id, consultation_fee, is_available FROM lawyers WHERE id = ? AND status = "approved" LIMIT 1');
    $lawyerStmt->execute([$lawyerId]);
    $lawyer = $lawyerStmt->fetch();
    if (!$lawyer) {
        jsonResponse(false, 'ไม่พบทนายที่เลือก', [], ['lawyer_id' => 'invalid'], 422);
    }
    if ((int) $lawyer['is_available'] !== 1) {
        jsonResponse(false, 'ทนายคนนี้ปิดรับงานชั่วคราว', [], ['lawyer_id' => 'unavailable'], 422);
    }

    if (!$bookingDate || !$bookingTime || !in_array($consultationType, ['chat', 'phone', 'video', 'onsite'], true)) {
        jsonResponse(false, 'กรุณากรอกข้อมูลการจองให้ครบ', [], [], 422);
    }

    $bookingTimestamp = strtotime($bookingDate . ' ' . $bookingTime);
    if (!$bookingTimestamp || $bookingTimestamp < time()) {
        jsonResponse(false, 'กรุณาเลือกวันและเวลานัดหมายในอนาคต', [], ['booking_date' => 'past'], 422);
    }

    $conflictStmt = db()->prepare(
        'SELECT COUNT(*) FROM bookings
         WHERE lawyer_id = ? AND booking_date = ? AND booking_time = ?
           AND status IN ("pending", "confirmed")'
    );
    $conflictStmt->execute([$lawyerId, $bookingDate, $bookingTime]);
    if ((int) $conflictStmt->fetchColumn() > 0) {
        jsonResponse(false, 'ช่วงเวลานี้ถูกจองแล้ว กรุณาเลือกเวลาอื่น', [], ['booking_time' => 'taken'], 422);
    }

    $bookingId = (new BookingService())->create((int) $user['id'], [
        'case_id' => $caseId,
        'lawyer_id' => $lawyerId,
        'booking_date' => $bookingDate,
        'booking_time' => $bookingTime,
        'consultation_type' => $consultationType,
        'price' => (float) $lawyer['consultation_fee'],
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
        'redirect' => url('/user/payment.php?booking_id=' . $bookingId),
    ]);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการสร้าง Booking', [], ['detail' => $exception->getMessage()], 500);
}
