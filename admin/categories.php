<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    if ($name && $slug) {
        $sql = db_driver() === 'sqlite'
            ? 'INSERT INTO legal_categories (name, slug, description) VALUES (?, ?, ?)
               ON CONFLICT(slug) DO UPDATE SET name = excluded.name, description = excluded.description'
            : 'INSERT INTO legal_categories (name, slug, description) VALUES (?, ?, ?)
               ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)';
        $stmt = db()->prepare($sql);
        $stmt->execute([$name, $slug, $description]);
        flash('success', 'บันทึกหมวดกฎหมายแล้ว');
    }
    redirect(url('/admin/categories.php'));
}
$categories = db()->query('SELECT * FROM legal_categories ORDER BY id')->fetchAll();
$pageTitle = 'หมวดกฎหมาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell"><div class="container"><div class="row g-4"><div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div><div class="col-lg-9">
<h1 class="h3 fw-bold mb-3">หมวดกฎหมาย</h1>
<div class="app-card p-3 mb-3"><form method="post" class="row g-3"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><div class="col-md-4"><input class="form-control" name="name" placeholder="ชื่อหมวด" required></div><div class="col-md-3"><input class="form-control" name="slug" placeholder="slug" required></div><div class="col-md-3"><input class="form-control" name="description" placeholder="คำอธิบาย"></div><div class="col-md-2"><button class="btn btn-primary w-100">บันทึก</button></div></form></div>
<div class="app-card p-3 table-responsive"><table class="table"><thead><tr><th>ชื่อ</th><th>Slug</th><th>คำอธิบาย</th></tr></thead><tbody><?php foreach ($categories as $category): ?><tr><td><?= e($category['name']) ?></td><td><?= e($category['slug']) ?></td><td><?= e($category['description']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</div></div></div></section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
