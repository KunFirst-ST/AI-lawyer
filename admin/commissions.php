<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$rows = db()->query(
    'SELECT c.*, u.name AS lawyer_name
     FROM commissions c
     JOIN lawyers l ON l.id = c.lawyer_id
     JOIN users u ON u.id = l.user_id
     ORDER BY c.created_at DESC'
)->fetchAll();
$pageTitle = 'Commission';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div><div class="col-lg-9">
<h1 class="h3 fw-bold mb-3">Commission</h1>
<div class="app-card p-3 table-responsive"><table class="table"><thead><tr><th>ทนาย</th><th>ยอดรวม</th><th>%</th><th>Commission</th><th>ทนายได้รับ</th><th>Status</th></tr></thead><tbody>
<?php foreach ($rows as $row): ?><tr><td><?= e($row['lawyer_name']) ?></td><td><?= e(formatMoney($row['gross_amount'])) ?></td><td><?= e($row['commission_percent']) ?></td><td><?= e(formatMoney($row['commission_amount'])) ?></td><td><?= e(formatMoney($row['lawyer_amount'])) ?></td><td><?= e($row['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="6" class="text-muted">ยังไม่มี commission</td></tr><?php endif; ?>
</tbody></table></div>
</div></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
