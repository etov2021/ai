-- Ai library, етап 12: събития от писма. Изпълнява се след 008 и 011, веднъж.
-- source казва от кое писмо е предложението; calendarChecked/calendarAttempts - дали писмото е прегледано за събития.

ALTER TABLE `AiCalendarProposals`
  ADD COLUMN `source` VARCHAR(80) NULL AFTER `timezone`,
  ADD KEY `idx_AiCalendarProposals_source` (`source`);

ALTER TABLE `AiMailMessages`
  ADD COLUMN `calendarChecked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `attempts`,
  ADD COLUMN `calendarAttempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `calendarChecked`;
