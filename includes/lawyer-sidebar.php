<?php
$currentPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$isActive = static fn (string $path): string => $currentPath === $path ? ' active' : '';
?>
<div class="list-group app-sidebar">
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/dashboard.php')) ?>" href="<?= e(url('/lawyer/dashboard.php')) ?>"><i class="bi bi-speedometer2 me-2"></i>แดชบอร์ด</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/profile.php')) ?>" href="<?= e(url('/lawyer/profile.php')) ?>"><i class="bi bi-person-badge me-2"></i>โปรไฟล์ทนาย</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/cases.php')) ?>" href="<?= e(url('/lawyer/cases.php')) ?>"><i class="bi bi-briefcase me-2"></i>เคสที่เสนอ</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/bookings.php')) ?>" href="<?= e(url('/lawyer/bookings.php')) ?>"><i class="bi bi-calendar-check me-2"></i>การจอง</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/messages.php')) ?>" href="<?= e(url('/lawyer/messages.php')) ?>"><i class="bi bi-chat-square-dots me-2"></i>แชตลูกความ</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/public/notifications.php')) ?>" href="<?= e(url('/public/notifications.php')) ?>"><i class="bi bi-bell me-2"></i>แจ้งเตือน</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/earnings.php')) ?>" href="<?= e(url('/lawyer/earnings.php')) ?>"><i class="bi bi-wallet2 me-2"></i>รายได้</a>
    <a class="list-group-item list-group-item-action<?= e($isActive('/lawyer/reviews.php')) ?>" href="<?= e(url('/lawyer/reviews.php')) ?>"><i class="bi bi-star me-2"></i>รีวิว</a>
</div>
