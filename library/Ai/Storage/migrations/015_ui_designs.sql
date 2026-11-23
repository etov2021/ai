-- Ai library, етап 15: UI дизайнер (HTML/CSS макети, генерирани от агент). Изпълнява се след 001.
-- Всяка промяна на макета е нова версия; старите се пазят за сравнение.

CREATE TABLE IF NOT EXISTS `AiUiDesigns` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL,
  `params` TEXT NOT NULL,
  `userId` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiUiDesignVersions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `designId` INT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `instruction` TEXT NOT NULL,
  `html` MEDIUMTEXT NOT NULL,
  `agentCode` VARCHAR(40) NOT NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiUiDesignVersions` (`designId`, `version`),
  CONSTRAINT `fk_AiUiDesignVersions_design` FOREIGN KEY (`designId`) REFERENCES `AiUiDesigns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
