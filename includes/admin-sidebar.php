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
    ['file' => 'bookings.php', 'label' => 'Booking', 'icon' => 'calendar-check'],
    ['file' => 'payments.php', 'label' => 'Payment', 'icon' => 'receipt', 'badge' => $adminBadge('SELECT COUNT(*) FROM payments WHERE status = "pending" AND slip_image IS NOT NULL')],
    ['file' => 'categories.php', 'label' => 'หมวดกฎหมาย', 'icon' => 'tags'],
    ['file' => 'contact-messages.php', 'label' => 'ข้อความติดต่อ', 'icon' => 'inbox', 'badge' => $adminBadge('SELECT COUNT(*) FROM contact_messages WHERE status = "new"')],
    ['file' => 'commissions.php', 'label' => 'Commission', 'icon' => 'percent'],
    ['file' => 'ai-settings.php', 'label' => 'AI Settings', 'icon' => 'cpu'],
    ['file' => 'social-login.php', 'label' => 'Social Login', 'icon' => 'shield-check'],
    ['file' => 'reports.php', 'label' => 'Reports', 'icon' => 'bar-chart'],
];
?>
<aside class="admin-sidebar app-sidebar">
    <div class="admin-profile-card">
        <div class="admin-avatar"><i class="bi bi-shield-lock"></i></div>
        <div class="min-w-0">
            <div class="admin-profile-label">Admin only</div>
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
