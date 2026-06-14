<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$stmt = db()->prepare(
    'SELECT c.*, lc.name AS category_name
     FROM cases c
     LEFT JOIN legal_categories lc ON lc.id = c.primary_category_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC'
);
$stmt->execute([$user['id']]);
$cases = $stmt->fetchAll();
$pageTitle = 'เคสของฉัน';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h1 class="h3 fw-bold mb-0">เคสของฉัน</h1>
                    <a class="btn btn-primary" href="<?= e(url('/user/ai-chat.php')) ?>">สร้างเคสจาก AI Chat</a>
                </div>
                <div class="app-card p-3">
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>เคส</th><th>หมวดหลัก</th><th>ซับซ้อน</th><th>เร่งด่วน</th><th>การจับคู่</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <td><?= e($case['title']) ?></td>
                                    <td><?= e($case['category_name'] ?? '-') ?></td>
                                    <td><?= e(levelLabel($case['complexity_level'])) ?></td>
                                    <td><?= e(levelLabel($case['urgency'])) ?></td>
                                    <td><span class="badge text-bg-light text-dark"><?= e($case['match_status']) ?></span></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/user/case-detail.php?id=' . $case['id'])) ?>">รายละเอียด</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$cases): ?>
                                <tr><td colspan="6" class="text-muted">ยังไม่มีเคส</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
