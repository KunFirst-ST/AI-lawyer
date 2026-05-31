<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

final class CallService
{
    public function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (db_driver() === 'sqlite') {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS call_signals (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room TEXT NOT NULL,
                    sender_id INTEGER NOT NULL,
                    receiver_id INTEGER NOT NULL,
                    signal_type TEXT NOT NULL,
                    payload TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (sender_id) REFERENCES users(id),
                    FOREIGN KEY (receiver_id) REFERENCES users(id)
                )'
            );
            db()->exec('CREATE INDEX IF NOT EXISTS idx_call_signals_room_receiver_id ON call_signals (room, receiver_id, id)');
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS call_signals (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room VARCHAR(80) NOT NULL,
                sender_id INT NOT NULL,
                receiver_id INT NOT NULL,
                signal_type VARCHAR(20) NOT NULL,
                payload LONGTEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (sender_id) REFERENCES users(id),
                FOREIGN KEY (receiver_id) REFERENCES users(id),
                KEY idx_call_signals_room_receiver_id (room, receiver_id, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function roomForParticipant(int $userId, string $room): array
    {
        ensureMessageMediaColumns();
        $this->ensureSchema();

        $stmt = db()->prepare(
            'SELECT m.call_room, m.call_type, m.sender_id, m.receiver_id,
                    su.name AS sender_name, ru.name AS receiver_name
             FROM messages m
             JOIN users su ON su.id = m.sender_id AND su.status = "active"
             JOIN users ru ON ru.id = m.receiver_id AND ru.status = "active"
             WHERE m.call_room = ? AND m.message_type = "call"
               AND (m.sender_id = ? OR m.receiver_id = ?)
             ORDER BY m.id DESC
             LIMIT 1'
        );
        $stmt->execute([$room, $userId, $userId]);
        $call = $stmt->fetch();
        if (!$call) {
            throw new DomainException('ไม่พบห้องโทรหรือคุณไม่มีสิทธิ์เข้าห้องนี้');
        }

        $isInitiator = (int) $call['sender_id'] === $userId;
        $call['peer_id'] = $isInitiator ? (int) $call['receiver_id'] : (int) $call['sender_id'];
        $call['peer_name'] = $isInitiator ? (string) $call['receiver_name'] : (string) $call['sender_name'];
        $call['is_initiator'] = $isInitiator;
        return $call;
    }

    public function sendSignal(int $userId, string $room, string $type, array $payload = []): int
    {
        if (!in_array($type, ['offer', 'answer', 'ice', 'hangup'], true)) {
            throw new DomainException('ชนิด signaling ไม่ถูกต้อง');
        }

        $call = $this->roomForParticipant($userId, $room);
        $this->cleanupExpired();
        $encodedPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encodedPayload === false || strlen($encodedPayload) > 100000) {
            throw new DomainException('ข้อมูล signaling ไม่ถูกต้องหรือมีขนาดใหญ่เกินไป');
        }

        $stmt = db()->prepare(
            'INSERT INTO call_signals (room, sender_id, receiver_id, signal_type, payload)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$room, $userId, (int) $call['peer_id'], $type, $encodedPayload]);
        return (int) db()->lastInsertId();
    }

    public function events(int $userId, string $room, int $afterId = 0): array
    {
        $this->roomForParticipant($userId, $room);
        $this->cleanupExpired();

        $stmt = db()->prepare(
            'SELECT id, signal_type, payload, created_at
             FROM call_signals
             WHERE room = ? AND receiver_id = ? AND id > ?
             ORDER BY id ASC
             LIMIT 100'
        );
        $stmt->execute([$room, $userId, max(0, $afterId)]);

        return array_map(static function (array $event): array {
            $payload = json_decode((string) ($event['payload'] ?? '{}'), true);
            return [
                'id' => (int) $event['id'],
                'type' => (string) $event['signal_type'],
                'payload' => is_array($payload) ? $payload : [],
                'created_at' => (string) $event['created_at'],
            ];
        }, $stmt->fetchAll());
    }

    private function cleanupExpired(): void
    {
        $sql = db_driver() === 'sqlite'
            ? 'DELETE FROM call_signals WHERE created_at < datetime("now", "-1 day")'
            : 'DELETE FROM call_signals WHERE created_at < NOW() - INTERVAL 1 DAY';
        db()->exec($sql);
    }
}
