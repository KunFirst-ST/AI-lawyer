<?php

require_once __DIR__ . '/../config/database.php';

final class ActivityService
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
                'CREATE TABLE IF NOT EXISTS case_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    case_id INTEGER NOT NULL,
                    actor_user_id INTEGER NULL,
                    event_type TEXT NOT NULL,
                    title TEXT NOT NULL,
                    details TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (case_id) REFERENCES cases(id),
                    FOREIGN KEY (actor_user_id) REFERENCES users(id)
                )'
            );
            db()->exec('CREATE INDEX IF NOT EXISTS idx_case_events_case_created ON case_events (case_id, created_at)');
            db()->exec(
                'CREATE TABLE IF NOT EXISTS audit_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    actor_user_id INTEGER NULL,
                    action TEXT NOT NULL,
                    entity_type TEXT NOT NULL,
                    entity_id INTEGER NULL,
                    details TEXT,
                    ip_address TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (actor_user_id) REFERENCES users(id)
                )'
            );
            db()->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_created ON audit_logs (created_at)');
            db()->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_action_created ON audit_logs (action, created_at)');
            db()->exec('CREATE INDEX IF NOT EXISTS idx_audit_logs_actor_created ON audit_logs (actor_user_id, created_at)');
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS case_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                case_id INT NOT NULL,
                actor_user_id INT NULL,
                event_type VARCHAR(60) NOT NULL,
                title VARCHAR(255) NOT NULL,
                details TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (case_id) REFERENCES cases(id),
                FOREIGN KEY (actor_user_id) REFERENCES users(id),
                KEY idx_case_events_case_created (case_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        db()->exec(
            'CREATE TABLE IF NOT EXISTS audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                actor_user_id INT NULL,
                action VARCHAR(100) NOT NULL,
                entity_type VARCHAR(60) NOT NULL,
                entity_id INT NULL,
                details TEXT,
                ip_address VARCHAR(64),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (actor_user_id) REFERENCES users(id),
                KEY idx_audit_logs_created (created_at),
                KEY idx_audit_logs_action_created (action, created_at),
                KEY idx_audit_logs_actor_created (actor_user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $this->ensureMysqlIndex('audit_logs', 'idx_audit_logs_action_created', 'CREATE INDEX idx_audit_logs_action_created ON audit_logs (action, created_at)');
        $this->ensureMysqlIndex('audit_logs', 'idx_audit_logs_actor_created', 'CREATE INDEX idx_audit_logs_actor_created ON audit_logs (actor_user_id, created_at)');
    }

    public function caseEvent(int $caseId, ?int $actorUserId, string $type, string $title, array $details = []): void
    {
        $this->ensureSchema();
        $stmt = db()->prepare(
            'INSERT INTO case_events (case_id, actor_user_id, event_type, title, details)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$caseId, $actorUserId, $type, $title, $this->encode($details)]);
    }

    public function audit(?int $actorUserId, string $action, string $entityType, ?int $entityId = null, array $details = []): void
    {
        $this->ensureSchema();
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $actorUserId,
            $action,
            $entityType,
            $entityId,
            $this->encode($details),
            substr(function_exists('clientIp') ? clientIp() : (string) ($_SERVER['REMOTE_ADDR'] ?? 'system'), 0, 64),
        ]);
    }

    public function recentAuditLogs(int $limit = 20): array
    {
        $this->ensureSchema();
        $limit = max(1, min($limit, 100));
        return db()->query(
            'SELECT al.*, u.name AS actor_name, u.email AS actor_email
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.actor_user_id
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT ' . $limit
        )->fetchAll();
    }

    public function auditSummary(int $minutes = 60): array
    {
        $this->ensureSchema();
        $cutoff = date('Y-m-d H:i:s', time() - max(1, min($minutes, 1440)) * 60);
        $stmt = db()->prepare(
            'SELECT action, COUNT(*) AS total
             FROM audit_logs
             WHERE created_at >= ?
             GROUP BY action
             ORDER BY total DESC, action ASC'
        );
        $stmt->execute([$cutoff]);

        $summary = [];
        foreach ($stmt->fetchAll() as $row) {
            $summary[(string) $row['action']] = (int) $row['total'];
        }

        return $summary;
    }

    public function countRecentAction(string $action, int $minutes = 60): int
    {
        $this->ensureSchema();
        $cutoff = date('Y-m-d H:i:s', time() - max(1, min($minutes, 1440)) * 60);
        $stmt = db()->prepare('SELECT COUNT(*) FROM audit_logs WHERE action = ? AND created_at >= ?');
        $stmt->execute([$action, $cutoff]);
        return (int) $stmt->fetchColumn();
    }

    public function caseTimeline(int $caseId, int $limit = 100): array
    {
        $this->ensureSchema();
        $stmt = db()->prepare(
            'SELECT ce.*, u.name AS actor_name
             FROM case_events ce
             LEFT JOIN users u ON u.id = ce.actor_user_id
             WHERE ce.case_id = ?
             ORDER BY ce.created_at DESC, ce.id DESC
             LIMIT ' . max(1, min($limit, 200))
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    private function encode(array $details): ?string
    {
        if (!$details) {
            return null;
        }
        $encoded = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    private function ensureMysqlIndex(string $table, string $index, string $sql): void
    {
        try {
            $stmt = db()->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?'
            );
            $stmt->execute([$table, $index]);
            if ((int) $stmt->fetchColumn() === 0) {
                db()->exec($sql);
            }
        } catch (Throwable) {
            // Index creation is an optimization; the audit table remains usable without it.
        }
    }
}
