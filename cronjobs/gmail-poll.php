<?php

// Периодично четене на Gmail: нови писма стават известия, а агентът от config ai.gmail.agent ги анализира.
// Пример за cron (на всеки 10 минути): */10 * * * * php /път/до/проекта/cronjobs/gmail-poll.php
$name = pathinfo(__FILE__, PATHINFO_FILENAME);

try {

    include __DIR__ . '/init.php';

    App::start($name);

    echo Bench::start($name);

    $services = AiAdmin\Service\AiServices::get();

    // Показва какво се търси и в кой акаунт: най-честата причина за "0 намерени" е твърде тясна заявка.
    echo 'Акаунт: ' . (implode(', ', array_column($services->gmailAccounts()->all(), 'email')) ?: 'няма') . NL;
    echo 'Заявка: ' . config('ai.gmail.query', 'in:inbox newer_than:2d') . NL;

    $result = $services->mailPoller()->run();

    echo sprintf(
        'Намерени: %d, нови: %d, анализирани: %d, неуспешни: %d, предложени събития: %d%s',
        $result['found'],
        $result['added'],
        $result['analyzed'],
        $result['failed'],
        $result['proposed'],
        $result['stopped'] !== null ? ', спряно: ' . $result['stopped'] : ''
    ) . NL;

    echo Bench::stop();

} catch (\Throwable $e) {

    echo 'ERROR: ' . $e->getMessage() . NL;
    exit(1);
}
