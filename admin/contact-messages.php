<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$statuses = [
    'new' => 'ใหม่',
    'in_progress' => 'กำลังดูแล',
    'closed' => 'ปิดงานแล้ว',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $messageId = (int) ($_POST['message_id'] ?? 0);
    $status = $_POST['status'] ?? 'new';
    $adminNote = trim($_POST['admin_note'] ?? '');

    if (isset($statuses[$status])) {
        $stmt = db()->prepare('UPDATE contact_messages SET status = ?, admin_note = ? WHERE id = ?');
        $stmt->execute([$status, $adminNote, $messageId]);
        flash('success', 'อัปเดตข้อความติดต่อแล้ว');
    }
    redirect(url('/admin/contact-messages.php'));
}

$filter = $_GET['status'] ?? '';
$params = [];
$where = '';
if ($filter !== '' && isset($statuses[$filter])) {
    $where = 'WHERE status = ?';
    $params[] = $filter;
}

$statusOrder = db_driver() === 'sqlite'
    ? "CASE status WHEN 'new' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'closed' THEN 3 ELSE 4 END"
    : "FIELD(status, 'new', 'in_progress', 'closed')";
$stmt = db()->prepare("SELECT * FROM contact_messages {$where} ORDER BY {$statusOrder}, created_at DESC");
$stmt->execute($params);
$messages = $stmt->fetchAll();

$counts = array_fill_keys(array_keys($statuses), 0);
$countRows = db()->query('SELECT status, COUNT(*) AS total FROM contact_messages GROUP BY status')->fetchAll();
foreach ($countRows as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int) $row['total'];
    }
}

$pageTitle = 'ข้อความติดต่อ';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/admin-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div>
                        <h1 class="h3 fw-bold mb-1">ข้อความติดต่อ</h1>
                        <p class="text-muted mb-0">ติดตามคำถามจากหน้าเว็บไซต์และสถานะการดูแล</p>
                    </div>
                    <a class="btn btn-outline-primary" href="<?= e(url('/public/contact.php')) ?>" target="_blank">ดูหน้าติดต่อ</a>
                </div>

                <div class="row g-3 mb-3">
                    <?php foreach ($statuses as $key => $label): ?>
                        <div class="col-md-4">
                            <a class="stat-card d-block text-dark <?= $filter === $key ? 'active-filter' : '' ?>" href="<?= e(url('/admin/contact-messages.php?status=' . $key)) ?>">
                                <div class="small-muted"><?= e($label) ?></div>
                                <div class="h4 fw-bold mb-0"><?= e((string) $counts[$key]) ?></div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="app-card p-3">
                    <?php foreach ($messages as $message): ?>
                        <div class="contact-thread border-bottom py-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                                <div>
                                    <div class="fw-bold"><?= e($message['subject'] ?: 'ไม่ระบุหัวข้อ') ?></div>
                                    <div class="small-muted">
                                        <?= e($message['name']) ?> ·
                                        <a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a>
                                        <?= $message['phone'] ? ' · ' . e($message['phone']) : '' ?>
                                        · <?= e($message['created_at']) ?>
                                    </div>
                                </div>
                                <span class="badge status-<?= e($message['status']) ?> align-self-start"><?= e($statuses[$message['status']] ?? $message['status']) ?></span>
                            </div>
                            <p class="my-3"><?= nl2br(e($message['message'])) ?></p>
                            <form method="post" class="row g-2 align-items-end">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="message_id" value="<?= e($message['id']) ?>">
                                <div class="col-md-3">
                                    <label class="form-label small-muted">สถานะ</label>
                                    <select class="form-select form-select-sm" name="status">
                                        <?php foreach ($statuses as $key => $label): ?>
                                            <option value="<?= e($key) ?>" <?= $message['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-7">
                                    <label class="form-label small-muted">บันทึกแอดมิน</label>
                                    <input class="form-control form-control-sm" name="admin_note" value="<?= e($message['admin_note']) ?>">
                                </div>
                                <div class="col-md-2">
                                    <button class="btn btn-sm btn-primary w-100">บันทึก</button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$messages): ?>
                        <div class="text-muted">ยังไม่มีข้อความติดต่อในเงื่อนไขนี้</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
