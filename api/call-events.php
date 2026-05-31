<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CallService.php';

try {
    requireLogin();
    rateLimit('call_events', 240, 60);

    $room = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['room'] ?? ''));
    $afterId = (int) ($_GET['after_id'] ?? 0);
    if ($room === '') {
        jsonResponse(false, 'ไม่พบห้องโทร', [], ['room' => 'required'], 422);
    }

    $events = (new CallService())->events((int) currentUser()['id'], $room, $afterId);
    jsonResponse(true, 'โหลด signaling แล้ว', [
        'events' => $events,
        'last_event_id' => $events ? (int) end($events)['id'] : $afterId,
    ]);
} catch (DomainException $exception) {
    jsonResponse(false, $exception->getMessage(), [], ['call' => 'forbidden'], 403);
} catch (Throwable $exception) {
    jsonResponse(false, 'ไม่สามารถโหลด signaling ได้', [], ['detail' => $exception->getMessage()], 500);
}
