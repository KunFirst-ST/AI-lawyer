<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'mark_all') {
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
        $stmt->execute([(int) $user['id']]);
        flash('success', 'อ่านแจ้งเตือนทั้งหมดแล้ว');
    } elseif ($action === 'mark_one') {
        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        $stmt = db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        $stmt->execute([$notificationId, (int) $user['id']]);
        flash('success', 'อ่านแจ้งเตือนแล้ว');
    }
    redirect(url('/public/notifications.php'));
}

$stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 100');
$stmt->execute([(int) $user['id']]);
$notifications = $stmt->fetchAll();

$unreadStmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unreadStmt->execute([(int) $user['id']]);
$unread = (int) $unreadStmt->fetchColumn();

$pageTitle = 'แจ้งเตือน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3">
                <?php
                if ($user['role'] === 'admin') {
                    require __DIR__ . '/../includes/admin-sidebar.php';
                } elseif ($user['role'] === 'lawyer') {
                    require __DIR__ . '/../includes/lawyer-sidebar.php';
                } else {
                    require __DIR__ . '/../includes/user-sidebar.php';
                }
                ?>
            </div>
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">แจ้งเตือน</h1>
                        <p class="text-muted mb-0">รายการอัปเดตจากระบบ Booking, Payment, Match และข้อความติดต่อ</p>
                    </div>
                    <?php if ($unread > 0): ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <button class="btn btn-outline-primary" name="action" value="mark_all">อ่านทั้งหมด</button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="app-card p-3">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?= (int) $notification['is_read'] === 0 ? 'unread' : '' ?>">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold"><?= e($notification['title']) ?></div>
                                    <div class="small-muted"><?= e($notification['type']) ?> · <?= e($notification['created_at']) ?></div>
                                </div>
                                <?php if ((int) $notification['is_read'] === 0): ?>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="notification_id" value="<?= e($notification['id']) ?>">
                                        <button class="btn btn-sm btn-outline-primary" name="action" value="mark_one">อ่านแล้ว</button>
                                    </form>
                                <?php else: ?>
                                    <span class="badge text-bg-light text-dark align-self-start">อ่านแล้ว</span>
                                <?php endif; ?>
                            </div>
                            <div class="mt-2"><?= nl2br(e($notification['message'])) ?></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$notifications): ?>
                        <div class="text-muted">ยังไม่มีแจ้งเตือน</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
