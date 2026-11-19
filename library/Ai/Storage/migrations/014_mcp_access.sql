-- Ai library, етап 14: MCP сървър (достъп за външни клиенти). Изпълнява се след 009.
-- Самият токен не се пази: само SHA-256 на него. Показва се веднъж при създаване.

CREATE TABLE IF NOT EXISTS `AiMcpTokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `tokenHash` CHAR(64) NOT NULL,
  `tokenPrefix` VARCHAR(16) NOT NULL,
  `tools` VARCHAR(255) NOT NULL,
  `documentIds` TEXT NULL,
  `agentCode` VARCHAR(40) NULL,
  `maskPii` TINYINT(1) NOT NULL DEFAULT 1,
  `rateLimitPerMinute` INT UNSIGNED NOT NULL DEFAULT 30,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `expires` DATETIME NULL,
  `lastUsed` DATETIME NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiMcpTokens_hash` (`tokenHash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiMcpCalls` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenId` INT UNSIGNED NOT NULL,
  `tool` VARCHAR(40) NOT NULL,
  `status` VARCHAR(20) NOT NULL,
  `durationMs` INT UNSIGNED NOT NULL DEFAULT 0,
  `responseChars` INT UNSIGNED NOT NULL DEFAULT 0,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiMcpCalls_token` (`tokenId`, `created`),
  CONSTRAINT `fk_AiMcpCalls_token` FOREIGN KEY (`tokenId`) REFERENCES `AiMcpTokens` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
