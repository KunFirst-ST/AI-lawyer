<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/ActivityService.php';

final class CaseService
{
    public function createOrUpdateFromAnalysis(int $userId, string $message, array $analysis, ?int $caseId = null): int
    {
        $pdo = db();
        $pdo->beginTransaction();
        $created = false;

        try {
            $case = $caseId ? $this->findForUser($caseId, $userId) : null;
            $primaryCategoryId = $this->categoryIdBySlug($analysis['primary_category'] ?? null);
            $title = $this->makeTitle($message);

            if ($case) {
                $nextMatchStatus = $case['match_status'] === 'not_asked' ? 'asked' : $case['match_status'];
                $nextStatus = $case['status'] ?: 'ai_consulting';
                $stmt = $pdo->prepare(
                    'UPDATE cases
                     SET title = COALESCE(NULLIF(title, ""), ?),
                         description = COALESCE(description, ?),
                         primary_category_id = ?,
                         complexity_level = ?,
                         urgency = ?,
                         ai_summary = ?,
                         lawyer_review_required = ?,
                         match_status = ?,
                         status = ?
                     WHERE id = ? AND user_id = ?'
                );
                $stmt->execute([
                    $title,
                    $message,
                    $primaryCategoryId,
                    $analysis['complexity_level'],
                    $analysis['urgency'],
                    $analysis['case_summary_for_lawyer'],
                    !empty($analysis['lawyer_review_required']) ? 1 : 0,
                    $nextMatchStatus,
                    $nextStatus,
                    $caseId,
                    $userId,
                ]);
            } else {
                $created = true;
                $stmt = $pdo->prepare(
                    'INSERT INTO cases
                     (user_id, title, description, primary_category_id, complexity_level, urgency, ai_summary, lawyer_review_required, match_status, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, "asked", "ai_consulting")'
                );
                $stmt->execute([
                    $userId,
                    $title,
                    $message,
                    $primaryCategoryId,
                    $analysis['complexity_level'],
                    $analysis['urgency'],
                    $analysis['case_summary_for_lawyer'],
                    !empty($analysis['lawyer_review_required']) ? 1 : 0,
                ]);
                $caseId = (int) $pdo->lastInsertId();
            }

            $shouldSyncLegalAnalysis = !$case || (($analysis['conversation_intent'] ?? 'new_legal_question') === 'new_legal_question');
            if ($shouldSyncLegalAnalysis) {
                $this->syncCategories((int) $caseId, $analysis);
                $this->syncIssues((int) $caseId, $analysis['sub_issues'] ?? []);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }

        if ($created) {
            $activity = new ActivityService();
            $activity->caseEvent((int) $caseId, $userId, 'case_created', 'สร้างเคสจากการสนทนากับ AI');
            $activity->audit($userId, 'case.create', 'case', (int) $caseId);
        }

        return (int) $caseId;
    }

    public function saveChat(int $userId, int $caseId, string $userMessage, array $analysis): void
    {
        $stmt = db()->prepare('INSERT INTO ai_chats (user_id, case_id, user_message, ai_response, ai_json) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $userId,
            $caseId,
            $userMessage,
            $analysis['reply_to_user'] ?? '',
            json_encode($analysis, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function contextForAI(?int $caseId, int $userId): array
    {
        if (!$caseId) {
            return [];
        }

        $case = $this->findForUser($caseId, $userId);
        if (!$case) {
            return [];
        }

        $primary = null;
        if (!empty($case['primary_category_id'])) {
            $stmt = db()->prepare('SELECT id, name, slug FROM legal_categories WHERE id = ? LIMIT 1');
            $stmt->execute([$case['primary_category_id']]);
            $primary = $stmt->fetch() ?: null;
        }

        $categoryOrder = db_driver() === 'sqlite'
            ? 'CASE cc.type WHEN "primary" THEN 1 WHEN "related" THEN 2 ELSE 3 END'
            : 'FIELD(cc.type, "primary", "related")';
        $stmt = db()->prepare(
            "SELECT lc.id, lc.name, lc.slug, cc.type
             FROM case_categories cc
             JOIN legal_categories lc ON lc.id = cc.category_id
             WHERE cc.case_id = ?
             ORDER BY {$categoryOrder}, lc.name"
        );
        $stmt->execute([$caseId]);
        $categories = $stmt->fetchAll();

        $chatStmt = db()->prepare(
            'SELECT user_message, ai_response, ai_json, created_at
             FROM ai_chats
             WHERE case_id = ? AND user_id = ?
             ORDER BY created_at DESC
             LIMIT 6'
        );
        $chatStmt->execute([$caseId, $userId]);
        $history = array_reverse($chatStmt->fetchAll());

        $lastAiJson = null;
        foreach (array_reverse($history) as $row) {
            $decoded = json_decode((string) ($row['ai_json'] ?? ''), true);
            if (is_array($decoded)) {
                $lastAiJson = $decoded;
                break;
            }
        }

        return [
            'case' => $case,
            'primary_category' => $primary,
            'related_categories' => array_values(array_filter($categories, fn ($row) => $row['type'] === 'related')),
            'all_categories' => $categories,
            'last_ai_json' => $lastAiJson,
            'last_questions' => $lastAiJson['questions_to_ask_next'] ?? [],
            'filled_fields' => [
                'province' => $case['province'],
                'consultation_type' => $case['consultation_type'],
                'budget_min' => $case['budget_min'],
                'budget_max' => $case['budget_max'],
                'urgency' => $case['urgency'],
            ],
            'history' => array_map(fn ($row) => [
                'user_message' => $row['user_message'],
                'ai_response' => $row['ai_response'],
                'created_at' => $row['created_at'],
            ], $history),
        ];
    }

    public function applyAnsweredFields(int $caseId, int $userId, array $fields): void
    {
        $data = [];
        if (!empty($fields['province'])) {
            $data['province'] = $fields['province'];
        }
        if (!empty($fields['consultation_type'])) {
            $data['consultation_type'] = $fields['consultation_type'];
        }
        if (($fields['budget_min'] ?? null) !== null) {
            $data['budget_min'] = $fields['budget_min'];
        }
        if (($fields['budget_max'] ?? null) !== null) {
            $data['budget_max'] = $fields['budget_max'];
        }

        if ($data) {
            $this->updateMatchDetails($caseId, $userId, $data);
        }
    }

    public function findForUser(int $caseId, int $userId): ?array
    {
        $stmt = db()->prepare('SELECT * FROM cases WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$caseId, $userId]);
        return $stmt->fetch() ?: null;
    }

    public function readiness(int $caseId, int $userId): array
    {
        $case = $this->findForUser($caseId, $userId);
        if (!$case) {
            return ['ready' => false, 'missing' => ['case'], 'questions' => ['ไม่พบเคสนี้']];
        }

        $missing = [];
        $questions = [];

        if (empty($case['primary_category_id'])) {
            $missing[] = 'primary_category';
        }
        if (empty($case['province'])) {
            $missing[] = 'province';
            $questions[] = 'เพื่อช่วยหาทนายให้ตรงขึ้น เหตุการณ์เกิดขึ้นที่จังหวัดไหน หรือคุณต้องการทนายในจังหวัดใด?';
        }
        if (empty($case['consultation_type']) || $case['consultation_type'] === 'any') {
            $missing[] = 'consultation_type';
            $questions[] = 'คุณสะดวกปรึกษาแบบออนไลน์ โทร วิดีโอคอล หรือพบตัวจริง?';
        }
        if ($case['budget_min'] === null && $case['budget_max'] === null) {
            $missing[] = 'budget';
            $questions[] = 'คุณมีงบประมาณเบื้องต้นสำหรับการปรึกษาทนายประมาณเท่าไร?';
        }
        if (empty($case['urgency'])) {
            $missing[] = 'urgency';
        }

        return [
            'ready' => count($missing) === 0,
            'missing' => $missing,
            'questions' => $questions,
            'case' => $case,
        ];
    }

    public function updateMatchDetails(int $caseId, int $userId, array $data): void
    {
        $allowedTypes = ['chat', 'phone', 'video', 'onsite', 'any'];
        $province = trim((string) ($data['province'] ?? ''));
        $consultationType = in_array($data['consultation_type'] ?? '', $allowedTypes, true) ? $data['consultation_type'] : null;
        $budgetMin = isset($data['budget_min']) && $data['budget_min'] !== '' ? (float) $data['budget_min'] : null;
        $budgetMax = isset($data['budget_max']) && $data['budget_max'] !== '' ? (float) $data['budget_max'] : null;

        $fields = [];
        $params = [];
        if ($province !== '') {
            $fields[] = 'province = ?';
            $params[] = $province;
        }
        if ($consultationType !== null) {
            $fields[] = 'consultation_type = ?';
            $params[] = $consultationType;
        }
        if ($budgetMin !== null) {
            $fields[] = 'budget_min = ?';
            $params[] = $budgetMin;
        }
        if ($budgetMax !== null) {
            $fields[] = 'budget_max = ?';
            $params[] = $budgetMax;
        }

        if (!$fields) {
            return;
        }

        $params[] = $caseId;
        $params[] = $userId;
        $stmt = db()->prepare('UPDATE cases SET ' . implode(', ', $fields) . ' WHERE id = ? AND user_id = ?');
        $stmt->execute($params);
    }

    public function setConsent(int $caseId, int $userId, bool $wantsLawyer): void
    {
        $stmt = db()->prepare(
            'UPDATE cases
             SET user_wants_lawyer = ?,
                 match_status = ?,
                 status = ?
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([
            $wantsLawyer ? 1 : 0,
            $wantsLawyer ? 'requested_by_user' : 'declined_by_user',
            $wantsLawyer ? 'waiting_match' : 'ai_consulting',
            $caseId,
            $userId,
        ]);

        if ($stmt->rowCount() > 0) {
            $activity = new ActivityService();
            $activity->caseEvent(
                $caseId,
                $userId,
                $wantsLawyer ? 'lawyer_requested' : 'lawyer_declined',
                $wantsLawyer ? 'ผู้ใช้ขอให้ระบบช่วยค้นหาทนาย' : 'ผู้ใช้ยังไม่ต้องการค้นหาทนาย'
            );
            $activity->audit($userId, $wantsLawyer ? 'case.request_lawyer' : 'case.decline_lawyer', 'case', $caseId);
        }
    }

    public function categoryIdBySlug(?string $slug): ?int
    {
        if (!$slug) {
            return null;
        }
        $stmt = db()->prepare('SELECT id FROM legal_categories WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int) $id;
    }

    private function syncCategories(int $caseId, array $analysis): void
    {
        $pdo = db();
        $pdo->prepare('DELETE FROM case_categories WHERE case_id = ?')->execute([$caseId]);

        $primaryId = $this->categoryIdBySlug($analysis['primary_category'] ?? null);
        if ($primaryId) {
            $stmt = $pdo->prepare('INSERT INTO case_categories (case_id, category_id, type, confidence_score) VALUES (?, ?, "primary", 95)');
            $stmt->execute([$caseId, $primaryId]);
        }

        $stmt = $pdo->prepare('INSERT INTO case_categories (case_id, category_id, type, confidence_score) VALUES (?, ?, "related", 75)');
        foreach (($analysis['related_categories'] ?? []) as $slug) {
            $categoryId = $this->categoryIdBySlug($slug);
            if ($categoryId) {
                $stmt->execute([$caseId, $categoryId]);
            }
        }
    }

    private function syncIssues(int $caseId, array $issues): void
    {
        $pdo = db();
        $pdo->prepare('DELETE FROM case_legal_issues WHERE case_id = ?')->execute([$caseId]);
        $stmt = $pdo->prepare(
            'INSERT INTO case_legal_issues (case_id, category_id, issue_title, issue_summary, risk_level)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($issues as $issue) {
            $categoryId = $this->categoryIdBySlug($issue['category'] ?? null);
            $risk = in_array($issue['risk'] ?? '', ['low', 'medium', 'high', 'critical'], true) ? $issue['risk'] : 'medium';
            $stmt->execute([
                $caseId,
                $categoryId,
                mb_substr((string) ($issue['issue'] ?? 'ประเด็นกฎหมาย'), 0, 255),
                (string) ($issue['issue'] ?? ''),
                $risk,
            ]);
        }
    }

    private function makeTitle(string $message): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $message));
        return mb_strlen($title) > 80 ? mb_substr($title, 0, 80) . '...' : $title;
    }
}
