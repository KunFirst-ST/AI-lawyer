<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lawyer');
$user = currentUser();
$lawyerStmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyerId = (int) ($lawyerStmt->fetchColumn() ?: 0);
$stmt = db()->prepare('SELECT * FROM commissions WHERE lawyer_id = ? ORDER BY created_at DESC');
$stmt->execute([$lawyerId]);
$commissions = $stmt->fetchAll();
$total = array_sum(array_map(fn ($row) => (float) $row['lawyer_amount'], $commissions));
$pageTitle = 'รายได้ทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="stat-card mb-3"><div class="small-muted">รายได้รวมหลังหัก Commission</div><div class="h3 fw-bold"><?= e(formatMoney($total)) ?></div></div>
                <div class="app-card p-3 table-responsive">
                    <table class="table"><thead><tr><th>Booking</th><th>ยอดรวม</th><th>Commission</th><th>ทนายได้รับ</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($commissions as $row): ?><tr><td>#<?= e($row['booking_id']) ?></td><td><?= e(formatMoney($row['gross_amount'])) ?></td><td><?= e(formatMoney($row['commission_amount'])) ?></td><td><?= e(formatMoney($row['lawyer_amount'])) ?></td><td><?= e($row['status']) ?></td></tr><?php endforeach; ?>
                    <?php if (!$commissions): ?><tr><td colspan="5" class="text-muted">ยังไม่มีรายได้</td></tr><?php endif; ?>
                    </tbody></table>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
