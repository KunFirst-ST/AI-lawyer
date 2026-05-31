<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/message-helpers.php';
require_once __DIR__ . '/../services/CallService.php';
requireLogin();

$room = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_GET['room'] ?? ''));
if ($room === '') {
    http_response_code(404);
    exit('ไม่พบห้องสนทนา');
}
$callService = new CallService();
try {
    $call = $callService->roomForParticipant((int) currentUser()['id'], $room);
} catch (DomainException) {
    http_response_code(403);
    exit('Forbidden');
}
$type = $call['call_type'] === 'video' ? 'video' : 'audio';

$pageTitle = $type === 'video' ? 'วิดีโอคอล' : 'โทรเสียง';
require_once __DIR__ . '/../includes/header.php';
?>
<section class="call-room-section">
    <div class="container">
        <div class="call-room" data-call-type="<?= e($type) ?>" data-call-room="<?= e($room) ?>" data-call-initiator="<?= $call['is_initiator'] ? '1' : '0' ?>">
            <div class="call-room-main">
                <span class="call-room-badge"><i class="bi bi-<?= e(callTypeIcon($type)) ?>"></i> <?= e(callTypeLabel($type)) ?></span>
                <h1>ห้องสนทนา <?= e($room) ?></h1>
                <p>เปิดไมค์<?= $type === 'video' ? 'และกล้อง' : '' ?>เพื่อเริ่มสนทนาในเบราว์เซอร์ ลิงก์นี้ถูกส่งผ่านแชตแล้ว</p>

                <div class="call-stage">
                    <div class="call-preview call-remote" data-call-remote>
                        <i class="bi bi-<?= e(callTypeIcon($type)) ?>"></i>
                        <span>รออีกฝ่ายเข้าห้องสนทนา</span>
                    </div>
                    <div class="call-local-preview" data-call-preview>
                        <i class="bi bi-person-video3"></i>
                        <span>ภาพของคุณ</span>
                    </div>
                </div>

                <div class="call-controls">
                    <button class="btn btn-light" type="button" data-call-start><i class="bi bi-play-fill"></i> เริ่ม</button>
                    <button class="btn btn-outline-light" type="button" data-call-mute disabled><i class="bi bi-mic"></i> เปิดไมค์</button>
                    <?php if ($type === 'video'): ?>
                        <button class="btn btn-outline-light" type="button" data-call-camera disabled><i class="bi bi-camera-video"></i> กล้อง</button>
                    <?php endif; ?>
                    <button class="btn btn-outline-light" type="button" data-call-copy><i class="bi bi-link-45deg"></i> คัดลอกลิงก์</button>
                    <button class="btn btn-danger" type="button" data-call-end><i class="bi bi-telephone-x"></i> วางสาย</button>
                    <a class="btn btn-outline-light" href="<?= e(url('/public/notifications.php')) ?>"><i class="bi bi-box-arrow-left"></i> ออกจากห้อง</a>
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
                    <span>คู่สนทนา</span>
                    <strong><?= e($call['peer_name']) ?></strong>
                </div>
            </aside>
        </div>
    </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
