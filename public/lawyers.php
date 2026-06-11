<?php
$pageTitle = 'ค้นหาทนาย';
require_once __DIR__ . '/../includes/header.php';

$category = $_GET['category'] ?? '';
$province = trim($_GET['province'] ?? '');
$params = [];
$where = ['l.status = "approved"'];
$joinCategory = '';

if ($category !== '') {
    $joinCategory = 'JOIN lawyer_categories lcf ON lcf.lawyer_id = l.id JOIN legal_categories fcat ON fcat.id = lcf.category_id';
    $where[] = 'fcat.slug = ?';
    $params[] = $category;
}
if ($province !== '') {
    $where[] = 'l.province = ?';
    $params[] = $province;
}

$stmt = db()->prepare(
    "SELECT l.*, u.name, u.profile_image,
            (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.lawyer_id = l.id) AS avg_rating
     FROM lawyers l
     JOIN users u ON u.id = l.user_id
     {$joinCategory}
     WHERE " . implode(' AND ', $where) . "
     ORDER BY l.verified DESC, avg_rating DESC, l.created_at DESC"
);
$stmt->execute($params);
$lawyers = $stmt->fetchAll();
$categories = db()->query('SELECT name, slug FROM legal_categories ORDER BY name')->fetchAll();
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <h1 class="h3 fw-bold mb-1">ค้นหาทนาย</h1>
                <p class="text-muted mb-0">แสดงเฉพาะทนายที่ผ่านการอนุมัติจากแอดมิน</p>
            </div>
            <a class="btn btn-primary" href="<?= e(url('/user/ai-chat.php')) ?>">ให้ AI ช่วยวิเคราะห์ก่อน</a>
        </div>
        <form class="app-card p-3 mb-3 row g-3">
            <div class="col-md-5">
                <label class="form-label">หมวดกฎหมาย</label>
                <select class="form-select" name="category">
                    <option value="">ทุกหมวด</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat['slug']) ?>" <?= $category === $cat['slug'] ? 'selected' : '' ?>><?= e($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-5"><label class="form-label">จังหวัด</label><input class="form-control" name="province" value="<?= e($province) ?>"></div>
            <div class="col-md-2 d-flex align-items-end"><button class="btn btn-outline-primary w-100">ค้นหา</button></div>
        </form>
        <div class="row g-3">
            <?php foreach ($lawyers as $lawyer): ?>
                <?php
                $catStmt = db()->prepare('SELECT lc.name FROM lawyer_categories lj JOIN legal_categories lc ON lc.id = lj.category_id WHERE lj.lawyer_id = ? ORDER BY lc.name');
                $catStmt->execute([$lawyer['id']]);
                $lawyerCategories = array_column($catStmt->fetchAll(), 'name');
                ?>
                <div class="col-md-6 col-xl-4">
                    <div class="app-card p-3 h-100">
                        <div class="d-flex gap-3">
                            <?= avatarHtml($lawyer['profile_image'] ?? null, 'person-badge') ?>
                            <div class="flex-grow-1">
                                <h2 class="h6 fw-bold mb-1"><?= e($lawyer['name']) ?></h2>
                                <div class="small-muted"><?= e($lawyer['province']) ?> · <?= e(formatMoney($lawyer['consultation_fee'])) ?></div>
                                <div class="mt-2">
                                    <?= (int) $lawyer['verified'] === 1 ? '<span class="badge text-bg-success">Verified</span>' : '' ?>
                                    <span class="badge text-bg-light text-dark">รีวิว <?= e(number_format((float) $lawyer['avg_rating'], 1)) ?></span>
                                </div>
                            </div>
                        </div>
                        <p class="small-muted mt-3"><?= e(mb_substr((string) $lawyer['bio'], 0, 150)) ?></p>
                        <div class="mb-3">
                            <?php foreach ($lawyerCategories as $catName): ?>
                                <span class="badge text-bg-light text-dark"><?= e($catName) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a class="btn btn-sm btn-primary" href="<?= e(url('/public/lawyer-detail.php?id=' . $lawyer['id'])) ?>">ดูโปรไฟล์</a>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (!$lawyers): ?>
                <div class="col-12"><div class="alert alert-info">ไม่พบทนายตามเงื่อนไข</div></div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
