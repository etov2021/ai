-- Ai library, етап 2: доставчици и модели. Изпълнява се ръчно, веднъж.
-- Колоните са camelCase като в останалите таблици на проекта.

CREATE TABLE IF NOT EXISTS `AiProviders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `baseUrl` VARCHAR(255) NULL,
  `apiKeyEnc` TEXT NULL,
  `apiKeyHint` VARCHAR(16) NULL,
  `timeoutSeconds` SMALLINT UNSIGNED NOT NULL DEFAULT 60,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiProviders_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiModels` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `providerId` INT UNSIGNED NOT NULL,
  `model` VARCHAR(120) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `inputPricePerMillion` DECIMAL(12,6) NOT NULL DEFAULT 0,
  `outputPricePerMillion` DECIMAL(12,6) NOT NULL DEFAULT 0,
  `contextWindow` INT UNSIGNED NULL,
  `maxOutputTokens` INT UNSIGNED NULL,
  `supportsTools` TINYINT(1) NOT NULL DEFAULT 1,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiModels_provider_model` (`providerId`, `model`),
  CONSTRAINT `fk_AiModels_provider` FOREIGN KEY (`providerId`) REFERENCES `AiProviders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
