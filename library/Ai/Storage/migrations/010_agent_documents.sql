-- Ai library, етап 10: кои документи са достъпни за кой агент. Изпълнява се след 002 и 009.
-- Агент без нито един ред тук търси във всички документи.

CREATE TABLE IF NOT EXISTS `AiAgentDocuments` (
  `agentId` INT UNSIGNED NOT NULL,
  `documentId` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`agentId`, `documentId`),
  CONSTRAINT `fk_AiAgentDocuments_agent` FOREIGN KEY (`agentId`) REFERENCES `AiAgents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_AiAgentDocuments_doc` FOREIGN KEY (`documentId`) REFERENCES `AiDocuments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
