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

    /**
     * Sanitize untrusted rich HTML (news/pages body) against stored XSS.
     * Allowlist-based: DOMDocument parses the input, forbidden elements are
     * removed, disallowed elements are unwrapped, and every attribute is
     * validated. A regex scrub runs last as defense-in-depth.
     */
    public static function sanitizeHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $allowedTags = [
            'p'=>1,'div'=>1,'br'=>1,'hr'=>1,
            'h2'=>1,'h3'=>1,'h4'=>1,'h5'=>1,'h6'=>1,
            'strong'=>1,'b'=>1,'em'=>1,'i'=>1,'u'=>1,'s'=>1,'strike'=>1,
            'ul'=>1,'ol'=>1,'li'=>1,'blockquote'=>1,'pre'=>1,'code'=>1,
            'a'=>1,'img'=>1,'figure'=>1,'figcaption'=>1,
            'table'=>1,'thead'=>1,'tbody'=>1,'tfoot'=>1,'tr'=>1,'td'=>1,'th'=>1,
            'span'=>1,'mark'=>1,'sub'=>1,'sup'=>1,'small'=>1,'del'=>1,'ins'=>1,
            'video'=>1,'audio'=>1,'source'=>1,
        ];
        $allowedAttrs = [
            'class'=>1,'id'=>1,'title'=>1,'alt'=>1,'width'=>1,'height'=>1,
            'colspan'=>1,'rowspan'=>1,'href'=>1,'src'=>1,'target'=>1,'rel'=>1,'style'=>1,
            'controls'=>1,'poster'=>1,'preload'=>1,'autoplay'=>1,'loop'=>1,'muted'=>1,'type'=>1,
        ];
        $urlAttrs = ['href'=>1,'src'=>1];

        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $ok = $dom->loadHTML('<?xml encoding="UTF-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return '';
        }

        $xpath = new DOMXPath($dom);
        $forbidden = '//script | //style | //iframe | //object | //embed | //link | //meta | //base | //form | //input | //button | //textarea | //select | //option | //svg | //math | //template | //noscript | //track | //frame | //frameset | //applet | //param | //portal | //title | //head | //body | //html | //xml | //annotation-xml | //foreignObject';
        foreach ($xpath->query($forbidden) as $node) {
            $node->parentNode && $node->parentNode->removeChild($node);
        }

        foreach ($xpath->query('//*') as $el) {
            $tag = strtolower($el->nodeName);
            if (!isset($allowedTags[$tag])) {
                // Unwrap disallowed element, keeping its children.
                $frag = $dom->createDocumentFragment();
                while ($el->firstChild) {
                    $frag->appendChild($el->firstChild);
                }
                $el->parentNode->replaceChild($frag, $el);
                continue;
            }
            $toRemove = [];
            foreach ($el->attributes as $attr) {
                $name = strtolower($attr->nodeName);
                if (!isset($allowedAttrs[$name])) {
                    $toRemove[] = $attr;
                    continue;
                }
                if ($name === 'style') {
                    $clean = self::sanitizeStyle($attr->nodeValue);
                    if ($clean === '') {
                        $toRemove[] = $attr;
                    } else {
                        $attr->nodeValue = $clean;
                    }
                } elseif (isset($urlAttrs[$name])) {
                    $clean = self::safeUrl($attr->nodeValue, $name === 'src');
                    if ($clean === '') {
                        $toRemove[] = $attr;
                    } else {
                        $attr->nodeValue = $clean;
                    }
                } elseif ($name === 'id' || $name === 'class') {
                    $v = trim(preg_replace('/[^a-zA-Z0-9_\- :]/', '', $attr->nodeValue));
                    if ($v === '') {
                        $toRemove[] = $attr;
                    } else {
                        $attr->nodeValue = $v;
                    }
                }
            }
            foreach ($toRemove as $attr) {
                $el->removeAttributeNode($attr);
            }
        }

        $out = '';
        foreach ($dom->childNodes as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                continue;
            }
            $out .= $dom->saveHTML($node);
        }

        return self::scrubHtml($out);
    }

    /** Validate a URL attribute value. Empty string means "reject". */
    private static function safeUrl(string $url, bool $isImg): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $lower = strtolower($url);
        if (preg_match('/[\x00-\x20\x7f]/', $url)) {
            return '';
        }
        if (preg_match('/^(javascript|vbscript|data|file|about|chrome|blob|filesystem|view-source):/i', $lower)) {
            if ($isImg && preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,/i', $lower)) {
                return $url;
            }
            return '';
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        if (preg_match('~^(https?:)?//~i', $url) || str_starts_with($url, '/') || str_starts_with($url, '#')
            || preg_match('/^(mailto|tel):/i', $url)) {
            return $url;
        }
        return '';
    }

    /** Allowlist inline CSS properties. Empty string means "reject". */
    private static function sanitizeStyle(string $css): string
    {
        $css = trim($css);
        if ($css === '') {
            return '';
        }
        if (preg_match('/(url\s*\(|expression\s*\(|javascript:|vbscript:|data:|behavior:|-moz-binding|@import|@charset|<|>|\))/i', $css)) {
            return '';
        }
        $allowedProps = [
            'color'=>1,'background-color'=>1,'text-align'=>1,'font-weight'=>1,'font-style'=>1,
            'text-decoration'=>1,'font-size'=>1,'padding'=>1,'padding-top'=>1,'padding-right'=>1,
            'padding-bottom'=>1,'padding-left'=>1,'margin'=>1,'margin-top'=>1,'margin-right'=>1,
            'margin-bottom'=>1,'margin-left'=>1,'width'=>1,'height'=>1,'max-width'=>1,'max-height'=>1,
            'min-width'=>1,'min-height'=>1,'border'=>1,'border-radius'=>1,'display'=>1,'float'=>1,
            'vertical-align'=>1,'line-height'=>1,'background'=>1,'letter-spacing'=>1,'word-spacing'=>1,
        ];
        $safe = [];
        foreach (preg_split('/;/', $css) as $part) {
            if (strpos($part, ':') === false) {
                continue;
            }
            [$prop, $val] = explode(':', $part, 2);
            $prop = strtolower(trim($prop));
            $val = trim($val);
            if (!isset($allowedProps[$prop]) || $val === '') {
                continue;
            }
            if (preg_match('/([{};]|\/\*|\\\\)/', $val)) {
                continue;
            }
            if (!preg_match('/^[\w\s#.%,\-\/()"\'"]+$/i', $val)) {
                continue;
            }
            $safe[] = $prop . ':' . $val;
        }
        return implode(';', $safe);
    }

    /** Defense-in-depth regex scrub of serialized output. */
    private static function scrubHtml(string $html): string
    {
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<!\[CDATA\[.*?\]\]>/s', '', $html);
        $html = preg_replace('/<\?(?:php)?\s?/i', '', $html);
        $html = preg_replace('/<\s*(\/)?\s*(script|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option|optgroup|svg|math|template|noscript|track|frame|frameset|applet|param|portal|style|title|body|head|html|xml|annotation-xml|foreignobject|isindex)\b[^>]*>/i', '', $html);
        $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(javascript|vbscript)\s*:/i', 'blocked:', $html);
        $html = preg_replace('/(\s(src|href|background|action|formaction)\s*=\s*)(["\']?)data:(?!image\/)/i', '$1$2blocked:', $html);
        return $html;
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
