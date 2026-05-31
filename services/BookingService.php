<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/ActivityService.php';
require_once __DIR__ . '/BookingWorkflowService.php';

final class BookingService
{
    public function create(int $userId, array $data): int
    {
        (new BookingWorkflowService())->ensureSchema();
        $activity = new ActivityService();
        $activity->ensureSchema();
        $caseId = (int) ($data['case_id'] ?? 0);
        $lawyerId = (int) ($data['lawyer_id'] ?? 0);
        $bookingDate = trim((string) ($data['booking_date'] ?? ''));
        $bookingTime = trim((string) ($data['booking_time'] ?? ''));
        $consultationType = (string) ($data['consultation_type'] ?? '');
        if (!$caseId || !$lawyerId || !$bookingDate || !$bookingTime || !in_array($consultationType, ['chat', 'phone', 'video', 'onsite'], true)) {
            throw new DomainException('กรุณากรอกข้อมูลการจองให้ครบ');
        }

        $bookingTimestamp = strtotime($bookingDate . ' ' . $bookingTime);
        if (!$bookingTimestamp || $bookingTimestamp < time()) {
            throw new DomainException('กรุณาเลือกวันและเวลานัดหมายในอนาคต');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $caseStmt = $pdo->prepare(
                'SELECT id
                 FROM cases
                 WHERE id = ? AND user_id = ? AND user_wants_lawyer = 1 AND status = "matched"
                 LIMIT 1'
            );
            $caseStmt->execute([$caseId, $userId]);
            if (!$caseStmt->fetchColumn()) {
                throw new DomainException('เคสนี้ยังไม่พร้อมจอง กรุณาเลือกทนายจากผล Match ของเคส');
            }

            $lawyerStmt = $pdo->prepare(
                'SELECT l.consultation_fee
                 FROM lawyers l
                 JOIN users u ON u.id = l.user_id
                 WHERE l.id = ? AND l.status = "approved" AND l.is_available = 1 AND u.status = "active"
                 LIMIT 1'
            );
            $lawyerStmt->execute([$lawyerId]);
            $price = $lawyerStmt->fetchColumn();
            if ($price === false) {
                throw new DomainException('ทนายที่เลือกยังไม่พร้อมรับงาน');
            }

            $matchStmt = $pdo->prepare(
                'SELECT id
                 FROM case_matches
                 WHERE case_id = ? AND lawyer_id = ? AND status IN ("suggested", "viewed", "selected")
                 LIMIT 1'
            );
            $matchStmt->execute([$caseId, $lawyerId]);
            if (!$matchStmt->fetchColumn()) {
                throw new DomainException('กรุณาเลือกทนายจากผล Match ของเคสนี้');
            }

            $existingStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM bookings
                 WHERE case_id = ? AND user_id = ? AND status IN ("pending", "confirmed", "completed")'
            );
            $existingStmt->execute([$caseId, $userId]);
            if ((int) $existingStmt->fetchColumn() > 0) {
                throw new DomainException('เคสนี้มี Booking อยู่แล้ว');
            }

            $conflictStmt = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM bookings
                 WHERE lawyer_id = ? AND booking_date = ? AND booking_time = ?
                   AND status IN ("pending", "confirmed")'
            );
            $conflictStmt->execute([$lawyerId, $bookingDate, $bookingTime]);
            if ((int) $conflictStmt->fetchColumn() > 0) {
                throw new DomainException('ช่วงเวลานี้ถูกจองแล้ว กรุณาเลือกเวลาอื่น');
            }

            $stmt = $pdo->prepare(
                'INSERT INTO bookings (case_id, user_id, lawyer_id, booking_date, booking_time, consultation_type, price, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([
                $caseId,
                $userId,
                $lawyerId,
                $bookingDate,
                $bookingTime,
                $consultationType,
                (float) $price,
            ]);
            $bookingId = (int) $pdo->lastInsertId();

            $paymentStmt = $pdo->prepare('INSERT INTO payments (booking_id, amount, status) VALUES (?, ?, "pending")');
            $paymentStmt->execute([$bookingId, (float) $price]);

            $pdo->prepare('UPDATE case_matches SET status = "selected" WHERE case_id = ? AND lawyer_id = ?')->execute([$caseId, $lawyerId]);
            $pdo->prepare('UPDATE cases SET status = "booked" WHERE id = ? AND user_id = ?')->execute([$caseId, $userId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $activity->caseEvent($caseId, $userId, 'booking_requested', 'ผู้ใช้ส่งคำขอนัดหมายให้ทนาย', [
            'booking_id' => $bookingId,
            'lawyer_id' => $lawyerId,
            'consultation_type' => $consultationType,
        ]);
        $activity->audit($userId, 'booking.create', 'booking', $bookingId, ['case_id' => $caseId, 'lawyer_id' => $lawyerId]);
        $this->notifyAfterCreate($bookingId, $userId, $lawyerId);

        return $bookingId;
    }

    private function notifyAfterCreate(int $bookingId, int $userId, int $lawyerId): void
    {
        $notify = new NotificationService();
        $notify->create($userId, 'ส่งคำขอนัดหมายแล้ว', 'ระบบส่ง Booking #' . $bookingId . ' ให้ทนายแล้ว กรุณารอทนายตอบรับก่อนชำระเงิน', 'booking');

        $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
        foreach ($admins as $admin) {
            $notify->create((int) $admin['id'], 'มี Booking ใหม่', 'ผู้ใช้สร้าง Booking #' . $bookingId . ' และกำลังรอทนายตอบรับ', 'booking');
        }

        $lawyerStmt = db()->prepare('SELECT user_id FROM lawyers WHERE id = ? LIMIT 1');
        $lawyerStmt->execute([$lawyerId]);
        $lawyerUserId = $lawyerStmt->fetchColumn();
        if ($lawyerUserId !== false) {
            $notify->create((int) $lawyerUserId, 'มีคำขอ Booking ใหม่', 'ลูกความเลือกคุณสำหรับ Booking #' . $bookingId . ' กรุณาตรวจสอบและตอบรับนัดหมาย', 'booking');
        }
    }
}
