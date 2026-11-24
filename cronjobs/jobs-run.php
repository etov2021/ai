<?php

// Фонов работник: пуска настъпилите автоматизации и изпълнява чакащите задачи (AI → Задачи, AI → Автоматизации).
// Пример за cron (на всяка минута): * * * * * php /път/до/проекта/cronjobs/jobs-run.php
$name = pathinfo(__FILE__, PATHINFO_FILENAME);

try {

    include __DIR__ . '/init.php';

    App::start($name);

    echo Bench::start($name);

    $services = AiAdmin\Service\AiServices::get();

    // Две пускания в една минута не си пречат: задачите и разписанията се вземат атомарно в базата.
    $created = $services->automationScheduler()->tick();

    $stats = $services->jobWorker()->run(
        (int) config('ai.jobs.worker_max_jobs', 20),
        (int) config('ai.jobs.worker_seconds', 50)
    );

    // От време на време се чистят старите приключили задачи.
    if (random_int(1, 100) === 1) {
        $services->jobs()->purge((int) config('ai.jobs.retention_days', 30));
    }

    echo sprintf(
        'Нови от разписания: %d, изпълнени: %d, успешни: %d, за повторен опит: %d, неуспешни: %d',
        $created,
        $stats['ran'],
        $stats['done'],
        $stats['retried'],
        $stats['failed']
    ) . NL;

    echo Bench::stop();

} catch (\Throwable $e) {

    echo 'ERROR: ' . $e->getMessage() . NL;
    exit(1);
}
