<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';
require_once __DIR__ . '/ActivityService.php';
require_once __DIR__ . '/BookingWorkflowService.php';

final class PaymentService
{
    public function approve(int $paymentId, string $adminNote = '', ?int $adminUserId = null): void
    {
        (new BookingWorkflowService())->ensureSchema();
        $activity = new ActivityService();
        $activity->ensureSchema();
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT p.*, b.lawyer_id, b.user_id, b.price, b.case_id, b.status AS booking_status, b.lawyer_response_status
                 FROM payments p
                 JOIN bookings b ON b.id = p.booking_id
                 WHERE p.id = ?
                 LIMIT 1'
            );
            $stmt->execute([$paymentId]);
            $payment = $stmt->fetch();
            if (!$payment) {
                throw new RuntimeException('ไม่พบรายการชำระเงิน');
            }
            if ($payment['status'] !== 'pending' || empty($payment['slip_image']) || $payment['booking_status'] !== 'pending' || $payment['lawyer_response_status'] !== 'accepted') {
                throw new DomainException('รายการนี้ไม่พร้อมอนุมัติ กรุณาตรวจสอบสถานะและสลิปอีกครั้ง');
            }

            $pdo->prepare('UPDATE payments SET status = "approved", admin_note = ? WHERE id = ?')->execute([$adminNote, $paymentId]);
            $pdo->prepare('UPDATE bookings SET status = "confirmed" WHERE id = ?')->execute([(int) $payment['booking_id']]);
            $pdo->prepare('UPDATE cases SET status = "in_progress" WHERE id = ? AND status = "booked"')->execute([(int) $payment['case_id']]);

            $commissionExists = $pdo->prepare('SELECT id FROM commissions WHERE booking_id = ? LIMIT 1');
            $commissionExists->execute([(int) $payment['booking_id']]);
            if (!$commissionExists->fetch()) {
                $commissionPercent = (float) setting('commission_percent', (string) app_config('commission_percent', 20));
                $gross = (float) $payment['amount'];
                $commission = round($gross * ($commissionPercent / 100), 2);
                $lawyerAmount = $gross - $commission;
                $commissionStmt = $pdo->prepare(
                    'INSERT INTO commissions (booking_id, lawyer_id, gross_amount, commission_percent, commission_amount, lawyer_amount, status)
                     VALUES (?, ?, ?, ?, ?, ?, "pending")'
                );
                $commissionStmt->execute([
                    (int) $payment['booking_id'],
                    (int) $payment['lawyer_id'],
                    $gross,
                    $commissionPercent,
                    $commission,
                    $lawyerAmount,
                ]);
            }

            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $activity->caseEvent((int) $payment['case_id'], $adminUserId, 'payment_approved', 'แอดมินอนุมัติการชำระเงินและยืนยันนัดหมาย', ['payment_id' => $paymentId, 'booking_id' => (int) $payment['booking_id']]);
        $activity->audit($adminUserId, 'payment.approve', 'payment', $paymentId, ['booking_id' => (int) $payment['booking_id']]);
        $notify = new NotificationService();
        $notify->create((int) $payment['user_id'], 'ชำระเงินสำเร็จ', 'แอดมินอนุมัติสลิปแล้ว การจองของคุณได้รับการยืนยัน', 'payment');
        $lawyerUserId = $this->lawyerUserId((int) $payment['lawyer_id']);
        if ($lawyerUserId) {
            $notify->create($lawyerUserId, 'มี Booking ยืนยันแล้ว', 'ลูกค้าชำระเงินเรียบร้อยแล้ว กรุณาตรวจสอบตารางนัด', 'booking');
        }
    }

    public function reject(int $paymentId, string $adminNote = '', ?int $adminUserId = null): void
    {
        (new BookingWorkflowService())->ensureSchema();
        $stmt = db()->prepare(
            'SELECT p.*, b.user_id, b.case_id
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             WHERE p.id = ?
             LIMIT 1'
        );
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            throw new RuntimeException('ไม่พบรายการชำระเงิน');
        }
        if ($payment['status'] !== 'pending') {
            throw new DomainException('รายการนี้ไม่สามารถปฏิเสธซ้ำได้');
        }

        $update = db()->prepare('UPDATE payments SET status = "rejected", admin_note = ? WHERE id = ?');
        $update->execute([$adminNote, $paymentId]);
        $activity = new ActivityService();
        $activity->caseEvent((int) $payment['case_id'], $adminUserId, 'payment_rejected', 'แอดมินขอให้ตรวจสอบสลิปใหม่', ['payment_id' => $paymentId, 'note' => $adminNote]);
        $activity->audit($adminUserId, 'payment.reject', 'payment', $paymentId, ['case_id' => (int) $payment['case_id']]);

        (new NotificationService())->create(
            (int) $payment['user_id'],
            'สลิปถูกปฏิเสธ',
            $adminNote !== '' ? $adminNote : 'กรุณาตรวจสอบหลักฐานชำระเงินและอัปโหลดใหม่',
            'payment'
        );
    }

    private function lawyerUserId(int $lawyerId): ?int
    {
        $stmt = db()->prepare('SELECT user_id FROM lawyers WHERE id = ? LIMIT 1');
        $stmt->execute([$lawyerId]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
