<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/CaseService.php';
require_once __DIR__ . '/../services/MatchService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('user_consent', 30, 60);

    $user = currentUser();
    $caseId = (int) ($_POST['case_id'] ?? 0);
    $consent = $_POST['consent'] ?? '';
    $caseService = new CaseService();

    if (!$caseService->findForUser($caseId, (int) $user['id'])) {
        jsonResponse(false, 'ไม่พบเคสนี้', [], [], 404);
    }

    if ($consent === 'no') {
        $caseService->setConsent($caseId, (int) $user['id'], false);
        jsonResponse(true, 'ได้ครับ ระบบจะยังไม่หาทนายให้ตอนนี้ คุณสามารถคุยกับ AI ต่อเพื่อทำความเข้าใจปัญหาเบื้องต้นได้ หรือกลับมากด “หาทนาย” ภายหลังเมื่อพร้อม', [
            'case_id' => $caseId,
            'match_status' => 'declined_by_user',
        ]);
    }

    if ($consent !== 'yes') {
        jsonResponse(false, 'กรุณาระบุความยินยอม', [], ['consent' => 'invalid'], 422);
    }

    $caseService->updateMatchDetails($caseId, (int) $user['id'], $_POST);
    $caseService->setConsent($caseId, (int) $user['id'], true);
    $readiness = $caseService->readiness($caseId, (int) $user['id']);

    if (!$readiness['ready']) {
        jsonResponse(true, 'ได้ครับ ระบบจะช่วยหาทนายที่เหมาะกับเคสนี้ ก่อนจับคู่ ขอข้อมูลเพิ่มเล็กน้อย', [
            'case_id' => $caseId,
            'requires_more_info' => true,
            'missing' => $readiness['missing'],
            'questions' => $readiness['questions'],
        ]);
    }

    if (setting('auto_match_after_consent', '1') !== '1') {
        jsonResponse(true, 'ระบบรับคำขอ Match แล้ว แอดมินตั้งค่าให้รอตรวจสอบก่อนจับคู่', [
            'case_id' => $caseId,
            'requires_more_info' => false,
            'matches' => [],
        ]);
    }

    $matches = (new MatchService())->matchCase($caseId);
    jsonResponse(true, 'ระบบพบทนายที่เหมาะกับเคสของคุณ', [
        'case_id' => $caseId,
        'requires_more_info' => false,
        'matches' => $matches,
    ]);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการบันทึกความยินยอม', [], ['detail' => $exception->getMessage()], 500);
}
