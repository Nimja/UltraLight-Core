<?php
namespace Core\Model\Tool\Order;
/**
 * Order item, for use in the order page.
 */
class Item
{
    /**
     * ID for the current item
     * @var int
     */
    public $id;
    /**
     * Name/label for the current item.
     * @var string
     */
    public $name;
    /**
     * Current sortable position in the array.
     * @var int
     */
    public $position;
    /**
     * If we should link from this item.
     * @var boolean
     */
    public $linkable = false;
    /**
     * If this item is sortable.
     * @var boolean
     */
    public $sortable = true;

    /**
     * Basic constructor.
     * @param int $id
     * @param string $name
     * @param boolean $linkable
     * @param boolean $sortable
     */
    public function __construct($id, $name, $linkable = false, $sortable = true)
    {
        $this->id = $id;
        $this->name = $name;
        $this->linkable = $linkable;
        $this->sortable = $sortable;
    }
}
