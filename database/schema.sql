-- ============================================================
-- AutoSecForge – database/schema.sql  (SECURITY-HARDENED)
--
-- ASF-007 FIX: password column renamed from password_sha256
--   (CHAR 64) to password_hash (VARCHAR 255) to accommodate
--   Argon2ID hashes.
--
-- ASF-008 FIX: Default seed data no longer contains plain-text
--   credentials, password hints, or a pre-seeded admin account
--   with a known password.  A one-time setup script (setup.php)
--   prompts the operator for a strong password on first run and
--   stores it as an Argon2ID hash.
-- ============================================================

CREATE DATABASE IF NOT EXISTS security_dashboard
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE security_dashboard;

-- ---- Users --------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    email         VARCHAR(255)    NOT NULL,
    -- ASF-007 FIX: VARCHAR(255) stores Argon2ID hashes; was CHAR(64)
    password_hash VARCHAR(255)    NOT NULL,
    role          ENUM('admin','manager','analyst') NOT NULL DEFAULT 'analyst',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ASF-008 FIX: No pre-seeded admin row with a known or hinted
-- password.  Run setup.php on first deployment to create the
-- initial admin account.

-- ---- Projects -----------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Project membership (for ASF-003 IDOR fix) --------------
CREATE TABLE IF NOT EXISTS project_members (
    project_id  INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('owner','member','viewer') NOT NULL DEFAULT 'member',
    PRIMARY KEY (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Scan runs ----------------------------------------------
CREATE TABLE IF NOT EXISTS scan_runs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    project_id  INT UNSIGNED NOT NULL,
    scan_type   ENUM('DAST','SAST','SCA','Mobile') NOT NULL,
    status      ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    started_at  DATETIME,
    finished_at DATETIME,
    created_by  INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_scan_runs_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Findings -----------------------------------------------
CREATE TABLE IF NOT EXISTS findings (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    scan_run_id  INT UNSIGNED NOT NULL,
    title        VARCHAR(512) NOT NULL,
    severity     ENUM('Critical','High','Medium','Low','Info') NOT NULL,
    status       ENUM('open','false_positive','accepted_risk','resolved','escalated')
                 NOT NULL DEFAULT 'open',
    description  TEXT,
    remediation  TEXT,
    cwe          VARCHAR(20),
    cvss         DECIMAL(4,1),
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_findings_scan_run (scan_run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Clients ------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name           VARCHAR(255) NOT NULL,
    -- ASF-004 FIX: stores only the random filename, never the full path
    logo_filename  VARCHAR(255),
    created_by     INT UNSIGNED NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---- Addons -------------------------------------------------
CREATE TABLE IF NOT EXISTS addons (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name         VARCHAR(255) NOT NULL,
    api_base_url VARCHAR(512) NOT NULL,
    created_by   INT UNSIGNED NOT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_addons_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
