<?php

namespace Micro\Paginator;

use Micro\Paginator\Adapter\AdapterInterface;
use Micro\Paginator\Adapter\AdapterArray;
use Micro\Paginator\Adapter\DatabaseTableSelect;
use Micro\Paginator\Adapter\DatabaseSelect;

use Micro\Database\Select as Select;
use Micro\Database\Table\Select as TableSelect;

class Paginator implements \Countable, \IteratorAggregate
{
	/**
	 * @var \Micro\Paginator\Adapter\AdapterInterface | array
	 */
    protected $model;

    /**
     * @var \ArrayIterator
     */
    protected $currentItems;

    /**
     * @var int
     */
    protected $pageCount;

    /**
     * @var int|null
     */
    protected $ipp = \null;

    /**
     * @var int
     */
    protected $pageNumber = 1;

    /**
     * @var int
     */
    protected $pageRange = 5;

    /**
     * @var int
     */
    protected $totalItemCount;

    /**
     * @var \Closure
     */
    protected $onSlidingPages;

    protected $onGetItems;
    protected $onCount;

    public function __construct($model)
    {
        if ($model instanceof AdapterInterface) {
            $this->model = $model;
        } else if (is_array($model)) {
            $this->model = new AdapterArray($model);
        } else if ($model instanceof TableSelect) {
            $this->model = new DatabaseTableSelect($model);
        } else if ($model instanceof Select) {
            $this->model = new DatabaseSelect($model);
        } else {
            $type = is_object($model) ? get_class($model) : gettype($model);
            throw new \Exception('No adapter for type ' . $type);
        }
    }

    /**
     * @return \Micro\Paginator\Adapter\AdapterInterface
     */
    public function getModel()
    {
        return $this->model;
    }

	/**
	 * (non-PHPdoc)
	 * @see IteratorAggregate::getIterator()
	 */
    #[\ReturnTypeWillChange]
    public function getIterator ()
    {
        if ($this->currentItems === \null) {

            $offset = ($this->normalizePageNumber($this->getPageNumber()) - 1) * (int) $this->getIpp();

            if ($this->onGetItems) {
                $items = call_user_func($this->onGetItems, $this->model, $offset, $this->getIpp());
            } else {
                $items = $this->model->getItems($offset, $this->getIpp());
            }

            if (!$items instanceof \Traversable) {
                $items = new \ArrayIterator($items);
            }

            $this->currentItems = $items;

        }

        return $this->currentItems;
    }

	/**
	 * (non-PHPdoc)
	 * @see Countable::count()
	 */
    #[\ReturnTypeWillChange]
    public function count ()
    {
        if ($this->pageCount === \null) {
        	$this->pageCount = (int) $this->getIpp() > 0 ? ceil($this->getTotalItemCount() / (int) $this->getIpp()) : 0;
        }

        return $this->pageCount;
    }

    /**
     * @return number
     */
    public function getTotalItemCount()
    {
    	if ($this->totalItemCount === \null) {
    	    if ($this->onCount) {
                $this->totalItemCount = call_user_func($this->onCount, $this->model);
    	    } else {
                $this->totalItemCount = $this->model->count();
    	    }
    	}

    	return $this->totalItemCount;
    }

	/**
     * @return int
     */
    public function getIpp ()
    {
        return $this->ipp;
    }

	/**
	 * @param int $ipp
	 * @return \Micro\Paginator\Paginator
	 */
    public function setIpp ($ipp)
    {
        $this->ipp = $ipp;

        return $this;
    }

	/**
     * @return int
     */
    public function getPageNumber ()
    {
        return $this->normalizePageNumber($this->pageNumber);
    }

	/**
	 * @param int $pageNumber
	 * @return \Micro\Paginator\Paginator
	 */
    public function setPageNumber ($pageNumber)
    {
        $this->pageNumber = $pageNumber;

        return $this;
    }

    /**
	 * @return int
	 */
	public function getPageRange()
	{
		return $this->pageRange;
	}

	/**
	 * @param int $pageRange
	 * @return \Micro\Paginator\Paginator
	 */
	public function setPageRange($pageRange)
	{
		$this->pageRange = $pageRange;

		return $this;
	}

