<?php
$pageTitle = 'โปรไฟล์ทนาย';
require_once __DIR__ . '/../includes/header.php';

$lawyerId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT l.*, u.name, u.email, u.phone, u.profile_image,
            (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.lawyer_id = l.id) AS avg_rating,
            (SELECT COUNT(*) FROM reviews r WHERE r.lawyer_id = l.id) AS review_count
     FROM lawyers l
     JOIN users u ON u.id = l.user_id
     WHERE l.id = ? AND l.status = "approved"
     LIMIT 1'
);
$stmt->execute([$lawyerId]);
$lawyer = $stmt->fetch();

if (!$lawyer) {
    http_response_code(404);
    exit('ไม่พบโปรไฟล์ทนาย');
}

$catStmt = db()->prepare('SELECT lc.name FROM lawyer_categories lj JOIN legal_categories lc ON lc.id = lj.category_id WHERE lj.lawyer_id = ? ORDER BY lc.name');
$catStmt->execute([$lawyerId]);
$categories = $catStmt->fetchAll();

$reviewStmt = db()->prepare(
    'SELECT r.*, u.name AS user_name
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.lawyer_id = ?
     ORDER BY r.created_at DESC
     LIMIT 5'
);
$reviewStmt->execute([$lawyerId]);
$reviews = $reviewStmt->fetchAll();
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="app-card p-4 mb-3">
            <div class="row g-4">
                <div class="col-md-4">
                    <?= avatarHtml($lawyer['profile_image'] ?? null, 'person-badge', 'profile-avatar mb-3') ?>
                    <h1 class="h3 fw-bold"><?= e($lawyer['name']) ?></h1>
                    <div class="small-muted mb-2"><?= e($lawyer['province']) ?></div>
                    <?= (int) $lawyer['verified'] === 1 ? '<span class="badge text-bg-success">ยืนยันแล้ว</span>' : '' ?>
                    <span class="badge text-bg-light text-dark">รีวิว <?= e(number_format((float) $lawyer['avg_rating'], 1)) ?> (<?= e($lawyer['review_count']) ?>)</span>
                </div>
                <div class="col-md-8">
                    <h2 class="h5 fw-bold">ข้อมูลทนาย</h2>
                    <p><?= nl2br(e($lawyer['bio'])) ?></p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4"><div class="stat-card"><div class="small-muted">ประสบการณ์</div><div class="fw-bold"><?= e($lawyer['experience_years']) ?> ปี</div></div></div>
                        <div class="col-md-4"><div class="stat-card"><div class="small-muted">ค่าปรึกษาเริ่มต้น</div><div class="fw-bold"><?= e(formatMoney($lawyer['consultation_fee'])) ?></div></div></div>
                        <div class="col-md-4"><div class="stat-card"><div class="small-muted">สถานะรับงาน</div><div class="fw-bold"><?= (int) $lawyer['is_available'] === 1 ? 'เปิดรับงาน' : 'ปิดรับงาน' ?></div></div></div>
                    </div>
                    <h3 class="h6 fw-bold">ความเชี่ยวชาญ</h3>
                    <div class="mb-4">
                        <?php foreach ($categories as $category): ?>
                            <span class="badge text-bg-light text-dark"><?= e($category['name']) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($user && $user['role'] === 'user'): ?>
                        <a class="btn btn-primary" href="<?= e(url('/user/booking.php?lawyer_id=' . $lawyer['id'])) ?>">จองปรึกษา</a>
                        <a class="btn btn-outline-secondary" href="<?= e(url('/user/messages.php?lawyer_id=' . $lawyer['id'])) ?>"><i class="bi bi-chat-dots me-1"></i>เริ่มแชต</a>
                    <?php else: ?>
                        <a class="btn btn-outline-primary" href="<?= e(url('/user/login.php')) ?>">เข้าสู่ระบบผู้ใช้เพื่อจองปรึกษา</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="app-card p-4">
            <h2 class="h5 fw-bold mb-3">รีวิวล่าสุด</h2>
            <?php foreach ($reviews as $review): ?>
                <div class="border-bottom py-3">
                    <div class="fw-semibold"><?= e($review['user_name']) ?> · <?= e($review['rating']) ?>/5</div>
                    <div class="text-muted"><?= nl2br(e($review['comment'])) ?></div>
                </div>
            <?php endforeach; ?>
            <?php if (!$reviews): ?>
                <div class="text-muted">ยังไม่มีรีวิว</div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
