<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/NotificationService.php';

final class SocialAuthService
{
    private const PROVIDERS = [
        'google' => [
            'name' => 'Google',
            'icon' => 'bi-google',
            'class' => 'is-google',
            'scope' => 'openid email profile',
            'auth_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_url' => 'https://oauth2.googleapis.com/token',
            'profile_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        ],
    ];

    public static function providerSummaries(): array
    {
        $summaries = [];
        foreach (self::PROVIDERS as $provider => $meta) {
            $summaries[$provider] = [
                'key' => $provider,
                'name' => $meta['name'],
                'icon' => $meta['icon'],
                'class' => $meta['class'],
                'configured' => self::isConfigured($provider),
                'start_url' => url('/public/oauth-start.php?provider=' . rawurlencode($provider)),
                'callback_url' => url('/public/oauth-callback.php'),
            ];
        }

        return $summaries;
    }

    public static function connectedProvidersForUser(int $userId): array
    {
        self::ensureSocialAccountsTable();
        $stmt = db()->prepare('SELECT provider, provider_email, created_at FROM social_accounts WHERE user_id = ? ORDER BY provider');
        $stmt->execute([$userId]);

        $connections = [];
        foreach ($stmt->fetchAll() as $connection) {
            $connections[(string) $connection['provider']] = $connection;
        }

        return $connections;
    }

    public static function isConfigured(string $provider): bool
    {
        $config = self::providerConfig($provider);
        return (bool) ($config['enabled'] ?? false)
            && trim((string) ($config['client_id'] ?? '')) !== ''
            && trim((string) ($config['client_secret'] ?? '')) !== '';
    }

    public function authorizationUrl(string $provider): string
    {
        $provider = $this->normalizeProvider($provider);
        $this->ensureConfigured($provider);

        $state = bin2hex(random_bytes(32));
        $redirectUri = $this->redirectUri($provider);
        $_SESSION['oauth_states'][$state] = [
            'provider' => $provider,
            'redirect_uri' => $redirectUri,
            'expires_at' => time() + 600,
        ];

        $config = self::providerConfig($provider);
        $meta = self::PROVIDERS[$provider];
        $query = [
            'client_id' => (string) $config['client_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => $meta['scope'],
            'state' => $state,
        ];

        if ($provider === 'google') {
            $query['prompt'] = 'select_account';
        }

        return $this->withQuery($this->providerEndpoint($provider, 'auth_url'), $query);
    }

    public function providerFromState(string $state): ?string
    {
        if ($state === '') {
            return null;
        }

        $match = ($_SESSION['oauth_states'] ?? [])[$state] ?? null;
        $provider = (string) ($match['provider'] ?? '');
        if (!$match || (int) ($match['expires_at'] ?? 0) < time() || !isset(self::PROVIDERS[$provider])) {
            return null;
        }

        return $provider;
    }

    public function handleCallback(string $provider, array $params): array
    {
        $provider = $this->normalizeProvider($provider);
        $this->ensureConfigured($provider);

        if (!empty($params['error'])) {
            throw new InvalidArgumentException('ยกเลิกการเข้าสู่ระบบด้วย ' . self::PROVIDERS[$provider]['name']);
        }

        $state = (string) ($params['state'] ?? '');
        $code = (string) ($params['code'] ?? '');
        if ($state === '' || $code === '') {
            throw new InvalidArgumentException('ข้อมูลยืนยันจากผู้ให้บริการไม่ครบ กรุณาลองใหม่');
        }

        $this->consumeState($provider, $state);
        $token = $this->exchangeCode($provider, $code);
        $profile = $this->fetchProfile($provider, (string) ($token['access_token'] ?? ''));

        return $this->findOrCreateUser($provider, $profile);
    }

    private static function providerConfig(string $provider): array
    {
        $all = (array) app_config('social_login', []);
        return (array) ($all[$provider] ?? []);
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (!isset(self::PROVIDERS[$provider])) {
            throw new InvalidArgumentException('ไม่รองรับผู้ให้บริการล็อกอินนี้');
        }

        return $provider;
    }

    private function ensureConfigured(string $provider): void
    {
        if (!self::isConfigured($provider)) {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า ' . self::PROVIDERS[$provider]['name'] . ' Login กรุณาใส่ Client ID และ Client Secret ในไฟล์ .env');
        }
    }

    private function redirectUri(string $provider): string
    {
        return url('/public/oauth-callback.php');
    }

    private function consumeState(string $provider, string $state): void
    {
        $states = $_SESSION['oauth_states'] ?? [];
        $match = $states[$state] ?? null;
        unset($_SESSION['oauth_states'][$state]);

        if (!$match || ($match['provider'] ?? '') !== $provider || (int) ($match['expires_at'] ?? 0) < time()) {
            throw new InvalidArgumentException('เซสชันล็อกอินหมดอายุ กรุณากดเข้าสู่ระบบอีกครั้ง');
        }
    }

    private function exchangeCode(string $provider, string $code): array
    {
        $config = self::providerConfig($provider);
        $payload = [
            'client_id' => (string) $config['client_id'],
            'client_secret' => (string) $config['client_secret'],
            'redirect_uri' => $this->redirectUri($provider),
            'code' => $code,
        ];

        if ($provider === 'google') {
            $payload['grant_type'] = 'authorization_code';
            return $this->requestJson('POST', $this->providerEndpoint($provider, 'token_url'), [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ], $payload);
        }

        throw new InvalidArgumentException('Unsupported social login provider.');
    }

    private function fetchProfile(string $provider, string $accessToken): array
    {
        if ($accessToken === '') {
            throw new RuntimeException('ไม่สามารถรับ access token จากผู้ให้บริการได้');
        }

        return $this->requestJson('GET', $this->providerEndpoint($provider, 'profile_url'), [
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
        ]);
    }

    private function findOrCreateUser(string $provider, array $profile): array
    {
        self::ensureSocialAccountsTable();
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? ''));
        $providerUserId = trim((string) ($profile['sub'] ?? ''));

        if ($providerUserId === '') {
            throw new InvalidArgumentException(self::PROVIDERS[$provider]['name'] . ' did not return an account ID. Please try again.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(self::PROVIDERS[$provider]['name'] . ' ไม่ส่งอีเมลกลับมา กรุณาใช้บัญชีที่มีอีเมลหรือเข้าสู่ระบบด้วยรหัสผ่าน');
        }

        if ($provider === 'google' && !filter_var($profile['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) {
            throw new InvalidArgumentException('กรุณาใช้บัญชี Google ที่ยืนยันอีเมลแล้ว');
        }

        $linkedUser = $this->findLinkedUser($provider, $providerUserId);
        if ($linkedUser) {
            return $linkedUser;
        }

        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $existing = $stmt->fetch();

        if ($existing) {
            if (($existing['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('บัญชีนี้ยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
            }
            if (($existing['role'] ?? '') !== 'user') {
                throw new InvalidArgumentException('อีเมลนี้เป็นบัญชีทนายหรือแอดมิน กรุณาเข้าสู่พอร์ทัลที่ถูกต้อง');
            }
            $this->linkProviderAccount((int) $existing['id'], $provider, $providerUserId, $email);
            return $existing;
        }

        if (!app_config('registration_enabled', true)) {
            throw new InvalidArgumentException('ระบบปิดรับสมัครสมาชิกใหม่ชั่วคราว');
        }

        if ($name === '') {
            $name = strstr($email, '@', true) ?: 'Social User';
        }

        $password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        try {
            $insert = db()->prepare(
                'INSERT INTO users (name, email, phone, password, role, status)
                 VALUES (?, ?, NULL, ?, "user", "active")'
            );
            $insert->execute([$name, $email, $password]);
        } catch (PDOException $exception) {
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            if ($user && ($user['role'] ?? '') === 'user' && ($user['status'] ?? '') === 'active') {
                return $user;
            }
            throw $exception;
        }

        $userId = (int) db()->lastInsertId();
        $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('สร้างบัญชีจาก Google Login ไม่สำเร็จ');
        }

        $this->linkProviderAccount($userId, $provider, $providerUserId, $email);
        $this->notifySocialSignup($user, self::PROVIDERS[$provider]['name']);
        return $user;
    }

    private function findLinkedUser(string $provider, string $providerUserId): ?array
    {
        $stmt = db()->prepare(
            'SELECT users.*
             FROM social_accounts
             INNER JOIN users ON users.id = social_accounts.user_id
             WHERE social_accounts.provider = ? AND social_accounts.provider_user_id = ?
             LIMIT 1'
        );
        $stmt->execute([$provider, $providerUserId]);
        $user = $stmt->fetch();
        if (!$user) {
            return null;
        }
        if (($user['status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('บัญชีนี้ยังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
        }
        if (($user['role'] ?? '') !== 'user') {
            throw new InvalidArgumentException('บัญชี Google นี้ไม่สามารถเข้าสู่พอร์ทัลผู้ใช้ได้');
        }

        return $user;
    }

    private function linkProviderAccount(int $userId, string $provider, string $providerUserId, string $email): void
    {
        $existing = db()->prepare('SELECT provider_user_id FROM social_accounts WHERE user_id = ? AND provider = ? LIMIT 1');
        $existing->execute([$userId, $provider]);
        $existingProviderUserId = $existing->fetchColumn();
        if ($existingProviderUserId !== false && !hash_equals((string) $existingProviderUserId, $providerUserId)) {
            throw new InvalidArgumentException('บัญชีนี้เชื่อมกับบัญชี ' . self::PROVIDERS[$provider]['name'] . ' อื่นอยู่แล้ว');
        }

        if (db_driver() === 'sqlite') {
            $stmt = db()->prepare(
                'INSERT INTO social_accounts (user_id, provider, provider_user_id, provider_email, updated_at)
                 VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                 ON CONFLICT(user_id, provider) DO UPDATE SET
                    provider_email = excluded.provider_email,
                    updated_at = CURRENT_TIMESTAMP'
            );
            $stmt->execute([$userId, $provider, $providerUserId, $email]);
            return;
        }

        $stmt = db()->prepare(
            'INSERT INTO social_accounts (user_id, provider, provider_user_id, provider_email)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                provider_email = VALUES(provider_email),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$userId, $provider, $providerUserId, $email]);
    }

    private function notifySocialSignup(array $user, string $providerName): void
    {
        try {
            $notify = new NotificationService();
            $notify->create((int) $user['id'], 'สมัครสมาชิกสำเร็จ', 'คุณเข้าสู่ระบบด้วย ' . $providerName . ' ได้แล้ว สามารถเริ่มถาม AI หรือค้นหาทนายได้ทันที', 'account');

            $admins = db()->query('SELECT id FROM users WHERE role = "admin" AND status = "active"')->fetchAll();
            foreach ($admins as $admin) {
                $notify->create((int) $admin['id'], 'มีสมาชิกใหม่จาก Google Login', $user['name'] . ' สมัครด้วย ' . $providerName . ' (' . $user['email'] . ')', 'account');
            }
        } catch (Throwable) {
            // Social login should not fail because notifications cannot be written.
        }
    }

    private static function ensureSocialAccountsTable(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (db_driver() === 'sqlite') {
            db()->exec(
                'CREATE TABLE IF NOT EXISTS social_accounts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    provider TEXT NOT NULL,
                    provider_user_id TEXT NOT NULL,
                    provider_email TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE (provider, provider_user_id),
                    UNIQUE (user_id, provider)
                )'
            );
            return;
        }

        db()->exec(
            'CREATE TABLE IF NOT EXISTS social_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                provider VARCHAR(30) NOT NULL,
                provider_user_id VARCHAR(255) NOT NULL,
                provider_email VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY social_provider_account_unique (provider, provider_user_id),
                UNIQUE KEY social_user_provider_unique (user_id, provider)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function providerEndpoint(string $provider, string $key): string
    {
        $endpoint = (string) (self::PROVIDERS[$provider][$key] ?? '');
        return $endpoint;
    }

    private function requestJson(string $method, string $url, array $headers = [], array $body = []): array
    {
        $method = strtoupper($method);
        $response = '';
        $status = 0;

        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method === 'POST') {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
            }

            $response = (string) curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($response === '' && $error !== '') {
                throw new RuntimeException('เชื่อมต่อผู้ให้บริการล็อกอินไม่สำเร็จ กรุณาลองใหม่');
            }
        } else {
            $context = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'timeout' => 20,
                    'ignore_errors' => true,
                ],
            ];
            if ($method === 'POST') {
                $context['http']['content'] = http_build_query($body);
            }
            $response = (string) file_get_contents($url, false, stream_context_create($context));
            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
                    $status = (int) $match[1];
                    break;
                }
            }
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            throw new RuntimeException('ผู้ให้บริการล็อกอินตอบกลับไม่ถูกต้อง');
        }

        if ($status >= 400 || isset($json['error'])) {
            throw new RuntimeException('ล็อกอินกับผู้ให้บริการไม่สำเร็จ กรุณาตรวจสอบการตั้งค่า OAuth');
        }

        return $json;
    }

    private function withQuery(string $url, array $query): string
    {
        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }
}
