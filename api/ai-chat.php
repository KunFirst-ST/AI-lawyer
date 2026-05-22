<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../services/AIService.php';
require_once __DIR__ . '/../services/CaseService.php';

try {
    requireRole('user');
    verify_csrf();
    rateLimit('ai_chat', 20, 60);

    $user = currentUser();
    $message = trim((string) ($_POST['message'] ?? ''));
    $caseId = isset($_POST['case_id']) && $_POST['case_id'] !== '' ? (int) $_POST['case_id'] : null;

    if ($message === '') {
        jsonResponse(false, 'กรุณาพิมพ์คำถามกฎหมาย', [], ['message' => 'required'], 422);
    }

    $caseService = new CaseService();
    $caseContext = $caseService->contextForAI($caseId, (int) $user['id']);
    $analysis = (new AIService())->analyze($message, $caseContext);

    if (!empty($analysis['is_casual_chat']) && !$caseId) {
        jsonResponse(true, 'AI ตอบกลับแล้ว', [
            'case_id' => null,
            'ai' => $analysis,
        ]);
    }

    $caseId = $caseService->createOrUpdateFromAnalysis((int) $user['id'], $message, $analysis, $caseId);
    $caseService->applyAnsweredFields((int) $caseId, (int) $user['id'], $analysis['answered_fields'] ?? []);
    $caseService->saveChat((int) $user['id'], $caseId, $message, $analysis);

    if (!empty($_FILES['case_document'])) {
        $path = uploadFile($_FILES['case_document'], 'case_documents');
        if ($path) {
            $stmt = db()->prepare('INSERT INTO documents (user_id, case_id, document_type, file_path, original_name) VALUES (?, ?, "case_document", ?, ?)');
            $stmt->execute([(int) $user['id'], $caseId, $path, $_FILES['case_document']['name'] ?? null]);
        }
    }

    jsonResponse(true, 'AI วิเคราะห์ข้อมูลเบื้องต้นแล้ว', [
        'case_id' => $caseId,
        'ai' => $analysis,
    ]);
} catch (Throwable $exception) {
    jsonResponse(false, 'เกิดข้อผิดพลาดในการวิเคราะห์ AI', [], ['detail' => $exception->getMessage()], 500);
}
