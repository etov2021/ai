-- Ai library, етап 16: библиотека с шаблони за UI дизайнера. Изпълнява се след 015.
-- Файловете на пакетите (CSS, JS, картинки) са в data/design/templates/<id>/, извън public.

CREATE TABLE IF NOT EXISTS `AiUiTemplates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `tags` VARCHAR(200) NOT NULL DEFAULT '',
  `source` VARCHAR(10) NOT NULL,
  `sourceUrl` VARCHAR(500) NULL,
  `entry` VARCHAR(255) NULL,
  `html` MEDIUMTEXT NULL,
  `summary` TEXT NOT NULL,
  `hasPackage` TINYINT(1) NOT NULL DEFAULT 0,
  `userId` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
