<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lawyer');
$user = currentUser();

$lawyerStmt = db()->prepare('SELECT * FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyer = $lawyerStmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $lawyer) {
    verify_csrf();
    $newAvailability = (int) ($_POST['is_available'] ?? 0);
    $stmt = db()->prepare('UPDATE lawyers SET is_available = ? WHERE id = ? AND user_id = ?');
    $stmt->execute([$newAvailability, $lawyer['id'], $user['id']]);
    flash('success', 'อัปเดตสถานะรับงานแล้ว');
    redirect(url('/lawyer/dashboard.php'));
}

$stats = ['cases' => 0, 'bookings' => 0, 'earnings' => 0, 'rating' => 0];
if ($lawyer) {
    $stmt = db()->prepare('SELECT COUNT(*) FROM case_matches WHERE lawyer_id = ?');
    $stmt->execute([$lawyer['id']]);
    $stats['cases'] = (int) $stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COUNT(*) FROM bookings WHERE lawyer_id = ?');
    $stmt->execute([$lawyer['id']]);
    $stats['bookings'] = (int) $stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COALESCE(SUM(lawyer_amount), 0) FROM commissions WHERE lawyer_id = ?');
    $stmt->execute([$lawyer['id']]);
    $stats['earnings'] = (float) $stmt->fetchColumn();
    $stmt = db()->prepare('SELECT COALESCE(AVG(rating), 0) FROM reviews WHERE lawyer_id = ?');
    $stmt->execute([$lawyer['id']]);
    $stats['rating'] = (float) $stmt->fetchColumn();
}

$pageTitle = 'แดชบอร์ดทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <?php if (!$lawyer): ?>
                    <div class="app-card p-4">
                        <h1 class="h3 fw-bold">ยังไม่มีโปรไฟล์ทนาย</h1>
                        <p class="text-muted">กรุณากรอกข้อมูลสมัครทนายเพื่อรอแอดมินตรวจสอบ</p>
                        <a class="btn btn-primary" href="<?= e(url('/lawyer/register-lawyer.php')) ?>">สมัครเป็นทนาย</a>
                    </div>
                <?php else: ?>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h1 class="h3 fw-bold mb-1">แดชบอร์ดทนาย</h1>
                            <p class="text-muted mb-0">สถานะโปรไฟล์: <span class="badge text-bg-light text-dark"><?= e($lawyer['status']) ?></span> <?= (int) $lawyer['verified'] ? '<span class="badge text-bg-success">ยืนยันแล้ว</span>' : '' ?></p>
                        </div>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="is_available" value="<?= (int) $lawyer['is_available'] === 1 ? 0 : 1 ?>">
                            <button class="btn <?= (int) $lawyer['is_available'] === 1 ? 'btn-outline-secondary' : 'btn-primary' ?>"><?= (int) $lawyer['is_available'] === 1 ? 'ปิดรับงาน' : 'เปิดรับงาน' ?></button>
                        </form>
                    </div>
                    <div class="row g-3">
                        <?php foreach ([['เคสที่ถูกเสนอ', $stats['cases']], ['จำนวนการจอง', $stats['bookings']], ['รายได้รวม', formatMoney($stats['earnings'])], ['คะแนนรีวิว', number_format($stats['rating'], 1)]] as $item): ?>
                            <div class="col-md-3"><div class="stat-card"><div class="small-muted"><?= e($item[0]) ?></div><div class="h4 fw-bold mb-0"><?= e((string) $item[1]) ?></div></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
