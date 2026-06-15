<?php
class Database {
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            $envFile = __DIR__ . '/../.env';
            if (!file_exists($envFile)) {
                $envFile = '/var/www/html/.env';
            }
            if (!file_exists($envFile)) {
                throw new Exception(".env file not found");
            }
            $env = parse_ini_file($envFile, false, INI_SCANNER_RAW);
            if (!$env) {
                throw new Exception("Failed to parse .env");
            }
            $dsn = "mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4";
            self::$instance = new PDO($dsn, $env['DB_USER'], $env['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                // Use real server-side prepared statements (not emulated string
                // substitution) so bound parameters can never be reinterpreted
                // as SQL — defence in depth alongside utf8mb4.
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$instance;
    }
}
?>
