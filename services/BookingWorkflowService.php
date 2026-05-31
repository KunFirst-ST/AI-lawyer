<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityService.php';
require_once __DIR__ . '/NotificationService.php';

final class BookingWorkflowService
{
    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (db_driver() === 'sqlite') {
            $columns = array_column(db()->query('PRAGMA table_info(bookings)')->fetchAll(), 'name');
            $definitions = [
                'lawyer_response_status' => 'ADD COLUMN lawyer_response_status TEXT DEFAULT "pending"',
                'lawyer_note' => 'ADD COLUMN lawyer_note TEXT NULL',
                'responded_at' => 'ADD COLUMN responded_at TEXT NULL',
            ];
            foreach ($definitions as $column => $definition) {
                if (!in_array($column, $columns, true)) {
                    db()->exec('ALTER TABLE bookings ' . $definition);
                }
            }
        } else {
            $columns = array_column(db()->query('SHOW COLUMNS FROM bookings')->fetchAll(), 'Field');
            $alters = [];
            if (!in_array('lawyer_response_status', $columns, true)) {
                $alters[] = 'ADD COLUMN lawyer_response_status VARCHAR(20) DEFAULT "pending" AFTER status';
            }
            if (!in_array('lawyer_note', $columns, true)) {
                $alters[] = 'ADD COLUMN lawyer_note TEXT NULL AFTER lawyer_response_status';
            }
            if (!in_array('responded_at', $columns, true)) {
                $alters[] = 'ADD COLUMN responded_at DATETIME NULL AFTER lawyer_note';
            }
            if ($alters) {
                db()->exec('ALTER TABLE bookings ' . implode(', ', $alters));
            }
        }

