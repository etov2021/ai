<?php

namespace Micro\Session\SaveHandler;

use SessionHandlerInterface;
use Micro\Database\Table\Table as TableDatabase;
use Micro\Database\Expr;

class Table implements SessionHandlerInterface
{
    protected $config;
    protected $table;
    protected $sessionSavePath;
    protected $sessionName;
    protected $lifetime;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->table = new TableDatabase([
            'name' => (isset($config['table']) ? $config['table'] : 'UsersSessions')
        ]);
    }

    #[\ReturnTypeWillChange]
    public function close()
    {
        return true;
    }

    #[\ReturnTypeWillChange]
    public function destroy($session_id)
    {
        return (bool) $this->table->delete([
            'id = ?'   => $session_id,
            'name = ?' => $this->sessionName,
        ]);
    }

    #[\ReturnTypeWillChange]
    public function gc($maxlifetime)
    {
        $adapter = $this->table->getAdapter();

        return (bool) $this->table->delete(
            sprintf(
                '%s < %s',
                $adapter->quoteIdentifier('modified'),
                new Expr('(UNIX_TIMESTAMP() - ' . $adapter->quoteIdentifier('lifetime') . ')')
            )
        );
    }

    #[\ReturnTypeWillChange]
    public function open($save_path, $name)
    {
        $this->sessionSavePath = $save_path;
        $this->sessionName     = $name;
        $this->lifetime        = isset($this->config['gc_maxlifetime']) ? $this->config['gc_maxlifetime'] : \ini_get('session.gc_maxlifetime');

        return true;
    }

    #[\ReturnTypeWillChange]
    public function read($session_id)
    {
        $row = $this->table->find($session_id, $this->sessionName)->current();

        if ($row !== \null) {
            if (($row->modified + $row->lifetime) > \time()) {
                return $row->data;
            }
            $this->destroy($session_id);
        }

        return '';
    }

    #[\ReturnTypeWillChange]
    public function write($session_id, $session_data)
    {
        $session_data = [
            'modified' => new Expr('UNIX_TIMESTAMP()'),
            'data'     => (string) $session_data,
        ];

        $row = $this->table->find($session_id, $this->sessionName)->current();

        if ($row !== \null) {
            return (bool) $this->table->update($session_data, [
                'id = ?'   => $session_id,
                'name = ?' => $this->sessionName,
            ]);
        }

        $session_data['id']       = $session_id;
        $session_data['name']     = $this->sessionName;
        $session_data['lifetime'] = $this->lifetime;

        return (bool) $this->table->insert($session_data);
    }
}