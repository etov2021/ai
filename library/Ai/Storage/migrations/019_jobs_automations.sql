-- Ai library, етап 19: опашка за фонови задачи и планирани автоматизации. Изпълнява се след 001.
-- AiJobs: една задача = тип + данни (JSON). Работникът (cronjobs/jobs-run.php) взима по една, при грешка опитва пак до maxAttempts.
-- AiAutomations: "агент или работен поток + вход + разписание"; планировчикът слага задача в AiJobs, когато му дойде времето.

CREATE TABLE IF NOT EXISTS `AiJobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` VARCHAR(30) NOT NULL,
  `payload` TEXT NULL,
  `status` VARCHAR(10) NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `maxAttempts` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `runAfter` DATETIME NOT NULL,
  `lockedUntil` DATETIME NULL,
  `userId` INT UNSIGNED NULL,
  `automationId` INT UNSIGNED NULL,
  `result` TEXT NULL,
  `error` VARCHAR(500) NULL,
  `created` DATETIME NOT NULL,
  `started` DATETIME NULL,
  `finished` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiJobs_queue` (`status`, `runAfter`),
  KEY `idx_AiJobs_automation` (`automationId`, `status`),
  KEY `idx_AiJobs_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiAutomations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `kind` VARCHAR(10) NOT NULL,
  `target` VARCHAR(40) NOT NULL,
  `input` TEXT NOT NULL,
  `scheduleType` VARCHAR(10) NOT NULL,
  `intervalMinutes` INT UNSIGNED NULL,
  `scheduleTime` CHAR(5) NULL,
  `weekdays` VARCHAR(13) NULL,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `nextRun` DATETIME NULL,
  `lastRun` DATETIME NULL,
  `lastJobId` BIGINT UNSIGNED NULL,
  `createdBy` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiAutomations_due` (`active`, `nextRun`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
