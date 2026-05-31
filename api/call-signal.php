<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CallService.php';

try {
    requireLogin();
    verify_csrf();
    rateLimit('call_signal', 500, 60);

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonResponse(false, 'ข้อมูล signaling ไม่ถูกต้อง', [], ['body' => 'invalid'], 422);
    }

    $room = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($input['room'] ?? ''));
    $type = (string) ($input['type'] ?? '');
    $payload = is_array($input['payload'] ?? null) ? $input['payload'] : [];
    if ($room === '') {
        jsonResponse(false, 'ไม่พบห้องโทร', [], ['room' => 'required'], 422);
    }

    $id = (new CallService())->sendSignal((int) currentUser()['id'], $room, $type, $payload);
    jsonResponse(true, 'ส่ง signaling แล้ว', ['id' => $id]);
} catch (DomainException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['call' => 'forbidden'], 403);
} catch (Throwable $exception) {
    jsonResponse(false, 'ไม่สามารถส่ง signaling ได้', [], ['detail' => $exception->getMessage()], 500);
}
