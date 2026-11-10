<?php

namespace Log\Controller\Admin;

use Log\Model\Log as ModelLog;
use Micro\Application\Controller;
use Micro\Acl\AuthorizeInterface;
use Micro\Grid\Grid;
use Micro\Application\Controller\FiltersTrait;
use Micro\Http\Response;
use Micro\Form\Form;

class Index extends Controller implements AuthorizeInterface
{
    use FiltersTrait;

    public $scope = 'admin';

    public function indexAction()
    {
        list($orderField, $orderDir) = explode('_', $this->request->getParam('sort', 'id_desc'));

        if (($filters = $this->handleFilters()) instanceof Response) {
            return $filters;
        }

        $table  = new ModelLog();
        $select = $table->createHybridSelect();
        $select->order("{$orderField} {$orderDir}");
        $select->columns(['creatorName' => 'creator']);

        $table->applyFilters($select, $filters);

        $grid = new Grid($select, __DIR__ . '/../../Resources/grids/admin/index.php');

        if (($column = $grid->getColumn($orderField)) !== null) {
            $column->setSorted($orderDir);
        }

        $grid->setIppRanger(false);
        $grid->setIpp(50);
        $grid->setPageNumber(max(1, (int) $this->request->getParam('page')));

        $form = new Form(__DIR__ . '/../../Resources/forms/admin/index-filters.php');
        $form->populate($filters);

        $this->prepareGrid($grid);

        return [
            'grid' => $grid,
            'form' => $form
        ];
    }

    protected function prepareGrid(Grid $grid)
    {
        $ids = [];
        foreach ($grid->getPaginator() as $item) {
            $ids[] = (int) $item['creator'];
        }
        if (!empty($ids)) {
            $pairs = $this->container->get('db')->fetchPairs('SELECT id, CONCAT(Users.username, " (", Users.email, ")") FROM Users WHERE id IN (' . implode(',', array_unique($ids)) . ')');
            foreach ($grid->getPaginator() as $item) {
                $item['creatorName'] = isset($pairs[$item['creator']]) ? $pairs[$item['creator']] : $item['creator'];
            }
        }
    }
}