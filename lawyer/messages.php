<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/message-helpers.php';
requireRole('lawyer');
ensureMessageMediaColumns();

$user = currentUser();
$requestedPeerId = (int) ($_GET['peer_id'] ?? 0);

$contacts = db()->query('SELECT id AS user_id, name, email, phone FROM users WHERE role = "user" AND status = "active" ORDER BY name')->fetchAll();

$messageStmt = db()->prepare(
    'SELECT m.*, su.name AS sender_name, ru.name AS receiver_name
     FROM messages m
     JOIN users su ON su.id = m.sender_id
     JOIN users ru ON ru.id = m.receiver_id
     WHERE m.sender_id = ? OR m.receiver_id = ?
     ORDER BY m.created_at DESC
     LIMIT 300'
);
$messageStmt->execute([(int) $user['id'], (int) $user['id']]);
$allMessages = $messageStmt->fetchAll();

$latestByPeer = [];
foreach ($allMessages as $message) {
    $peerId = (int) $message['sender_id'] === (int) $user['id'] ? (int) $message['receiver_id'] : (int) $message['sender_id'];
    $latestByPeer[$peerId] ??= $message;
}

$activePeerId = $requestedPeerId ?: (int) ($contacts[0]['user_id'] ?? 0);
$activeContact = null;
foreach ($contacts as $contact) {
    if ((int) $contact['user_id'] === $activePeerId) {
        $activeContact = $contact;
        break;
    }
}

$thread = [];
if ($activePeerId > 0) {
    $threadStmt = db()->prepare(
        'SELECT m.*, su.name AS sender_name, ru.name AS receiver_name
         FROM messages m
         JOIN users su ON su.id = m.sender_id
         JOIN users ru ON ru.id = m.receiver_id
         WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
         ORDER BY m.created_at ASC
         LIMIT 200'
    );
    $threadStmt->execute([(int) $user['id'], $activePeerId, $activePeerId, (int) $user['id']]);
    $thread = $threadStmt->fetchAll();
}

