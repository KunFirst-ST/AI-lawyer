<?php

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/database.php';

function currentUser(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }

    return $user;
}

function authenticateAccount(string $email, string $password, ?string $role = null): ?array
{
    $email = strtolower(trim($email));
    $sql = 'SELECT * FROM users WHERE email = ? AND status = "active"';
    $params = [$email];
    if ($role !== null) {
        $sql .= ' AND role = ?';
        $params[] = $role;
    }
    $sql .= ' LIMIT 1';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $account = $stmt->fetch();

    return ($account && password_verify($password, $account['password'])) ? $account : null;
}

function loginUser(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function dashboardPathForRole(string $role): string
{
    return match ($role) {
        'admin' => url('/admin/dashboard.php'),
        'lawyer' => url('/lawyer/dashboard.php'),
        default => url('/user/ai-chat.php'),
    };
}

function loginPathForRole(string $role = 'user'): string
{
    return match ($role) {
        'admin' => url('/admin/login.php'),
        'lawyer' => url('/lawyer/login.php'),
        default => url('/user/login.php'),
    };
}

function requireLogin(?string $loginUrl = null): void
{
    if (!currentUser()) {
        flash('warning', 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
        redirect($loginUrl ?? url('/public/login.php'));
    }
}

function requireRole(string|array $role): void
{
    $roles = (array) $role;
    requireLogin(loginPathForRole((string) $roles[0]));
    $user = currentUser();
    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        exit('403 Forbidden: บัญชีนี้ไม่มีสิทธิ์เข้าพอร์ทัลนี้');
    }
}

function isAdmin(): bool
{
    return (currentUser()['role'] ?? null) === 'admin';
}

function isLawyer(): bool
{
    return (currentUser()['role'] ?? null) === 'lawyer';
}

function isUser(): bool
{
    return (currentUser()['role'] ?? null) === 'user';
}
