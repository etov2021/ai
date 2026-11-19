<?php

// Създава вектори за търсене по смисъл за частите на документите, които още нямат такива (виж config ai.knowledge.embeddings).
// Пример за cron (на всеки 10 минути): */10 * * * * php /път/до/проекта/cronjobs/embed-documents.php
$name = pathinfo(__FILE__, PATHINFO_FILENAME);

try {

    include __DIR__ . '/init.php';

    App::start($name);

    echo Bench::start($name);

    $indexer = AiAdmin\Service\AiServices::get()->documentIndexer();

    if ($indexer === null) {
        echo 'Търсенето по смисъл не е настроено (config ai.knowledge.embeddings.provider).' . NL;
    } else {
        $result = $indexer->run(2000);

        echo sprintf('Индексирани части: %d, оставащи: %d%s', $result['indexed'], $result['remaining'], $result['error'] !== null ? ', спряно: ' . $result['error'] : '') . NL;
    }

    echo Bench::stop();

} catch (\Throwable $e) {

    echo 'ERROR: ' . $e->getMessage() . NL;
    exit(1);
}
