-- === COPY START ===
-- moderation_journal_log.sql
-- Выполнять после moderation_grave_cemetery.sql

CREATE TABLE IF NOT EXISTS `moderation_journal_log` (
                                                        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                                        `entity_type` ENUM('grave', 'cemetery') NOT NULL,
                                                        `entity_id` INT UNSIGNED NOT NULL,
                                                        `action_type` ENUM('submitted', 'approved', 'rejected') NOT NULL,
                                                        `status_after` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                                                        `actor_user_id` INT(11) NULL COMMENT 'Хто виконав дію',
                                                        `source_user_id` INT(11) NULL COMMENT 'Хто подав запис (автор картки)',
                                                        `note` TEXT NULL,
                                                        `reject_reason` TEXT NULL,
                                                        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                                                        PRIMARY KEY (`id`),
                                                        KEY `idx_mjl_entity_time` (`entity_type`, `entity_id`, `created_at`),
                                                        KEY `idx_mjl_action_time` (`action_type`, `created_at`),
                                                        KEY `idx_mjl_actor` (`actor_user_id`, `created_at`),
                                                        KEY `idx_mjl_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DELIMITER $$

DROP TRIGGER IF EXISTS `trg_grave_modlog_ai` $$
CREATE TRIGGER `trg_grave_modlog_ai`
    AFTER INSERT ON `grave`
    FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(16);
    DECLARE v_action VARCHAR(16);
    DECLARE v_created DATETIME;
    DECLARE v_actor INT;

    SET v_status = IF(NEW.moderation_status IN ('pending', 'approved', 'rejected'), NEW.moderation_status, 'pending');
    SET v_action = CASE v_status
                       WHEN 'approved' THEN 'approved'
                       WHEN 'rejected' THEN 'rejected'
                       ELSE 'submitted'
        END;
    SET v_created = CASE v_status
                        WHEN 'approved' THEN COALESCE(NEW.moderation_reviewed_at, NEW.moderation_submitted_at, NEW.idtadd, NOW())
                        WHEN 'rejected' THEN COALESCE(NEW.moderation_reviewed_at, NEW.moderation_submitted_at, NEW.idtadd, NOW())
                        ELSE COALESCE(NEW.moderation_submitted_at, NEW.idtadd, NOW())
        END;
    SET v_actor = CASE v_status
                      WHEN 'pending' THEN NEW.idxadd
                      ELSE NEW.moderation_reviewed_by
        END;

    INSERT INTO `moderation_journal_log` (
        `entity_type`, `entity_id`, `action_type`, `status_after`,
        `actor_user_id`, `source_user_id`, `note`, `reject_reason`, `created_at`
    ) VALUES (
                 'grave', NEW.idx, v_action, v_status,
                 v_actor, NEW.idxadd, NEW.moderation_note, NEW.moderation_reject_reason, v_created
             );
END $$

DROP TRIGGER IF EXISTS `trg_grave_modlog_au` $$
CREATE TRIGGER `trg_grave_modlog_au`
    AFTER UPDATE ON `grave`
    FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(16);
    DECLARE v_action VARCHAR(16);
    DECLARE v_created DATETIME;
    DECLARE v_actor INT;

    IF COALESCE(OLD.moderation_status, 'pending') <> COALESCE(NEW.moderation_status, 'pending') THEN
        SET v_status = IF(NEW.moderation_status IN ('pending', 'approved', 'rejected'), NEW.moderation_status, 'pending');
        SET v_action = CASE v_status
                           WHEN 'approved' THEN 'approved'
                           WHEN 'rejected' THEN 'rejected'
                           ELSE 'submitted'
            END;
        SET v_created = CASE v_status
                            WHEN 'approved' THEN COALESCE(NEW.moderation_reviewed_at, NOW())
                            WHEN 'rejected' THEN COALESCE(NEW.moderation_reviewed_at, NOW())
                            ELSE COALESCE(NEW.moderation_submitted_at, NOW())
            END;
        SET v_actor = CASE v_status
                          WHEN 'pending' THEN NEW.idxadd
                          ELSE NEW.moderation_reviewed_by
            END;

        INSERT INTO `moderation_journal_log` (
            `entity_type`, `entity_id`, `action_type`, `status_after`,
            `actor_user_id`, `source_user_id`, `note`, `reject_reason`, `created_at`
        ) VALUES (
                     'grave', NEW.idx, v_action, v_status,
                     v_actor, NEW.idxadd, NEW.moderation_note, NEW.moderation_reject_reason, v_created
                 );
    END IF;
END $$

DROP TRIGGER IF EXISTS `trg_cemetery_modlog_ai` $$
CREATE TRIGGER `trg_cemetery_modlog_ai`
    AFTER INSERT ON `cemetery`
    FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(16);
    DECLARE v_action VARCHAR(16);
    DECLARE v_created DATETIME;
    DECLARE v_actor INT;

    SET v_status = IF(NEW.moderation_status IN ('pending', 'approved', 'rejected'), NEW.moderation_status, 'pending');
    SET v_action = CASE v_status
                       WHEN 'approved' THEN 'approved'
                       WHEN 'rejected' THEN 'rejected'
                       ELSE 'submitted'
        END;
    SET v_created = CASE v_status
                        WHEN 'approved' THEN COALESCE(NEW.moderation_reviewed_at, NEW.moderation_submitted_at, NEW.dtadd, NOW())
                        WHEN 'rejected' THEN COALESCE(NEW.moderation_reviewed_at, NEW.moderation_submitted_at, NEW.dtadd, NOW())
                        ELSE COALESCE(NEW.moderation_submitted_at, NEW.dtadd, NOW())
        END;
    SET v_actor = CASE v_status
                      WHEN 'pending' THEN NEW.idxadd
                      ELSE NEW.moderation_reviewed_by
        END;

    INSERT INTO `moderation_journal_log` (
        `entity_type`, `entity_id`, `action_type`, `status_after`,
        `actor_user_id`, `source_user_id`, `note`, `reject_reason`, `created_at`
    ) VALUES (
                 'cemetery', NEW.idx, v_action, v_status,
                 v_actor, NEW.idxadd, NEW.moderation_note, NEW.moderation_reject_reason, v_created
             );
END $$

DROP TRIGGER IF EXISTS `trg_cemetery_modlog_au` $$
CREATE TRIGGER `trg_cemetery_modlog_au`
    AFTER UPDATE ON `cemetery`
    FOR EACH ROW
BEGIN
    DECLARE v_status VARCHAR(16);
    DECLARE v_action VARCHAR(16);
    DECLARE v_created DATETIME;
    DECLARE v_actor INT;

    IF COALESCE(OLD.moderation_status, 'pending') <> COALESCE(NEW.moderation_status, 'pending') THEN
        SET v_status = IF(NEW.moderation_status IN ('pending', 'approved', 'rejected'), NEW.moderation_status, 'pending');
        SET v_action = CASE v_status
                           WHEN 'approved' THEN 'approved'
                           WHEN 'rejected' THEN 'rejected'
                           ELSE 'submitted'
            END;
        SET v_created = CASE v_status
                            WHEN 'approved' THEN COALESCE(NEW.moderation_reviewed_at, NOW())
                            WHEN 'rejected' THEN COALESCE(NEW.moderation_reviewed_at, NOW())
                            ELSE COALESCE(NEW.moderation_submitted_at, NOW())
            END;
        SET v_actor = CASE v_status
                          WHEN 'pending' THEN NEW.idxadd
                          ELSE NEW.moderation_reviewed_by
            END;

        INSERT INTO `moderation_journal_log` (
            `entity_type`, `entity_id`, `action_type`, `status_after`,
            `actor_user_id`, `source_user_id`, `note`, `reject_reason`, `created_at`
        ) VALUES (
                     'cemetery', NEW.idx, v_action, v_status,
                     v_actor, NEW.idxadd, NEW.moderation_note, NEW.moderation_reject_reason, v_created
                 );
    END IF;
END $$

DELIMITER ;
-- === COPY END ===
