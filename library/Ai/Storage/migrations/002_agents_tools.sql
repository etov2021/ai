-- Ai library, етап 3: агенти и инструменти. Изпълнява се след 001.

CREATE TABLE IF NOT EXISTS `AiTools` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NOT NULL,
  `type` VARCHAR(20) NOT NULL,
  `parameters` TEXT NOT NULL,
  `config` TEXT NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiTools_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiAgents` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT NULL,
  `systemPrompt` MEDIUMTEXT NOT NULL,
  `modelId` INT UNSIGNED NOT NULL,
  `temperature` DECIMAL(3,2) NULL,
  `maxTokens` INT UNSIGNED NULL,
  `maxSteps` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `timeoutSeconds` SMALLINT UNSIGNED NOT NULL DEFAULT 120,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiAgents_code` (`code`),
  CONSTRAINT `fk_AiAgents_model` FOREIGN KEY (`modelId`) REFERENCES `AiModels` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiAgentTools` (
  `agentId` INT UNSIGNED NOT NULL,
  `toolId` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`agentId`, `toolId`),
  CONSTRAINT `fk_AiAgentTools_agent` FOREIGN KEY (`agentId`) REFERENCES `AiAgents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_AiAgentTools_tool` FOREIGN KEY (`toolId`) REFERENCES `AiTools` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
