<?php
/**
 * News Portal - Database wrapper (PDO singleton).
 */

require_once __DIR__ . '/../config.php';

final class DB
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            try {
                self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
                // Align MySQL NOW() with PHP's configured timezone so
                // published_at comparisons are consistent.
                self::$pdo->exec('SET time_zone = ' . self::$pdo->quote(self::mysqlTimezone()));
            } catch (PDOException $e) {
                if (APP_DEBUG) {
                    die('DB connection failed: ' . htmlspecialchars($e->getMessage()));
                }
                die('Database connection error. Please check configuration.');
            }
        }
        return self::$pdo;
    }

    /** MySQL offset string for the app timezone, e.g. "+05:30". */
    private static function mysqlTimezone(): string
    {
        try {
            $tz = new DateTimeZone(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'UTC');
            $offset = $tz->getOffset(new DateTime('now', $tz));
            $sign = $offset >= 0 ? '+' : '-';
            $abs = abs($offset);
            return sprintf('%s%02d:%02d', $sign, (int) floor($abs / 3600), (int) round(($abs % 3600) / 60));
        } catch (Throwable $e) {
            return '+00:00';
        }
    }

    /** Run a prepared statement and return the statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::run($sql, $params)->fetchColumn();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::run($sql, $params);
        return (int) self::conn()->lastInsertId();
    }
}
