-- ============================================================
-- FlowStack — Full Database Schema
-- MySQL 8.0+ | utf8mb4 | InnoDB
-- Location: /database/schema.sql
--
-- HOW TO RUN:
--   Option 1: phpMyAdmin → Import this file
--   Option 2: mysql -u root flowstack < schema.sql
--   Option 3: Visit http://localhost/FlowStack/setup_db.php
-- ============================================================

CREATE DATABASE IF NOT EXISTS flowstack
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE flowstack;

-- ── users ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120)    NOT NULL,
    email         VARCHAR(180)    NOT NULL,
    password_hash VARCHAR(255)    NOT NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── habits ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS habits (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED    NOT NULL,
    name           VARCHAR(200)    NOT NULL,
    streak         INT             NOT NULL DEFAULT 0,
    last_completed DATE                     DEFAULT NULL,
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_habits_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── habit_logs ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS habit_logs (
    id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    habit_id       INT UNSIGNED    NOT NULL,
    user_id        INT UNSIGNED    NOT NULL,
    completed_date DATE            NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_habit_day (habit_id, completed_date),
    KEY idx_habit_logs_user_date (user_id, completed_date),
    FOREIGN KEY (habit_id) REFERENCES habits(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── focus_sessions ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS focus_sessions (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    duration_minutes INT             NOT NULL DEFAULT 0,
    session_date     DATE            NOT NULL,
    time_of_day      ENUM('morning','afternoon','evening','night') NOT NULL DEFAULT 'morning',
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_focus_user_date (user_id, session_date),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── decisions ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS decisions (
    id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED    NOT NULL,
    decision_text TEXT            NOT NULL,
    outcome       ENUM('good','bad','neutral') NOT NULL DEFAULT 'neutral',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_decisions_user (user_id),
    KEY idx_decisions_outcome (user_id, outcome),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── skills ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS skills (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    user_id           INT UNSIGNED     NOT NULL,
    skill_name        VARCHAR(150)     NOT NULL,
    proficiency_level TINYINT UNSIGNED NOT NULL DEFAULT 5,
    created_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_skills_user (user_id),
    CONSTRAINT chk_proficiency CHECK (proficiency_level BETWEEN 1 AND 10),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── skill_history ─────────────────────────────────────────────
-- Tracks proficiency level changes over time for growth analytics
CREATE TABLE IF NOT EXISTS skill_history (
    id                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    skill_id          INT UNSIGNED     NOT NULL,
    user_id           INT UNSIGNED     NOT NULL,
    proficiency_level TINYINT UNSIGNED NOT NULL,
    recorded_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_skill_history_user (user_id),
    KEY idx_skill_history_skill (skill_id),
    CONSTRAINT chk_history_proficiency CHECK (proficiency_level BETWEEN 1 AND 10),
    FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── path_compare ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS path_compare (
    id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED    NOT NULL,
    option_a        TEXT            NOT NULL,
    option_b        TEXT            NOT NULL,
    selected_option ENUM('A','B')   NOT NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_path_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── next_move ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS next_move (
    id               INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    user_id          INT UNSIGNED    NOT NULL,
    situation_text   TEXT            NOT NULL,
    generated_advice TEXT            NOT NULL,
    cluster          VARCHAR(50)     DEFAULT 'general',
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_nextmove_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── analytics_logs ────────────────────────────────────────────
-- Optional general event log for future expansion
CREATE TABLE IF NOT EXISTS analytics_logs (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    INT UNSIGNED NOT NULL,
    event_type VARCHAR(80)  NOT NULL,
    event_data JSON                  DEFAULT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_analytics_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- VERIFICATION
-- SHOW TABLES;
-- SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'flowstack';
-- ============================================================
