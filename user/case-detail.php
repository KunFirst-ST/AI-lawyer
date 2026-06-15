<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/ActivityService.php';
requireRole('user');
$user = currentUser();
$caseId = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare(
    'SELECT c.*, lc.name AS category_name
     FROM cases c
     LEFT JOIN legal_categories lc ON lc.id = c.primary_category_id
     WHERE c.id = ? AND c.user_id = ?
     LIMIT 1'
);
$stmt->execute([$caseId, $user['id']]);
$case = $stmt->fetch();
if (!$case) {
    http_response_code(404);
    exit('ไม่พบเคส');
}

$chatStmt = db()->prepare('SELECT * FROM ai_chats WHERE case_id = ? AND user_id = ? ORDER BY created_at DESC LIMIT 10');
$chatStmt->execute([$caseId, $user['id']]);
$chats = $chatStmt->fetchAll();
$docStmt = db()->prepare('SELECT * FROM documents WHERE case_id = ? AND user_id = ? ORDER BY created_at DESC');
$docStmt->execute([$caseId, $user['id']]);
$documents = $docStmt->fetchAll();
$timeline = (new ActivityService())->caseTimeline($caseId);

$pageTitle = 'รายละเอียดเคส';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4 mb-3">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <h1 class="h3 fw-bold"><?= e($case['title']) ?></h1>
                            <div class="small-muted"><?= e(formatDateThai($case['created_at'])) ?></div>
                        </div>
                        <span class="badge text-bg-light text-dark align-self-start"><?= e(matchStatusLabel($case['match_status'])) ?></span>
                    </div>
                    <hr>
                    <div class="row g-3">
                        <div class="col-md-4"><div class="small-muted">หมวดหลัก</div><div class="fw-semibold"><?= e($case['category_name'] ?? '-') ?></div></div>
                        <div class="col-md-4"><div class="small-muted">ความซับซ้อน</div><div class="fw-semibold"><?= e(levelLabel($case['complexity_level'])) ?></div></div>
                        <div class="col-md-4"><div class="small-muted">ความเร่งด่วน</div><div class="fw-semibold"><?= e(levelLabel($case['urgency'])) ?></div></div>
                        <div class="col-md-4"><div class="small-muted">จังหวัด</div><div class="fw-semibold"><?= e($case['province'] ?? '-') ?></div></div>
                        <div class="col-md-4"><div class="small-muted">รูปแบบปรึกษา</div><div class="fw-semibold"><?= e(consultationTypeLabel($case['consultation_type'])) ?></div></div>
                        <div class="col-md-4"><div class="small-muted">งบประมาณ</div><div class="fw-semibold"><?= $case['budget_max'] ? e(formatMoney($case['budget_max'])) : '-' ?></div></div>
                    </div>
                    <div class="mt-3">
                        <div class="small-muted">สรุปสำหรับส่งต่อทนาย</div>
                        <p><?= nl2br(e($case['ai_summary'])) ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-primary" href="<?= e(url('/user/ai-chat.php')) ?>">คุยกับ AI ต่อ</a>
                        <a class="btn btn-outline-primary" href="<?= e(url('/user/matches.php?case_id=' . $caseId)) ?>">ดูทนายที่แนะนำ</a>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="app-card p-3 h-100">
                            <h2 class="h5 fw-bold">ประวัติการปรึกษา AI</h2>
                            <?php foreach ($chats as $chat): ?>
                                <div class="border-bottom py-2">
                                    <div class="fw-semibold"><?= e(mb_substr($chat['user_message'], 0, 120)) ?></div>
                                    <div class="small-muted"><?= e(mb_substr($chat['ai_response'], 0, 160)) ?></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$chats): ?><div class="text-muted">ยังไม่มีประวัติ</div><?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="app-card p-3 h-100">
                            <h2 class="h5 fw-bold">เอกสารเคส</h2>
                            <?php foreach ($documents as $doc): ?>
                                <div class="border-bottom py-2"><i class="bi bi-paperclip me-2"></i><a href="<?= e(url('/public/file.php?document_id=' . $doc['id'])) ?>" target="_blank"><?= e($doc['original_name'] ?: basename($doc['file_path'])) ?></a></div>
                            <?php endforeach; ?>
                            <?php if (!$documents): ?><div class="text-muted">ยังไม่มีเอกสาร</div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="app-card p-3 mt-3">
                    <h2 class="h5 fw-bold mb-3">ไทม์ไลน์เคส</h2>
                    <div class="case-timeline">
                        <?php foreach ($timeline as $event): ?>
                            <div class="case-timeline-item">
                                <span class="case-timeline-dot"></span>
                                <div>
                                    <strong><?= e($event['title']) ?></strong>
                                    <small><?= e($event['actor_name'] ?: 'ระบบ') ?> · <?= e(formatDateThai($event['created_at'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$timeline): ?><div class="text-muted">ยังไม่มีเหตุการณ์ในไทม์ไลน์</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
