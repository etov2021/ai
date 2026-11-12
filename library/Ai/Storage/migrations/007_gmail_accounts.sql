-- Ai library, етап 7: свързан Gmail акаунт (само четене). Изпълнява се след 001.

CREATE TABLE IF NOT EXISTS `AiGmailAccounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `refreshTokenEnc` TEXT NOT NULL,
  `scope` VARCHAR(255) NOT NULL,
  `created` DATETIME NOT NULL,
  `updated` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiGmailAccounts_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
