<?php
require_once __DIR__ . '/../services/ConversationService.php';
$currentPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$isActive = static fn (string $path): string => $currentPath === $path ? ' active' : '';
$chatUnread = (new \ConversationService())->unreadCount((int) currentUser()['id']);
?>
<div class="list-group app-sidebar">
    <a class="list-group-item list-group-item-action<?= e($isActive('/user/ai-chat.php')) ?>" href="<?= e(url('/user/ai-chat.php')) ?>"><i class="bi bi-chat-dots me-2"></i>ถาม AI</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/user/cases.php')) ?>" href="<?= e(url('/user/cases.php')) ?>"><i class="bi bi-folder2-open me-2"></i>เคสของฉัน</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/user/matches.php')) ?>" href="<?= e(url('/user/matches.php')) ?>"><i class="bi bi-person-check me-2"></i>ทนายที่แนะนำ</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/user/bookings.php')) ?>" href="<?= e(url('/user/bookings.php')) ?>"><i class="bi bi-calendar-check me-2"></i>การจอง</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/user/messages.php')) ?>" href="<?= e(url('/user/messages.php')) ?>"><i class="bi bi-chat-square-dots me-2"></i>แชตทนาย<?php if ($chatUnread): ?><span class="badge text-bg-primary float-end"><?= e((string) min($chatUnread, 99)) ?></span><?php endif; ?></a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/public/notifications.php')) ?>" href="<?= e(url('/public/notifications.php')) ?>"><i class="bi bi-bell me-2"></i>แจ้งเตือน</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/user/profile.php')) ?>" href="<?= e(url('/user/profile.php')) ?>"><i class="bi bi-person me-2"></i>โปรไฟล์</a>
</div>
