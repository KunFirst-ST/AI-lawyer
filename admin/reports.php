<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
$byCategory = db()->query(
    'SELECT lc.name, COUNT(cc.id) AS total
     FROM legal_categories lc
     LEFT JOIN case_categories cc ON cc.category_id = lc.id
     GROUP BY lc.id, lc.name
     ORDER BY total DESC'
)->fetchAll();
$byStatus = db()->query('SELECT status, COUNT(*) AS total FROM cases GROUP BY status')->fetchAll();
$pageTitle = 'รายงาน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div><div class="col-lg-9">
<h1 class="h3 fw-bold mb-3">รายงานภาพรวม</h1>
<div class="row g-3">
<div class="col-md-6"><div class="app-card p-3"><h2 class="h6 fw-bold">เคสตามหมวดกฎหมาย</h2><?php foreach ($byCategory as $row): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e($row['name']) ?></span><strong><?= e($row['total']) ?></strong></div><?php endforeach; ?></div></div>
<div class="col-md-6"><div class="app-card p-3"><h2 class="h6 fw-bold">เคสตามสถานะ</h2><?php foreach ($byStatus as $row): ?><div class="d-flex justify-content-between border-bottom py-2"><span><?= e(caseStatusLabel($row['status'])) ?></span><strong><?= e($row['total']) ?></strong></div><?php endforeach; ?></div></div>
</div>
</div></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
