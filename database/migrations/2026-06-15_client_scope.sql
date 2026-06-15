-- ── Client-specific reporting: link each scan job to a client ───────
-- Idempotent migration for EXISTING databases (schema.sql already has the
-- column for fresh installs). Run on the box:
--   docker exec -i autosecforge-db \
--     mysql -u dashboard -p"$DB_PASSWORD" security_dashboard \
--     < database/migrations/2026-06-15_client_scope.sql
--
-- MySQL has no "ADD COLUMN IF NOT EXISTS" before 8.0.29 in all builds, so we
-- guard with information_schema and a prepared statement.

SET @col := (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'scan_jobs'
               AND COLUMN_NAME  = 'client_id');
SET @sql := IF(@col = 0,
  'ALTER TABLE scan_jobs ADD COLUMN client_id INT NULL AFTER project_id',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Index (guarded the same way).
SET @idx := (SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'scan_jobs'
               AND INDEX_NAME   = 'idx_client');
SET @sql := IF(@idx = 0,
  'ALTER TABLE scan_jobs ADD INDEX idx_client (client_id)',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Foreign key (guarded).
SET @fk := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA    = DATABASE()
              AND TABLE_NAME      = 'scan_jobs'
              AND CONSTRAINT_NAME = 'fk_scan_jobs_client');
SET @sql := IF(@fk = 0,
  'ALTER TABLE scan_jobs ADD CONSTRAINT fk_scan_jobs_client FOREIGN KEY (client_id) REFERENCES users(id) ON DELETE SET NULL',
  'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
