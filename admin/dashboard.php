<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/BookingWorkflowService.php';
requireRole('admin');
(new BookingWorkflowService())->ensureSchema();

$fetchValue = static function (string $sql) {
    try {
        return db()->query($sql)->fetchColumn();
    } catch (Throwable) {
        return 0;
    }
};

$fetchAll = static function (string $sql): array {
    try {
        return db()->query($sql)->fetchAll();
    } catch (Throwable) {
        return [];
    }
};

$stats = [
    'users' => $fetchValue('SELECT COUNT(*) FROM users WHERE role = "user"'),
    'active_users' => $fetchValue('SELECT COUNT(*) FROM users WHERE role = "user" AND status = "active"'),
    'lawyers' => $fetchValue('SELECT COUNT(*) FROM lawyers'),
    'pending_lawyers' => $fetchValue('SELECT COUNT(*) FROM lawyers WHERE status = "pending"'),
    'approved_lawyers' => $fetchValue('SELECT COUNT(*) FROM lawyers WHERE status = "approved"'),
    'cases' => $fetchValue('SELECT COUNT(*) FROM cases'),
    'requested_matches' => $fetchValue('SELECT COUNT(*) FROM cases WHERE match_status = "requested_by_user"'),
    'bookings' => $fetchValue('SELECT COUNT(*) FROM bookings'),
    'pending_booking_responses' => $fetchValue('SELECT COUNT(*) FROM bookings WHERE status = "pending" AND lawyer_response_status = "pending"'),
    'pending_payments' => $fetchValue('SELECT COUNT(*) FROM payments WHERE status = "pending" AND slip_image IS NOT NULL'),
    'contact_new' => $fetchValue('SELECT COUNT(*) FROM contact_messages WHERE status = "new"'),
    'revenue' => $fetchValue('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "approved"'),
    'commissions' => $fetchValue('SELECT COALESCE(SUM(commission_amount), 0) FROM commissions'),
];

$caseStatusRows = $fetchAll('SELECT status, COUNT(*) AS total FROM cases GROUP BY status ORDER BY total DESC');
$maxCaseStatus = max(array_map(static fn ($row) => (int) $row['total'], $caseStatusRows) ?: [1]);

$recentCases = $fetchAll(
    'SELECT c.title, c.status, c.match_status, c.created_at, u.name AS user_name
     FROM cases c
     JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at DESC
     LIMIT 6'
);

$pendingLawyers = $fetchAll(
    'SELECT l.id, l.province, l.license_number, l.created_at, u.name, u.email
     FROM lawyers l
     JOIN users u ON u.id = l.user_id
     WHERE l.status = "pending"
     ORDER BY l.created_at DESC
     LIMIT 5'
);

$pendingPayments = $fetchAll(
    'SELECT p.id, p.amount, p.created_at, uu.name AS user_name, lu.name AS lawyer_name
     FROM payments p
     JOIN bookings b ON b.id = p.booking_id
     JOIN users uu ON uu.id = b.user_id
     JOIN lawyers l ON l.id = b.lawyer_id
     JOIN users lu ON lu.id = l.user_id
     WHERE p.status = "pending" AND p.slip_image IS NOT NULL
     ORDER BY p.created_at DESC
     LIMIT 5'
);

