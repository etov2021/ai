-- Ai library, етап 11: предложения за събития в календара от агентите. Изпълнява се след 001.
-- Агентът само предлага; събитието се създава в Google Calendar след потвърждение от потребител.

CREATE TABLE IF NOT EXISTS `AiCalendarProposals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `summary` VARCHAR(200) NOT NULL,
  `description` TEXT NULL,
  `location` VARCHAR(200) NULL,
  `startAt` DATETIME NOT NULL,
  `endAt` DATETIME NOT NULL,
  `timezone` VARCHAR(64) NOT NULL,
  `status` VARCHAR(10) NOT NULL DEFAULT 'pending',
  `eventLink` VARCHAR(500) NULL,
  `error` VARCHAR(255) NULL,
  `created` DATETIME NOT NULL,
  `decided` DATETIME NULL,
  `decidedBy` INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiCalendarProposals_status` (`status`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
