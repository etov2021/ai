-- Ai library, етап 8: получени писма и анализ от агент (известия в админа). Изпълнява се след 002.

CREATE TABLE IF NOT EXISTS `AiMailMessages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `gmailId` VARCHAR(64) NOT NULL,
  `fromAddr` VARCHAR(255) NOT NULL DEFAULT '',
  `subject` VARCHAR(255) NOT NULL DEFAULT '',
  `snippet` VARCHAR(255) NOT NULL DEFAULT '',
  `receivedAt` DATETIME NOT NULL,
  `analysis` TEXT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `seen` TINYINT(1) NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiMailMessages_gmailId` (`gmailId`),
  KEY `idx_AiMailMessages_seen` (`seen`, `receivedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