$adminUser = currentUser();
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell admin-dashboard">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="admin-hero mb-4">
                    <div>
                        <span class="admin-eyebrow"><i class="bi bi-shield-check"></i> Admin Control Center</span>
                        <h1>แดชบอร์ดสำหรับแอดมินเท่านั้น</h1>
                        <p>ควบคุมผู้ใช้ ทนาย เคส การจอง การชำระเงิน ค่าคอมมิชชั่น และข้อความติดต่อจากศูนย์เดียว</p>
                        <div class="admin-hero-actions">
                            <a class="btn btn-light" href="<?= e(url('/admin/lawyers.php')) ?>"><i class="bi bi-person-check me-2"></i>ตรวจทนาย</a>
                            <a class="btn btn-outline-light" href="<?= e(url('/admin/payments.php')) ?>"><i class="bi bi-receipt me-2"></i>ตรวจสลิป</a>
                            <a class="btn btn-outline-light" href="<?= e(url('/admin/contact-messages.php')) ?>"><i class="bi bi-inbox me-2"></i>ข้อความใหม่</a>
                        </div>
                    </div>
                    <div class="admin-hero-panel">
                        <div class="admin-secure-ring"><i class="bi bi-lock-fill"></i></div>
                        <div class="small">ลงชื่อเข้าใช้ในฐานะ</div>
                        <strong><?= e($adminUser['name'] ?? 'Administrator') ?></strong>
                        <span><?= e($adminUser['email'] ?? '') ?></span>
                        <em>Role: admin</em>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <?php foreach ([
                        ['label' => 'ผู้ใช้ทั้งหมด', 'value' => $stats['users'], 'hint' => 'active ' . $stats['active_users'], 'icon' => 'people', 'tone' => 'blue'],
                        ['label' => 'ทนายทั้งหมด', 'value' => $stats['lawyers'], 'hint' => 'approved ' . $stats['approved_lawyers'], 'icon' => 'person-badge', 'tone' => 'green'],
                        ['label' => 'ทนายรอตรวจ', 'value' => $stats['pending_lawyers'], 'hint' => 'ต้องอนุมัติ', 'icon' => 'hourglass-split', 'tone' => 'amber'],
                        ['label' => 'เคสทั้งหมด', 'value' => $stats['cases'], 'hint' => 'ขอ match ' . $stats['requested_matches'], 'icon' => 'folder2-open', 'tone' => 'blue'],
                        ['label' => 'Booking', 'value' => $stats['bookings'], 'hint' => 'การนัดหมาย', 'icon' => 'calendar-check', 'tone' => 'green'],
                        ['label' => 'Booking รอทนาย', 'value' => $stats['pending_booking_responses'], 'hint' => 'รอตอบรับนัดหมาย', 'icon' => 'calendar2-check', 'tone' => 'amber'],
                        ['label' => 'Payment รอตรวจ', 'value' => $stats['pending_payments'], 'hint' => 'มีสลิปแล้ว', 'icon' => 'receipt', 'tone' => 'red'],
                        ['label' => 'ข้อความใหม่', 'value' => $stats['contact_new'], 'hint' => 'จากหน้าติดต่อ', 'icon' => 'inbox', 'tone' => 'amber'],
                        ['label' => 'รายได้รวม', 'value' => formatMoney($stats['revenue']), 'hint' => 'อนุมัติแล้ว', 'icon' => 'cash-coin', 'tone' => 'green'],
                        ['label' => 'Commission รวม', 'value' => formatMoney($stats['commissions']), 'hint' => 'รวมทุกทนาย', 'icon' => 'percent', 'tone' => 'blue'],
                    ] as $item): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="admin-stat-card tone-<?= e($item['tone']) ?>">
                                <div>
                                    <span><?= e($item['label']) ?></span>
                                    <strong><?= e((string) $item['value']) ?></strong>
                                    <small><?= e((string) $item['hint']) ?></small>
                                </div>
                                <i class="bi bi-<?= e($item['icon']) ?>"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-xl-5">
                        <div class="app-card admin-panel-card h-100">
                            <div class="admin-card-heading">
                                <div>
                                    <h2>สถานะเคส</h2>
                                    <p>ภาพรวม pipeline ของเคสทั้งหมด</p>
                                </div>
                                <i class="bi bi-activity"></i>
                            </div>
                            <div class="admin-progress-list">
                                <?php foreach ($caseStatusRows as $row): ?>
                                    <?php $percent = $maxCaseStatus > 0 ? ((int) $row['total'] / $maxCaseStatus) * 100 : 0; ?>
                                    <div>
                                        <div class="d-flex justify-content-between gap-2">
                                            <span><?= e($row['status']) ?></span>
                                            <strong><?= e((string) $row['total']) ?></strong>
                                        </div>
                                        <div class="admin-progress"><span style="width: <?= e((string) $percent) ?>%"></span></div>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$caseStatusRows): ?><div class="text-muted">ยังไม่มีข้อมูลเคส</div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-7">
                        <div class="app-card admin-panel-card h-100">
                            <div class="admin-card-heading">
                                <div>
                                    <h2>งานที่ควรจัดการก่อน</h2>
                                    <p>รายการรอตรวจที่มีผลกับการใช้งานของลูกค้าและทนาย</p>
                                </div>
                                <i class="bi bi-lightning-charge"></i>
                            </div>
                            <div class="admin-action-grid">
                                <a href="<?= e(url('/admin/lawyers.php')) ?>">
                                    <i class="bi bi-person-badge"></i>
                                    <span>ทนายรอตรวจ</span>
                                    <strong><?= e((string) $stats['pending_lawyers']) ?></strong>
                                </a>
                                <a href="<?= e(url('/admin/payments.php')) ?>">
                                    <i class="bi bi-receipt"></i>
                                    <span>สลิปรอตรวจ</span>
                                    <strong><?= e((string) $stats['pending_payments']) ?></strong>
                                </a>
                                <a href="<?= e(url('/admin/bookings.php')) ?>">
                                    <i class="bi bi-calendar2-check"></i>
                                    <span>Booking รอทนาย</span>
                                    <strong><?= e((string) $stats['pending_booking_responses']) ?></strong>
                                </a>
                                <a href="<?= e(url('/admin/cases.php')) ?>">
                                    <i class="bi bi-person-check"></i>
                                    <span>เคสขอ Match</span>
                                    <strong><?= e((string) $stats['requested_matches']) ?></strong>
                                </a>
                                <a href="<?= e(url('/admin/contact-messages.php')) ?>">
                                    <i class="bi bi-inbox"></i>
                                    <span>ข้อความใหม่</span>
                                    <strong><?= e((string) $stats['contact_new']) ?></strong>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-xl-6">
                        <div class="app-card admin-panel-card h-100">
                            <div class="admin-card-heading">
                                <div>
                                    <h2>ทนายรอตรวจสอบ</h2>
                                    <p>ใบสมัครใหม่ที่ควรเปิดดูเอกสาร</p>
                                </div>
                                <a href="<?= e(url('/admin/lawyers.php')) ?>">ดูทั้งหมด</a>
                            </div>
                            <div class="admin-feed-list">
                                <?php foreach ($pendingLawyers as $lawyer): ?>
                                    <a href="<?= e(url('/admin/lawyer-verify.php?id=' . $lawyer['id'])) ?>">
                                        <span class="admin-feed-icon"><i class="bi bi-person-vcard"></i></span>
                                        <span>
                                            <strong><?= e($lawyer['name']) ?></strong>
                                            <small><?= e($lawyer['province']) ?> · <?= e($lawyer['license_number']) ?></small>
                                        </span>
                                        <i class="bi bi-chevron-right ms-auto"></i>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (!$pendingLawyers): ?><div class="text-muted">ไม่มีทนายรอตรวจตอนนี้</div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="app-card admin-panel-card h-100">
                            <div class="admin-card-heading">
                                <div>
                                    <h2>สลิปรออนุมัติ</h2>
                                    <p>ตรวจยอดเงินและหลักฐานชำระล่าสุด</p>
                                </div>
                                <a href="<?= e(url('/admin/payments.php')) ?>">ดูทั้งหมด</a>
                            </div>
                            <div class="admin-feed-list">
                                <?php foreach ($pendingPayments as $payment): ?>
                                    <a href="<?= e(url('/admin/payments.php')) ?>">
                                        <span class="admin-feed-icon"><i class="bi bi-credit-card"></i></span>
                                        <span>
                                            <strong><?= e(formatMoney($payment['amount'])) ?></strong>
                                            <small><?= e($payment['user_name']) ?> → <?= e($payment['lawyer_name']) ?></small>
                                        </span>
                                        <i class="bi bi-chevron-right ms-auto"></i>
                                    </a>
                                <?php endforeach; ?>
                                <?php if (!$pendingPayments): ?><div class="text-muted">ไม่มีสลิปรอตรวจตอนนี้</div><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="app-card p-3 table-responsive">
                            <table class="table">
                                <thead>
                                <tr><th>เคสล่าสุด</th><th>ผู้ใช้</th><th>Match</th><th>Status</th><th>สร้างเมื่อ</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($recentCases as $case): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($case['title']) ?></td>
                                        <td><?= e($case['user_name']) ?></td>
                                        <td><?= e($case['match_status']) ?></td>
                                        <td><?= e($case['status']) ?></td>
                                        <td><?= e($case['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (!$recentCases): ?><tr><td colspan="5" class="text-muted">ยังไม่มีเคสล่าสุด</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
