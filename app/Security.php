<?php
/**
 * News Portal - Security helpers: CSRF, XSS, input sanitization, uploads.
 */

final class Security
{
    /** Generate or reuse a CSRF token stored in session. */
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Hidden input field for forms. */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . self::csrfToken() . '">';
    }

    /** Validate a submitted CSRF token. */
    public static function csrfCheck(?string $token = null): bool
    {
        $token = $token ?? ($_POST['csrf_token'] ?? '');
        return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
    }

    /** Verify CSRF and terminate with 403 on failure. */
    public static function csrfValidate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !self::csrfCheck()) {
            http_response_code(403);
            die('Invalid or expired security token. Please go back, refresh the page and try again.');
        }
    }

    /** Escape for safe HTML output. */
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    /** Truncate a string to $limit chars on word boundary. */
    public static function truncate(?string $text, int $limit = 120, string $suffix = '...'): string
    {
        $text = trim(strip_tags((string) $text));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $pos = mb_strrpos($cut, ' ');
        return mb_substr($cut, 0, $pos !== false ? $pos : $limit) . $suffix;
    }

    /** Normalize a user-supplied slug. */
    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/u', '', $text);
        $text = preg_replace('/[\s_]+/', '-', $text);
        $text = preg_replace('/-+/', '-', $text);
        return trim($text, '-') ?: 'item';
    }

    /** Client IP with validation. */
    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /** Secure session start. */
    public static function secureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_start();
        if (!isset($_SESSION['started_at'])) {
            session_regenerate_id(true);
            $_SESSION['started_at'] = time();
        }
        // Session expiry (e.g. 8 hours)
        if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > 28800) {
            session_unset();
            session_destroy();
            session_start();
            session_regenerate_id(true);
        }
        $_SESSION['last_active'] = time();
    }

    /** Rate-limit login attempts per IP+username. */
    public static function loginThrottled(string $username): bool
    {
        $ip = self::ip();
        $window = time() - 900; // 15 minutes
        $sql = "SELECT COUNT(*) FROM login_attempts WHERE ip = :ip AND attempted_at > FROM_UNIXTIME(:win)";
        $byIp = (int) DB::scalar($sql, ['ip' => $ip, 'win' => $window]);
        $byUser = (int) DB::scalar($sql . " AND username = :u", ['ip' => $ip, 'win' => $window, 'u' => $username]);
        return $byIp >= 10 || $byUser >= 5;
    }

    public static function recordLoginAttempt(string $username): void
    {
        DB::insert(
            'INSERT INTO login_attempts (username, ip, attempted_at) VALUES (:u, :ip, NOW())',
            ['u' => $username, 'ip' => self::ip()]
        );
    }

    public static function clearLoginAttempts(string $username): void
    {
        DB::run('DELETE FROM login_attempts WHERE username = :u OR ip = :ip', ['u' => $username, 'ip' => self::ip()]);
    }

    /** Validate & persist an uploaded image. Returns stored relative path or null. */
    public static function uploadImage(array $file, string $subdir = 'news', int $maxKb = 2048): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if ($file['size'] > $maxKb * 1024) {
            return null;
        }

        // Verify real MIME type, not just extension.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return null;
        }

        // Validate the image actually decodes.
        if (@getimagesize($file['tmp_name']) === false) {
            return null;
        }

        // Create nested subdir by month to keep folders small.
        $month = date('Y/m');
        $dir = UPLOAD_PATH . '/' . $subdir . '/' . $month;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = $subdir . '-' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        $dest = $dir . '/' . $name;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return null;
        }
        @chmod($dest, 0644);
        return UPLOAD_URL . '/' . $subdir . '/' . $month . '/' . $name;
    }

    /** Validate & persist a PDF (for epaper). */
    public static function uploadPdf(array $file, string $subdir = 'epaper', int $maxKb = 51200): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > $maxKb * 1024) {
            return null;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if ($finfo->file($file['tmp_name']) !== 'application/pdf') {
            return null;
        }
        $month = date('Y/m');
        $dir = UPLOAD_PATH . '/' . $subdir . '/' . $month;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = 'epaper-' . date('Ymd') . '-' . bin2hex(random_bytes(6)) . '.pdf';
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
            return null;
        }
        @chmod($dir . '/' . $name, 0644);
        return UPLOAD_URL . '/' . $subdir . '/' . $month . '/' . $name;
    }

    /** Send security headers. */
    public static function sendSecurityHeaders(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 0'); // modern browsers; CSP is the real protection
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data: blob: https:; media-src \'self\' blob: https:; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com; script-src \'self\' \'unsafe-inline\' https://www.googletagmanager.com; frame-ancestors \'self\'; base-uri \'self\'; form-action \'self\'');
    }

    /** Output 404 and stop. */
    public static function notFound(string $msg = 'Page not found'): void
    {
        http_response_code(404);
        require BASE_PATH . '/views/404.php';
        exit;
    }
}
