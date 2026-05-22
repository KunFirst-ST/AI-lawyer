<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($name === '') {
        flash('danger', 'กรุณากรอกชื่อ');
    } else {
        if ($password !== '') {
            $stmt = db()->prepare('UPDATE users SET name = ?, phone = ?, password = ? WHERE id = ?');
            $stmt->execute([$name, $phone, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        } else {
            $stmt = db()->prepare('UPDATE users SET name = ?, phone = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $user['id']]);
        }
        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect(url('/user/profile.php'));
    }
}

$pageTitle = 'โปรไฟล์';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <h1 class="h3 fw-bold mb-3">โปรไฟล์</h1>
                    <form method="post" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-md-6"><label class="form-label">ชื่อ</label><input class="form-control" name="name" value="<?= e($user['name']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e($user['phone']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">อีเมล</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
                        <div class="col-md-6"><label class="form-label">เปลี่ยนรหัสผ่าน</label><input class="form-control" type="password" name="password" minlength="8"></div>
                        <div class="col-12"><button class="btn btn-primary">บันทึก</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
