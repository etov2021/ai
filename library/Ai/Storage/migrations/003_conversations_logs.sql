-- Ai library, етап 4: разговори и логове. Изпълнява се след 002.

CREATE TABLE IF NOT EXISTS `AiConversations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `agentId` INT UNSIGNED NOT NULL,
  `userId` INT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL DEFAULT '',
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiConversations_user` (`userId`, `updated`),
  CONSTRAINT `fk_AiConversations_agent` FOREIGN KEY (`agentId`) REFERENCES `AiAgents` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiMessages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversationId` INT UNSIGNED NOT NULL,
  `seq` INT UNSIGNED NOT NULL,
  `role` VARCHAR(12) NOT NULL,
  `content` MEDIUMTEXT NULL,
  `toolCalls` MEDIUMTEXT NULL,
  `toolCallId` VARCHAR(100) NULL,
  `name` VARCHAR(64) NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiMessages_conversation_seq` (`conversationId`, `seq`),
  CONSTRAINT `fk_AiMessages_conversation` FOREIGN KEY (`conversationId`) REFERENCES `AiConversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Одитен лог без външни ключове: записите се пазят и след изтриване на агент или разговор.
-- kind: llm (заявка към модела), tool (инструмент), run (край на изпълнението, без токени и цена).
CREATE TABLE IF NOT EXISTS `AiRequestLog` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `runId` CHAR(32) NOT NULL,
  `kind` VARCHAR(8) NOT NULL,
  `conversationId` INT UNSIGNED NULL,
  `agentId` INT UNSIGNED NULL,
  `userId` INT UNSIGNED NULL,
  `providerCode` VARCHAR(40) NOT NULL DEFAULT '',
  `model` VARCHAR(120) NOT NULL DEFAULT '',
  `step` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `tool` VARCHAR(64) NULL,
  `status` VARCHAR(20) NOT NULL,
  `finishReason` VARCHAR(20) NULL,
  `inputTokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `outputTokens` INT UNSIGNED NOT NULL DEFAULT 0,
  `cost` DECIMAL(12,6) NOT NULL DEFAULT 0,
  `durationMs` INT UNSIGNED NOT NULL DEFAULT 0,
  `error` VARCHAR(500) NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiRequestLog_run` (`runId`),
  KEY `idx_AiRequestLog_created` (`created`),
  KEY `idx_AiRequestLog_agent` (`agentId`, `created`),
  KEY `idx_AiRequestLog_user` (`userId`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
