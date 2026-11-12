<?php

// Периодично четене на Gmail: нови писма стават известия, а агентът от config ai.gmail.agent ги анализира.
// Пример за cron (на всеки 10 минути): */10 * * * * php /път/до/проекта/cronjobs/gmail-poll.php
$name = pathinfo(__FILE__, PATHINFO_FILENAME);

try {

    include __DIR__ . '/init.php';

    App::start($name);

    echo Bench::start($name);

    $result = AiAdmin\Service\AiServices::get()->mailPoller()->run();

    echo sprintf(
        'Намерени: %d, нови: %d, анализирани: %d, неуспешни: %d%s',
        $result['found'],
        $result['added'],
        $result['analyzed'],
        $result['failed'],
        $result['stopped'] !== null ? ', спряно: ' . $result['stopped'] : ''
    ) . NL;

    echo Bench::stop();

} catch (\Throwable $e) {

    echo 'ERROR: ' . $e->getMessage() . NL;
    exit(1);
}
