-- AutoSecForge Pro v12.1 – Full Schema
-- Run: docker exec -i autosecforge-db-1 mysql -u dashboard -pChangeMe123 security_dashboard < database/schema.sql

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ── Users ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(100) NOT NULL,
    email      VARCHAR(255) UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    role       ENUM('admin','manager','analyst','client','auditor','executive') DEFAULT 'analyst',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin (password: Admin@123 – change immediately)
INSERT IGNORE INTO users (full_name, email, password, role)
VALUES ('Administrator', 'admin@autosecforge.local',
        '$2b$12$6mQ4df5e3nLI6rPa7VPOde2wQzVpgWOsObVdVEcsRIVCDQMTX/ZKi', 'admin');

-- ── Projects ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS projects (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(255) NOT NULL,
    client_id  INT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id)  REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Project members ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS project_members (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    user_id    INT NOT NULL,
    role       ENUM('owner','member','viewer') NOT NULL DEFAULT 'member',
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    UNIQUE KEY unique_member (project_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Scan jobs ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS scan_jobs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    target       VARCHAR(255)  NOT NULL,
    scan_types   VARCHAR(100)  NOT NULL DEFAULT 'network',
    raw_output   MEDIUMTEXT,
    analysis     MEDIUMTEXT,
    model        VARCHAR(100),
    triggered_by INT,
    status       ENUM('completed','partial','failed') DEFAULT 'completed',
    project_id   INT,
    client_id    INT,                       -- client this report belongs to (role='client')
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (triggered_by) REFERENCES users(id)    ON DELETE SET NULL,
    FOREIGN KEY (project_id)   REFERENCES projects(id) ON DELETE SET NULL,
    FOREIGN KEY (client_id)    REFERENCES users(id)    ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_client  (client_id),
    INDEX idx_target  (target(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Findings ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS findings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    scan_job_id  INT,
    title        VARCHAR(500) NOT NULL,
    description  TEXT,
    severity     ENUM('critical','high','medium','low','info') DEFAULT 'medium',
    cvss_score   DECIMAL(3,1),
    cwe_id       VARCHAR(20),
    cve_id       VARCHAR(30),
    affected_url VARCHAR(1000),
    remediation  TEXT,
    status       ENUM('open','in_progress','resolved','wont_fix') DEFAULT 'open',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (scan_job_id) REFERENCES scan_jobs(id) ON DELETE CASCADE,
    INDEX idx_severity (severity),
    INDEX idx_status   (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Audit log ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    action     VARCHAR(200) NOT NULL,
    detail     TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user   (user_id),
    INDEX idx_action (action(50))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Clients (extended user view for client role) ───────────────────
CREATE TABLE IF NOT EXISTS clients (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNIQUE,
    company    VARCHAR(255),
    phone      VARCHAR(30),
    notes      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Notifications ──────────────────────────────────────────────────
-- One row per recipient. A scan/report completion fans out into a row
-- for the triggering user plus every admin/manager.
CREATE TABLE IF NOT EXISTS notifications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    type       VARCHAR(40) NOT NULL DEFAULT 'info',
    title      VARCHAR(255) NOT NULL,
    body       VARCHAR(500),
    link       VARCHAR(255),
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Ideas & Feedback board ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS ideas (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT,
    title      VARCHAR(200) NOT NULL,
    body       TEXT,
    category   ENUM('idea','feedback','bug','question') NOT NULL DEFAULT 'idea',
    status     ENUM('open','planned','in_progress','done','declined') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status   (status),
    INDEX idx_category (category),
    INDEX idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS idea_comments (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    idea_id    INT NOT NULL,
    user_id    INT,
    body       TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_idea (idea_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS idea_votes (
    idea_id    INT NOT NULL,
    user_id    INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (idea_id, user_id),
    FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET foreign_key_checks = 1;
