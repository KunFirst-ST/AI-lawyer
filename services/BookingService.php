<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

final class BookingService
{
    public function create(int $userId, array $data): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO bookings (case_id, user_id, lawyer_id, booking_date, booking_time, consultation_type, price, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $stmt->execute([
                (int) $data['case_id'],
                $userId,
                (int) $data['lawyer_id'],
                $data['booking_date'],
                $data['booking_time'],
                $data['consultation_type'],
                (float) $data['price'],
            ]);
            $bookingId = (int) $pdo->lastInsertId();

            $paymentStmt = $pdo->prepare('INSERT INTO payments (booking_id, amount, status) VALUES (?, ?, "pending")');
            $paymentStmt->execute([$bookingId, (float) $data['price']]);

            $pdo->prepare('UPDATE cases SET status = "booked" WHERE id = ? AND user_id = ?')->execute([(int) $data['case_id'], $userId]);
            $pdo->commit();

            $this->notifyAfterCreate($bookingId, $userId);

            return $bookingId;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function notifyAfterCreate(int $bookingId, int $userId): void
    {
        $notify = new NotificationService();
        $notify->create($userId, 'สร้าง Booking แล้ว', 'กรุณาชำระเงินและอัปโหลดสลิปสำหรับ Booking #' . $bookingId, 'booking');

        $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
        foreach ($admins as $admin) {
            $notify->create((int) $admin['id'], 'มี Booking ใหม่', 'ผู้ใช้สร้าง Booking #' . $bookingId . ' และกำลังรอชำระเงิน', 'booking');
        }
    }
}
