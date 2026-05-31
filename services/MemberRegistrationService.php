<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

final class MemberRegistrationService
{
    public function register(array $input): array
    {
        if (!app_config('registration_enabled', true)) {
            throw new InvalidArgumentException('ระบบปิดรับสมัครสมาชิกชั่วคราว');
        }

        $data = $this->validate($input);
        $pdo = db();

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO users (name, email, phone, password, role, status)
                 VALUES (?, ?, ?, ?, "user", "active")'
            );
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                password_hash($data['password'], PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new InvalidArgumentException('อีเมลนี้ถูกใช้สมัครสมาชิกแล้ว');
            }
            throw $exception;
        }

        $userId = (int) $pdo->lastInsertId();
        $user = $this->findUser($userId);
        $this->notifyWelcome($user);

        return $user;
    }

    public function emailAvailable(string $email): bool
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        return (int) $stmt->fetchColumn() === 0;
    }

    private function validate(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $phone = trim((string) ($input['phone'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirm'] ?? '');
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
        if (strlen($password) < $minLength) {
            throw new InvalidArgumentException('รหัสผ่านต้องมีอย่างน้อย ' . $minLength . ' ตัวอักษร');
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            throw new InvalidArgumentException('รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลข');
        }
        if ($password !== $passwordConfirm) {
            throw new InvalidArgumentException('ยืนยันรหัสผ่านไม่ตรงกัน');
        }
        if (app_config('require_terms_acceptance', true) && !$acceptedTerms) {
            throw new InvalidArgumentException('กรุณายอมรับเงื่อนไขการใช้งานและนโยบายความเป็นส่วนตัว');
        }

        return [
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
        ];
    }

    private function findUser(int $userId): array
    {
        $stmt = db()->prepare('SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('ไม่พบสมาชิกที่สร้างใหม่');
        }
        return $user;
    }

    private function notifyWelcome(array $user): void
    {
        $notify = new NotificationService();
        $notify->create((int) $user['id'], 'สมัครสมาชิกสำเร็จ', 'ยินดีต้อนรับสู่ทนายคู่ดี คุณสามารถเริ่มถาม AI หรือค้นหาทนายได้ทันที', 'account');

        $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
        foreach ($admins as $admin) {
            $notify->create((int) $admin['id'], 'มีสมาชิกใหม่', $user['name'] . ' สมัครสมาชิกด้วยอีเมล ' . $user['email'], 'account');
        }
    }
}
