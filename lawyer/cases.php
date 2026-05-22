<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('lawyer');
$user = currentUser();
$lawyerStmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$lawyerStmt->execute([$user['id']]);
$lawyerId = (int) ($lawyerStmt->fetchColumn() ?: 0);
$stmt = db()->prepare(
    'SELECT cm.*, c.title, c.urgency, c.complexity_level, c.status AS case_status, u.name AS user_name
     FROM case_matches cm
     JOIN cases c ON c.id = cm.case_id
     JOIN users u ON u.id = c.user_id
     WHERE cm.lawyer_id = ?
     ORDER BY cm.match_score DESC, cm.created_at DESC'
);
$stmt->execute([$lawyerId]);
$cases = $stmt->fetchAll();
$pageTitle = 'เคสที่ถูกเสนอ';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-3"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-9">
                <h1 class="h3 fw-bold mb-3">เคสที่ถูกเสนอ</h1>
                <div class="app-card p-3">
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>เคส</th><th>ลูกค้า</th><th>Match</th><th>ซับซ้อน</th><th>เร่งด่วน</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($cases as $case): ?>
                                <tr>
                                    <td><?= e($case['title']) ?></td>
                                    <td><?= e($case['user_name']) ?></td>
                                    <td><?= e((string) round($case['match_score'])) ?></td>
                                    <td><?= e(levelLabel($case['complexity_level'])) ?></td>
                                    <td><?= e(levelLabel($case['urgency'])) ?></td>
                                    <td><a class="btn btn-sm btn-outline-primary" href="<?= e(url('/lawyer/case-detail.php?id=' . $case['case_id'])) ?>">ดู</a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$cases): ?><tr><td colspan="6" class="text-muted">ยังไม่มีเคสที่เสนอ</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
