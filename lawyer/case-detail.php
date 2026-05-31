<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/ActivityService.php';
requireRole('lawyer');
$user = currentUser();
$caseId = (int) ($_GET['id'] ?? 0);
$lawyerStmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyerId = (int) ($lawyerStmt->fetchColumn() ?: 0);
$stmt = db()->prepare(
    'SELECT c.*, cm.match_score, cm.match_reason, u.name AS user_name, u.phone, lc.name AS category_name
     FROM case_matches cm
     JOIN cases c ON c.id = cm.case_id
     JOIN users u ON u.id = c.user_id
     LEFT JOIN legal_categories lc ON lc.id = c.primary_category_id
     WHERE cm.case_id = ? AND cm.lawyer_id = ? AND cm.status IN ("suggested", "viewed", "selected")
     LIMIT 1'
);
$stmt->execute([$caseId, $lawyerId]);
$case = $stmt->fetch();
if (!$case) {
    http_response_code(404);
    exit('ไม่พบเคส');
}
$docStmt = db()->prepare('SELECT * FROM documents WHERE case_id = ? ORDER BY created_at DESC');
$docStmt->execute([$caseId]);
$documents = $docStmt->fetchAll();
$timeline = (new ActivityService())->caseTimeline($caseId);
$pageTitle = 'รายละเอียดเคส';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <div class="app-card p-4">
                    <h1 class="h3 fw-bold"><?= e($case['title']) ?></h1>
                    <div class="small-muted mb-3">ลูกค้า: <?= e($case['user_name']) ?> · <?= e($case['phone']) ?></div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-3"><div class="stat-card"><div class="small-muted">Match Score</div><div class="fw-bold"><?= e((string) round($case['match_score'])) ?></div></div></div>
                        <div class="col-md-3"><div class="stat-card"><div class="small-muted">หมวด</div><div class="fw-bold"><?= e($case['category_name'] ?? '-') ?></div></div></div>
                        <div class="col-md-3"><div class="stat-card"><div class="small-muted">ซับซ้อน</div><div class="fw-bold"><?= e(levelLabel($case['complexity_level'])) ?></div></div></div>
                        <div class="col-md-3"><div class="stat-card"><div class="small-muted">เร่งด่วน</div><div class="fw-bold"><?= e(levelLabel($case['urgency'])) ?></div></div></div>
                    </div>
                    <h2 class="h6 fw-bold">เหตุผลที่ Match</h2>
                    <p><?= e($case['match_reason']) ?></p>
                    <h2 class="h6 fw-bold">สรุปเคสจาก AI</h2>
                    <p><?= nl2br(e($case['ai_summary'])) ?></p>
                    <h2 class="h6 fw-bold">เอกสาร</h2>
                    <?php foreach ($documents as $doc): ?><div class="border-bottom py-2"><i class="bi bi-paperclip me-2"></i><a href="<?= e(url('/public/file.php?document_id=' . $doc['id'])) ?>" target="_blank"><?= e($doc['original_name'] ?: basename($doc['file_path'])) ?></a></div><?php endforeach; ?>
                    <?php if (!$documents): ?><div class="text-muted">ยังไม่มีเอกสาร</div><?php endif; ?>
                </div>
                <div class="app-card p-3 mt-3">
                    <h2 class="h5 fw-bold mb-3">Timeline เคส</h2>
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
                        <?php if (!$timeline): ?><div class="text-muted">ยังไม่มีเหตุการณ์ใน timeline</div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
