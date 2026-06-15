<?php
require_once __DIR__ . '/../includes/auth.php';
requireRole('user');

$user = currentUser();
$requestedCaseId = (int) ($_GET['case_id'] ?? 0);

$caseStmt = db()->prepare(
    'SELECT c.*, lc.name AS category_name
     FROM cases c
     LEFT JOIN legal_categories lc ON lc.id = c.primary_category_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC
     LIMIT 30'
);
$caseStmt->execute([(int) $user['id']]);
$cases = $caseStmt->fetchAll();

$activeCaseId = isset($_GET['new']) ? 0 : ($requestedCaseId ?: (int) ($cases[0]['id'] ?? 0));
$activeCase = null;
foreach ($cases as $case) {
    if ((int) $case['id'] === $activeCaseId) {
        $activeCase = $case;
        break;
    }
}

$chatHistory = [];
$lastAnalysis = null;
if ($activeCaseId > 0) {
    $chatStmt = db()->prepare(
        'SELECT *
         FROM ai_chats
         WHERE user_id = ? AND case_id = ?
         ORDER BY created_at ASC
         LIMIT 120'
    );
    $chatStmt->execute([(int) $user['id'], $activeCaseId]);
    $chatHistory = $chatStmt->fetchAll();

    for ($i = count($chatHistory) - 1; $i >= 0; $i--) {
        $decoded = json_decode((string) ($chatHistory[$i]['ai_json'] ?? ''), true);
        if (is_array($decoded)) {
            $lastAnalysis = $decoded;
            break;
        }
    }
}

$pageTitle = 'ถาม AI';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell ai-chat-page">
    <div class="container-fluid px-lg-4">
        <div class="row g-4">
            <div class="col-lg-2"><?php require __DIR__ . '/../includes/user-sidebar.php'; ?></div>
            <div class="col-lg-10">
                <div class="ai-chat-layout">
                    <aside class="app-card ai-case-panel">
                        <div class="chat-panel-head">
                            <div>
                                <h2>เคส AI</h2>
                                <span><?= e((string) count($cases)) ?> เคส</span>
                            </div>
                            <a class="btn btn-sm btn-primary" href="<?= e(url('/user/ai-chat.php?new=1')) ?>" title="เริ่มเคสใหม่"><i class="bi bi-plus-lg"></i></a>
                        </div>
                        <div class="chat-contact-list">
                            <a class="chat-contact <?= $activeCaseId === 0 ? 'active' : '' ?>" href="<?= e(url('/user/ai-chat.php?new=1')) ?>">
                                <span class="chat-avatar"><i class="bi bi-stars"></i></span>
                                <span class="min-w-0">
                                    <strong>เริ่มคำถามใหม่</strong>
                                    <small>ให้ AI ช่วยวิเคราะห์เคสใหม่</small>
                                </span>
                            </a>
                            <?php foreach ($cases as $case): ?>
                                <a class="chat-contact <?= (int) $case['id'] === $activeCaseId ? 'active' : '' ?>" href="<?= e(url('/user/ai-chat.php?case_id=' . $case['id'])) ?>">
                                    <span class="chat-avatar"><i class="bi bi-folder2-open"></i></span>
                                    <span class="min-w-0">
                                        <strong><?= e($case['title']) ?></strong>
                                        <small><?= e($case['category_name'] ?? 'ยังไม่จัดหมวด') ?> · <?= e(caseStatusLabel($case['status'])) ?></small>
                                    </span>
                                    <em><?= e(substr((string) $case['created_at'], 5, 5)) ?></em>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </aside>

                    <main class="app-card ai-chat-room">
                        <header class="chat-room-header">
                            <div class="chat-avatar lg"><i class="bi bi-robot"></i></div>
                            <div>
                                <h1>ผู้ช่วยกฎหมาย คู่ดี AI</h1>
                                <p><?= $activeCase ? e($activeCase['title']) : 'เริ่มเล่าปัญหากฎหมายของคุณได้เลย' ?></p>
                            </div>
                            <div class="ai-room-actions ms-auto">
                                <span class="ai-ready-pill"><i class="bi bi-shield-check"></i> พร้อมวิเคราะห์</span>
                                <?php if ($activeCase): ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('/user/case-detail.php?id=' . $activeCase['id'])) ?>">ดูเคส</a>
                                <?php endif; ?>
                            </div>
                        </header>

                        <div id="chatMessages" class="ai-chat-window" data-current-case-id="<?= e((string) $activeCaseId) ?>">
                            <?php if (!$chatHistory): ?>
                                <div class="ai-chat-row ai">
                                    <div class="chat-message ai">สวัสดีครับ เล่าเรื่องที่กังวลมาได้เลย ผมจะช่วยจับประเด็นให้สั้น ๆ ว่าควรทำอะไรก่อน</div>
                                </div>
                            <?php endif; ?>
                            <?php foreach ($chatHistory as $chat): ?>
                                <div class="ai-chat-row user"><div class="chat-message user"><?= nl2br(e($chat['user_message'])) ?></div></div>
                                <div class="ai-chat-row ai"><div class="chat-message ai"><?= nl2br(e($chat['ai_response'])) ?></div></div>
                            <?php endforeach; ?>
                        </div>

                        <form id="chatForm" class="ai-chat-composer">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <textarea id="messageInput" class="form-control" name="message" rows="1" placeholder="เล่าเรื่องที่เกิดขึ้น หรือถามสิ่งที่กังวลได้เลย..." autocomplete="off" required></textarea>
                            <label class="ai-attach-button" title="แนบเอกสารหรือไฟล์เสียง">
                                <i class="bi bi-paperclip"></i>
                                <input id="caseDocument" class="d-none" type="file" name="case_document" accept=".pdf,image/*,.doc,.docx,audio/*,.webm">
                            </label>
                            <button class="ai-attach-button ai-voice-button" type="button" data-ai-speech-text title="พูดเป็นข้อความ">
                                <i class="bi bi-mic"></i>
                                <span class="visually-hidden">เสียงเป็นข้อความ</span>
                            </button>
                            <button class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
                            <div class="ai-file-preview" data-ai-file-preview hidden></div>
                            <div class="ai-voice-result" data-ai-voice-result hidden></div>
                        </form>
                    </main>

                    <aside class="ai-analysis-column">
                        <div id="analysisPanel">
                            <?php if ($lastAnalysis): ?>
                                <div class="app-card analysis-card p-3">
                                    <h2 class="h6 fw-bold">วิเคราะห์ล่าสุด</h2>
                                    <div class="small-muted">หมวดหลัก</div>
                                    <div class="fw-semibold mb-2"><?= e(legalCategoryName($lastAnalysis['primary_category'] ?? null)) ?></div>
                                    <div class="small-muted">ความเร่งด่วน</div>
                                    <div class="fw-semibold level-<?= e($lastAnalysis['urgency'] ?? '') ?>"><?= e(levelLabel($lastAnalysis['urgency'] ?? null)) ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="app-card p-3 mt-3">
                            <h2 class="h6 fw-bold">ข้อควรทราบ</h2>
                            <p class="small-muted mb-0">AI วิเคราะห์เบื้องต้น ไม่ใช่คำปรึกษาจากทนายโดยตรง ระบบจะถามก่อนเสมอก่อนเริ่มจับคู่ทนาย</p>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
</section>
<script src="<?= e(url('/assets/js/ai-chat.js')) ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
