<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lawyer');
$user = currentUser();
$lawyerStmt = db()->prepare('SELECT * FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyer = $lawyerStmt->fetch();
if (!$lawyer) {
    redirect(url('/lawyer/register-lawyer.php'));
}

$categories = db()->query('SELECT id, name FROM legal_categories ORDER BY name')->fetchAll();
$selectedStmt = db()->prepare('SELECT category_id FROM lawyer_categories WHERE lawyer_id = ?');
$selectedStmt->execute([$lawyer['id']]);
$selected = array_map('intval', array_column($selectedStmt->fetchAll(), 'category_id'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $profileImage = (string) ($user['profile_image'] ?? '');
        if (isset($_FILES['profile_image']) && ($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newProfileImage = uploadProfileImage($_FILES['profile_image']);
            if ($newProfileImage) {
                deleteUploadedFile($profileImage);
                $profileImage = $newProfileImage;
            }
        }

        $userStmt = $pdo->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
        $userStmt->execute([$profileImage ?: null, $user['id']]);

        $stmt = $pdo->prepare('UPDATE lawyers SET license_number = ?, province = ?, bio = ?, experience_years = ?, consultation_fee = ?, complex_case_experience = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([
            trim($_POST['license_number'] ?? ''),
            trim($_POST['province'] ?? ''),
            trim($_POST['bio'] ?? ''),
            (int) ($_POST['experience_years'] ?? 0),
            (float) ($_POST['consultation_fee'] ?? 0),
            isset($_POST['complex_case_experience']) ? 1 : 0,
            $lawyer['id'],
            $user['id'],
        ]);
        $pdo->prepare('DELETE FROM lawyer_categories WHERE lawyer_id = ?')->execute([$lawyer['id']]);
        $catStmt = $pdo->prepare('INSERT INTO lawyer_categories (lawyer_id, category_id) VALUES (?, ?)');
        foreach (array_map('intval', $_POST['categories'] ?? []) as $categoryId) {
            $catStmt->execute([$lawyer['id'], $categoryId]);
        }
        $pdo->commit();
        flash('success', 'บันทึกโปรไฟล์ทนายแล้ว');
        redirect(url('/lawyer/profile.php'));
    } catch (Throwable $exception) {
        $pdo->rollBack();
        flash('danger', 'บันทึกไม่สำเร็จ: ' . $exception->getMessage());
    }
}

$pageTitle = 'โปรไฟล์ทนาย';
$profileImageUrl = profileImageUrl($user['profile_image'] ?? null);
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <h1 class="h3 fw-bold mb-3">โปรไฟล์ทนาย</h1>
                    <form method="post" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-12">
                            <div class="profile-upload-card">
                                <div class="profile-avatar profile-avatar-xl <?= $profileImageUrl ? 'has-image' : '' ?>">
                                    <?php if ($profileImageUrl): ?>
                                        <img src="<?= e($profileImageUrl) ?>" alt="Profile image">
                                    <?php else: ?>
                                        <i class="bi bi-person-badge"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <label class="form-label">รูปโปรไฟล์ทนาย</label>
                                    <input class="form-control" type="file" name="profile_image" accept="image/jpeg,image/png,image/webp">
                                    <div class="form-text">รูปนี้จะแสดงในรายชื่อทนาย โปรไฟล์ และแชต</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label">เลขใบอนุญาตทนาย</label><input class="form-control" name="license_number" value="<?= e($lawyer['license_number']) ?>" required></div>
                        <div class="col-md-6"><label class="form-label">จังหวัด</label><input class="form-control" name="province" value="<?= e($lawyer['province']) ?>" required></div>
                        <div class="col-md-4"><label class="form-label">ประสบการณ์</label><input class="form-control" type="number" name="experience_years" value="<?= e($lawyer['experience_years']) ?>"></div>
                        <div class="col-md-4"><label class="form-label">ค่าปรึกษา</label><input class="form-control" type="number" name="consultation_fee" value="<?= e($lawyer['consultation_fee']) ?>"></div>
                        <div class="col-md-4 d-flex align-items-end"><div class="form-check"><input class="form-check-input" type="checkbox" name="complex_case_experience" id="complex" <?= (int) $lawyer['complex_case_experience'] ? 'checked' : '' ?>><label class="form-check-label" for="complex">คดีซับซ้อน</label></div></div>
                        <div class="col-12"><label class="form-label">ประวัติย่อ</label><textarea class="form-control" name="bio" rows="4"><?= e($lawyer['bio']) ?></textarea></div>
                        <div class="col-12">
                            <label class="form-label">หมวดกฎหมาย</label>
                            <div class="row g-2">
                                <?php foreach ($categories as $category): ?>
                                    <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="categories[]" value="<?= e($category['id']) ?>" id="cat<?= e($category['id']) ?>" <?= in_array((int) $category['id'], $selected, true) ? 'checked' : '' ?>><label class="form-check-label" for="cat<?= e($category['id']) ?>"><?= e($category['name']) ?></label></div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12"><button class="btn btn-primary">บันทึก</button></div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
