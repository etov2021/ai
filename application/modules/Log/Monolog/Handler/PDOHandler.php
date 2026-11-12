<?php

namespace Log\Monolog\Handler;

use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Handler\AbstractProcessingHandler;
use Micro\Auth\Auth;
use Micro\Container\SharedContainer;
use Micro\Router\Route;

class PDOHandler extends AbstractProcessingHandler
{
    private $initialized = false;
    private $pdo;
    private $statement;

    public function __construct(\PDO $pdo, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        $this->pdo = $pdo;

        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $request = SharedContainer::getInstance()->get('request');
        $route = $request->getParam(Route::class);

        try {
            $identity = Auth::identity();
        } catch (\Exception $e) {
            $identity = null;
        }

        $this->statement->execute(array(
            'channel' => $record->channel,
            'level'   => $record->level->value,
            'message' => !empty($record->message) ? $record->message : null,
            'module'  => $request->getParam('module'),
            'controller' => $request->getParam('controller'),
            'action'  => $request->getParam('action'),
            'params'  => json_encode(['route' => ($route ? $route->getName() : null), 'params' => ($route ? $route->getParams() : [])]),
            'context' => json_encode($record->context),
            'time'    => $record->datetime->format('U'),
            'creator' => $identity ? $identity->getId() : null,
        ));
    }

    private function initialize()
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS Log (
                id BIGINT UNSIGNED AUTO_INCREMENT,
                channel VARCHAR(255),
                level INTEGER,
                module VARCHAR(100),
                controller VARCHAR(100),
                action VARCHAR(100),
                params LONGTEXT,
                message LONGTEXT,
                context LONGTEXT,
                time INTEGER UNSIGNED,
                creator INTEGER UNSIGNED NULL,
                PRIMARY KEY (id)
            )
        ');

        $this->statement = $this->pdo->prepare('
            INSERT INTO Log (
                channel,
                level,
                module,
                controller,
                action,
                params,
                message,
                context,
                time,
                creator
            ) VALUES (
                :channel,
                :level,
                :module,
                :controller,
                :action,
                :params,
                :message,
                :context,
                :time,
                :creator
            )
        ');

        $this->initialized = true;
    }
}