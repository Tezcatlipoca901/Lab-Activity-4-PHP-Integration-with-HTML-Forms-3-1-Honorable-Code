-- =====================================================================
--  Brewlist – Database Schema
--  Engine : MySQL / MariaDB (InnoDB)
--  Charset: utf8mb4 / utf8mb4_unicode_ci
-- =====================================================================

CREATE DATABASE IF NOT EXISTS task_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE task_db;

-- ---------------------------------------------------------------------
--  Table: categories
--  Stores the user-defined categories (School, Work, Personal, etc.)
--  The "Others" category is the default fallback and cannot be deleted.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    name       VARCHAR(20)      NOT NULL,
    color      CHAR(7)          NOT NULL DEFAULT '#6c7086',   -- hex color, e.g. #cba6f7
    is_default TINYINT(1)       NOT NULL DEFAULT 0,           -- 1 = "Others" (protected)
    created_at TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Table: tasks
--  One row per task; category is a FK to categories.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    category_id INT UNSIGNED     NOT NULL,
    title       VARCHAR(80)      NOT NULL,
    description VARCHAR(255)     NOT NULL,
    due_date    DATE             NULL DEFAULT NULL,            -- NULL means "No due date"
    done        TINYINT(1)       NOT NULL DEFAULT 0,          -- 0 = active, 1 = completed
    created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT fk_tasks_category
        FOREIGN KEY (category_id)
        REFERENCES categories (id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT        -- prevent deleting a category that still has tasks
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  Indexes for common filter / sort queries
--    • Filter by done status
--    • Filter by due date range (today / week / month / year / nodue)
--    • Sort by due_date ASC/DESC
-- ---------------------------------------------------------------------
CREATE INDEX idx_tasks_done     ON tasks (done);
CREATE INDEX idx_tasks_due_date ON tasks (due_date);
CREATE INDEX idx_tasks_category ON tasks (category_id);

-- ---------------------------------------------------------------------
--  Seed: default categories (mirrors the hardcoded PHP session defaults)
-- ---------------------------------------------------------------------
INSERT INTO categories (name, color, is_default) VALUES
    ('Others',   '#6c7086', 1),   -- default / fallback; protected from deletion
    ('School',   '#cba6f7', 0),
    ('Personal', '#a6e3a1', 0),
    ('Work',     '#b794f6', 0);

-- ---------------------------------------------------------------------
--  Seed: sample tasks (optional – remove in production)
-- ---------------------------------------------------------------------
INSERT INTO tasks (category_id, title, description, due_date, done) VALUES
    (2, 'Submit lab report',      'Complete and submit the Lab Activity 4 report.',  CURDATE(),              0),
    (4, 'Team standup notes',     'Write up notes from the Monday standup call.',     DATE_ADD(CURDATE(), INTERVAL 2 DAY), 0),
    (3, 'Buy groceries',          'Milk, eggs, coffee beans, and oat milk.',          DATE_ADD(CURDATE(), INTERVAL 1 DAY), 0),
    (2, 'Review lecture slides',  'Go over the SQL chapter slides before the quiz.',  DATE_SUB(CURDATE(), INTERVAL 1 DAY), 0),  -- overdue
    (3, 'Morning run',            'Complete a 5 km run before 7 AM.',                 NULL,                   1);  -- completed, no due date
