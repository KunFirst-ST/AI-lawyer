<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$lawyers = db()->query(
    'SELECT l.*, u.name, u.email, u.phone
     FROM lawyers l
     JOIN users u ON u.id = l.user_id
     ORDER BY FIELD(l.status, "pending", "approved", "rejected", "suspended"), l.created_at DESC'
)->fetchAll();
$pageTitle = 'จัดการทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">จัดการทนาย</h1>
                <div class="app-card p-3 table-responsive">
                    <table class="table">
                        <thead><tr><th>ทนาย</th><th>จังหวัด</th><th>ใบอนุญาต</th><th>Status</th><th>Verified</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($lawyers as $lawyer): ?>
                            <tr>
                                <td><div class="fw-semibold"><?= e($lawyer['name']) ?></div><div class="small-muted"><?= e($lawyer['email']) ?> · <?= e($lawyer['phone']) ?></div></td>
                                <td><?= e($lawyer['province']) ?></td>
                                <td><?= e($lawyer['license_number']) ?></td>
                                <td><span class="badge text-bg-light text-dark"><?= e($lawyer['status']) ?></span></td>
                                <td><?= (int) $lawyer['verified'] ? 'ใช่' : 'ไม่' ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/admin/lawyer-verify.php?id=' . $lawyer['id'])) ?>">ตรวจสอบ</a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$lawyers): ?><tr><td colspan="6" class="text-muted">ยังไม่มีทนาย</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
