<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CaseService.php';
require_once __DIR__ . '/../services/MatchService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('match_lawyers', 10, 60);

    $user = currentUser();
    $caseId = (int) ($_POST['case_id'] ?? 0);
    $caseService = new CaseService();
    $case = $caseService->findForUser($caseId, (int) $user['id']);

    if (!$case) {
        jsonResponse(false, 'ไม่พบเคสนี้', [], [], 404);
    }
    if ((int) $case['user_wants_lawyer'] !== 1) {
        jsonResponse(false, 'ระบบยังไม่สามารถ Match ได้ เพราะผู้ใช้ยังไม่ได้ยินยอม', [], ['consent' => 'required'], 403);
    }

    $caseService->updateMatchDetails($caseId, (int) $user['id'], $_POST);
    $readiness = $caseService->readiness($caseId, (int) $user['id']);
    if (!$readiness['ready']) {
        jsonResponse(false, 'ข้อมูลยังไม่ครบสำหรับ Match ทนาย', [
            'missing' => $readiness['missing'],
            'questions' => $readiness['questions'],
        ], [], 422);
    }

    $matches = (new MatchService())->matchCase($caseId);
    jsonResponse(true, 'Match ทนายสำเร็จ', ['matches' => $matches]);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการ Match ทนาย', [], ['detail' => $exception->getMessage()], 500);
}
