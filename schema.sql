-- AutoSecForge Pro v12 — Database Schema
-- Fixes: ASF-003 (project_members FK), ASF-007 (Argon2ID column size), ASF-008 (no seed creds)

CREATE DATABASE IF NOT EXISTS security_dashboard CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE security_dashboard;

-- ─── Users ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)     NOT NULL,
    email         VARCHAR(255)     NOT NULL UNIQUE,
    -- ASF-007: VARCHAR(255) for Argon2ID hash (bcrypt fits in 60, Argon2ID needs up to 255)
    password_hash VARCHAR(255)     NOT NULL,
    -- ASF-008: role ENUM — no default admin row seeded here; setup.php prompts on first run
    role          ENUM('admin','manager','analyst','client','auditor','executive') NOT NULL DEFAULT 'client',
    active        TINYINT(1)       NOT NULL DEFAULT 1,
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
);

-- ─── Clients (tenants) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clients (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(200) NOT NULL,
    manager_id INT UNSIGNED,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ─── Projects ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    status      ENUM('active','completed','archived') NOT NULL DEFAULT 'active',
    created_by  INT UNSIGNED,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)  REFERENCES clients(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)   ON DELETE SET NULL
);

-- ─── ASF-003: project_members (IDOR fix) ─────────────────────────
-- Every access to project data MUST JOIN this table for non-admin roles.
CREATE TABLE IF NOT EXISTS project_members (
    project_id INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    role       ENUM('owner','member','viewer') NOT NULL DEFAULT 'member',
    added_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);

-- ─── Scan Jobs ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS scan_jobs (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id INT UNSIGNED NOT NULL,
    type       ENUM('sast','dast','sca','network','infra','api','cloud','container','mobile','oasm') NOT NULL,
    target     VARCHAR(500) NOT NULL,
    status     ENUM('queued','running','completed','failed') NOT NULL DEFAULT 'queued',
    started_by INT UNSIGNED,
    started_at TIMESTAMP    NULL,
    ended_at   TIMESTAMP    NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (started_by) REFERENCES users(id)    ON DELETE SET NULL
);

-- ─── Findings ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS findings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id  INT UNSIGNED NOT NULL,
    scan_job_id INT UNSIGNED,
    title       VARCHAR(500) NOT NULL,
    description TEXT,
    severity    ENUM('critical','high','medium','low','informational') NOT NULL,
    cvss_score  DECIMAL(3,1),
    cwe_id      VARCHAR(20),
    status      ENUM('open','in_progress','fixed','accepted','false_positive') NOT NULL DEFAULT 'open',
    created_by  INT UNSIGNED,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)  REFERENCES projects(id)   ON DELETE CASCADE,
    FOREIGN KEY (scan_job_id) REFERENCES scan_jobs(id)  ON DELETE SET NULL,
    FOREIGN KEY (created_by)  REFERENCES users(id)      ON DELETE SET NULL
);

-- ─── Evidence ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS evidence (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    finding_id   INT UNSIGNED NOT NULL,
    filename     VARCHAR(255) NOT NULL,  -- randomised safe name (ASF-004)
    original_name VARCHAR(255),
    mime_type    VARCHAR(100) NOT NULL,
    file_size    INT UNSIGNED NOT NULL,
    uploaded_by  INT UNSIGNED,
    uploaded_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (finding_id)  REFERENCES findings(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)    ON DELETE SET NULL
);

-- ─── Reports ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS reports (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id  INT UNSIGNED NOT NULL,
    title       VARCHAR(300) NOT NULL,
    type        ENUM('technical','executive','compliance') NOT NULL DEFAULT 'technical',
    status      ENUM('draft','pending_approval','approved','delivered') NOT NULL DEFAULT 'draft',
    created_by  INT UNSIGNED,
    approved_by INT UNSIGNED,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id)  REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by)  REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id)    ON DELETE SET NULL
);

-- ─── Audit Logs (immutable) ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED,
    action     VARCHAR(100) NOT NULL,
    entity     VARCHAR(100),
    entity_id  INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action    (action),
    INDEX idx_created   (created_at)
);

-- ─── ASF-008: No seed data — setup.php creates the first admin on first run ──
