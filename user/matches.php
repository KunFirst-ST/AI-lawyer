<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$caseId = isset($_GET['case_id']) ? (int) $_GET['case_id'] : null;
$params = [$user['id']];
$where = 'c.user_id = ?';
if ($caseId) {
    $where .= ' AND c.id = ?';
    $params[] = $caseId;
}

$stmt = db()->prepare(
    "SELECT cm.*, c.title AS case_title, l.province, l.consultation_fee, l.verified, l.is_available, u.name, u.profile_image,
            (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.lawyer_id = l.id) AS avg_rating
     FROM case_matches cm
     JOIN cases c ON c.id = cm.case_id
     JOIN lawyers l ON l.id = cm.lawyer_id
     JOIN users u ON u.id = l.user_id
     WHERE {$where} AND cm.status IN ('suggested', 'viewed', 'selected')
     ORDER BY cm.match_score DESC, cm.created_at DESC"
);
$stmt->execute($params);
$matches = $stmt->fetchAll();

$pageTitle = 'ทนายที่ Match';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">ทนายที่ Match</h1>
                <div class="row g-3">
                    <?php foreach ($matches as $match): ?>
                        <?php
                        $catStmt = db()->prepare('SELECT lc.name FROM lawyer_categories lj JOIN legal_categories lc ON lc.id = lj.category_id WHERE lj.lawyer_id = ? ORDER BY lc.name');
                        $catStmt->execute([$match['lawyer_id']]);
                        $cats = array_column($catStmt->fetchAll(), 'name');
                        ?>
                        <div class="col-md-6">
                            <div class="app-card p-3 h-100">
                                <div class="d-flex gap-3">
                                    <?= avatarHtml($match['profile_image'] ?? null, 'person-badge') ?>
                                    <div>
                                        <h2 class="h6 fw-bold mb-1"><?= e($match['name']) ?></h2>
                                        <div class="small-muted"><?= e($match['province']) ?> · <?= e(formatMoney($match['consultation_fee'])) ?></div>
                                        <div class="mt-2"><span class="badge text-bg-primary">Match <?= e((string) round($match['match_score'])) ?> คะแนน</span> <?= (int) $match['verified'] === 1 ? '<span class="badge text-bg-success">Verified</span>' : '' ?></div>
                                    </div>
                                </div>
                                <div class="small-muted mt-3">เคส: <?= e($match['case_title']) ?></div>
                                <p class="mt-2"><?= e($match['match_reason']) ?></p>
                                <div class="mb-3">
                                    <?php foreach ($cats as $cat): ?><span class="badge text-bg-light text-dark"><?= e($cat) ?></span><?php endforeach; ?>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/public/lawyer-detail.php?id=' . $match['lawyer_id'])) ?>">ดูโปรไฟล์</a>
                                    <a class="btn btn-sm btn-primary" href="<?= e(url('/user/booking.php?case_id=' . $match['case_id'] . '&lawyer_id=' . $match['lawyer_id'])) ?>">จองปรึกษา</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('/user/messages.php?lawyer_id=' . $match['lawyer_id'] . '&case_id=' . $match['case_id'])) ?>">แชต</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$matches): ?>
                        <div class="col-12"><div class="alert alert-info">ยังไม่มีรายการ Match ทนาย</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
