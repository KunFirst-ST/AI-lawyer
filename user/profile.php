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
    $removeProfileImage = (string) ($_POST['remove_profile_image'] ?? '') === '1';

    if ($name === '') {
        flash('danger', 'กรุณากรอกชื่อ');
    } else {
        if ($removeProfileImage) {
            deleteUploadedFile($profileImage);
            $profileImage = '';
        }

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
                            <div class="profile-photo-card" data-profile-uploader>
                                <div class="profile-photo-preview">
                                    <div class="profile-photo-frame <?= $profileImageUrl ? 'has-image' : '' ?>" data-profile-preview>
                                        <?php if ($profileImageUrl): ?>
                                            <img src="<?= e($profileImageUrl) ?>" alt="Profile image">
                                        <?php else: ?>
                                            <i class="bi bi-person"></i>
                                        <?php endif; ?>
                                    </div>
                                    <span class="profile-photo-status"><?= $profileImageUrl ? 'มีรูปโปรไฟล์แล้ว' : 'ยังไม่มีรูปโปรไฟล์' ?></span>
                                </div>
                                <div class="profile-photo-body">
                                    <span class="profile-photo-kicker">บัญชีผู้ใช้</span>
                                    <h2>รูปโปรไฟล์</h2>
                                    <p><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
                                    <div class="profile-photo-actions">
                                        <label class="btn btn-primary profile-photo-button">
                                            <i class="bi bi-image"></i>
                                            <span>เลือกรูป</span>
                                            <input class="visually-hidden" type="file" name="profile_image" accept="image/*" data-profile-input>
                                        </label>
                                        <button class="btn btn-outline-danger profile-photo-button" type="submit" name="remove_profile_image" value="1" <?= $profileImageUrl ? '' : 'disabled' ?>>
                                            <i class="bi bi-trash"></i>
                                            <span>ลบรูป</span>
                                        </button>
                                    </div>
                                    <div class="profile-photo-meta" data-profile-meta>รองรับไฟล์รูปภาพทุกชนิดที่เบราว์เซอร์และเซิร์ฟเวอร์อ่านได้</div>
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
