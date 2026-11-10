-- Ai library, етап 5: лимити и бюджети. Изпълнява се след 003.
-- Суми в EUR. NULL = без ограничение, 0 = забранено.

CREATE TABLE IF NOT EXISTS `AiBudgets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope` VARCHAR(10) NOT NULL,
  `scopeKey` VARCHAR(40) NOT NULL DEFAULT '',
  `rateLimitPerMinute` INT UNSIGNED NULL,
  `dailyLimit` DECIMAL(12,4) NULL,
  `monthlyLimit` DECIMAL(12,4) NULL,
  `warnPercent` TINYINT UNSIGNED NOT NULL DEFAULT 80,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiBudgets_scope_key` (`scope`, `scopeKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiUsageDaily` (
  `day` DATE NOT NULL,
  `scope` VARCHAR(10) NOT NULL,
  `scopeKey` VARCHAR(40) NOT NULL DEFAULT '',
  `requests` INT UNSIGNED NOT NULL DEFAULT 0,
  `inputTokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `outputTokens` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `cost` DECIMAL(14,6) NOT NULL DEFAULT 0,
  PRIMARY KEY (`day`, `scope`, `scopeKey`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiRateLimit` (
  `bucket` VARCHAR(100) NOT NULL,
  `windowStart` INT UNSIGNED NOT NULL,
  `hits` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket`, `windowStart`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
