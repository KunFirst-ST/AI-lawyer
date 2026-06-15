<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

final class N8nPaymentVerificationService
{
    private array $config;

    public function __construct(?array $config = null)
    {
        $this->config = $config ?? require __DIR__ . '/../config/n8n.php';
    }

    public function isConfigured(): bool
    {
        return !empty($this->config['payment_verification_enabled'])
            && !empty($this->config['payment_webhook_url'])
            && !empty($this->config['payment_callback_secret'])
            && function_exists('curl_init');
    }

    public function dispatchPaymentSlip(int $paymentId): array
    {
        if (!$this->isConfigured()) {
            return [
                'sent' => false,
                'message' => 'ยังไม่ได้เปิดระบบตรวจสลิปอัตโนมัติผ่าน n8n',
            ];
        }

        $payload = $this->buildPayload($paymentId);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('เตรียมข้อมูลสำหรับส่งไป n8n ไม่สำเร็จ');
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if (!empty($this->config['payment_webhook_secret'])) {
            $headers[] = 'X-N8N-Payment-Secret: ' . $this->config['payment_webhook_secret'];
        }

        $curl = curl_init((string) $this->config['payment_webhook_url']);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $this->config['timeout'],
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $status < 200 || $status >= 300) {
            return [
                'sent' => false,
                'status' => $status,
                'message' => $error ?: 'n8n ตอบกลับไม่สำเร็จ',
            ];
        }

        return [
            'sent' => true,
            'status' => $status,
            'message' => 'ส่งสลิปให้ n8n ตรวจอัตโนมัติแล้ว',
        ];
    }

    private function buildPayload(int $paymentId): array
    {
        $stmt = db()->prepare(
            'SELECT p.*, b.case_id, b.booking_date, b.booking_time, b.consultation_type,
                    u.name AS user_name, u.email AS user_email, u.phone AS user_phone,
                    lu.name AS lawyer_name, lu.email AS lawyer_email, l.id AS lawyer_id
             FROM payments p
             JOIN bookings b ON b.id = p.booking_id
             JOIN users u ON u.id = b.user_id
             JOIN lawyers l ON l.id = b.lawyer_id
             JOIN users lu ON lu.id = l.user_id
             WHERE p.id = ?
             LIMIT 1'
        );
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        if (!$payment || empty($payment['slip_image'])) {
            throw new RuntimeException('ไม่พบสลิปชำระเงินสำหรับส่งไป n8n');
        }

        $file = $this->slipFile((string) $payment['slip_image']);
        $callbackSecret = (string) ($this->config['payment_callback_secret'] ?? '');

        return [
            'source' => 'thanai_khu_dee',
            'event' => 'payment.slip_uploaded',
            'payment' => [
                'id' => (int) $payment['id'],
                'booking_id' => (int) $payment['booking_id'],
                'case_id' => (int) $payment['case_id'],
                'amount' => (float) $payment['amount'],
                'currency' => 'THB',
                'method' => (string) $payment['payment_method'],
                'status' => (string) $payment['status'],
                'created_at' => (string) $payment['created_at'],
            ],
            'booking' => [
                'date' => (string) $payment['booking_date'],
                'time' => substr((string) $payment['booking_time'], 0, 5),
                'consultation_type' => consultationTypeLabel((string) $payment['consultation_type']),
            ],
            'customer' => [
                'name' => (string) $payment['user_name'],
                'email' => (string) $payment['user_email'],
                'phone' => (string) ($payment['user_phone'] ?? ''),
            ],
            'lawyer' => [
                'id' => (int) $payment['lawyer_id'],
                'name' => (string) $payment['lawyer_name'],
                'email' => (string) $payment['lawyer_email'],
            ],
            'slip' => [
                'file_name' => basename((string) $payment['slip_image']),
                'mime_type' => $file['mime_type'],
                'size_bytes' => $file['size_bytes'],
                'base64' => base64_encode($file['contents']),
            ],
            'callback' => [
                'url' => url('/api/n8n-payment-callback.php'),
                'method' => 'POST',
                'secret_header' => 'X-N8N-Payment-Secret',
                'secret' => $callbackSecret,
                'accepted_decisions' => ['approved', 'rejected', 'manual_review'],
            ],
        ];
    }

    private function slipFile(string $relativePath): array
    {
        $uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
        $absolutePath = realpath(dirname(__DIR__) . '/' . ltrim($relativePath, '/'));
        if (!$uploadsRoot || !$absolutePath || !str_starts_with($absolutePath, $uploadsRoot) || !is_file($absolutePath)) {
            throw new RuntimeException('ไม่พบไฟล์สลิปในระบบ');
        }

        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            throw new RuntimeException('อ่านไฟล์สลิปไม่สำเร็จ');
        }

        return [
            'contents' => $contents,
            'mime_type' => (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath) ?: 'application/octet-stream',
            'size_bytes' => filesize($absolutePath) ?: strlen($contents),
        ];
    }
}
