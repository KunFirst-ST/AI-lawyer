<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lawyer');
$user = currentUser();
$lawyerStmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyerId = (int) ($lawyerStmt->fetchColumn() ?: 0);
$stmt = db()->prepare('SELECT r.*, u.name AS user_name FROM reviews r JOIN users u ON u.id = r.user_id WHERE r.lawyer_id = ? ORDER BY r.created_at DESC');
$stmt->execute([$lawyerId]);
$reviews = $stmt->fetchAll();
$pageTitle = 'รีวิว';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">รีวิว</h1>
                <div class="app-card p-3">
                    <?php foreach ($reviews as $review): ?>
                        <div class="border-bottom py-3"><div class="fw-semibold"><?= e($review['user_name']) ?> · <?= e((string) $review['rating']) ?>/5</div><div><?= nl2br(e($review['comment'])) ?></div></div>
                    <?php endforeach; ?>
                    <?php if (!$reviews): ?><div class="text-muted">ยังไม่มีรีวิว</div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
