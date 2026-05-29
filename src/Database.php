<?php
// ============================================================
// AutoSecForge – Database.php
// FIX: ASF-001 – Removed all hard-coded credential fallbacks.
//      All connection parameters MUST be supplied via environment
//      variables (set in .env or Docker secrets).
// FIX: ASF-010 – Schema migration failures are now logged via
//      error_log() instead of silently discarded.
// ============================================================

class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            // ASF-001 FIX: No hard-coded fallback values.
            // Missing env vars will throw an exception on first use.
            $host = getenv('DB_HOST')   ?: self::missingEnv('DB_HOST');
            $name = getenv('DB_NAME')   ?: self::missingEnv('DB_NAME');
            $user = getenv('DB_USER')   ?: self::missingEnv('DB_USER');
            $pass = getenv('DB_PASSWORD');          // empty string is valid
            if ($pass === false) {
                self::missingEnv('DB_PASSWORD');
            }

            $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                // Never expose DB details to the browser
                error_log('Database connection failed: ' . $e->getMessage());
                throw new RuntimeException('Database connection unavailable.');
            }
        }
        return self::$instance;
    }

    // -------------------------------------------------------
    // ASF-010 FIX: Log migration errors instead of swallowing.
    // -------------------------------------------------------
    public static function runSchemaStatement(string $sql): void
    {
        try {
            self::getInstance()->exec($sql);
        } catch (Throwable $e) {
            error_log('Schema migration failed [' . substr($sql, 0, 120) . ']: ' . $e->getMessage());
            // Re-throw so callers know the migration did not complete.
            throw $e;
        }
    }

    private static function missingEnv(string $name): never
    {
        error_log("Required environment variable '{$name}' is not set.");
        throw new RuntimeException("Server configuration error: missing environment variable '{$name}'.");
    }
}