$pageTitle = 'แชตลูกความ';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="dashboard-shell conversation-shell">
    <div class="container-fluid px-lg-4">
        <div class="row g-4">
            <div class="col-lg-2"><?php require __DIR__ . '/../includes/lawyer-sidebar.php'; ?></div>
            <div class="col-lg-10">
                <div class="conversation-hero mb-3">
                    <div>
                        <span><i class="bi bi-chat-square-dots"></i> Client Chat</span>
                        <h1>แชตระหว่างทนายกับลูกความ</h1>
                        <p>เลือกผู้ใช้แล้วคุยในห้องแชตเดียว พร้อมแนบไฟล์ เสียง โทร และวิดีโอคอล</p>
                    </div>
                    <i class="bi bi-person-video3"></i>
                </div>

                <div class="law-chat-layout">
                    <aside class="app-card chat-contact-panel">
                        <div class="chat-panel-head">
                            <div>
                                <h2>ลูกความ</h2>
                                <span><?= e((string) count($contacts)) ?> รายชื่อ</span>
                            </div>
                            <i class="bi bi-people"></i>
                        </div>
                        <div class="chat-contact-list">
                            <?php foreach ($contacts as $contact): ?>
                                <?php
                                $peerId = (int) $contact['user_id'];
                                $latest = $latestByPeer[$peerId] ?? null;
                                $active = $peerId === $activePeerId;
                                ?>
                                <a class="chat-contact <?= $active ? 'active' : '' ?>" href="<?= e(url('/lawyer/messages.php?peer_id=' . $peerId)) ?>">
                                    <span class="chat-avatar"><i class="bi bi-person"></i></span>
                                    <span class="min-w-0">
                                        <strong><?= e($contact['name']) ?></strong>
                                        <small><?= e($latest['message'] ?? ($contact['phone'] ?: $contact['email'])) ?></small>
                                    </span>
                                    <em><?= $latest ? e(substr((string) $latest['created_at'], 5, 11)) : '' ?></em>
                                </a>
                            <?php endforeach; ?>
                            <?php if (!$contacts): ?><div class="text-muted p-3">ยังไม่มีผู้ใช้</div><?php endif; ?>
                        </div>
                    </aside>

                    <section class="app-card chat-room-panel">
                        <header class="chat-room-header">
                            <div class="chat-avatar lg"><i class="bi bi-person"></i></div>
                            <div>
                                <h2><?= e($activeContact['name'] ?? 'เลือกลูกความเพื่อเริ่มสนทนา') ?></h2>
                                <p><?= $activeContact ? e($activeContact['email']) . ($activeContact['phone'] ? ' · ' . e($activeContact['phone']) : '') : 'เลือกคู่สนทนาจากรายชื่อด้านซ้าย' ?></p>
                            </div>
                        </header>

                        <div class="chat-thread-window">
                            <?php foreach ($thread as $message): ?>
                                <?php $mine = (int) $message['sender_id'] === (int) $user['id']; ?>
                                <article class="conversation-message <?= $mine ? 'mine' : 'theirs' ?>">
                                    <div class="conversation-message-meta">
                                        <span><?= e($message['sender_name']) ?></span>
                                        <time><?= e($message['created_at']) ?></time>
                                    </div>
                                    <?php if (trim((string) $message['message']) !== ''): ?>
                                        <div class="conversation-bubble"><?= nl2br(e($message['message'])) ?></div>
                                    <?php endif; ?>
                                    <?= renderMessageMedia($message) ?>
                                </article>
                            <?php endforeach; ?>
                            <?php if (!$thread): ?>
                                <div class="chat-empty-state">
                                    <i class="bi bi-chat-dots"></i>
                                    <strong>ยังไม่มีแชตในห้องนี้</strong>
                                    <span>พิมพ์แชตแรก หรือเริ่มโทรเพื่อเปิดบทสนทนา</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <form id="messageForm" class="conversation-form chat-composer-bar" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="receiver_id" value="<?= e((string) $activePeerId) ?>">
                            <input type="hidden" name="message_type" value="text">
                            <input type="hidden" name="call_type" value="audio">
                            <textarea class="form-control" name="message" rows="1" placeholder="<?= $activePeerId ? 'พิมพ์แชตถึงลูกความ...' : 'เลือกลูกความก่อนเริ่มแชต' ?>" <?= $activePeerId ? '' : 'disabled' ?>></textarea>
                            <div class="conversation-tools">
                                <label class="conversation-tool" title="แนบรูปหรือไฟล์">
                                    <i class="bi bi-image"></i>
                                    <span>ไฟล์</span>
                                    <input class="visually-hidden" type="file" name="message_file" accept="image/*,audio/*,.webm,.pdf,.doc,.docx" <?= $activePeerId ? '' : 'disabled' ?>>
                                </label>
                                <button class="conversation-tool" type="button" data-speech-text <?= $activePeerId ? '' : 'disabled' ?>><i class="bi bi-mic"></i><span>เสียงเป็นข้อความ</span></button>
                                <button class="conversation-tool" type="button" data-call-type="audio" <?= $activePeerId ? '' : 'disabled' ?>><i class="bi bi-telephone"></i><span>โทร</span></button>
                                <button class="conversation-tool" type="button" data-call-type="video" <?= $activePeerId ? '' : 'disabled' ?>><i class="bi bi-camera-video"></i><span>วิดีโอ</span></button>
                            </div>
                            <div class="conversation-file-preview" data-file-preview hidden></div>
                            <div class="conversation-recording" data-recording-state hidden><span></span><button class="btn btn-sm btn-outline-danger" type="button" data-stop-recording>หยุดอัด</button></div>
                            <button class="btn btn-primary conversation-submit" type="submit" <?= $activePeerId ? '' : 'disabled' ?>><i class="bi bi-send"></i></button>
                        </form>
                        <div id="messageResult" class="px-3 pb-3"></div>
                    </section>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
