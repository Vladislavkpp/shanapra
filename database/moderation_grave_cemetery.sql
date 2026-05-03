-- moderation_grave_cemetery.sql
--
-- Цель:
-- Добавить в таблицы `grave` и `cemetery` поля для процесса модерации.
--
-- Поля (и назначение):
-- 1) moderation_status
--    Статус модерации записи:
--    - pending  : ожидает проверки модератором
--    - approved : подтверждено модератором
--    - rejected : отклонено модератором
--
-- 2) moderation_submitted_at
--    Дата/время, когда запись попала в очередь модерации.
--    Для новых записей проставляется автоматически (CURRENT_TIMESTAMP).
--
-- 3) moderation_reviewed_at
--    Дата/время, когда модератор принял решение (approved/rejected).
--
-- 4) moderation_reviewed_by
--    ID пользователя-модератора, который принял решение (users.idx).
--
-- 5) moderation_reject_reason
--    Причина отклонения (заполняется для rejected).
--
-- 6) moderation_note
--    Внутренняя заметка модератора (необязательно).
--
-- Дополнительно:
-- - Добавляются индексы для быстрого списка очереди модерации.
-- - Миграция идемпотентная: повторный запуск безопасен.

/* =========================================================
   TABLE: grave
   ========================================================= */
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
);

-- grave.moderation_status
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND COLUMN_NAME = 'moderation_status'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `grave` ADD COLUMN `moderation_status` ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' COMMENT ''Статус модерації: pending/approved/rejected''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- grave.moderation_submitted_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND COLUMN_NAME = 'moderation_submitted_at'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `grave` ADD COLUMN `moderation_submitted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT ''Дата/час потрапляння запису в чергу модерації''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- grave.moderation_reviewed_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND COLUMN_NAME = 'moderation_reviewed_at'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `grave` ADD COLUMN `moderation_reviewed_at` TIMESTAMP NULL DEFAULT NULL COMMENT ''Дата/час рішення модератора''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- grave.moderation_reviewed_by
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND COLUMN_NAME = 'moderation_reviewed_by'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `grave` ADD COLUMN `moderation_reviewed_by` INT(11) NULL COMMENT ''ID модератора (users.idx), який прийняв рішення''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- grave.moderation_reject_reason
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND COLUMN_NAME = 'moderation_reject_reason'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `grave` ADD COLUMN `moderation_reject_reason` TEXT NULL COMMENT ''Причина відхилення (для статусу rejected)''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- grave.moderation_note
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND COLUMN_NAME = 'moderation_note'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `grave` ADD COLUMN `moderation_note` TEXT NULL COMMENT ''Внутрішня нотатка модератора''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: queue list by status/date
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND INDEX_NAME = 'idx_grave_mod_queue'
);
SET @sql := IF(
    @table_exists = 1 AND @idx_exists = 0,
    'ALTER TABLE `grave` ADD INDEX `idx_grave_mod_queue` (`moderation_status`, `moderation_submitted_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: reviewer filter
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'grave'
      AND INDEX_NAME = 'idx_grave_mod_reviewer'
);
SET @sql := IF(
    @table_exists = 1 AND @idx_exists = 0,
    'ALTER TABLE `grave` ADD INDEX `idx_grave_mod_reviewer` (`moderation_reviewed_by`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


/* =========================================================
   TABLE: cemetery
   ========================================================= */
SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
);

-- cemetery.moderation_status
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND COLUMN_NAME = 'moderation_status'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `cemetery` ADD COLUMN `moderation_status` ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending'' COMMENT ''Статус модерації: pending/approved/rejected''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cemetery.moderation_submitted_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND COLUMN_NAME = 'moderation_submitted_at'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `cemetery` ADD COLUMN `moderation_submitted_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT ''Дата/час потрапляння запису в чергу модерації''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cemetery.moderation_reviewed_at
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND COLUMN_NAME = 'moderation_reviewed_at'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `cemetery` ADD COLUMN `moderation_reviewed_at` TIMESTAMP NULL DEFAULT NULL COMMENT ''Дата/час рішення модератора''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cemetery.moderation_reviewed_by
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND COLUMN_NAME = 'moderation_reviewed_by'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `cemetery` ADD COLUMN `moderation_reviewed_by` INT(11) NULL COMMENT ''ID модератора (users.idx), який прийняв рішення''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cemetery.moderation_reject_reason
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND COLUMN_NAME = 'moderation_reject_reason'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `cemetery` ADD COLUMN `moderation_reject_reason` TEXT NULL COMMENT ''Причина відхилення (для статусу rejected)''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cemetery.moderation_note
SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND COLUMN_NAME = 'moderation_note'
);
SET @sql := IF(
    @table_exists = 1 AND @col_exists = 0,
    'ALTER TABLE `cemetery` ADD COLUMN `moderation_note` TEXT NULL COMMENT ''Внутрішня нотатка модератора''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: queue list by status/date
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND INDEX_NAME = 'idx_cemetery_mod_queue'
);
SET @sql := IF(
    @table_exists = 1 AND @idx_exists = 0,
    'ALTER TABLE `cemetery` ADD INDEX `idx_cemetery_mod_queue` (`moderation_status`, `moderation_submitted_at`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Index: reviewer filter
SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'cemetery'
      AND INDEX_NAME = 'idx_cemetery_mod_reviewer'
);
SET @sql := IF(
    @table_exists = 1 AND @idx_exists = 0,
    'ALTER TABLE `cemetery` ADD INDEX `idx_cemetery_mod_reviewer` (`moderation_reviewed_by`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- ===================================================================
-- (Опционально) Если хотите считать все существующие записи "одобренными"
-- и модерировать только новые, выполните ОДИН РАЗ вручную:
--
-- UPDATE `grave`
--    SET `moderation_status` = ''approved'',
--        `moderation_reviewed_at` = COALESCE(`moderation_reviewed_at`, `idtadd`),
--        `moderation_reviewed_by` = COALESCE(`moderation_reviewed_by`, `idxadd`)
--  WHERE `moderation_status` = ''pending'';
--
-- UPDATE `cemetery`
--    SET `moderation_status` = ''approved'',
--        `moderation_reviewed_at` = COALESCE(`moderation_reviewed_at`, `dtadd`),
--        `moderation_reviewed_by` = COALESCE(`moderation_reviewed_by`, `idxadd`)
--  WHERE `moderation_status` = ''pending'';
-- ===================================================================
