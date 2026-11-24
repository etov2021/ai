-- Ai library, етап 17: връщане на последната промяна на шаблон, направена с промпт. Изпълнява се след 016 (еднократно).
-- Пази се предишният HTML на шаблона; следващата промяна го заменя.

ALTER TABLE `AiUiTemplates` ADD COLUMN `previousHtml` MEDIUMTEXT NULL AFTER `html`;
