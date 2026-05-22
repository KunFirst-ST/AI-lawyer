<?php

function messageType(array $message): string
{
    if (($message['message_type'] ?? '') !== '') {
        return (string) $message['message_type'];
    }

    return !empty($message['file_path']) ? uploadMimeKind((string) $message['file_path']) : 'text';
}

function callTypeLabel(?string $type): string
{
    return $type === 'video' ? 'วิดีโอคอล' : 'โทรเสียง';
}

function callTypeIcon(?string $type): string
{
    return $type === 'video' ? 'camera-video' : 'telephone';
}

function renderMessageMedia(array $message): string
{
    $type = messageType($message);
    $messageId = (int) $message['id'];

    if ($type === 'call') {
        $callUrl = (string) ($message['call_url'] ?? '');
        if ($callUrl === '') {
            return '';
        }
        $label = callTypeLabel($message['call_type'] ?? 'audio');
        $icon = callTypeIcon($message['call_type'] ?? 'audio');

        return '<div class="conversation-call-card">'
            . '<div><i class="bi bi-' . e($icon) . '"></i></div>'
            . '<span><strong>' . e($label) . '</strong><small>กดเพื่อเปิดห้องสนทนาในเบราว์เซอร์</small></span>'
            . '<a class="btn btn-sm btn-primary" href="' . e(url($callUrl)) . '" target="_blank">เข้าห้อง</a>'
            . '</div>';
    }

    if (empty($message['file_path'])) {
        return '';
    }

    $fileUrl = url('/public/file.php?message_id=' . $messageId);
    if ($type === 'image') {
        return '<a class="conversation-image-link" href="' . e($fileUrl) . '" target="_blank">'
            . '<img src="' . e($fileUrl) . '" alt="รูปภาพที่แนบในแชต">'
            . '</a>';
    }

    if ($type === 'audio') {
        return '<div class="conversation-audio">'
            . '<i class="bi bi-soundwave"></i>'
            . '<audio controls preload="metadata" src="' . e($fileUrl) . '"></audio>'
            . '</div>';
    }

    return '<a class="conversation-file-link" href="' . e($fileUrl) . '" target="_blank">'
        . '<i class="bi bi-paperclip"></i> เปิดไฟล์แนบ'
        . '</a>';
}

function serializeConversationMessage(array $message, int $currentUserId): array
{
    $messageId = (int) $message['id'];
    $type = messageType($message);
    $fileUrl = !empty($message['file_path']) ? url('/public/file.php?message_id=' . $messageId) : null;
    $callUrl = !empty($message['call_url']) ? url((string) $message['call_url']) : null;

    return [
        'id' => $messageId,
        'sender_id' => (int) $message['sender_id'],
        'receiver_id' => (int) $message['receiver_id'],
        'sender_name' => (string) ($message['sender_name'] ?? ''),
        'receiver_name' => (string) ($message['receiver_name'] ?? ''),
        'message' => (string) ($message['message'] ?? ''),
        'message_type' => $type,
        'call_type' => $message['call_type'] ?? null,
        'call_url' => $callUrl,
        'file_url' => $fileUrl,
        'created_at' => (string) ($message['created_at'] ?? ''),
        'mine' => (int) $message['sender_id'] === $currentUserId,
    ];
}
