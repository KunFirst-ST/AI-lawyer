<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/message-helpers.php';
require_once __DIR__ . '/../services/ConversationService.php';
requireLogin();

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['room'] ?? ''));
$type = ($_GET['type'] ?? 'audio') === 'video' ? 'video' : 'audio';
if ($room === '') {
    http_response_code(404);
    exit('ไม่พบห้องสนทนา');
}
if (!(new ConversationService())->canAccessCallRoom((int) currentUser()['id'], $room)) {
    http_response_code(403);
    exit('Forbidden');
}

$pageTitle = $type === 'video' ? 'วิดีโอคอล' : 'โทรเสียง';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="call-room-section">
    <div class="container">
        <div class="call-room" data-call-type="<?= e($type) ?>">
            <div class="call-room-main">
                <span class="call-room-badge"><i class="bi bi-<?= e(callTypeIcon($type)) ?>"></i> <?= e(callTypeLabel($type)) ?></span>
                <h1>ห้องสนทนา <?= e($room) ?></h1>
                <p>เปิดไมค์<?= $type === 'video' ? 'และกล้อง' : '' ?>เพื่อเริ่มสนทนาในเบราว์เซอร์ ลิงก์นี้ถูกส่งผ่านแชตแล้ว</p>

                <div class="call-preview" data-call-preview>
                    <i class="bi bi-<?= e(callTypeIcon($type)) ?>"></i>
                    <span>พร้อมเริ่มสนทนา</span>
                </div>

                <div class="call-controls">
                    <button class="btn btn-light" type="button" data-call-start><i class="bi bi-play-fill"></i> เริ่ม</button>
                    <button class="btn btn-outline-light" type="button" data-call-mute disabled><i class="bi bi-mic"></i> เปิดไมค์</button>
                    <?php if ($type === 'video'): ?>
                        <button class="btn btn-outline-light" type="button" data-call-camera disabled><i class="bi bi-camera-video"></i> กล้อง</button>
                    <?php endif; ?>
                    <button class="btn btn-outline-light" type="button" data-call-copy><i class="bi bi-link-45deg"></i> คัดลอกลิงก์</button>
                    <a class="btn btn-danger" href="<?= e(url('/public/notifications.php')) ?>"><i class="bi bi-telephone-x"></i> วางสาย</a>
                </div>
                <div class="call-status" data-call-status>ยังไม่ได้เปิดไมค์<?= $type === 'video' ? '/กล้อง' : '' ?></div>
            </div>
            <aside class="call-room-side">
                <div>
                    <span>ประเภท</span>
                    <strong><?= e(callTypeLabel($type)) ?></strong>
                </div>
                <div>
                    <span>ห้อง</span>
                    <strong><?= e($room) ?></strong>
                </div>
                <div>
                    <span>ผู้ใช้</span>
                    <strong><?= e(currentUser()['name'] ?? '-') ?></strong>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
