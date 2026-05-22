<?php

require_once __DIR__ . '/../includes/functions.php';

final class AIService
{
    private array $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/ai.php';
    }

    public function analyze(string $message, array $caseContext = []): array
    {
        $message = trim($message);
        if ($message === '') {
            throw new InvalidArgumentException('กรุณาพิมพ์คำถามกฎหมาย');
        }

        if ($this->isCasualChat($message) && empty($caseContext['case'])) {
            return $this->casualChatReply($message);
        }

        if (!empty($this->config['api_key']) && function_exists('curl_init')) {
            try {
                $aiJson = $this->callOpenAI($message, $caseContext);
                return $this->normalize($aiJson, $message, $caseContext);
            } catch (Throwable $exception) {
                error_log('AI API failed: ' . $exception->getMessage());
            }
        }

        return $this->fallbackAnalysis($message, $caseContext);
    }

    private function callOpenAI(string $message, array $caseContext): array
    {
        $contextText = $caseContext ? "\n\nข้อมูลเคสเดิม:\n" . json_encode($caseContext, JSON_UNESCAPED_UNICODE) : '';
        $payload = [
            'model' => $this->config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $this->config['system_prompt']],
                ['role' => 'user', 'content' => $message . $contextText],
            ],
            'temperature' => 0.45,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init((string) $this->config['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => (int) $this->config['timeout'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->config['api_key'],
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $statusCode >= 400) {
            throw new RuntimeException($curlError ?: 'OpenAI API returned HTTP ' . $statusCode);
        }

        $decoded = json_decode((string) $response, true);
        $content = $decoded['choices'][0]['message']['content'] ?? $decoded['output_text'] ?? null;
        if (!$content) {
            throw new RuntimeException('AI response has no content');
        }

        $jsonText = $this->extractJson((string) $content);
        $aiJson = json_decode($jsonText, true);
        if (!is_array($aiJson)) {
            throw new RuntimeException('AI response is not valid JSON');
        }

        return $aiJson;
    }

    private function extractJson(string $content): string
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = preg_replace('/\s*```$/', '', (string) $content);
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($content, $start, $end - $start + 1);
        }

        return $content;
    }

    private function normalize(array $data, string $message, array $caseContext = []): array
    {
        $categories = [
            'criminal', 'civil', 'family', 'labor', 'business', 'land', 'inheritance',
            'tax', 'consumer', 'intellectual_property', 'immigration', 'bankruptcy', 'contract',
        ];

        $primary = in_array($data['primary_category'] ?? '', $categories, true)
            ? $data['primary_category']
            : $this->fallbackPrimaryCategory($message);

        $related = array_values(array_unique(array_filter($data['related_categories'] ?? [], fn ($slug) => in_array($slug, $categories, true) && $slug !== $primary)));
        $complexity = in_array($data['complexity_level'] ?? '', ['low', 'medium', 'high'], true) ? $data['complexity_level'] : 'medium';
        $urgency = in_array($data['urgency'] ?? '', ['low', 'medium', 'high', 'critical'], true) ? $data['urgency'] : 'medium';
        $fallbackAnsweredFields = $this->detectAnsweredFields($message);
        $intent = in_array($data['conversation_intent'] ?? '', ['new_legal_question', 'answering_follow_up', 'procedural_follow_up', 'lawyer_match_info', 'casual_chat'], true)
            ? $data['conversation_intent']
            : $this->detectConversationIntent($message, $caseContext, $fallbackAnsweredFields);

        $answeredFields = $this->normalizeAnsweredFields(is_array($data['answered_fields'] ?? null) ? $data['answered_fields'] : $fallbackAnsweredFields);
        $missingContextFields = is_array($data['missing_context_fields'] ?? null) && $data['missing_context_fields']
            ? array_values(array_filter($data['missing_context_fields']))
            : $this->missingContextFields($caseContext, $answeredFields);

        return [
            'reply_to_user' => (string) ($data['reply_to_user'] ?? "เข้าใจครับ ผมช่วยประเมินเบื้องต้นให้แล้ว\n\nข้อมูลนี้ยังไม่ใช่คำปรึกษาจากทนายโดยตรง ถ้าต้องการ ผมช่วยเตรียมเคสและหาทนายที่เหมาะกับเรื่องนี้ให้ต่อได้ครับ"),
            'primary_category' => $primary,
            'related_categories' => $related,
            'category_count' => max(1, 1 + count($related)),
            'sub_issues' => is_array($data['sub_issues'] ?? null) ? $data['sub_issues'] : [],
            'complexity_level' => $complexity,
            'urgency' => $urgency,
            'recommended_documents' => array_values(array_filter((array) ($data['recommended_documents'] ?? []))),
            'possible_legal_sections' => $this->normalizeLegalSections($data['possible_legal_sections'] ?? []),
            'conversation_intent' => $intent,
            'answered_fields' => $answeredFields,
            'missing_context_fields' => $missingContextFields,
            'should_reuse_existing_case' => (bool) ($data['should_reuse_existing_case'] ?? ($intent !== 'new_legal_question')),
            'case_summary_for_lawyer' => (string) ($data['case_summary_for_lawyer'] ?? $message),
            'ask_user_for_lawyer_match' => true,
            'user_wants_lawyer' => null,
            'can_match_now' => false,
            'lawyer_review_required' => (bool) ($data['lawyer_review_required'] ?? ($complexity === 'high' || in_array($urgency, ['high', 'critical'], true))),
            'questions_to_ask_next' => array_values(array_filter((array) ($data['questions_to_ask_next'] ?? []))),
        ];
    }

    private function fallbackAnalysis(string $message, array $caseContext = []): array
    {
        if ($this->isCasualChat($message) && empty($caseContext['case'])) {
            return $this->casualChatReply($message);
        }

        $answeredFields = $this->detectAnsweredFields($message);
        $intent = $this->detectConversationIntent($message, $caseContext, $answeredFields);
        $shouldReuseCase = $intent !== 'new_legal_question' && !empty($caseContext['case']);
        $case = $caseContext['case'] ?? [];

        $primary = $shouldReuseCase && !empty($caseContext['primary_category']['slug'])
            ? $caseContext['primary_category']['slug']
            : $this->fallbackPrimaryCategory($message);
        $related = $shouldReuseCase && !empty($caseContext['related_categories'])
            ? array_values(array_filter(array_map(fn ($row) => $row['slug'] ?? null, $caseContext['related_categories'])))
            : $this->detectRelatedCategories($message, $primary);
        $urgency = $shouldReuseCase && !empty($case['urgency']) ? $case['urgency'] : $this->detectUrgency($message);
        $complexity = count($related) >= 2 || in_array($urgency, ['high', 'critical'], true) ? 'high' : (count($related) === 1 ? 'medium' : 'low');
        if ($shouldReuseCase && !empty($case['complexity_level'])) {
            $complexity = $case['complexity_level'];
        }
        $sections = $this->fallbackLegalSections($message, $primary, $related);

        $documents = $this->documentsForCategory($primary, $message);

        $questions = $this->buildSmartQuestions($caseContext, $answeredFields, $message, $primary);
        $missingFields = $this->missingContextFields($caseContext, $answeredFields);

        $reply = $this->buildFallbackReply($message, $primary, $related, $urgency, $sections, $documents, $questions, $intent);

        return [
            'reply_to_user' => $reply,
            'primary_category' => $primary,
            'related_categories' => $related,
            'category_count' => 1 + count($related),
            'sub_issues' => [
                [
                    'issue' => mb_substr($message, 0, 120),
                    'category' => $primary,
                    'risk' => $urgency,
                ],
            ],
            'complexity_level' => $complexity,
            'urgency' => $urgency,
            'recommended_documents' => $documents,
            'possible_legal_sections' => $sections,
            'conversation_intent' => $intent,
            'answered_fields' => $answeredFields,
            'missing_context_fields' => $missingFields,
            'should_reuse_existing_case' => $shouldReuseCase,
            'case_summary_for_lawyer' => $shouldReuseCase && !empty($case['ai_summary'])
                ? $case['ai_summary'] . "\nข้อมูลเพิ่มเติมจากผู้ใช้: " . mb_substr($message, 0, 400)
                : mb_substr($message, 0, 800),
            'ask_user_for_lawyer_match' => true,
            'user_wants_lawyer' => null,
            'can_match_now' => false,
            'lawyer_review_required' => $complexity === 'high' || in_array($urgency, ['high', 'critical'], true),
            'questions_to_ask_next' => $questions,
        ];
    }

    private function buildFallbackReply(string $message, string $primary, array $related, string $urgency, array $sections, array $documents, array $questions, string $intent): string
    {
        $categoryName = legalCategoryName($primary);
        $insight = $this->issueInsight($message, $primary, $urgency);
        $actions = $this->actionStepsForCategory($primary, $message, $urgency);
        $actionText = implode("\n", array_map(fn (string $step): string => '- ' . $step, array_slice($actions, 0, 2)));
        $questionText = $questions
            ? "\n\nขอถามเพิ่มนิดเดียว: " . rtrim((string) $questions[0], '?') . '?'
            : '';
        $categoryLine = $insight . ' น่าจะเกี่ยวกับ' . $categoryName . 'ครับ';

        $opening = match ($urgency) {
            'critical' => 'เข้าใจครับ เรื่องนี้เร่งด่วน ควรดูความปลอดภัยและหลักฐานก่อน',
            'high' => 'เข้าใจครับ เรื่องนี้มีความเสี่ยง ควรรีบจัดหลักฐานไว้ก่อน',
            default => 'โอเคครับ ผมจับประเด็นให้สั้น ๆ นะ',
        };

        if ($intent === 'answering_follow_up') {
            $opening = 'รับข้อมูลเพิ่มแล้วครับ อันนี้ผมจะนับต่อจากเคสเดิม';
        } elseif ($intent === 'procedural_follow_up') {
            $opening = 'ได้ครับ ขั้นต่อไปทำแบบนี้ก่อน';
        }

        return $opening .
            "\n\n" . $categoryLine .
            "\n\nทำก่อน:\n" . $actionText .
            $questionText .
            "\n\nถ้าต้องการ ผมหาทนายที่เหมาะกับเรื่องนี้ให้ได้ครับ";
    }

    private function isCasualChat(string $message): bool
    {
        $text = trim(mb_strtolower($message));
        $text = preg_replace('/[[:punct:]\s]+/u', '', (string) $text);
        if ($text === '') {
            return false;
        }

        $casual = [
            'สวัสดี', 'สวัสดีครับ', 'สวัสดีค่ะ', 'ดีครับ', 'ดีค่ะ', 'หวัดดี', 'หวัดดีครับ', 'หวัดดีค่ะ',
            'hello', 'hi', 'hey', 'ขอบคุณ', 'ขอบคุณครับ', 'ขอบคุณค่ะ', 'โอเค', 'ok', 'okay',
        ];

        foreach ($casual as $phrase) {
            if ($text === mb_strtolower($phrase)) {
                return true;
            }
        }

        return mb_strlen($text) <= 20
            && !$this->hasLegalIssueKeyword($text)
            && !$this->messageContainsAny($text, ['โดน', 'ถูก', 'ฟ้อง', 'เงิน', 'สัญญา', 'แจ้งความ', 'หมาย']);
    }

    private function casualChatReply(string $message): array
    {
        $text = mb_strtolower(trim($message));
        $reply = str_contains($text, 'ขอบคุณ')
            ? 'ยินดีครับ ถ้ามีเรื่องกฎหมายที่กังวล เล่าให้ผมฟังได้เลย'
            : "สวัสดีครับ เล่าเรื่องที่เกิดขึ้นแบบสั้น ๆ ได้เลย\nผมจะช่วยจับประเด็นให้ว่าเกี่ยวกับอะไร และควรทำอะไรก่อน";

        return [
            'reply_to_user' => $reply,
            'primary_category' => 'civil',
            'related_categories' => [],
            'category_count' => 1,
            'sub_issues' => [],
            'complexity_level' => 'low',
            'urgency' => 'low',
            'recommended_documents' => [],
            'possible_legal_sections' => [],
            'conversation_intent' => 'casual_chat',
            'answered_fields' => $this->normalizeAnsweredFields([]),
            'missing_context_fields' => [],
            'should_reuse_existing_case' => false,
            'case_summary_for_lawyer' => '',
            'ask_user_for_lawyer_match' => false,
            'user_wants_lawyer' => null,
            'can_match_now' => false,
            'lawyer_review_required' => false,
            'questions_to_ask_next' => [],
            'is_casual_chat' => true,
        ];
    }

    private function issueInsight(string $message, string $primary, string $urgency): string
    {
        if ($this->messageContainsAny($message, ['ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'คุกคามทางเพศ'])) {
            return 'เรื่องที่เล่าอาจเป็นคดีอาญาร้ายแรง สิ่งสำคัญคือความปลอดภัย หลักฐาน และการขอความช่วยเหลือเร็วที่สุด';
        }
        if ($this->messageContainsAny($message, ['โอนเงิน', 'ไม่ได้รับสินค้า', 'ไม่ส่งของ', 'บล็อก', 'ไม่ตอบแชต', 'หลอกโอน'])) {
            return 'เรื่องนี้คล้ายซื้อของออนไลน์แล้วไม่ได้ของ อาจดูได้ทั้งเรื่องหลอกลวงและการเรียกเงินคืน';
        }
        if ($this->messageContainsAny($message, ['เลิกจ้าง', 'ไม่จ่ายเงินเดือน', 'ค่าชดเชย', 'นายจ้าง'])) {
            return 'ประเด็นหลักคือการจ้างงาน ต้องดูเหตุเลิกจ้าง เงินค้าง และอายุงาน';
        }
        if ($this->messageContainsAny($message, ['หย่า', 'ค่าเลี้ยงดู', 'บุตร', 'สินสมรส'])) {
            return 'เรื่องนี้เกี่ยวกับครอบครัว ต้องแยกเรื่องหย่า บุตร ทรัพย์สิน หรือค่าเลี้ยงดูให้ชัด';
        }
        if ($this->messageContainsAny($message, ['สัญญา', 'ผิดสัญญา', 'ยกเลิกสัญญา', 'เงินมัดจำ'])) {
            return 'แกนของเรื่องอยู่ที่สัญญา ต้องดูว่าตกลงอะไรไว้ และอีกฝ่ายผิดเงื่อนไขตรงไหน';
        }
        if ($this->messageContainsAny($message, ['หมายศาล', 'หมายเรียก', 'ถูกฟ้อง'])) {
            return 'มีเรื่องคดีเข้ามาแล้ว ต้องดูวันครบกำหนดในหมายก่อนพลาดเวลา';
        }

        return 'ข้อมูลยังน้อยอยู่ ผมประเมินเบื้องต้นได้ แต่ต้องรู้วันเกิดเหตุ จังหวัด คู่กรณี และเอกสารที่มีเพิ่ม';
    }

    private function actionStepsForCategory(string $primary, string $message, string $urgency): array
    {
        if ($this->messageContainsAny($message, ['ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'คุกคามทางเพศ'])) {
            return [
                'ถ้ายังไม่ปลอดภัย ให้ย้ายไปอยู่กับคนที่ไว้ใจได้หรือสถานที่ปลอดภัยก่อน',
                'พยายามเก็บหลักฐานเดิมไว้ เช่น เสื้อผ้า แชต รูป พยาน ตำแหน่งที่เกิดเหตุ และเวลาเกิดเหตุ',
                'ไปพบแพทย์หรือหน่วยงานที่เกี่ยวข้องเพื่อตรวจร่างกายและทำบันทึกหลักฐานโดยเร็ว',
                'คุยกับทนายหรือผู้เชี่ยวชาญก่อนเล่ารายละเอียดต่อคู่กรณีหรือบุคคลที่อาจกดดันคุณ',
            ];
        }

        return match ($primary) {
            'criminal' => [
                'เก็บหลักฐานต้นฉบับ เช่น แชต สลิป รูปภาพ พยาน และข้อมูลคู่กรณี',
                'เรียงไทม์ไลน์ว่าเกิดอะไรขึ้น วันไหน โอนหรือเสียหายเท่าไร',
                'หลีกเลี่ยงการข่มขู่หรือโพสต์กล่าวหาเพิ่ม เพราะอาจเกิดความเสี่ยงทางกฎหมายกลับมา',
                in_array($urgency, ['high', 'critical'], true) ? 'ควรให้ทนายหรือเจ้าหน้าที่ช่วยดูขั้นตอนเร็วขึ้น' : 'ถ้าจะดำเนินคดี ให้เตรียมข้อมูลก่อนเข้าพบตำรวจหรือทนาย',
            ],
            'labor' => [
                'เก็บสัญญาจ้าง สลิปเงินเดือน ตารางงาน และหนังสือเลิกจ้างถ้ามี',
                'จดวันที่เริ่มงาน วันที่เลิกจ้าง และยอดเงินที่ยังไม่ได้รับ',
                'เก็บแชตหรืออีเมลที่นายจ้างแจ้งเหตุเลิกจ้างหรือสั่งงาน',
                'อย่าเพิ่งลงชื่อเอกสารยอมรับเงินหรือสละสิทธิถ้ายังไม่เข้าใจผลของเอกสาร',
            ],
            'family' => [
                'แยกประเด็นให้ชัดว่าเป็นเรื่องหย่า บุตร ค่าเลี้ยงดู หรือทรัพย์สิน',
                'รวบรวมทะเบียนสมรส สูติบัตร หลักฐานรายได้ และรายการทรัพย์สิน',
                'ถ้ามีความรุนแรงในครอบครัว ให้ให้ความสำคัญกับความปลอดภัยก่อน',
                'เตรียมคำถามที่ต้องการตกลงหรือฟ้องร้องให้ชัดก่อนคุยกับทนาย',
            ],
            'land' => [
                'เตรียมโฉนด เอกสารสิทธิ สัญญา และภาพถ่ายพื้นที่',
                'จดพิกัด ขอบเขตที่ดิน และชื่อบุคคลที่เกี่ยวข้อง',
                'เก็บหนังสือราชการหรือเอกสารจากสำนักงานที่ดินถ้ามี',
                'หลีกเลี่ยงการรื้อถอนหรือเข้าไปในพื้นที่พิพาทโดยไม่เข้าใจสิทธิของตน',
            ],
            'business' => [
                'รวบรวมหนังสือรับรองบริษัท สัญญา ใบเสนอราคา ใบแจ้งหนี้ และหลักฐานการโอนเงิน',
                'แยกว่าคู่กรณีเป็นลูกค้า หุ้นส่วน กรรมการ หรือผู้ถือหุ้น',
                'ตรวจว่ามีเงื่อนไขบอกเลิก ปรับ หรือระงับบริการในสัญญาหรือไม่',
                'ประเมินมูลค่าความเสียหายและผลกระทบต่อธุรกิจให้ชัด',
            ],
            default => [
                'รวบรวมเอกสารและหลักฐานที่เกี่ยวข้องทั้งหมดไว้ในที่เดียว',
                'เขียนไทม์ไลน์สั้น ๆ ว่าเกิดอะไรขึ้น วันไหน ใครเกี่ยวข้อง',
                'ประเมินมูลค่าความเสียหายหรือสิ่งที่ต้องการให้คู่กรณีแก้ไข',
                'อย่าเพิ่งเซ็นเอกสารหรือรับข้อเสนอใหม่ถ้ายังไม่เข้าใจผลทางกฎหมาย',
            ],
        };
    }

    private function documentsForCategory(string $primary, string $message): array
    {
        if ($this->messageContainsAny($message, ['ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'คุกคามทางเพศ'])) {
            return ['ใบรับรองแพทย์หรือบันทึกการตรวจถ้ามี', 'เสื้อผ้าหรือหลักฐานเดิมที่เกี่ยวกับเหตุการณ์', 'แชต รูปภาพ พิกัด เวลา และพยาน', 'บันทึกประจำวันหรือใบแจ้งความถ้ามี'];
        }

        return match ($primary) {
            'criminal' => ['บันทึกประจำวันหรือหมายเรียกถ้ามี', 'หลักฐานแชตหรือภาพถ่าย', 'สลิปโอนเงินหรือเอกสารความเสียหาย', 'ข้อมูลคู่กรณี'],
            'business' => ['หนังสือรับรองบริษัท', 'สัญญาหุ้นส่วนหรือสัญญาธุรกิจ', 'เอกสารบัญชีหรือภาษี', 'หลักฐานการโอนเงิน'],
            'family' => ['ทะเบียนสมรสหรือสูติบัตร', 'หลักฐานรายได้', 'เอกสารทรัพย์สิน', 'ข้อตกลงเดิมถ้ามี'],
            'labor' => ['สัญญาจ้าง', 'สลิปเงินเดือน', 'หนังสือเลิกจ้าง', 'แชตหรืออีเมลกับนายจ้าง'],
            'land' => ['โฉนดหรือเอกสารสิทธิ', 'ภาพถ่ายพื้นที่', 'สัญญาซื้อขายหรือเช่า', 'หนังสือราชการที่เกี่ยวข้อง'],
            default => ['สัญญาหรือเอกสารที่เกี่ยวข้อง', 'หลักฐานการติดต่อกับคู่กรณี', 'หลักฐานการชำระเงิน', 'หนังสือทวงถามหรือหมายศาลถ้ามี'],
        };
    }

    private function messageContainsAny(string $message, array $keywords): bool
    {
        $lower = mb_strtolower($message);
        foreach ($keywords as $keyword) {
            if (str_contains($lower, mb_strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeLegalSections(array $sections): array
    {
        $normalized = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            $lawName = trim((string) ($section['law_name'] ?? ''));
            $sectionNo = trim((string) ($section['section'] ?? ''));
            if ($lawName === '' || $sectionNo === '') {
                continue;
            }
            $confidence = in_array($section['confidence'] ?? '', ['low', 'medium', 'high'], true) ? $section['confidence'] : 'medium';
            $normalized[] = [
                'law_name' => $lawName,
                'section' => $sectionNo,
                'plain_meaning' => trim((string) ($section['plain_meaning'] ?? '')),
                'why_relevant' => trim((string) ($section['why_relevant'] ?? '')),
                'confidence' => $confidence,
                'needs_lawyer_review' => (bool) ($section['needs_lawyer_review'] ?? true),
            ];
        }

        return array_slice($normalized, 0, 8);
    }

    private function fallbackLegalSections(string $message, string $primary, array $related): array
    {
        $categories = array_values(array_unique(array_merge([$primary], $related)));
        $sections = [];
        foreach ($categories as $category) {
            foreach ($this->sectionTemplatesForCategory($category, $message) as $section) {
                if (($section['confidence'] ?? 'medium') === 'low') {
                    continue;
                }
                $key = $section['law_name'] . '#' . $section['section'];
                $sections[$key] = $section;
            }
        }

        return array_slice(array_values($sections), 0, 8);
    }

    private function sectionTemplatesForCategory(string $category, string $message): array
    {
        $contains = fn (array $keywords): bool => array_reduce(
            $keywords,
            fn (bool $found, string $keyword): bool => $found || str_contains($message, $keyword),
            false
        );

        $templates = match ($category) {
            'criminal' => [
                [
                    'law_name' => 'ประมวลกฎหมายอาญา',
                    'section' => '276',
                    'plain_meaning' => 'ความผิดเกี่ยวกับการข่มขืนกระทำชำเรา ต้องดูข้อเท็จจริงเรื่องการยินยอม สภาพบังคับ ข่มขู่ หรือเหตุที่กฎหมายคุ้มครองเป็นพิเศษ',
                    'why_relevant' => 'ควรตรวจทันทีเมื่อผู้ใช้เล่าว่าถูกข่มขืนหรือถูกบังคับให้มีเพศสัมพันธ์',
                    'confidence' => $contains(['ข่มขืน', 'บังคับมีเพศสัมพันธ์', 'ล่วงละเมิดทางเพศ']) ? 'high' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายอาญา',
                    'section' => '278',
                    'plain_meaning' => 'ความผิดเกี่ยวกับการกระทำอนาจาร ต้องดูพฤติการณ์ การยินยอม อายุ และพยานหลักฐาน',
                    'why_relevant' => 'ควรตรวจเพิ่มเติมเมื่อมีการแตะต้อง ล่วงละเมิด คุกคาม หรือกระทำทางเพศโดยไม่ยินยอม',
                    'confidence' => $contains(['อนาจาร', 'ล่วงละเมิด', 'คุกคามทางเพศ', 'จับต้อง']) ? 'high' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายอาญา',
                    'section' => '341',
                    'plain_meaning' => 'ความผิดฐานฉ้อโกงโดยหลอกลวงให้ผู้อื่นส่งมอบทรัพย์สินหรือทำให้เสียประโยชน์',
                    'why_relevant' => 'ใช้พิจารณาเมื่อมีการหลอกให้โอนเงิน ส่งมอบทรัพย์ หรือทำธุรกรรมจากข้อมูลเท็จ',
                    'confidence' => $contains(['โกง', 'หลอก', 'โอนเงิน', 'ไม่ได้รับสินค้า', 'ฉ้อโกง']) ? 'high' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายอาญา',
                    'section' => '343',
                    'plain_meaning' => 'ฉ้อโกงประชาชนหรือหลอกลวงต่อประชาชนในลักษณะกว้าง',
                    'why_relevant' => 'ควรตรวจเพิ่มหากมีประกาศขายออนไลน์ หลอกหลายคน หรือมีพฤติการณ์เผยแพร่ต่อสาธารณะ',
                    'confidence' => $contains(['ออนไลน์', 'โพสต์', 'ประกาศ', 'หลายคน', 'เพจ']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติว่าด้วยการกระทำความผิดเกี่ยวกับคอมพิวเตอร์',
                    'section' => '14(1)',
                    'plain_meaning' => 'นำเข้าข้อมูลคอมพิวเตอร์อันเป็นเท็จในลักษณะที่อาจก่อให้เกิดความเสียหาย',
                    'why_relevant' => 'ควรตรวจเพิ่มเมื่อข้อเท็จจริงเกิดผ่านระบบออนไลน์ เว็บไซต์ แอป หรือโซเชียลมีเดีย',
                    'confidence' => $contains(['ออนไลน์', 'เว็บ', 'แอป', 'เพจ', 'เฟซบุ๊ก', 'ไลน์']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'civil' => [
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '420',
                    'plain_meaning' => 'ความรับผิดทางละเมิดเมื่อจงใจหรือประมาททำให้ผู้อื่นเสียหาย',
                    'why_relevant' => 'ใช้ประเมินการเรียกค่าเสียหายเมื่อมีการกระทำที่ก่อความเสียหายต่อทรัพย์สิน สิทธิ หรือชื่อเสียง',
                    'confidence' => 'medium',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '213',
                    'plain_meaning' => 'เจ้าหนี้อาจขอให้ลูกหนี้ชำระหนี้หรือปฏิบัติตามสัญญา',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีคู่สัญญาไม่ส่งมอบของ ไม่ให้บริการ หรือไม่ทำตามข้อตกลง',
                    'confidence' => $contains(['สัญญา', 'ซื้อ', 'ขาย', 'ส่งมอบ', 'ชำระหนี้']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '222',
                    'plain_meaning' => 'การเรียกค่าสินไหมทดแทนจากการไม่ชำระหนี้ตามปกติที่คาดหมายได้',
                    'why_relevant' => 'ใช้พิจารณาค่าเสียหายจากการผิดสัญญาหรือไม่ปฏิบัติตามหนี้',
                    'confidence' => $contains(['เสียหาย', 'ค่าเสียหาย', 'ผิดสัญญา', 'ไม่ส่งของ']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'contract' => [
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '386',
                    'plain_meaning' => 'สิทธิบอกเลิกสัญญาเมื่อมีเหตุตามสัญญาหรือกฎหมาย',
                    'why_relevant' => 'เกี่ยวข้องเมื่อผู้ใช้ต้องการยกเลิกสัญญาเพราะอีกฝ่ายผิดนัดหรือผิดเงื่อนไข',
                    'confidence' => $contains(['เลิกสัญญา', 'ยกเลิก', 'ผิดสัญญา']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '391',
                    'plain_meaning' => 'ผลของการเลิกสัญญา คู่สัญญาต้องกลับคืนสู่ฐานะเดิมเท่าที่ทำได้',
                    'why_relevant' => 'ใช้พิจารณาการคืนเงิน คืนทรัพย์ หรือชดใช้หลังเลิกสัญญา',
                    'confidence' => $contains(['คืนเงิน', 'คืนของ', 'เลิกสัญญา', 'ยกเลิก']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'consumer' => [
                [
                    'law_name' => 'พระราชบัญญัติคุ้มครองผู้บริโภค',
                    'section' => '22',
                    'plain_meaning' => 'ควบคุมข้อความโฆษณาที่ไม่เป็นธรรม เป็นเท็จ หรืออาจทำให้ผู้บริโภคเข้าใจผิด',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีประกาศขาย โฆษณา หรือคำรับรองสินค้า/บริการที่อาจไม่ตรงความจริง',
                    'confidence' => $contains(['โฆษณา', 'ประกาศ', 'สินค้า', 'บริการ', 'ออนไลน์']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติวิธีพิจารณาคดีผู้บริโภค',
                    'section' => '3',
                    'plain_meaning' => 'นิยามคดีผู้บริโภคและคู่กรณีที่เกี่ยวกับการบริโภคสินค้า/บริการ',
                    'why_relevant' => 'ใช้ดูว่าเรื่องนี้อาจเข้าสู่กระบวนพิจารณาคดีผู้บริโภคหรือไม่',
                    'confidence' => $contains(['ซื้อ', 'สินค้า', 'บริการ', 'ผู้บริโภค', 'ออนไลน์']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'labor' => [
                [
                    'law_name' => 'พระราชบัญญัติคุ้มครองแรงงาน',
                    'section' => '17',
                    'plain_meaning' => 'การบอกกล่าวล่วงหน้าเมื่อเลิกสัญญาจ้างในบางกรณี',
                    'why_relevant' => 'ใช้ตรวจว่าการเลิกจ้างหรือให้ออกจากงานมีการบอกกล่าวถูกต้องหรือไม่',
                    'confidence' => $contains(['เลิกจ้าง', 'ให้ออก', 'ไล่ออก']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติคุ้มครองแรงงาน',
                    'section' => '118',
                    'plain_meaning' => 'สิทธิค่าชดเชยเมื่อถูกเลิกจ้างตามอายุงานและเงื่อนไขที่กฎหมายกำหนด',
                    'why_relevant' => 'ใช้ประเมินสิทธิค่าชดเชยเบื้องต้นเมื่อถูกเลิกจ้าง',
                    'confidence' => $contains(['เลิกจ้าง', 'ค่าชดเชย', 'ให้ออก']) ? 'high' : 'medium',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติคุ้มครองแรงงาน',
                    'section' => '119',
                    'plain_meaning' => 'กรณีที่นายจ้างอาจไม่ต้องจ่ายค่าชดเชยหากเข้าเงื่อนไขร้ายแรงตามกฎหมาย',
                    'why_relevant' => 'ควรตรวจหากนายจ้างอ้างเหตุทุจริต ฝ่าฝืนข้อบังคับ หรือทำผิดร้ายแรง',
                    'confidence' => $contains(['ทุจริต', 'ผิดร้ายแรง', 'ฝ่าฝืน', 'ไม่จ่ายค่าชดเชย']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'family' => [
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1516',
                    'plain_meaning' => 'เหตุฟ้องหย่าตามกฎหมาย',
                    'why_relevant' => 'เกี่ยวข้องเมื่อเป็นข้อพิพาทหย่า นอกใจ ทอดทิ้ง ทำร้าย หรือเหตุอื่นตามกฎหมาย',
                    'confidence' => $contains(['หย่า', 'นอกใจ', 'ทอดทิ้ง', 'ทำร้าย']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1522',
                    'plain_meaning' => 'ผลของการหย่าและสิทธิเรียกร้องบางกรณี',
                    'why_relevant' => 'ใช้ดูผลหลังหย่าและประเด็นเรียกร้องที่เกี่ยวข้อง',
                    'confidence' => $contains(['หย่า', 'สินสมรส', 'ค่าเลี้ยงดู']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1566',
                    'plain_meaning' => 'อำนาจปกครองบุตรของบิดามารดา',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีประเด็นสิทธิเลี้ยงดู อำนาจปกครอง หรือการตัดสินใจเกี่ยวกับบุตร',
                    'confidence' => $contains(['บุตร', 'ลูก', 'เลี้ยงดู', 'อำนาจปกครอง']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'land' => [
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1336',
                    'plain_meaning' => 'เจ้าของทรัพย์มีสิทธิใช้ จำหน่าย ได้ดอกผล และติดตามเอาคืนจากผู้ไม่มีสิทธิ',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีปัญหาบุกรุก ครอบครอง หรือโต้แย้งสิทธิในที่ดิน/ทรัพย์',
                    'confidence' => $contains(['ที่ดิน', 'บุกรุก', 'โฉนด', 'ครอบครอง']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1382',
                    'plain_meaning' => 'การได้กรรมสิทธิ์โดยครอบครองปรปักษ์เมื่อครบเงื่อนไขตามกฎหมาย',
                    'why_relevant' => 'ควรตรวจเมื่อมีการครอบครองที่ดินของผู้อื่นเป็นเวลานานโดยสงบและเปิดเผย',
                    'confidence' => $contains(['ครอบครองปรปักษ์', 'ครอบครอง', 'หลายปี']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'inheritance' => [
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1599',
                    'plain_meaning' => 'มรดกตกทอดแก่ทายาทเมื่อเจ้ามรดกตาย',
                    'why_relevant' => 'เป็นหลักตั้งต้นของคดีมรดกเมื่อมีผู้เสียชีวิตและต้องแบ่งทรัพย์',
                    'confidence' => 'medium',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1629',
                    'plain_meaning' => 'ลำดับทายาทโดยธรรม',
                    'why_relevant' => 'ใช้ประเมินเบื้องต้นว่าใครอาจมีสิทธิรับมรดก',
                    'confidence' => $contains(['มรดก', 'ทายาท', 'แบ่งทรัพย์']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1656',
                    'plain_meaning' => 'แบบพินัยกรรมชนิดทำเป็นหนังสือ ลงวันเดือนปี และมีพยาน',
                    'why_relevant' => 'ควรตรวจเมื่อมีประเด็นความถูกต้องของพินัยกรรม',
                    'confidence' => $contains(['พินัยกรรม']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'tax' => [
                [
                    'law_name' => 'ประมวลรัษฎากร',
                    'section' => '40',
                    'plain_meaning' => 'ประเภทเงินได้พึงประเมิน',
                    'why_relevant' => 'ใช้ดูว่าเงินหรือรายได้ที่เกี่ยวข้องอาจถูกจัดเป็นเงินได้ประเภทใด',
                    'confidence' => $contains(['รายได้', 'เงินได้', 'ภาษี']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลรัษฎากร',
                    'section' => '37',
                    'plain_meaning' => 'โทษบางกรณีเกี่ยวกับการหลีกเลี่ยงหรือเจตนาไม่เสียภาษี',
                    'why_relevant' => 'ควรตรวจเมื่อมีหนังสือจากสรรพากร ภาษีย้อนหลัง หรือข้อกล่าวหาเรื่องหลีกเลี่ยงภาษี',
                    'confidence' => $contains(['สรรพากร', 'ภาษีย้อนหลัง', 'เลี่ยงภาษี']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'business' => [
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1168',
                    'plain_meaning' => 'หน้าที่ของกรรมการบริษัทต้องใช้ความระมัดระวังในการจัดการงานบริษัท',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีข้อพิพาทกรรมการ หุ้นส่วน หรือการบริหารเงินบริษัท',
                    'confidence' => $contains(['กรรมการ', 'บริษัท', 'หุ้นส่วน', 'เงินบริษัท']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'ประมวลกฎหมายแพ่งและพาณิชย์',
                    'section' => '1195',
                    'plain_meaning' => 'ผู้ถือหุ้นอาจฟ้องกรรมการในบางกรณีเมื่อบริษัทไม่ดำเนินการ',
                    'why_relevant' => 'ควรตรวจเมื่อผู้ถือหุ้นต้องการดำเนินคดีแทนบริษัทหรือตรวจความรับผิดกรรมการ',
                    'confidence' => $contains(['ผู้ถือหุ้น', 'กรรมการ', 'ฟ้องกรรมการ']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'intellectual_property' => [
                [
                    'law_name' => 'พระราชบัญญัติลิขสิทธิ์',
                    'section' => '15',
                    'plain_meaning' => 'สิทธิแต่ผู้เดียวของเจ้าของลิขสิทธิ์',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีการใช้ ทำซ้ำ เผยแพร่ หรือดัดแปลงงานโดยไม่ได้รับอนุญาต',
                    'confidence' => $contains(['ลิขสิทธิ์', 'ก็อป', 'รูป', 'เพลง', 'ซอฟต์แวร์']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติเครื่องหมายการค้า',
                    'section' => '44',
                    'plain_meaning' => 'สิทธิแต่ผู้เดียวของเจ้าของเครื่องหมายการค้าที่จดทะเบียน',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีการใช้แบรนด์ โลโก้ หรือเครื่องหมายที่อาจเหมือน/คล้ายกัน',
                    'confidence' => $contains(['เครื่องหมายการค้า', 'แบรนด์', 'โลโก้']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'immigration' => [
                [
                    'law_name' => 'พระราชบัญญัติคนเข้าเมือง',
                    'section' => '37',
                    'plain_meaning' => 'หน้าที่บางประการของคนต่างด้าวระหว่างอยู่ในราชอาณาจักร',
                    'why_relevant' => 'เกี่ยวข้องเมื่อเป็นปัญหาวีซ่า การแจ้งที่พัก หรือเงื่อนไขการพำนัก',
                    'confidence' => $contains(['วีซ่า', 'ต่างชาติ', 'พำนัก', 'แจ้งที่พัก']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติคนเข้าเมือง',
                    'section' => '81',
                    'plain_meaning' => 'บทกำหนดโทษกรณีอยู่ในราชอาณาจักรโดยไม่ได้รับอนุญาตหรือเกินกำหนด',
                    'why_relevant' => 'ควรตรวจเมื่อมีประเด็น overstay หรือหมดอายุวีซ่า',
                    'confidence' => $contains(['overstay', 'อยู่เกิน', 'วีซ่าหมด']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            'bankruptcy' => [
                [
                    'law_name' => 'พระราชบัญญัติล้มละลาย',
                    'section' => '9',
                    'plain_meaning' => 'เงื่อนไขเบื้องต้นในการที่เจ้าหนี้อาจฟ้องลูกหนี้ให้ล้มละลาย',
                    'why_relevant' => 'เกี่ยวข้องเมื่อมีหนี้จำนวนมาก ถูกทวงถาม หรือเสี่ยงถูกฟ้องล้มละลาย',
                    'confidence' => $contains(['ล้มละลาย', 'หนี้', 'เจ้าหนี้', 'ถูกฟ้อง']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
                [
                    'law_name' => 'พระราชบัญญัติล้มละลาย',
                    'section' => '90/3',
                    'plain_meaning' => 'หลักการยื่นคำร้องขอฟื้นฟูกิจการของลูกหนี้',
                    'why_relevant' => 'เกี่ยวข้องเมื่อเป็นธุรกิจที่มีหนี้และต้องการปรับโครงสร้างกิจการ',
                    'confidence' => $contains(['ฟื้นฟูกิจการ', 'บริษัท', 'หนี้ธุรกิจ']) ? 'medium' : 'low',
                    'needs_lawyer_review' => true,
                ],
            ],
            default => [],
        };

        return $templates;
    }

    private function detectConversationIntent(string $message, array $caseContext, array $answeredFields): string
    {
        $lower = mb_strtolower($message);
        $hasCase = !empty($caseContext['case']);
        $hasAnsweredFields = count(array_filter($answeredFields, fn ($value) => $value !== null && $value !== '')) > 0;

        if ($hasCase && $hasAnsweredFields) {
            return 'answering_follow_up';
        }

        foreach (['ทำยังไง', 'ควรทำไง', 'ต่อยังไง', 'ขั้นตอน', 'ทำอย่างไร', 'เอาไงต่อ', 'ฟ้องได้ไหม', 'แจ้งความได้ไหม'] as $phrase) {
            if ($hasCase && str_contains($lower, $phrase)) {
                return 'procedural_follow_up';
            }
        }

        foreach (['หาทนาย', 'ต้องการทนาย', 'จ้างทนาย', 'match', 'แมตช์', 'จับคู่'] as $phrase) {
            if ($hasCase && str_contains($lower, $phrase)) {
                return 'lawyer_match_info';
            }
        }

        if ($hasCase && mb_strlen($message) <= 80 && !$this->hasLegalIssueKeyword($lower)) {
            return 'answering_follow_up';
        }

        return 'new_legal_question';
    }

    private function normalizeAnsweredFields(array $fields): array
    {
        return [
            'province' => $fields['province'] ?? null,
            'consultation_type' => $fields['consultation_type'] ?? null,
            'budget_min' => isset($fields['budget_min']) && $fields['budget_min'] !== '' ? (float) $fields['budget_min'] : null,
            'budget_max' => isset($fields['budget_max']) && $fields['budget_max'] !== '' ? (float) $fields['budget_max'] : null,
            'incident_date' => $fields['incident_date'] ?? null,
            'damage_amount' => isset($fields['damage_amount']) && $fields['damage_amount'] !== '' ? (float) $fields['damage_amount'] : null,
            'has_court_or_police_document' => $fields['has_court_or_police_document'] ?? null,
        ];
    }

    private function detectAnsweredFields(string $message): array
    {
        $fields = [
            'province' => $this->detectProvince($message),
            'consultation_type' => $this->detectConsultationType($message),
            'budget_min' => null,
            'budget_max' => null,
            'incident_date' => null,
            'damage_amount' => null,
            'has_court_or_police_document' => null,
        ];

        $amounts = $this->extractMoneyAmounts($message);
        if ($amounts) {
            if (count($amounts) >= 2 && preg_match('/-|ถึง|ระหว่าง/u', $message)) {
                $fields['budget_min'] = min($amounts[0], $amounts[1]);
                $fields['budget_max'] = max($amounts[0], $amounts[1]);
            } else {
                $amount = $amounts[0];
                if (preg_match('/งบ|ค่าปรึกษา|ไม่เกิน|ประมาณ|จ่ายได้/u', $message)) {
                    $fields['budget_max'] = $amount;
                } elseif (preg_match('/เสียหาย|มูลค่า|โอน|โดนโกง|หนี้/u', $message)) {
                    $fields['damage_amount'] = $amount;
                }
            }
        }

        if (preg_match('/(วันนี้|เมื่อวาน|พรุ่งนี้|วันที่\s*\d{1,2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4})/u', $message, $match)) {
            $fields['incident_date'] = $match[0];
        }

        if (preg_match('/(ยังไม่มี|ไม่มี).*(หมายศาล|หมายเรียก|หนังสือทวงถาม|บันทึกประจำวัน|ใบแจ้งความ)/u', $message)) {
            $fields['has_court_or_police_document'] = false;
        } elseif (preg_match('/(มี|ได้รับ|ได้|มีแล้ว).*(หมายศาล|หมายเรียก|หนังสือทวงถาม|บันทึกประจำวัน|ใบแจ้งความ)/u', $message)) {
            $fields['has_court_or_police_document'] = true;
        }

        return $this->normalizeAnsweredFields($fields);
    }

    private function detectProvince(string $message): ?string
    {
        foreach (['กทม' => 'กรุงเทพมหานคร', 'กรุงเทพ' => 'กรุงเทพมหานคร', 'กรุงเทพฯ' => 'กรุงเทพมหานคร'] as $alias => $province) {
            if (str_contains($message, $alias)) {
                return $province;
            }
        }

        $provinces = [
            'กระบี่', 'กาญจนบุรี', 'กาฬสินธุ์', 'กำแพงเพชร', 'ขอนแก่น', 'จันทบุรี', 'ฉะเชิงเทรา',
            'ชลบุรี', 'ชัยนาท', 'ชัยภูมิ', 'ชุมพร', 'เชียงราย', 'เชียงใหม่', 'ตรัง', 'ตราด', 'ตาก',
            'นครนายก', 'นครปฐม', 'นครพนม', 'นครราชสีมา', 'นครศรีธรรมราช', 'นครสวรรค์', 'นนทบุรี',
            'นราธิวาส', 'น่าน', 'บึงกาฬ', 'บุรีรัมย์', 'ปทุมธานี', 'ประจวบคีรีขันธ์', 'ปราจีนบุรี',
            'ปัตตานี', 'พระนครศรีอยุธยา', 'พะเยา', 'พังงา', 'พัทลุง', 'พิจิตร', 'พิษณุโลก',
            'เพชรบุรี', 'เพชรบูรณ์', 'แพร่', 'ภูเก็ต', 'มหาสารคาม', 'มุกดาหาร', 'แม่ฮ่องสอน',
            'ยโสธร', 'ยะลา', 'ร้อยเอ็ด', 'ระนอง', 'ระยอง', 'ราชบุรี', 'ลพบุรี', 'ลำปาง', 'ลำพูน',
            'เลย', 'ศรีสะเกษ', 'สกลนคร', 'สงขลา', 'สตูล', 'สมุทรปราการ', 'สมุทรสงคราม',
            'สมุทรสาคร', 'สระแก้ว', 'สระบุรี', 'สิงห์บุรี', 'สุโขทัย', 'สุพรรณบุรี',
            'สุราษฎร์ธานี', 'สุรินทร์', 'หนองคาย', 'หนองบัวลำภู', 'อ่างทอง', 'อำนาจเจริญ',
            'อุดรธานี', 'อุตรดิตถ์', 'อุทัยธานี', 'อุบลราชธานี',
        ];
        foreach ($provinces as $province) {
            if (str_contains($message, $province)) {
                return $province;
            }
        }

        return null;
    }

    private function detectConsultationType(string $message): ?string
    {
        $lower = mb_strtolower($message);
        if (preg_match('/วิดีโอ|วีดีโอ|video|zoom|meet/u', $lower)) {
            return 'video';
        }
        if (preg_match('/โทร|phone|call/u', $lower)) {
            return 'phone';
        }
        if (preg_match('/พบตัว|เจอตัว|ไปสำนักงาน|onsite|ออฟไลน์/u', $lower)) {
            return 'onsite';
        }
        if (preg_match('/ออนไลน์|แชต|แชท|chat|line|ไลน์/u', $lower)) {
            return 'chat';
        }

        return null;
    }

    private function extractMoneyAmounts(string $message): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/u', $message, $matches);
        $amounts = [];
        foreach ($matches[0] ?? [] as $raw) {
            $amount = (float) str_replace(',', '', $raw);
            if ($amount > 0) {
                $amounts[] = $amount;
            }
        }

        return $amounts;
    }

    private function buildSmartQuestions(array $caseContext, array $answeredFields, string $message = '', ?string $primary = null): array
    {
        $missing = $this->missingContextFields($caseContext, $answeredFields);
        $questions = [];
        if ($this->messageContainsAny($message, ['ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'คุกคามทางเพศ'])) {
            $questions[] = 'ตอนนี้คุณอยู่ในที่ปลอดภัยแล้วหรือยัง และมีคนที่ไว้ใจได้อยู่ใกล้ ๆ ไหม?';
            $questions[] = 'เหตุเกิดเมื่อไร ที่จังหวัดใด และมีการพบแพทย์หรือแจ้งความแล้วหรือยัง?';
        } elseif ($this->messageContainsAny($message, ['โอนเงิน', 'ไม่ได้รับสินค้า', 'ไม่ส่งของ', 'บล็อก'])) {
            $questions[] = 'โอนเงินวันไหน ยอดเท่าไร และมีชื่อบัญชี/เลขบัญชีของผู้ขายไหม?';
            $questions[] = 'มีแชต ประกาศขาย ลิงก์เพจ หรือสลิปโอนเงินครบหรือไม่?';
        } elseif ($primary === 'labor') {
            $questions[] = 'เริ่มงานเมื่อไร ถูกเลิกจ้างวันไหน และได้รับหนังสือเลิกจ้างหรือไม่?';
        } elseif ($primary === 'contract') {
            $questions[] = 'ในสัญญาระบุเงื่อนไขผิดนัดหรือบอกเลิกไว้อย่างไรบ้าง?';
        }
        if (in_array('province', $missing, true)) {
            $questions[] = 'เหตุการณ์เกิดขึ้นที่จังหวัดใด หรือคุณต้องการทนายในจังหวัดใด?';
        }
        if (in_array('has_court_or_police_document', $missing, true)) {
            $questions[] = 'มีหมายศาล หมายเรียก หนังสือทวงถาม หรือเอกสารจากตำรวจ/หน่วยงานรัฐแล้วหรือยัง?';
        }
        if (in_array('damage_amount', $missing, true)) {
            $questions[] = 'มูลค่าความเสียหายหรือวงเงินที่เกี่ยวข้องประมาณเท่าไร?';
        }
        if (in_array('consultation_type', $missing, true)) {
            $questions[] = 'ถ้าต้องการปรึกษาทนาย คุณสะดวกแบบออนไลน์ โทร วิดีโอคอล หรือพบตัวจริง?';
        }
        if (in_array('budget', $missing, true)) {
            $questions[] = 'ถ้าต้องการให้ช่วยหาทนาย คุณมีงบประมาณค่าปรึกษาเบื้องต้นประมาณเท่าไร?';
        }

        return array_slice(array_values(array_unique($questions)), 0, 4);
    }

    private function missingContextFields(array $caseContext, array $answeredFields): array
    {
        $case = $caseContext['case'] ?? [];
        $missing = [];
        if (empty($case['province']) && empty($answeredFields['province'])) {
            $missing[] = 'province';
        }
        if ((empty($case['consultation_type']) || $case['consultation_type'] === 'any') && empty($answeredFields['consultation_type'])) {
            $missing[] = 'consultation_type';
        }
        if (($case['budget_min'] ?? null) === null && ($case['budget_max'] ?? null) === null && empty($answeredFields['budget_max'])) {
            $missing[] = 'budget';
        }
        if (empty($answeredFields['damage_amount'])) {
            $missing[] = 'damage_amount';
        }
        if ($answeredFields['has_court_or_police_document'] === null) {
            $missing[] = 'has_court_or_police_document';
        }

        return array_values(array_unique($missing));
    }

    private function hasLegalIssueKeyword(string $message): bool
    {
        foreach (['โกง', 'ฉ้อโกง', 'ฟ้อง', 'หมายศาล', 'เลิกจ้าง', 'หย่า', 'ที่ดิน', 'มรดก', 'ภาษี', 'สัญญา', 'บริษัท', 'ทำร้าย', 'ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'ข่มขู่', 'แจ้งความ'] as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function fallbackPrimaryCategory(string $message): string
    {
        $lower = mb_strtolower($message);
        if (
            (str_contains($lower, 'โอนเงิน') || str_contains($lower, 'สลิป'))
            && (str_contains($lower, 'ไม่ได้รับสินค้า') || str_contains($lower, 'ไม่ส่งของ') || str_contains($lower, 'บล็อก') || str_contains($lower, 'ไม่ตอบแชต'))
        ) {
            return 'criminal';
        }

        $rules = [
            'criminal' => ['โกง', 'ฉ้อโกง', 'แจ้งความ', 'ถูกจับ', 'หมายเรียก', 'ทำร้าย', 'ยักยอก', 'อาญา', 'หลอกโอน', 'ไม่ส่งของ', 'ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'คุกคามทางเพศ', 'ข่มขู่', 'ทำร้ายร่างกาย'],
            'family' => ['หย่า', 'บุตร', 'ค่าเลี้ยงดู', 'สินสมรส', 'ครอบครัว'],
            'labor' => ['เลิกจ้าง', 'เงินเดือน', 'นายจ้าง', 'ลูกจ้าง', 'ค่าชดเชย', 'แรงงาน'],
            'business' => ['บริษัท', 'หุ้นส่วน', 'กรรมการ', 'ธุรกิจ', 'ลูกค้า', 'หุ้น'],
            'land' => ['ที่ดิน', 'โฉนด', 'บุกรุก', 'เช่าที่', 'บ้าน'],
            'inheritance' => ['มรดก', 'พินัยกรรม', 'ทายาท'],
            'tax' => ['ภาษี', 'สรรพากร'],
            'consumer' => ['สินค้า', 'บริการ', 'ซื้อของ', 'ออนไลน์', 'ผู้บริโภค'],
            'intellectual_property' => ['ลิขสิทธิ์', 'เครื่องหมายการค้า', 'สิทธิบัตร', 'แบรนด์'],
            'immigration' => ['วีซ่า', 'ต่างชาติ', 'ใบอนุญาตทำงาน'],
            'bankruptcy' => ['ล้มละลาย', 'ฟื้นฟูกิจการ'],
            'contract' => ['สัญญา', 'ผิดสัญญา', 'ข้อตกลง'],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    return $category;
                }
            }
        }

        return 'civil';
    }

    private function detectRelatedCategories(string $message, string $primary): array
    {
        $related = [];
        $rules = [
            'criminal' => ['โกง', 'ฉ้อโกง', 'แจ้งความ', 'หลอกโอน', 'ไม่ส่งของ', 'ไม่ได้รับสินค้า', 'ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'คุกคามทางเพศ', 'ข่มขู่', 'ทำร้าย'],
            'civil' => ['เงินคืน', 'เรียกเงิน', 'ค่าเสียหาย', 'เสียหาย', 'หนี้', 'ผิดนัด', 'โอนเงิน'],
            'consumer' => ['สินค้า', 'บริการ', 'ซื้อของ', 'ออนไลน์', 'ผู้บริโภค', 'ประกาศขาย', 'เพจ'],
            'contract' => ['สัญญา', 'ข้อตกลง', 'ผิดสัญญา', 'เลิกสัญญา', 'ยกเลิก'],
            'tax' => ['ภาษี', 'สรรพากร', 'ภาษีย้อนหลัง'],
            'business' => ['บริษัท', 'หุ้นส่วน', 'กรรมการ', 'ธุรกิจ', 'ผู้ถือหุ้น'],
            'labor' => ['เลิกจ้าง', 'เงินเดือน', 'นายจ้าง', 'ลูกจ้าง', 'ค่าชดเชย'],
            'family' => ['หย่า', 'บุตร', 'ค่าเลี้ยงดู', 'สินสมรส'],
            'land' => ['ที่ดิน', 'โฉนด', 'บุกรุก', 'ครอบครองปรปักษ์'],
            'inheritance' => ['มรดก', 'พินัยกรรม', 'ทายาท'],
            'intellectual_property' => ['ลิขสิทธิ์', 'เครื่องหมายการค้า', 'สิทธิบัตร', 'แบรนด์'],
            'immigration' => ['วีซ่า', 'ต่างชาติ', 'ใบอนุญาตทำงาน', 'overstay'],
            'bankruptcy' => ['ล้มละลาย', 'ฟื้นฟูกิจการ'],
        ];

        foreach ($rules as $category => $keywords) {
            if ($category === $primary) {
                continue;
            }
            foreach ($keywords as $keyword) {
                if (str_contains($message, $keyword)) {
                    $related[] = $category;
                    break;
                }
            }
        }

        if ($primary === 'criminal' && str_contains($message, 'เงิน')) {
            $related[] = 'civil';
        }
        if ($primary === 'criminal' && $this->messageContainsAny($message, ['ข่มขืน', 'ล่วงละเมิด', 'อนาจาร', 'ทำร้าย'])) {
            $related[] = 'civil';
        }
        if (str_contains($message, 'สัญญา') && $primary !== 'contract') {
            $related[] = 'contract';
        }
        if (str_contains($message, 'ออนไลน์') && $primary !== 'consumer') {
            $related[] = 'consumer';
        }

        return array_values(array_unique(array_slice($related, 0, 4)));
    }

    private function detectUrgency(string $message): string
    {
        $critical = ['หมายศาล', 'ถูกจับ', 'อายัด', 'พรุ่งนี้', 'วันนี้', 'ครบกำหนด', 'บังคับคดี', 'ข่มขืน', 'ล่วงละเมิด', 'ทำร้ายร่างกาย', 'ถูกขู่ฆ่า'];
        foreach ($critical as $keyword) {
            if (str_contains($message, $keyword)) {
                return 'critical';
            }
        }

        $high = ['ถูกฟ้อง', 'หมายเรียก', 'หนังสือทวงถาม', 'ตำรวจ', 'สรรพากร', 'ฟ้อง', 'ข่มขู่', 'อนาจาร', 'คุกคามทางเพศ'];
        foreach ($high as $keyword) {
            if (str_contains($message, $keyword)) {
                return 'high';
            }
        }

        return 'medium';
    }
}
