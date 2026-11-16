-- Ai library, етап 9: библиотека с документи (база знания за търсене от агентите). Изпълнява се след 001.
-- Съхранява се само извлеченият текст на части; оригиналният файл не се пази.

CREATE TABLE IF NOT EXISTS `AiDocuments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `fileName` VARCHAR(255) NOT NULL,
  `extension` VARCHAR(10) NOT NULL,
  `size` INT UNSIGNED NOT NULL,
  `chars` INT UNSIGNED NOT NULL,
  `chunkCount` INT UNSIGNED NOT NULL DEFAULT 0,
  `hash` CHAR(40) NOT NULL,
  `userId` INT UNSIGNED NULL,
  `created` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_AiDocuments_hash` (`hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `AiDocumentChunks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `documentId` INT UNSIGNED NOT NULL,
  `position` INT UNSIGNED NOT NULL,
  `content` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_AiDocumentChunks_doc` (`documentId`, `position`),
  FULLTEXT KEY `ft_AiDocumentChunks_content` (`content`),
  CONSTRAINT `fk_AiDocumentChunks_doc` FOREIGN KEY (`documentId`) REFERENCES `AiDocuments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
