<?php
$adminUser = currentUser();
$currentFile = basename((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH));

$adminBadge = static function (string $sql): int {
    try {
        return (int) db()->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
};

$adminNav = [
    ['file' => 'dashboard.php', 'label' => 'แดชบอร์ด', 'icon' => 'speedometer2'],
    ['file' => 'users.php', 'label' => 'ผู้ใช้', 'icon' => 'people'],
    ['file' => 'lawyers.php', 'label' => 'ทนาย', 'icon' => 'person-badge', 'badge' => $adminBadge('SELECT COUNT(*) FROM lawyers WHERE status = "pending"')],
    ['file' => 'cases.php', 'label' => 'เคส', 'icon' => 'folder2-open', 'badge' => $adminBadge('SELECT COUNT(*) FROM cases WHERE match_status = "requested_by_user"')],
    ['file' => 'bookings.php', 'label' => 'การจอง', 'icon' => 'calendar-check', 'badge' => $adminBadge('SELECT COUNT(*) FROM bookings WHERE status = "pending" AND lawyer_response_status = "pending"')],
    ['file' => 'payments.php', 'label' => 'ชำระเงิน', 'icon' => 'receipt', 'badge' => $adminBadge('SELECT COUNT(*) FROM payments WHERE status = "pending" AND slip_image IS NOT NULL')],
    ['file' => 'categories.php', 'label' => 'หมวดกฎหมาย', 'icon' => 'tags'],
    ['file' => 'contact-messages.php', 'label' => 'ข้อความติดต่อ', 'icon' => 'inbox', 'badge' => $adminBadge('SELECT COUNT(*) FROM contact_messages WHERE status = "new"')],
    ['file' => 'commissions.php', 'label' => 'ค่าคอมมิชชั่น', 'icon' => 'percent'],
    ['file' => 'ai-settings.php', 'label' => 'ตั้งค่า AI', 'icon' => 'cpu'],
    ['file' => 'social-login.php', 'label' => 'เข้าสู่ระบบภายนอก', 'icon' => 'shield-check'],
    ['file' => 'email-notifications.php', 'label' => 'แจ้งเตือน Gmail', 'icon' => 'envelope-check', 'badge' => $adminBadge('SELECT COUNT(*) FROM email_notifications WHERE status = "failed"')],
    ['file' => 'system-status.php', 'label' => 'สถานะระบบ', 'icon' => 'activity'],
    ['file' => 'reports.php', 'label' => 'รายงาน', 'icon' => 'bar-chart'],
    ['file' => 'audit-logs.php', 'label' => 'บันทึกความปลอดภัย', 'icon' => 'journal-text'],
];
?>
<aside class="admin-sidebar app-sidebar">
    <div class="admin-profile-card">
        <div class="admin-avatar"><i class="bi bi-shield-lock"></i></div>
        <div class="min-w-0">
            <div class="admin-profile-label">ผู้ดูแลระบบเท่านั้น</div>
            <div class="admin-profile-name"><?= e($adminUser['name'] ?? 'Administrator') ?></div>
            <div class="admin-profile-email"><?= e($adminUser['email'] ?? '') ?></div>
        </div>
    </div>
    <div class="admin-nav list-group">
        <?php foreach ($adminNav as $item): ?>
            <?php $isActive = $currentFile === $item['file'] || ($item['file'] === 'lawyers.php' && $currentFile === 'lawyer-verify.php'); ?>
            <a class="list-group-item list-group-item-action <?= $isActive ? 'active' : '' ?>" href="<?= e(url('/admin/' . $item['file'])) ?>">
                <span><i class="bi bi-<?= e($item['icon']) ?>"></i><?= e($item['label']) ?></span>
                <?php if (!empty($item['badge'])): ?>
                    <em><?= e((string) min((int) $item['badge'], 99)) ?></em>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <a class="admin-public-link" href="<?= e(url('/public/notifications.php')) ?>">
        <i class="bi bi-bell"></i>
        <span>แจ้งเตือนระบบ</span>
    </a>
</aside>
