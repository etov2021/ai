<?php

namespace Micro\Paginator\Adapter;

use Micro\Database\Database;
use Micro\Database\Select;
use Micro\Database\Expr;

class DatabaseSelect implements AdapterInterface
{
    /**
     * Name of the row count column
     *
     * @var string
     */
    const ROW_COUNT_COLUMN = 'micro_paginator_row_count';

    /**
     * The COUNT query
     *
     * @var \Micro\Database\Select
     */
    protected $_countSelect = \null;

    /**
     * Database query
     *
     * @var \Micro\Database\Select
     */
    protected $_select = \null;

    /**
     * Total item count
     *
     * @var integer
     */
    protected $_rowCount = \null;

    /**
     * Constructor.
     *
     * @param \Micro\Database\Select $select The select query
     */
    public function __construct(Select $select)
    {
        $this->_select = $select;
    }

    /**
     * @return \Micro\Database\Select
     */
    public function getSelect()
    {
        return $this->_select;
    }

    /**
     * Sets the total row count, either directly or through a supplied
     * query.  Without setting this, {@link getPages()} selects the count
     * as a subquery (SELECT COUNT ... FROM (SELECT ...)).  While this
     * yields an accurate count even with queries containing clauses like
     * LIMIT, it can be slow in some circumstances.  For example, in MySQL,
     * subqueries are generally slow when using the InnoDB storage engine.
     * Users are therefore encouraged to profile their queries to find
     * the solution that best meets their needs.
     *
     * @param  \Micro\Database\Select|integer $rowCount Total row count integer
     *                                                 or query
     * @return \Micro\Paginator\Adapter\DatabaseSelect $this
     * @throws \Exception
     */
    public function setRowCount($rowCount)
    {
        if ($rowCount instanceof Select) {
            $columns = $rowCount->getPart(Select::COLUMNS);

            $countColumnPart = empty($columns[0][2])
                               ? $columns[0][1]
                               : $columns[0][2];

            if ($countColumnPart instanceof Expr) {
                $countColumnPart = $countColumnPart->__toString();
            }

            $rowCountColumn = $this->_select->getAdapter()->foldCase(self::ROW_COUNT_COLUMN);

            // The select query can contain only one column, which should be the row count column
            if (\false === \strpos($countColumnPart, $rowCountColumn)) {
                throw new \Exception('Row count column not found');
            }

            $result = $rowCount->query(Database::FETCH_ASSOC)->fetch();

            $this->_rowCount = \count($result) > 0 ? $result[$rowCountColumn] : 0;
        } else if (\is_integer($rowCount)) {
            $this->_rowCount = $rowCount;
        } else {
            throw new \Exception('Invalid row count');
        }

        return $this;
    }

    /**
     * Returns an array of items for a page.
     *
     * @param  integer $offset Page offset
     * @param  integer $itemCountPerPage Number of items per page
     * @return array
     */
    public function getItems($offset = \null, $itemCountPerPage = \null)
    {
        $this->_select->limit($itemCountPerPage, $offset);

        return $this->_select->query()->fetchAll();
    }

    /**
     * Returns the total number of rows in the result set.
     *
     * @return integer
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        if ($this->_rowCount === \null) {
            $this->setRowCount(
                $this->getCountSelect()
            );
        }

        return $this->_rowCount;
    }

    /**
     * Get the COUNT select object for the provided query
     *
     * TODO: Have a look at queries that have both GROUP BY and DISTINCT specified.
     * In that use-case I'm expecting problems when either GROUP BY or DISTINCT
     * has one column.
     *
     * @return \Micro\Database\Select
     */
    public function getCountSelect()
    {
        /**
         * We only need to generate a COUNT query once. It will not change for
         * this instance.
         */
        if ($this->_countSelect !== \null) {
            return $this->_countSelect;
        }

        $rowCount = clone $this->_select;
        $rowCount->__toString();

        $db = $rowCount->getAdapter();

        $countColumn = $db->quoteIdentifier($db->foldCase(self::ROW_COUNT_COLUMN));
        $countPart   = 'COUNT(1) AS ';
        $groupPart   = \null;
        $unionParts  = $rowCount->getPart(Select::UNION);

        /**
         * If we're dealing with a UNION query, execute the UNION as a subquery
         * to the COUNT query.
         */
        if (!empty($unionParts)) {
            $expression = new Expr($countPart . $countColumn);

            $rowCount = $db->select()
                           ->bind($rowCount->getBind())
                           ->from($rowCount, $expression);
        } else {
            $columnParts = $rowCount->getPart(Select::COLUMNS);
            $groupParts  = $rowCount->getPart(Select::GROUP);
            $havingParts = $rowCount->getPart(Select::HAVING);
            $isDistinct  = $rowCount->getPart(Select::DISTINCT);

            /**
             * If there is more than one column AND it's a DISTINCT query, more
             * than one group, or if the query has a HAVING clause, then take
             * the original query and use it as a subquery os the COUNT query.
             */
            if (($isDistinct && ((\count($columnParts) == 1 && $columnParts[0][1] == Select::SQL_WILDCARD)
                 || \count($columnParts) > 1)) || \count($groupParts) > 1 || !empty($havingParts)) {
                $rowCount->reset(Select::ORDER);
                $rowCount = $db->select()
                               ->bind($rowCount->getBind())
                               ->from($rowCount);
            } else if ($isDistinct) {
                $part = $columnParts[0];

                if ($part[1] !== Select::SQL_WILDCARD && !($part[1] instanceof Expr)) {
                    $column = $db->quoteIdentifier($part[1], \true);

                    if (!empty($part[0])) {
                        $column = $db->quoteIdentifier($part[0], \true) . '.' . $column;
                    }

                    $groupPart = $column;
                }
            } else if (!empty($groupParts)) {
                $groupPart = $db->quoteIdentifier($groupParts[0], \true);
            }

            /**
             * If the original query had a GROUP BY or a DISTINCT part and only
             * one column was specified, create a COUNT(DISTINCT ) query instead
             * of a regular COUNT query.
             */
            if (!empty($groupPart)) {
                $countPart = 'COUNT(DISTINCT ' . $groupPart . ') AS ';
            }

            /**
             * Create the COUNT part of the query
             */
            $expression = new Expr($countPart . $countColumn);

            $rowCount->reset(Select::COLUMNS)
                     ->reset(Select::ORDER)
                     ->reset(Select::LIMIT_OFFSET)
                     ->reset(Select::GROUP)
                     ->reset(Select::DISTINCT)
                     ->reset(Select::HAVING)
                     ->columns($expression);
        }

        $this->_countSelect = $rowCount;

        return $rowCount;
    }
}