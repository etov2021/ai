-- Ai library, етап 6: работни потоци (workflows). Изпълнява се след 002.

CREATE TABLE IF NOT EXISTS `AiWorkflows` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `definition` MEDIUMTEXT NOT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiWorkflows_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
