<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

final class PaymentService
{
    public function approve(int $paymentId, string $adminNote = ''): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT p.*, b.lawyer_id, b.user_id, b.price
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

            $pdo->prepare('UPDATE payments SET status = "approved", admin_note = ? WHERE id = ?')->execute([$adminNote, $paymentId]);
            $pdo->prepare('UPDATE bookings SET status = "confirmed" WHERE id = ?')->execute([(int) $payment['booking_id']]);

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

            $notify = new NotificationService();
            $notify->create((int) $payment['user_id'], 'ชำระเงินสำเร็จ', 'แอดมินอนุมัติสลิปแล้ว การจองของคุณได้รับการยืนยัน', 'payment');
            $lawyerUserId = $this->lawyerUserId((int) $payment['lawyer_id']);
            if ($lawyerUserId) {
                $notify->create($lawyerUserId, 'มี Booking ยืนยันแล้ว', 'ลูกค้าชำระเงินเรียบร้อยแล้ว กรุณาตรวจสอบตารางนัด', 'booking');
            }
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    public function reject(int $paymentId, string $adminNote = ''): void
    {
        $stmt = db()->prepare(
            'SELECT p.*, b.user_id
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

        $update = db()->prepare('UPDATE payments SET status = "rejected", admin_note = ? WHERE id = ?');
        $update->execute([$adminNote, $paymentId]);

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
