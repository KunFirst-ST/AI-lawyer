<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/NotificationService.php';
require_once __DIR__ . '/../services/ActivityService.php';
requireRole('admin');
$lawyerId = (int) ($_GET['id'] ?? ($_POST['lawyer_id'] ?? 0));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $status = match ($action) {
        'approve' => 'approved',
        'reject' => 'rejected',
        'suspend' => 'suspended',
        default => null,
    };
    if ($status) {
        $verified = $action === 'approve' ? 1 : 0;
        $stmt = db()->prepare('UPDATE lawyers SET status = ?, verified = ? WHERE id = ?');
        $stmt->execute([$status, $verified, $lawyerId]);
        (new ActivityService())->audit((int) currentUser()['id'], 'lawyer.' . $status, 'lawyer', $lawyerId);
        $userStmt = db()->prepare('SELECT user_id FROM lawyers WHERE id = ? LIMIT 1');
        $userStmt->execute([$lawyerId]);
        $lawyerUserId = $userStmt->fetchColumn();
        if ($lawyerUserId !== false) {
            $message = match ($status) {
                'approved' => 'โปรไฟล์ทนายของคุณผ่านการตรวจสอบแล้วและพร้อมรับเคสจากระบบ',
                'rejected' => 'โปรไฟล์ทนายของคุณยังไม่ผ่านการตรวจสอบ กรุณาตรวจสอบข้อมูลและเอกสาร',
                default => 'โปรไฟล์ทนายของคุณถูกระงับชั่วคราว กรุณาติดต่อผู้ดูแลระบบ',
            };
            (new NotificationService())->create((int) $lawyerUserId, 'อัปเดตสถานะโปรไฟล์ทนาย', $message, 'lawyer_status');
        }
        flash('success', 'อัปเดตสถานะทนายแล้ว');
    }
    redirect(url('/admin/lawyer-verify.php?id=' . $lawyerId));
}

$stmt = db()->prepare('SELECT l.*, u.name, u.email, u.phone FROM lawyers l JOIN users u ON u.id = l.user_id WHERE l.id = ? LIMIT 1');
$stmt->execute([$lawyerId]);
$lawyer = $stmt->fetch();
if (!$lawyer) {
    http_response_code(404);
    exit('ไม่พบทนาย');
}
$docs = db()->prepare('SELECT * FROM documents WHERE lawyer_id = ? ORDER BY created_at DESC');
$docs->execute([$lawyerId]);
$documents = $docs->fetchAll();
$cats = db()->prepare('SELECT lc.name FROM lawyer_categories lj JOIN legal_categories lc ON lc.id = lj.category_id WHERE lj.lawyer_id = ?');
$cats->execute([$lawyerId]);
$categories = $cats->fetchAll();
$pageTitle = 'ตรวจสอบทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h1 class="h3 fw-bold"><?= e($lawyer['name']) ?></h1>
                            <div class="small-muted"><?= e($lawyer['email']) ?> · <?= e($lawyer['phone']) ?></div>
                        </div>
                        <span class="badge text-bg-light text-dark align-self-start"><?= e($lawyer['status']) ?></span>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-4"><div class="small-muted">เลขใบอนุญาต</div><div class="fw-semibold"><?= e($lawyer['license_number']) ?></div></div>
                        <div class="col-md-4"><div class="small-muted">จังหวัด</div><div class="fw-semibold"><?= e($lawyer['province']) ?></div></div>
                        <div class="col-md-4"><div class="small-muted">ค่าปรึกษา</div><div class="fw-semibold"><?= e(formatMoney($lawyer['consultation_fee'])) ?></div></div>
                    </div>
                    <p class="mt-3"><?= nl2br(e($lawyer['bio'])) ?></p>
                    <h2 class="h6 fw-bold">หมวดเชี่ยวชาญ</h2>
                    <div class="mb-3"><?php foreach ($categories as $category): ?><span class="badge text-bg-light text-dark"><?= e($category['name']) ?></span><?php endforeach; ?></div>
                    <h2 class="h6 fw-bold">เอกสารทนาย</h2>
                    <?php foreach ($documents as $doc): ?><div class="border-bottom py-2"><i class="bi bi-file-earmark-text me-2"></i><a href="<?= e(url('/public/file.php?document_id=' . $doc['id'])) ?>" target="_blank"><?= e($doc['document_type']) ?> · <?= e($doc['original_name'] ?: basename($doc['file_path'])) ?></a></div><?php endforeach; ?>
                    <?php if (!$documents): ?><div class="text-muted">ยังไม่มีเอกสาร</div><?php endif; ?>
                    <form method="post" class="d-flex flex-wrap gap-2 mt-4">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="lawyer_id" value="<?= e($lawyer['id']) ?>">
                        <button name="action" value="approve" class="btn btn-success">อนุมัติและยืนยันโปรไฟล์</button>
                        <button name="action" value="reject" class="btn btn-outline-danger">ปฏิเสธ</button>
                        <button name="action" value="suspend" class="btn btn-outline-secondary">ระงับ</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
