-- Ai library, етап: ескалация към по-мощен модел. Изпълнява се след 004, веднъж.
-- Евтиният модел от AiAgents.modelId отговаря първи; escalationModelId се ползва само при нужда.

ALTER TABLE `AiAgents`
  ADD COLUMN `escalationModelId` INT UNSIGNED NULL AFTER `modelId`,
  ADD CONSTRAINT `fk_AiAgents_escalationModel` FOREIGN KEY (`escalationModelId`) REFERENCES `AiModels` (`id`) ON DELETE RESTRICT;