        db()->exec(
            'UPDATE bookings
             SET lawyer_response_status = "accepted", responded_at = COALESCE(responded_at, created_at)
             WHERE status IN ("confirmed", "completed") AND lawyer_response_status = "pending"'
        );
        (new ActivityService())->ensureSchema();
    }

    public function respond(int $lawyerUserId, int $bookingId, string $action, string $note = ''): void
    {
        $this->ensureSchema();
        if (!in_array($action, ['accept', 'reject'], true)) {
            throw new DomainException('คำสั่งตอบรับ Booking ไม่ถูกต้อง');
        }

        $booking = $this->forLawyer($lawyerUserId, $bookingId);
        if (!$booking || $booking['status'] !== 'pending' || $booking['lawyer_response_status'] !== 'pending') {
            throw new DomainException('Booking นี้ไม่อยู่ในสถานะที่ตอบรับได้');
        }
        if (($booking['payment_status'] ?? '') === 'approved') {
            throw new DomainException('Booking ที่ชำระเงินแล้วต้องให้แอดมินเป็นผู้ดำเนินการ');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $activity = new ActivityService();
            if ($action === 'accept') {
                $pdo->prepare(
                    'UPDATE bookings
                     SET lawyer_response_status = "accepted", lawyer_note = ?, responded_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([$note, $bookingId]);
                $activity->caseEvent((int) $booking['case_id'], $lawyerUserId, 'booking_accepted', 'ทนายตอบรับนัดหมายแล้ว', ['booking_id' => $bookingId]);
                $activity->audit($lawyerUserId, 'booking.accept', 'booking', $bookingId, ['case_id' => (int) $booking['case_id']]);
            } else {
                $pdo->prepare(
                    'UPDATE bookings
                     SET status = "cancelled", lawyer_response_status = "rejected", lawyer_note = ?, responded_at = CURRENT_TIMESTAMP
                     WHERE id = ?'
                )->execute([$note, $bookingId]);
                $pdo->prepare('UPDATE cases SET status = "matched" WHERE id = ? AND status = "booked"')->execute([(int) $booking['case_id']]);
                $pdo->prepare('UPDATE case_matches SET status = "rejected" WHERE case_id = ? AND lawyer_id = ?')->execute([(int) $booking['case_id'], (int) $booking['lawyer_id']]);
                $activity->caseEvent((int) $booking['case_id'], $lawyerUserId, 'booking_rejected', 'ทนายไม่สะดวกรับนัดหมาย', ['booking_id' => $bookingId, 'note' => $note]);
                $activity->audit($lawyerUserId, 'booking.reject', 'booking', $bookingId, ['case_id' => (int) $booking['case_id']]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        $notify = new NotificationService();
        $notify->create(
            (int) $booking['user_id'],
            $action === 'accept' ? 'ทนายตอบรับนัดหมายแล้ว' : 'ทนายไม่สะดวกรับนัดหมาย',
            $action === 'accept'
                ? 'Booking #' . $bookingId . ' ได้รับการตอบรับแล้ว กรุณาชำระเงินเพื่อยืนยันนัดหมาย'
                : 'Booking #' . $bookingId . ' ถูกปฏิเสธ' . ($note !== '' ? ': ' . $note : ' กรุณาเลือกทนายคนอื่นจากผล Match'),
            'booking'
        );
    }

    public function complete(int $lawyerUserId, int $bookingId): void
    {
        $this->ensureSchema();
        $booking = $this->forLawyer($lawyerUserId, $bookingId);
        if (!$booking || $booking['status'] !== 'confirmed') {
            throw new DomainException('สามารถปิดงานได้เฉพาะ Booking ที่ยืนยันแล้วเท่านั้น');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE bookings SET status = "completed" WHERE id = ?')->execute([$bookingId]);
            $pdo->prepare('UPDATE cases SET status = "closed" WHERE id = ?')->execute([(int) $booking['case_id']]);
            $activity = new ActivityService();
            $activity->caseEvent((int) $booking['case_id'], $lawyerUserId, 'case_closed', 'ทนายปิดงานและจบการให้คำปรึกษา', ['booking_id' => $bookingId]);
            $activity->audit($lawyerUserId, 'booking.complete', 'booking', $bookingId, ['case_id' => (int) $booking['case_id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        (new NotificationService())->create(
            (int) $booking['user_id'],
            'การปรึกษาเสร็จสิ้นแล้ว',
            'Booking สำหรับเคส "' . ($booking['case_title'] ?: 'ไม่ระบุชื่อเคส') . '" ถูกปิดงานแล้ว คุณสามารถให้รีวิวทนายได้',
            'booking'
        );
    }

    public function cancelByUser(int $userId, int $bookingId): void
    {
        $this->ensureSchema();
        $stmt = db()->prepare(
            'SELECT b.*, p.status AS payment_status, l.user_id AS lawyer_user_id
             FROM bookings b
             JOIN lawyers l ON l.id = b.lawyer_id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE b.id = ? AND b.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$bookingId, $userId]);
        $booking = $stmt->fetch();
        if (!$booking || $booking['status'] !== 'pending' || ($booking['payment_status'] ?? '') === 'approved') {
            throw new DomainException('Booking นี้ไม่สามารถยกเลิกเองได้ กรุณาติดต่อผู้ดูแลระบบ');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE bookings SET status = "cancelled" WHERE id = ?')->execute([$bookingId]);
            $pdo->prepare('UPDATE cases SET status = "matched" WHERE id = ? AND status = "booked"')->execute([(int) $booking['case_id']]);
            $pdo->prepare('UPDATE case_matches SET status = "suggested" WHERE case_id = ? AND lawyer_id = ? AND status = "selected"')->execute([(int) $booking['case_id'], (int) $booking['lawyer_id']]);
            $activity = new ActivityService();
            $activity->caseEvent((int) $booking['case_id'], $userId, 'booking_cancelled', 'ผู้ใช้ยกเลิกคำขอนัดหมาย', ['booking_id' => $bookingId]);
            $activity->audit($userId, 'booking.cancel', 'booking', $bookingId, ['case_id' => (int) $booking['case_id']]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        (new NotificationService())->create((int) $booking['lawyer_user_id'], 'Booking ถูกยกเลิก', 'ผู้ใช้ยกเลิก Booking #' . $bookingId, 'booking');
    }

    private function forLawyer(int $lawyerUserId, int $bookingId): ?array
    {
        $stmt = db()->prepare(
            'SELECT b.*, p.status AS payment_status, c.title AS case_title
             FROM bookings b
             JOIN lawyers l ON l.id = b.lawyer_id
             JOIN cases c ON c.id = b.case_id
             LEFT JOIN payments p ON p.booking_id = b.id
             WHERE b.id = ? AND l.user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$bookingId, $lawyerUserId]);
        return $stmt->fetch() ?: null;
    }
}
