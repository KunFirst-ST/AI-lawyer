<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/SocialAuthService.php';
requireRole('user');
$user = currentUser();
$socialProviders = SocialAuthService::providerSummaries();
$connectedSocialProviders = SocialAuthService::connectedProvidersForUser((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $profileImage = (string) ($user['profile_image'] ?? '');

    if ($name === '') {
        flash('danger', 'กรุณากรอกชื่อ');
    } else {
        if (isset($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newProfileImage = uploadProfileImage($_FILES['profile_image']);
            if ($newProfileImage) {
                deleteUploadedFile($profileImage);
                $profileImage = $newProfileImage;
            }
        }

        if ($password !== '') {
            $stmt = db()->prepare('UPDATE users SET name = ?, phone = ?, profile_image = ?, password = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $profileImage ?: null, password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        } else {
            $stmt = db()->prepare('UPDATE users SET name = ?, phone = ?, profile_image = ? WHERE id = ?');
            $stmt->execute([$name, $phone, $profileImage ?: null, $user['id']]);
        }
        flash('success', 'บันทึกโปรไฟล์แล้ว');
        redirect(url('/user/profile.php'));
    }
}

$pageTitle = 'โปรไฟล์';
$profileImageUrl = profileImageUrl($user['profile_image'] ?? null);
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <h1 class="h3 fw-bold mb-3">โปรไฟล์</h1>
                    <form method="post" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-12">
                            <div class="profile-upload-card">
                                <div class="profile-avatar profile-avatar-xl <?= $profileImageUrl ? 'has-image' : '' ?>">
                                    <?php if ($profileImageUrl): ?>
                                        <img src="<?= e($profileImageUrl) ?>" alt="Profile image">
                                    <?php else: ?>
                                        <i class="bi bi-person"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">รูปโปรไฟล์</label>
                                    <input class="form-control" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text">รองรับ JPG, PNG, WebP</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label">ชื่อ</label><input class="form-control" name="name" value="<?= e($user['name']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">เบอร์โทร</label><input class="form-control" name="phone" value="<?= e($user['phone']) ?>"></div>
                        <div class="col-md-6"><label class="form-label">อีเมล</label><input class="form-control" value="<?= e($user['email']) ?>" disabled></div>
                        <div class="col-md-6"><label class="form-label">เปลี่ยนรหัสผ่าน</label><input class="form-control" type="password" name="password" minlength="8"></div>
                        <div class="col-12"><button class="btn btn-primary">บันทึก</button></div>
                    </form>
                </div>
                <div class="app-card p-4 mt-3">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <div>
                            <h2 class="h5 fw-bold mb-1">บัญชี Social Login</h2>
                            <p class="small-muted mb-0">สถานะบัญชี Google และ Facebook สำหรับการเข้าสู่ระบบผู้ใช้</p>
                        </div>
                        <i class="bi bi-shield-check text-primary fs-4"></i>
                    </div>
                    <div class="profile-social-grid">
                        <?php foreach ($socialProviders as $provider): ?>
                            <?php $connection = $connectedSocialProviders[$provider['key']] ?? null; ?>
                            <div class="profile-social-item">
                                <i class="bi <?= e($provider['icon']) ?> <?= e($provider['class']) ?>"></i>
                                <div>
                                    <strong><?= e($provider['name']) ?></strong>
                                    <span><?= $connection ? e((string) $connection['provider_email']) : ($provider['configured'] ? 'พร้อมเชื่อมเมื่อเข้าสู่ระบบครั้งแรก' : 'ผู้ดูแลยังไม่ได้เปิดใช้งาน') ?></span>
                                </div>
                                <em class="<?= $connection ? 'is-connected' : '' ?>"><?= $connection ? 'เชื่อมแล้ว' : 'ยังไม่เชื่อม' ?></em>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
