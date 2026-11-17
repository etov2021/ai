-- Ai library, етап 13: вектори (embeddings) на частите на документите за търсене по смисъл. Изпълнява се след 009.
-- Векторът е float32 (little-endian), кодиран в base64: така минава през текстовите параметри без загуба.
-- Смяната на модела не изисква изтриване: частите с друг модел се индексират наново.

CREATE TABLE IF NOT EXISTS `AiDocumentEmbeddings` (
  `chunkId` INT UNSIGNED NOT NULL,
  `model` VARCHAR(100) NOT NULL,
  `vector` TEXT CHARACTER SET ascii NOT NULL,
  PRIMARY KEY (`chunkId`),
  KEY `idx_AiDocumentEmbeddings_model` (`model`),
  CONSTRAINT `fk_AiDocumentEmbeddings_chunk` FOREIGN KEY (`chunkId`) REFERENCES `AiDocumentChunks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
