<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

final class MatchService
{
    public function matchCase(int $caseId): array
    {
        $case = $this->caseWithPrimaryCategory($caseId);
        if (!$case) {
            throw new RuntimeException('ไม่พบเคส');
        }
        if ((int) $case['user_wants_lawyer'] !== 1) {
            throw new RuntimeException('ยังไม่ได้รับความยินยอมให้ Match ทนาย');
        }

        $caseCategories = $this->caseCategories($caseId);
        $primaryCategory = $case['primary_slug'];
        $relatedCategories = array_values(array_filter(array_map(
            fn ($row) => $row['type'] === 'related' ? $row['slug'] : null,
            $caseCategories
        )));

        $lawyers = $this->approvedLawyers();
        $matches = [];
        foreach ($lawyers as $lawyer) {
            $lawyerCategories = $this->lawyerCategories((int) $lawyer['id']);
            $categorySlugs = array_column($lawyerCategories, 'slug');
            $score = 0;
            $reasons = [];

            if ($primaryCategory && in_array($primaryCategory, $categorySlugs, true)) {
                $score += 50;
                $reasons[] = 'ตรงกับหมวดหลัก “' . legalCategoryName($primaryCategory) . '”';
            }

            foreach ($relatedCategories as $category) {
                if (in_array($category, $categorySlugs, true)) {
                    $score += 20;
                    $reasons[] = 'มีความเชี่ยวชาญเพิ่มเติมด้าน “' . legalCategoryName($category) . '”';
                }
            }

            if (!empty($case['province']) && $lawyer['province'] === $case['province']) {
                $score += 15;
                $reasons[] = 'อยู่จังหวัดเดียวกับคุณ';
            }

            if ($case['budget_max'] !== null && (float) $lawyer['consultation_fee'] <= (float) $case['budget_max']) {
                $score += 10;
                $reasons[] = 'ค่าปรึกษาอยู่ในงบประมาณ';
            }

            if ((int) $lawyer['complex_case_experience'] === 1 && $case['complexity_level'] === 'high') {
                $score += 15;
                $reasons[] = 'มีประสบการณ์ดูแลคดีซับซ้อน';
            }

            if ((int) $lawyer['verified'] === 1) {
                $score += 10;
                $reasons[] = 'ผ่านการยืนยันจากแอดมินแล้ว';
            }

            if ((int) $lawyer['is_available'] === 1) {
                $score += 5;
                $reasons[] = 'เปิดรับงานอยู่';
            }

            $ratingScore = min(((float) ($lawyer['avg_rating'] ?? 0)) * 2, 10);
            $score += $ratingScore;
            if ($ratingScore > 0) {
                $reasons[] = 'มีคะแนนรีวิวดี';
            }

            if ($score > 0) {
                $lawyer['match_score'] = $score;
                $lawyer['match_reason'] = $this->makeReason($reasons);
                $lawyer['categories'] = $lawyerCategories;
                $matches[] = $lawyer;
            }
        }

        usort($matches, fn ($a, $b) => $b['match_score'] <=> $a['match_score']);
        $matches = array_slice($matches, 0, 10);
        $this->storeMatches($caseId, $matches);

        return $matches;
    }

    private function caseWithPrimaryCategory(int $caseId): ?array
    {
        $stmt = db()->prepare(
            'SELECT c.*, lc.slug AS primary_slug, lc.name AS primary_name
             FROM cases c
             LEFT JOIN legal_categories lc ON lc.id = c.primary_category_id
             WHERE c.id = ?
             LIMIT 1'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetch() ?: null;
    }

    private function caseCategories(int $caseId): array
    {
        $stmt = db()->prepare(
            'SELECT lc.slug, lc.name, cc.type
             FROM case_categories cc
             JOIN legal_categories lc ON lc.id = cc.category_id
             WHERE cc.case_id = ?'
        );
        $stmt->execute([$caseId]);
        return $stmt->fetchAll();
    }

    private function approvedLawyers(): array
    {
        $stmt = db()->prepare(
            'SELECT l.*, u.name, u.email, u.phone,
                    (SELECT COALESCE(AVG(r.rating), 0) FROM reviews r WHERE r.lawyer_id = l.id) AS avg_rating
             FROM lawyers l
             JOIN users u ON u.id = l.user_id
             WHERE l.status = "approved"
             ORDER BY l.verified DESC, l.is_available DESC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function lawyerCategories(int $lawyerId): array
    {
        $stmt = db()->prepare(
            'SELECT lc.slug, lc.name
             FROM lawyer_categories lcj
             JOIN legal_categories lc ON lc.id = lcj.category_id
             WHERE lcj.lawyer_id = ?
             ORDER BY lc.name'
        );
        $stmt->execute([$lawyerId]);
        return $stmt->fetchAll();
    }

    private function storeMatches(int $caseId, array $matches): void
    {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM case_matches WHERE case_id = ? AND status = "suggested"')->execute([$caseId]);
            $sql = db_driver() === 'sqlite'
                ? 'INSERT INTO case_matches (case_id, lawyer_id, match_score, match_reason, status)
                   VALUES (?, ?, ?, ?, "suggested")
                   ON CONFLICT(case_id, lawyer_id) DO UPDATE SET
                       match_score = excluded.match_score,
                       match_reason = excluded.match_reason,
                       status = "suggested"'
                : 'INSERT INTO case_matches (case_id, lawyer_id, match_score, match_reason, status)
                   VALUES (?, ?, ?, ?, "suggested")
                   ON DUPLICATE KEY UPDATE match_score = VALUES(match_score), match_reason = VALUES(match_reason), status = "suggested"';
            $stmt = $pdo->prepare($sql);
            foreach ($matches as $match) {
                $stmt->execute([$caseId, $match['id'], $match['match_score'], $match['match_reason']]);
            }

            $status = $matches ? 'matched' : 'waiting_match';
            $matchStatus = $matches ? 'matched' : 'requested_by_user';
            $matchedAt = $matches ? date('Y-m-d H:i:s') : null;
            $caseStmt = $pdo->prepare('UPDATE cases SET status = ?, match_status = ?, matched_at = ? WHERE id = ?');
            $caseStmt->execute([$status, $matchStatus, $matchedAt, $caseId]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function makeReason(array $reasons): string
    {
        if (!$reasons) {
            return 'ทนายคนนี้มีข้อมูลตรงกับเคสของคุณบางส่วน';
        }

        return 'ทนายคนนี้' . implode(' และ', array_map(fn ($reason) => $reason, $reasons));
    }
}
