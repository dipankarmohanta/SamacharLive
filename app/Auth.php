<?php
/**
 * News Portal - Authentication & authorization.
 */

final class Auth
{
    /** Start secure session. Call before output. */
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        return DB::fetch('SELECT * FROM users WHERE id = :id AND status = 1', ['id' => (int) $_SESSION['user_id']]);
    }

    public static function id(): ?int
    {
        self::start();
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        self::start();
        return $_SESSION['role'] ?? null;
    }

    /** Attempt login. Returns error string or null on success. */
    public static function attempt(string $username, string $password): ?string
    {
        if (Security::loginThrottled($username)) {
            return 'Too many failed attempts. Please wait 15 minutes.';
        }
        $user = DB::fetch('SELECT * FROM users WHERE username = :u1 OR email = :u2 LIMIT 1', ['u1' => trim($username), 'u2' => trim($username)]);
        if (!$user || !password_verify($password, $user['password'])) {
            Security::recordLoginAttempt($username);
            return 'Invalid username or password.';
        }
        if ((int) $user['status'] !== 1) {
            Security::recordLoginAttempt($username);
            return 'Your account has been disabled.';
        }
        // Rehash if needed
        if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
            DB::run('UPDATE users SET password = :p WHERE id = :id', [
                'p' => password_hash($password, PASSWORD_DEFAULT),
                'id' => $user['id'],
            ]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        DB::run('UPDATE users SET last_login = NOW() WHERE id = :id', ['id' => $user['id']]);
        Security::clearLoginAttempts($username);
        return null;
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): void
    {
        self::start();
        if (!self::id()) {
            header('Location: index.php');
            exit;
        }
    }

    /** Restrict area to given roles. */
    public static function requireRole(array $roles): void
    {
        self::start();
        if (!self::id()) {
            header('Location: index.php');
            exit;
        }
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            die('Access denied. You do not have permission for this area.');
        }
    }
}
