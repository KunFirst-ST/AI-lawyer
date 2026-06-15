<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$cases = db()->query(
    'SELECT c.*, u.name AS user_name, lc.name AS category_name
     FROM cases c
     JOIN users u ON u.id = c.user_id
     LEFT JOIN legal_categories lc ON lc.id = c.primary_category_id
     ORDER BY c.created_at DESC'
)->fetchAll();
$pageTitle = 'จัดการเคส';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div><div class="col-lg-9">
<h1 class="h3 fw-bold mb-3">เคสทั้งหมด</h1>
<div class="app-card p-3 table-responsive"><table class="table"><thead><tr><th>เคส</th><th>ผู้ใช้</th><th>หมวด</th><th>สถานะจับคู่</th><th>สถานะ</th></tr></thead><tbody>
<?php foreach ($cases as $case): ?><tr><td><?= e($case['title']) ?></td><td><?= e($case['user_name']) ?></td><td><?= e($case['category_name'] ?? '-') ?></td><td><?= e(matchStatusLabel($case['match_status'])) ?></td><td><?= e(caseStatusLabel($case['status'])) ?></td></tr><?php endforeach; ?>
<?php if (!$cases): ?><tr><td colspan="5" class="text-muted">ยังไม่มีเคส</td></tr><?php endif; ?>
</tbody></table></div>
</div></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
