<?php
/**
 * News Portal - Settings manager.
 * Caches all settings from DB in a static array for one request.
 */

final class Settings
{
    private static ?Settings $instance = null;
    private array $data = [];

    public static function instance(): Settings
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        try {
            foreach (DB::fetchAll('SELECT setting_key, setting_value FROM settings') as $row) {
                $this->data[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            $this->data = [];
        }
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->data[$key] ?? $default;
    }

    public function bool(string $key, bool $default = false): bool
    {
        return isset($this->data[$key]) ? (bool) $this->data[$key] : $default;
    }

    /** Bulk update settings from admin. */
    public static function update(array $values): void
    {
        $stmt = DB::conn()->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        foreach ($values as $key => $value) {
            $stmt->execute(['k' => $key, 'v' => (string) $value]);
        }
        self::$instance = null; // force refresh
    }
}
