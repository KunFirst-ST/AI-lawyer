<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/ActivityService.php';
requireRole('admin');

$activity = new ActivityService();
$activity->ensureSchema();
$logs = db()->query(
    'SELECT al.*, u.name AS actor_name, u.email AS actor_email
     FROM audit_logs al
     LEFT JOIN users u ON u.id = al.actor_user_id
     ORDER BY al.created_at DESC, al.id DESC
     LIMIT 300'
)->fetchAll();

$pageTitle = 'Audit Logs';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">Audit Logs</h1>
                        <div class="small-muted">ประวัติการทำรายการสำคัญล่าสุดของระบบ</div>
                    </div>
                    <span class="badge text-bg-light text-dark"><?= e((string) count($logs)) ?> รายการ</span>
                </div>
                <div class="app-card p-3 table-responsive">
                    <table class="table align-middle">
                        <thead>
                        <tr><th>เวลา</th><th>ผู้ดำเนินการ</th><th>Action</th><th>รายการ</th><th>IP</th><th>รายละเอียด</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                            $decoded = json_decode((string) ($log['details'] ?? ''), true);
                            $details = is_array($decoded) && $decoded ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-';
                            ?>
                            <tr>
                                <td class="text-nowrap"><?= e($log['created_at']) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= e($log['actor_name'] ?: 'system') ?></div>
                                    <div class="small-muted"><?= e($log['actor_email'] ?: '-') ?></div>
                                </td>
                                <td><span class="badge text-bg-light text-dark"><?= e($log['action']) ?></span></td>
                                <td><?= e($log['entity_type']) ?><?= $log['entity_id'] !== null ? ' #' . e((string) $log['entity_id']) : '' ?></td>
                                <td class="text-nowrap"><?= e($log['ip_address'] ?: '-') ?></td>
                                <td><small><?= e($details) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$logs): ?><tr><td colspan="6" class="text-muted">ยังไม่มี audit log</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
