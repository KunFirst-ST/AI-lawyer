<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');
$user = currentUser();
$caseId = (int) ($_GET['case_id'] ?? 0);
$lawyerId = (int) ($_GET['lawyer_id'] ?? 0);

$casesStmt = db()->prepare(
    'SELECT c.id, c.title
     FROM cases c
     WHERE c.user_id = ? AND c.user_wants_lawyer = 1 AND c.status = "matched"
       AND NOT EXISTS (
           SELECT 1 FROM bookings b
           WHERE b.case_id = c.id AND b.status IN ("pending", "confirmed", "completed")
       )
     ORDER BY c.created_at DESC'
);
$casesStmt->execute([$user['id']]);
$cases = $casesStmt->fetchAll();
$lawyersStmt = db()->prepare(
    'SELECT cm.case_id, l.id, l.consultation_fee, u.name
     FROM case_matches cm
     JOIN cases c ON c.id = cm.case_id
     JOIN lawyers l ON l.id = cm.lawyer_id
     JOIN users u ON u.id = l.user_id
     WHERE c.user_id = ? AND c.user_wants_lawyer = 1 AND c.status = "matched"
       AND cm.status IN ("suggested", "viewed", "selected")
       AND l.status = "approved" AND l.is_available = 1 AND u.status = "active"
     ORDER BY u.name'
);
$lawyersStmt->execute([$user['id']]);
$lawyers = $lawyersStmt->fetchAll();

$pageTitle = 'จองปรึกษาทนาย';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <h1 class="h3 fw-bold mb-3">จองปรึกษาทนาย</h1>
                    <form id="bookingForm" class="row g-3" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <div class="col-md-6">
                            <label class="form-label">เคส</label>
                            <select class="form-select" name="case_id" required>
                                <option value="">เลือกเคส</option>
                                <?php foreach ($cases as $case): ?>
                                    <option value="<?= e($case['id']) ?>" <?= $caseId === (int) $case['id'] ? 'selected' : '' ?>><?= e($case['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ทนาย</label>
                            <select class="form-select" name="lawyer_id" required>
                                <option value="">เลือกทนาย</option>
                                <?php foreach ($lawyers as $lawyer): ?>
                                    <option value="<?= e($lawyer['id']) ?>" data-case-id="<?= e($lawyer['case_id']) ?>" <?= $lawyerId === (int) $lawyer['id'] && (!$caseId || $caseId === (int) $lawyer['case_id']) ? 'selected' : '' ?>><?= e($lawyer['name']) ?> (<?= e(formatMoney($lawyer['consultation_fee'])) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">วันที่</label><input class="form-control" type="date" name="booking_date" required></div>
                        <div class="col-md-4"><label class="form-label">เวลา</label><input class="form-control" type="time" name="booking_time" required></div>
                        <div class="col-md-4">
                            <label class="form-label">รูปแบบปรึกษา</label>
                            <select class="form-select" name="consultation_type" required>
                                <option value="chat">ออนไลน์/แชต</option>
                                <option value="phone">โทร</option>
                                <option value="video">วิดีโอคอล</option>
                                <option value="onsite">พบตัวจริง</option>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">แนบเอกสารคดี</label><input class="form-control" type="file" name="case_document" accept=".pdf,image/*,.doc,.docx"></div>
                        <div class="col-12"><button class="btn btn-primary" <?= $cases && $lawyers ? '' : 'disabled' ?>>ส่งคำขอนัดหมาย</button></div>
                    </form>
                    <?php if (!$cases): ?><div class="alert alert-info mt-3 mb-0">ยังไม่มีเคสที่พร้อมจอง กรุณาเลือกทนายจากหน้าผลจับคู่ก่อน</div><?php endif; ?>
                    <div id="bookingResult" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
const bookingForm = document.querySelector('#bookingForm');
const bookingCase = bookingForm.querySelector('[name="case_id"]');
const bookingLawyer = bookingForm.querySelector('[name="lawyer_id"]');
const filterMatchedLawyers = () => {
    const selectedCaseId = bookingCase.value;
    Array.from(bookingLawyer.options).forEach((option, index) => {
        if (index === 0) return;
        option.hidden = option.dataset.caseId !== selectedCaseId;
        option.disabled = option.hidden;
    });
    if (bookingLawyer.selectedOptions[0]?.disabled) bookingLawyer.value = '';
};
bookingCase.addEventListener('change', filterMatchedLawyers);
filterMatchedLawyers();
bookingForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    const result = document.querySelector('#bookingResult');
    const response = await fetch('<?= e(url('/api/booking-create.php')) ?>', {
        method: 'POST',
        headers: {'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content},
        body: new FormData(this)
    });
    const json = await response.json();
    result.innerHTML = `<div class="alert alert-${json.success ? 'success' : 'danger'}">${json.message}</div>`;
    if (json.success && json.data.redirect) window.location.href = json.data.redirect;
});
</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
