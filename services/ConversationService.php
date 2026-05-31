<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

final class ConversationService
{
    public function assertCanTalk(int $currentUserId, int $peerId, ?int $caseId = null, ?int $bookingId = null): array
    {
        if ($currentUserId <= 0 || $peerId <= 0 || $currentUserId === $peerId) {
            throw new DomainException('ไม่พบคู่สนทนาที่อนุญาต');
        }

        $accounts = $this->accounts([$currentUserId, $peerId]);
        $current = $accounts[$currentUserId] ?? null;
        $peer = $accounts[$peerId] ?? null;
        if (!$current || !$peer) {
            throw new DomainException('ไม่พบคู่สนทนาที่อนุญาต');
        }

        $roles = [$current['role'], $peer['role']];
        sort($roles);
        if ($roles !== ['lawyer', 'user']) {
            throw new DomainException('แชตรองรับการสนทนาระหว่างผู้ใช้กับทนายที่เกี่ยวข้องกับเคสเท่านั้น');
        }

        $userId = $current['role'] === 'user' ? $currentUserId : $peerId;
        $lawyerUserId = $current['role'] === 'lawyer' ? $currentUserId : $peerId;
        $lawyerId = $this->lawyerIdForUser($lawyerUserId);
        if (!$lawyerId || !$this->hasRelationship($userId, $lawyerId)) {
            throw new DomainException('คุณยังไม่มีเคสหรือ Booking ที่เชื่อมกับคู่สนทนานี้');
        }

        if ($caseId !== null && !$this->caseBelongsToRelationship($caseId, $userId, $lawyerId)) {
            throw new DomainException('เคสนี้ไม่เชื่อมกับคู่สนทนาที่เลือก');
        }

        if ($bookingId !== null && !$this->bookingBelongsToRelationship($bookingId, $userId, $lawyerId)) {
            throw new DomainException('Booking นี้ไม่เชื่อมกับคู่สนทนาที่เลือก');
        }

        return [
            'user_id' => $userId,
            'lawyer_id' => $lawyerId,
            'lawyer_user_id' => $lawyerUserId,
        ];
    }

    public function contactsForUser(int $userId): array
    {
        $stmt = db()->prepare(
            'SELECT DISTINCT l.id AS lawyer_id, l.user_id, l.province, l.consultation_fee, u.name, u.email
             FROM lawyers l
             JOIN users u ON u.id = l.user_id AND u.status = "active"
             WHERE EXISTS (
                 SELECT 1
                 FROM case_matches cm
                 JOIN cases c ON c.id = cm.case_id
                 WHERE cm.lawyer_id = l.id AND c.user_id = ?
                   AND cm.status IN ("suggested", "viewed", "selected")
             ) OR EXISTS (
                 SELECT 1 FROM bookings b WHERE b.lawyer_id = l.id AND b.user_id = ?
             )
             ORDER BY u.name'
        );
        $stmt->execute([$userId, $userId]);
        return $stmt->fetchAll();
    }

    public function contactsForLawyer(int $lawyerUserId): array
    {
        $lawyerId = $this->lawyerIdForUser($lawyerUserId);
        if (!$lawyerId) {
            return [];
        }

        $stmt = db()->prepare(
            'SELECT DISTINCT u.id AS user_id, u.name, u.email, u.phone
             FROM users u
             WHERE u.role = "user" AND u.status = "active"
               AND (
                   EXISTS (
                       SELECT 1
                       FROM case_matches cm
                       JOIN cases c ON c.id = cm.case_id
                       WHERE cm.lawyer_id = ? AND c.user_id = u.id
                         AND cm.status IN ("suggested", "viewed", "selected")
                   )
                   OR EXISTS (
                       SELECT 1 FROM bookings b WHERE b.lawyer_id = ? AND b.user_id = u.id
                   )
               )
             ORDER BY u.name'
        );
        $stmt->execute([$lawyerId, $lawyerId]);
        return $stmt->fetchAll();
    }

    public function canAccessCallRoom(int $userId, string $room): bool
    {
        ensureMessageMediaColumns();

        $roleStmt = db()->prepare('SELECT role FROM users WHERE id = ? AND status = "active" LIMIT 1');
        $roleStmt->execute([$userId]);
        if ($roleStmt->fetchColumn() === 'admin') {
            return true;
        }

        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM messages
             WHERE call_room = ? AND (sender_id = ? OR receiver_id = ?)'
        );
        $stmt->execute([$room, $userId, $userId]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function accounts(array $ids): array
    {
        $stmt = db()->prepare(
            'SELECT id, role
             FROM users
             WHERE id IN (?, ?) AND status = "active"'
        );
        $stmt->execute(array_values($ids));

        $accounts = [];
        foreach ($stmt->fetchAll() as $account) {
            $accounts[(int) $account['id']] = $account;
        }
        return $accounts;
    }

    private function lawyerIdForUser(int $lawyerUserId): ?int
    {
        $stmt = db()->prepare('SELECT id FROM lawyers WHERE user_id = ? LIMIT 1');
        $stmt->execute([$lawyerUserId]);
        $lawyerId = $stmt->fetchColumn();
        return $lawyerId === false ? null : (int) $lawyerId;
    }

    private function hasRelationship(int $userId, int $lawyerId): bool
    {
        $stmt = db()->prepare(
            'SELECT (
                 EXISTS (
                     SELECT 1
                     FROM case_matches cm
                     JOIN cases c ON c.id = cm.case_id
                     WHERE cm.lawyer_id = ? AND c.user_id = ?
                       AND cm.status IN ("suggested", "viewed", "selected")
                 )
                 OR EXISTS (
                     SELECT 1 FROM bookings b WHERE b.lawyer_id = ? AND b.user_id = ?
                 )
             )'
        );
        $stmt->execute([$lawyerId, $userId, $lawyerId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function caseBelongsToRelationship(int $caseId, int $userId, int $lawyerId): bool
    {
        $stmt = db()->prepare(
            'SELECT (
                 EXISTS (
                     SELECT 1
                     FROM case_matches cm
                     JOIN cases c ON c.id = cm.case_id
                     WHERE cm.case_id = ? AND cm.lawyer_id = ? AND c.user_id = ?
                       AND cm.status IN ("suggested", "viewed", "selected")
                 )
                 OR EXISTS (
                     SELECT 1
                     FROM bookings b
                     WHERE b.case_id = ? AND b.lawyer_id = ? AND b.user_id = ?
                 )
             )'
        );
        $stmt->execute([$caseId, $lawyerId, $userId, $caseId, $lawyerId, $userId]);
        return (bool) $stmt->fetchColumn();
    }

    private function bookingBelongsToRelationship(int $bookingId, int $userId, int $lawyerId): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM bookings
             WHERE id = ? AND user_id = ? AND lawyer_id = ?'
        );
        $stmt->execute([$bookingId, $userId, $lawyerId]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
