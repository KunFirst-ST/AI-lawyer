<?php

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../services/ActivityService.php';
require_once __DIR__ . '/../services/NotificationService.php';

function paymentCallbackValue(array $payload, string $key, mixed $default = null): mixed
{
    return $payload[$key] ?? ($payload['payment'][$key] ?? $default);
}

function paymentCallbackNote(array $payload, string $default): string
{
    $note = trim((string) ($payload['note'] ?? $payload['reason'] ?? $payload['message'] ?? ''));
    $confidence = trim((string) ($payload['confidence'] ?? ''));
    $parts = [$note !== '' ? $note : $default];
    if ($confidence !== '') {
        $parts[] = 'ความมั่นใจจาก n8n: ' . $confidence;
    }

    return implode(' | ', $parts);
}

function markPaymentManualReview(int $paymentId, string $note): void
{
    $stmt = db()->prepare(
        'SELECT p.id, p.booking_id, b.case_id
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

    db()->prepare('UPDATE payments SET admin_note = ? WHERE id = ? AND status = "pending"')
        ->execute([$note, $paymentId]);

    $activity = new ActivityService();
    $activity->caseEvent((int) $payment['case_id'], null, 'payment_manual_review', 'n8n ขอให้แอดมินตรวจสลิปเพิ่มเติม', [
        'payment_id' => $paymentId,
        'booking_id' => (int) $payment['booking_id'],
        'note' => $note,
    ]);
    $activity->audit(null, 'payment.n8n_manual_review', 'payment', $paymentId, ['booking_id' => (int) $payment['booking_id']]);

    $notify = new NotificationService();
    $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
    foreach ($admins as $admin) {
        $notify->create((int) $admin['id'], 'n8n ขอให้ตรวจสลิปเพิ่มเติม', 'รายการชำระเงิน #' . $paymentId . ' ต้องให้แอดมินตรวจต่อ: ' . $note, 'payment');
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        jsonResponse(false, 'รองรับเฉพาะคำขอแบบ POST', [], [], 405);
    }

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        jsonResponse(false, 'รูปแบบข้อมูลจาก n8n ไม่ถูกต้อง', [], [], 422);
    }

    $config = require __DIR__ . '/../config/n8n.php';
    $expectedSecret = (string) ($config['payment_callback_secret'] ?? '');
    if ($expectedSecret === '') {
        jsonResponse(false, 'ยังไม่ได้ตั้งค่า secret สำหรับ n8n callback', [], [], 503);
    }

    $providedSecret = (string) ($_SERVER['HTTP_X_N8N_PAYMENT_SECRET'] ?? ($payload['secret'] ?? ''));
    if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
        jsonResponse(false, 'secret สำหรับ n8n callback ไม่ถูกต้อง', [], [], 401);
    }

    $paymentId = (int) paymentCallbackValue($payload, 'id', $payload['payment_id'] ?? 0);
    if ($paymentId <= 0) {
        jsonResponse(false, 'ไม่พบ payment id ในผลตรวจจาก n8n', [], [], 422);
    }

    $decision = strtolower(trim((string) ($payload['decision'] ?? $payload['status'] ?? $payload['result'] ?? '')));
    $decision = match ($decision) {
        'approve', 'approved', 'pass', 'passed', 'valid', 'success' => 'approved',
        'reject', 'rejected', 'fail', 'failed', 'invalid' => 'rejected',
        'manual', 'manual_review', 'review', 'needs_review' => 'manual_review',
        default => $decision,
    };

    $service = new PaymentService();
    if ($decision === 'approved') {
        $service->approve($paymentId, paymentCallbackNote($payload, 'ตรวจผ่านอัตโนมัติโดย n8n'), null);
    } elseif ($decision === 'rejected') {
        $service->reject($paymentId, paymentCallbackNote($payload, 'ผลตรวจอัตโนมัติไม่ผ่าน กรุณาอัปโหลดสลิปใหม่'), null);
    } elseif ($decision === 'manual_review') {
        markPaymentManualReview($paymentId, paymentCallbackNote($payload, 'n8n ไม่มั่นใจผลตรวจ กรุณาให้แอดมินตรวจเพิ่มเติม'));
    } else {
        jsonResponse(false, 'ผลตรวจจาก n8n ต้องเป็น approved, rejected หรือ manual_review', [], [], 422);
    }

    jsonResponse(true, 'รับผลตรวจจาก n8n แล้ว', [
        'payment_id' => $paymentId,
        'decision' => $decision,
    ]);
} catch (DomainException $exception) {
    jsonResponse(false, $exception->getMessage(), [], [], 409);
} catch (Throwable $exception) {
    jsonResponse(false, 'ประมวลผล callback จาก n8n ไม่สำเร็จ', [], ['detail' => $exception->getMessage()], 500);
}
