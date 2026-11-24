-- Ai library, етап 18: одит (кой какво е правил) и права по агент и инструмент. Изпълнява се след 001.
-- AiAudit пази само безопасни данни: тайни и текстове на заявки не се записват.
-- AiAccessRules: ако за агент или инструмент няма редове, той е достъпен за всички; иначе само за изброените роли (алиас на група потребители).

CREATE TABLE IF NOT EXISTS `AiAudit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `created` DATETIME NOT NULL,
  `actorType` VARCHAR(10) NOT NULL,
  `actorId` INT UNSIGNED NULL,
  `actorName` VARCHAR(100) NULL,
  `ip` VARCHAR(45) NULL,
  `action` VARCHAR(60) NOT NULL,
  `entityType` VARCHAR(40) NULL,
  `entityId` VARCHAR(80) NULL,
  `result` VARCHAR(10) NOT NULL DEFAULT 'ok',
  `details` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiAudit_created` (`created`),
  KEY `idx_AiAudit_action` (`action`, `created`),
  KEY `idx_AiAudit_actor` (`actorType`, `actorId`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiAccessRules` (
  `subjectType` VARCHAR(10) NOT NULL,
  `subjectCode` VARCHAR(40) NOT NULL,
  `role` VARCHAR(60) NOT NULL,
  PRIMARY KEY (`subjectType`, `subjectCode`, `role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