	/**
     * @return \stdClass
     */
    public function getPages()
    {
        $pageCount         = $this->count();
        $currentPageNumber = $this->getPageNumber();

        $pages = new \stdClass();
        $pages->pageCount        = $pageCount;
        $pages->itemCountPerPage = $this->getIpp();
        $pages->first            = 1;
        $pages->current          = $currentPageNumber;
        $pages->last             = $pageCount;

        // Previous and next
        if ($currentPageNumber - 1 > 0) {
            $pages->previous = $currentPageNumber - 1;
        }

        if ($currentPageNumber + 1 <= $pageCount) {
            $pages->next = $currentPageNumber + 1;
        }

        // Pages in range
        $onSlidingPages = $this->onSlidingPages;
        $pages->pagesInRange     = $onSlidingPages instanceof \Closure ? $onSlidingPages($this) : $this->slidingPages();
        $pages->firstPageInRange = min($pages->pagesInRange);
        $pages->lastPageInRange  = max($pages->pagesInRange);
        $pages->totalItemCount   = $this->getTotalItemCount();

        if ($this->currentItems !== null) {
            $pages->currentItemCount = count($this->currentItems);
            $pages->itemCountPerPage = $this->getIpp();
            $pages->firstItemNumber  = (($currentPageNumber - 1) * (int) $this->getIpp()) + 1;
            $pages->lastItemNumber   = $pages->firstItemNumber + $pages->currentItemCount - 1;
        }

        return $pages;
    }

    /**
     * @return array
     */
    protected function slidingPages()
    {
        $pageRange  = $this->getPageRange();
        $pageNumber = $this->getPageNumber();
        $pageCount  = $this->count();

        if ($pageRange > $pageCount) {
            $pageRange = $pageCount;
        }

        $delta = ceil($pageRange / 2);

        if ($pageNumber - $delta > $pageCount - $pageRange) {
            $lowerBound = $pageCount - $pageRange + 1;
            $upperBound = $pageCount;
        } else {
            if ($pageNumber - $delta < 0) {
                $delta = $pageNumber;
            }
            $offset     = $pageNumber - $delta;
            $lowerBound = $offset + 1;
            $upperBound = $offset + $pageRange;
        }

        $lowerBound = $this->normalizePageNumber($lowerBound);
        $upperBound = $this->normalizePageNumber($upperBound);

        $pages = [];

        for ($pageNumber = $lowerBound; $pageNumber <= $upperBound; $pageNumber++) {
            $pages[$pageNumber] = $pageNumber;
        }

        return $pages;
    }

    /**
     * Returns a subset of pages within a given range.
     *
     * @param  integer $lowerBound Lower bound of the range
     * @param  integer $upperBound Upper bound of the range
     * @return array
     */
    public function getPagesInRange($lowerBound, $upperBound)
    {
        $lowerBound = $this->normalizePageNumber($lowerBound);
        $upperBound = $this->normalizePageNumber($upperBound);

        $pages = array();

        for ($pageNumber = $lowerBound; $pageNumber <= $upperBound; $pageNumber++) {
            $pages[$pageNumber] = $pageNumber;
        }

        return $pages;
    }

    /**
     * @param int $pageNumber
     * @return number
     */
    public function normalizePageNumber($pageNumber)
    {
        if (is_object($pageNumber)) {
            $pageNumber = 0;
        }

        $pageNumber = (int) $pageNumber;

        if ($pageNumber < 1) {
            $pageNumber = 1;
        }

        $pageCount = $this->count();

        if ($pageCount > 0 && $pageNumber > $pageCount) {
            $pageNumber = $pageCount;
        }

        return $pageNumber;
    }

    /**
     * @param \Closure|null $callback
     */
    public function setOnSlidingPages(?\Closure $callback = \null)
    {
        $this->onSlidingPages = $callback;
    }

    /**
     * @param \Closure|null $callback
     */
    public function setOnGetItems(?\Closure $callback = \null)
    {
        $this->onGetItems = $callback;
    }

    /**
     * @param \Closure|null $callback
     */
    public function setOnCount(?\Closure $callback = \null)
    {
        $this->onCount = $callback;
    }
}