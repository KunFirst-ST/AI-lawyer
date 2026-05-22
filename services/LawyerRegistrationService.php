<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

final class LawyerRegistrationService
{
    public function register(array $input, array $files = []): array
    {
        if (!app_config('lawyer_registration_enabled', true)) {
            throw new InvalidArgumentException('ระบบปิดรับสมัครทนายชั่วคราว');
        }

        $data = $this->validate($input);
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $userStmt = $pdo->prepare(
                'INSERT INTO users (name, email, phone, password, role, status)
                 VALUES (?, ?, ?, ?, "lawyer", "active")'
            );
            $userStmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $lawyerStmt = $pdo->prepare(
                'INSERT INTO lawyers (user_id, license_number, province, bio, experience_years, consultation_fee, complex_case_experience, status, verified)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending", 0)'
            );
            $lawyerStmt->execute([
                $userId,
                $data['license_number'],
                $data['province'],
                $data['bio'],
                $data['experience_years'],
                $data['consultation_fee'],
                $data['complex_case_experience'],
            ]);
            $lawyerId = (int) $pdo->lastInsertId();

            $catStmt = $pdo->prepare('INSERT INTO lawyer_categories (lawyer_id, category_id) VALUES (?, ?)');
            foreach ($data['categories'] as $categoryId) {
                $catStmt->execute([$lawyerId, $categoryId]);
            }

            foreach (['license_document' => 'lawyer_license', 'id_card' => 'id_card'] as $inputName => $type) {
                if (!empty($files[$inputName]) && ($files[$inputName]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $path = uploadFile($files[$inputName], 'lawyer_documents');
                    if ($path) {
                        $doc = $pdo->prepare('INSERT INTO documents (user_id, lawyer_id, document_type, file_path, original_name) VALUES (?, ?, ?, ?, ?)');
                        $doc->execute([$userId, $lawyerId, $type, $path, $files[$inputName]['name'] ?? null]);
                    }
                }
            }

            $pdo->commit();
            $user = $this->findUser($userId);
            $this->notifyAfterRegister($user, $lawyerId);

            return ['user' => $user, 'lawyer_id' => $lawyerId];
        } catch (PDOException $exception) {
            $pdo->rollBack();
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('อีเมลนี้ถูกใช้สมัครแล้ว หรือเลขใบอนุญาตซ้ำในระบบ');
            }
            throw $exception;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
    }

    private function validate(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? $password);
        $licenseNumber = trim((string) ($input['license_number'] ?? ''));
        $province = trim((string) ($input['province'] ?? ''));
        $bio = trim((string) ($input['bio'] ?? ''));
        $experienceYears = max(0, (int) ($input['experience_years'] ?? 0));
        $consultationFee = max(0, (float) ($input['consultation_fee'] ?? 0));
        $complexCaseExperience = isset($input['complex_case_experience']) ? 1 : 0;
        $rawCategories = $input['categories'] ?? [];
        if (!is_array($rawCategories)) {
            $rawCategories = [$rawCategories];
        }
        $categories = array_values(array_unique(array_filter(array_map('intval', $rawCategories))));
        $acceptedTerms = filter_var($input['accepted_terms'] ?? false, FILTER_VALIDATE_BOOL);
        $minLength = (int) app_config('password_min_length', 8);

        if (mb_strlen($name) < 2) {
            throw new InvalidArgumentException('กรุณากรอกชื่ออย่างน้อย 2 ตัวอักษร');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('รูปแบบอีเมลไม่ถูกต้อง');
        }
        if ($phone !== '' && !preg_match('/^[0-9+\-\s().]{8,30}$/', $phone)) {
            throw new InvalidArgumentException('รูปแบบเบอร์โทรไม่ถูกต้อง');
        }
        if (strlen($password) < $minLength || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย ' . $minLength . ' ตัวอักษร และมีทั้งตัวอักษรกับตัวเลข');
        }
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('ยืนยันรหัสผ่านไม่ตรงกัน');
        }
        if ($licenseNumber === '' || $province === '') {
            throw new InvalidArgumentException('กรุณากรอกเลขใบอนุญาตและจังหวัด');
        }
        if (!$categories) {
            throw new InvalidArgumentException('กรุณาเลือกหมวดกฎหมายอย่างน้อย 1 หมวด');
        }
        if (app_config('require_terms_acceptance', true) && !$acceptedTerms) {
            throw new InvalidArgumentException('กรุณายอมรับเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว');
        }

        return compact(
            'name',
            'email',
            'phone',
            'password',
            'licenseNumber',
            'province',
            'bio',
            'experienceYears',
            'consultationFee',
            'complexCaseExperience',
            'categories'
        ) + [
            'license_number' => $licenseNumber,
            'experience_years' => $experienceYears,
            'consultation_fee' => $consultationFee,
            'complex_case_experience' => $complexCaseExperience,
        ];
    }

    private function findUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('ไม่พบผู้สมัครทนายที่สร้างใหม่');
        }
        return $user;
    }

    private function notifyAfterRegister(array $user, int $lawyerId): void
    {
        $notify = new NotificationService();
        $notify->create((int) $user['id'], 'ส่งใบสมัครทนายแล้ว', 'ระบบได้รับใบสมัครทนายของคุณแล้ว กรุณารอแอดมินตรวจสอบ', 'account');

        $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
        foreach ($admins as $admin) {
            $notify->create((int) $admin['id'], 'มีทนายสมัครใหม่', $user['name'] . ' ส่งใบสมัครทนาย #' . $lawyerId . ' รอตรวจสอบ', 'lawyer');
        }
    }
}
