<?php
require_once __DIR__ . '/auth.php';
if (!headers_sent()) {
    header('Permissions-Policy: microphone=(self), camera=(self)');
}
$pageTitle = $pageTitle ?? app_config('app_name');
$user = currentUser();
$currentPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$unreadNotifications = 0;
if ($user) {
    try {
        $notificationStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $notificationStmt->execute([(int) $user['id']]);
        $unreadNotifications = (int) $notificationStmt->fetchColumn();
    } catch (Throwable) {
        $unreadNotifications = 0;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?></title>
    <link href="<?= e(url('/assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('/assets/vendor/bootstrap-icons/bootstrap-icons.css')) ?>" rel="stylesheet">
    <link href="<?= e(url('/assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="<?= e(url('/public/index.php')) ?>">
            <i class="bi bi-shield-check me-1"></i> AI Lawyer
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="เปิดเมนู">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= $currentPath === '/public/lawyers.php' ? 'active' : '' ?>" href="<?= e(url('/public/lawyers.php')) ?>">ค้นหาทนาย</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === '/public/about.php' ? 'active' : '' ?>" href="<?= e(url('/public/about.php')) ?>">เกี่ยวกับระบบ</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === '/public/faq.php' ? 'active' : '' ?>" href="<?= e(url('/public/faq.php')) ?>">FAQ</a></li>
                <li class="nav-item"><a class="nav-link <?= $currentPath === '/public/contact.php' ? 'active' : '' ?>" href="<?= e(url('/public/contact.php')) ?>">ติดต่อ</a></li>
            </ul>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if ($user): ?>
                    <a class="btn btn-light btn-sm position-relative" href="<?= e(url('/public/notifications.php')) ?>" title="แจ้งเตือน" aria-label="แจ้งเตือน">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadNotifications > 0): ?>
                            <span class="notification-badge"><?= e((string) min($unreadNotifications, 99)) ?></span>
                        <?php endif; ?>
                    </a>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e(dashboardPathForRole($user['role'])) ?>"><?= e($user['role'] === 'user' ? 'ถาม AI' : 'แดชบอร์ด') ?></a>
                    <a class="btn btn-light btn-sm" href="<?= e(url('/public/logout.php')) ?>">ออกจากระบบ</a>
                <?php else: ?>
                    <a class="btn btn-outline-primary btn-sm" href="<?= e(url('/public/portals.php')) ?>">เลือกพอร์ทัล</a>
                    <a class="btn btn-primary btn-sm" href="<?= e(url('/public/register.php')) ?>">สมัครผู้ใช้</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<main>
<?php foreach (flash_messages() as $flash): ?>
    <div class="container mt-3">
        <div class="alert alert-<?= e($flash['type']) ?> mb-0"><?= e($flash['message']) ?></div>
    </div>
<?php endforeach; ?>
