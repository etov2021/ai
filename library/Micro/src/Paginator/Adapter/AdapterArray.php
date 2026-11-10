<?php

namespace Micro\Paginator\Adapter;

class AdapterArray implements AdapterInterface
{
    /**
     * Array
     *
     * @var array
     */
    protected $array = \null;

    /**
     * Item count
     *
     * @var integer
     */
    protected $count = \null;

    /**
     * Constructor.
     *
     * @param array $array Array to paginate
     */
    public function __construct(array $array)
    {
        $this->array = $array;
        $this->count = \count($array);
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
        $offset = (int) $offset;
        $itemCountPerPage = (int) $itemCountPerPage;

        if ($itemCountPerPage === 0) {
            $itemCountPerPage = $this->count;
        } else if ($itemCountPerPage > $this->count) {
            $itemCountPerPage = $this->count;
        }

        return \array_slice($this->array, $offset, $itemCountPerPage);
    }

    /**
     * Returns the total number of rows in the array.
     *
     * @return integer
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return $this->count;
    }
}