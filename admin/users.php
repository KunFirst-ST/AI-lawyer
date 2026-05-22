<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $userId = (int) ($_POST['user_id'] ?? 0);
    $status = $_POST['status'] ?? 'active';
    if (in_array($status, ['active', 'inactive', 'banned'], true)) {
        $stmt = db()->prepare('UPDATE users SET status = ? WHERE id = ? AND role != "admin"');
        $stmt->execute([$status, $userId]);
        flash('success', 'อัปเดตผู้ใช้แล้ว');
    }
    redirect(url('/admin/users.php'));
}

$users = db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'จัดการผู้ใช้';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">ผู้ใช้</h1>
                <div class="app-card p-3 table-responsive">
                    <table class="table">
                        <thead><tr><th>ชื่อ</th><th>อีเมล</th><th>Role</th><th>Status</th><th>จัดการ</th></tr></thead>
                        <tbody>
                        <?php foreach ($users as $account): ?>
                            <tr>
                                <td><?= e($account['name']) ?></td><td><?= e($account['email']) ?></td><td><?= e($account['role']) ?></td><td><?= e($account['status']) ?></td>
                                <td>
                                    <?php if ($account['role'] !== 'admin'): ?>
                                        <form method="post" class="d-flex gap-2">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="user_id" value="<?= e($account['id']) ?>">
                                            <select class="form-select form-select-sm" name="status"><option value="active">active</option><option value="inactive">inactive</option><option value="banned">banned</option></select>
                                            <button class="btn btn-sm btn-outline-primary">บันทึก</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
