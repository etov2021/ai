<?php

namespace Nomenclatures\Controller\Admin;

use Nomenclatures\Model\StatusInner as Model;
use Micro\Application\Controller\Admin;
use Micro\Http\Response;

class StatusInner extends Admin
{
    protected $model = Model::class;
    
    public function indexAction()
    {        
        $response = parent::indexAction();
        
        if (!$response instanceof Response) {
            $this->view->grid->setIpp(null);
            $this->view->grid->setIppRanger(false);
            $this->getModel()->resetOrder()->addOrder('order', 'asc');
            foreach ($this->view->grid->getColumns() as $column) {
                $column->setSortable(0);
                if ($column->getName() === 'idss') {
                    $column->setViewScript('admin::_grid-sort');
                    $column->setTitle('');
                    $column->setClass('text-center');
                }
            }
        } else {
            return $response;
        }
    }
    
    public function ajaxReorderAction()
    {
        $sort = $this->request->getPost('sort', []);
        
        if (is_array($sort)) {
            $table = $this->getModel()->getTable();
            $table->getAdapter()->beginTransaction();
            foreach ($sort as $k => $id) {
                $table->update(
                    ['order' => ($k + 1)],
                    ['id = ?' => $id]
                    );
            }
            $this->getModel()->removeCache();
            $table->getAdapter()->commit();
        }
        
        return '';
    }
}