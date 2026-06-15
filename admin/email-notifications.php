<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/EmailNotificationService.php';

requireRole('admin');
$user = currentUser();
$emailService = new EmailNotificationService();
$emailService->ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'send_test') {
            $testEmail = strtolower(trim((string) ($_POST['test_email'] ?? '')));
            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('กรุณากรอกอีเมลสำหรับทดสอบให้ถูกต้อง');
            }
            $emailService->sendTest($testEmail, (string) ($user['name'] ?? ''));
            flash('success', 'ส่งอีเมลทดสอบแล้ว กรุณาตรวจกล่องจดหมายเข้าและโฟลเดอร์สแปมของ Gmail');
        } elseif ($action === 'retry_pending') {
            $result = $emailService->retryPending(50);
            flash('success', 'ลองส่งรายการค้างใหม่แล้ว: สำเร็จ ' . $result['sent'] . ' รายการ, ยังล้มเหลว ' . $result['failed'] . ' รายการ');
        }
    } catch (Throwable $exception) {
        flash('danger', 'ดำเนินการไม่สำเร็จ: ' . $exception->getMessage());
    }
    redirect(url('/admin/email-notifications.php'));
}

$mailStatus = $emailService->status();
$summary = $emailService->summary();
$recentEmails = $emailService->recent();
$pageTitle = 'แจ้งเตือน Gmail';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">แจ้งเตือน Gmail</h1>
                        <p class="text-muted mb-0">ติดตามการส่งอีเมลแจ้งเตือนของระบบผ่าน Gmail SMTP</p>
                    </div>
                    <span class="badge <?= $mailStatus['configured'] ? 'text-bg-success' : 'text-bg-warning' ?> align-self-start">
                        <?= $mailStatus['configured'] ? 'พร้อมส่งอีเมล' : 'ยังไม่ได้ตั้งค่า SMTP' ?>
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4"><div class="app-card p-3"><div class="small-muted">รอส่ง</div><div class="h3 fw-bold mb-0"><?= e((string) $summary['queued']) ?></div></div></div>
                    <div class="col-md-4"><div class="app-card p-3"><div class="small-muted">ส่งแล้ว</div><div class="h3 fw-bold text-success mb-0"><?= e((string) $summary['sent']) ?></div></div></div>
                    <div class="col-md-4"><div class="app-card p-3"><div class="small-muted">ล้มเหลว</div><div class="h3 fw-bold text-danger mb-0"><?= e((string) $summary['failed']) ?></div></div></div>
                </div>

                <div class="app-card p-4 mb-3">
                    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
                        <div>
                            <h2 class="h5 fw-bold">การตั้งค่า SMTP</h2>
                            <div class="small-muted">เซิร์ฟเวอร์ส่งอีเมล: <?= e($mailStatus['host']) ?>:<?= e((string) $mailStatus['port']) ?></div>
                            <div class="small-muted">อีเมลผู้ส่ง: <?= e($mailStatus['from_address'] ?: '-') ?></div>
                            <div class="small-muted">ประเภทแจ้งเตือน: <?= e(implode(', ', array_map('emailNotificationTypeLabel', $mailStatus['notify_types']))) ?></div>
                        </div>
                        <?php if ($summary['queued'] > 0 || $summary['failed'] > 0): ?>
                            <form method="post" class="align-self-start">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="btn btn-outline-primary" name="action" value="retry_pending"><i class="bi bi-arrow-clockwise me-1"></i>ลองส่งรายการค้างใหม่</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <?php if (!$mailStatus['configured']): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            ตั้งค่าอีเมลผู้ส่งในไฟล์ <code>.env</code> ให้ครบก่อนเปิดใช้งานจริง โดยใช้ Google App Password สำหรับบัญชี Gmail
                        </div>
                    <?php endif; ?>
                </div>

                <div class="app-card p-4 mb-3">
                    <h2 class="h5 fw-bold">ส่งอีเมลทดสอบ</h2>
                    <form method="post" class="row g-2">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-md-8"><input class="form-control" type="email" name="test_email" value="<?= e((string) ($user['email'] ?? '')) ?>" placeholder="name@gmail.com" required></div>
                        <div class="col-md-4"><button class="btn btn-primary w-100" name="action" value="send_test"><i class="bi bi-send me-1"></i>ส่งอีเมลทดสอบ</button></div>
                    </form>
                </div>

                <div class="app-card p-3">
                    <h2 class="h5 fw-bold px-1 pt-1">ประวัติการส่งล่าสุด</h2>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr><th>เวลา</th><th>ผู้รับ</th><th>หัวข้อ</th><th>ประเภท</th><th>สถานะ</th><th>ครั้ง</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentEmails as $email): ?>
                                <tr>
                                    <td class="text-nowrap"><?= e((string) $email['created_at']) ?></td>
                                    <td><?= e((string) $email['recipient_email']) ?></td>
                                    <td>
                                        <div><?= e((string) $email['subject']) ?></div>
                                        <?php if (!empty($email['last_error'])): ?><small class="text-danger"><?= e((string) $email['last_error']) ?></small><?php endif; ?>
                                    </td>
                                    <td><?= e(emailNotificationTypeLabel((string) $email['notification_type'])) ?></td>
                                    <td><span class="badge <?= $email['status'] === 'sent' ? 'text-bg-success' : ($email['status'] === 'failed' ? 'text-bg-danger' : 'text-bg-warning') ?>"><?= e(emailStatusLabel((string) $email['status'])) ?></span></td>
                                    <td><?= e((string) $email['attempts']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$recentEmails): ?><tr><td colspan="6" class="text-muted">ยังไม่มีประวัติการส่งอีเมล</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
