<?php namespace Core\Model\Tool;
/**
 * Helper entity for page model, but can be used more broad.
 *
 * @author Nimja
 */
class Order
{
    /**
     * The class we use.
     * @var string
     */
    protected $_entityClass;
    /**
     * The order link.
     * @var string
     */
    protected $_orderLink;
    /**
     * The item we're currently viewing children of.
     * @var int
     */
    protected $_currentId;

    /**
     * Max position for the items.
     * @var int
     */
    protected $_max;

    /**
     * Reverse lookup by positions for sortable items.
     * @var Order\Item[]
     */
    protected $_sortableItems;

    /**
     * Basic constructor.
     * @param string $class
     * @param string $orderLink
     * @param int $currentId
     */
    public function __construct($class, $orderLink, $currentId = null)
    {
        $this->_entityClass = $class;
        $this->_orderLink = $orderLink;
        $this->_currentId = $currentId;
    }

    /**
     * Get items of current page.
     * @return Order\Item[]
     */
    public function getItems()
    {
        $class = $this->_entityClass;
        $items = $class::getOrdered($this->_currentId);
        if (empty($items) && $this->_currentId > 0) {
            \Request::redirect($this->_orderLink);
        }
        $index = 0;
        $this->_idPositions = [];
        foreach ($items as $item) {
            if ($item->sortable) {
                $item->position = $index;
                $this->_sortableItems[$item->id] = $item;
                $index++;
            }
        }
        $this->_max = $index - 1;
        return $items;
    }

    /**
     * Get table output, with current children.
     * @return string
     */
    public function getOutput()
    {
        $items = $this->getItems();
        if ($this->_isMoveNeeded()) {
            \Request::redirect($this->_getOrderUrl($this->_currentId));
        }
        $result = [
            '<table class="table table-striped table-bordered table-condensed">',
            '<tr><th>Title</th><th>Up &uArr;</th><th>Down &dArr;</th></tr>',
        ];
        $position = 0;
        foreach ($items as $item) {
            $up = $item->sortable && ($position > 0);
            $down = $item->sortable && ($position < $this->_max);
            $result[] = $this->_makeRow($position, $item->id, $item->name, $up, $down, $item->linkable);
            if ($item->sortable) {
                $position++;
            }
        }
        $result[] = '</table>';
        return implode(PHP_EOL, $result);
    }

    /**
     * Check if we are moving an item.
     * @return true If moved.
     */
    private function _isMoveNeeded()
    {
        $move = \Request::value('move');
        if (empty($move) || strpos($move, '-') === false) {
            return false;
        }
        list($id, $position) = explode('-', $move);
        $item = getKey($this->_sortableItems, $id);
        // Move we cannot do.
        if (empty($item) || $item->position == $position || $position > $this->_max || $position < 0) {
            return false;
        }
        $sortables = array_values($this->_sortableItems);
        array_splice($sortables, $item->position, 1);
        array_splice($sortables, $position, 0, [$item]);
        return $this->_updatePositions($sortables);
    }

    /**
     * Update positions for ids, in the order they are supplied.
     * @param Order\Item[] $items
     * @return boolean
     */
    private function _updatePositions($items)
    {
        $class = $this->_entityClass;
        $position = 1;
        $isUpdated = false;
        foreach ($items as $item) {
            $model = $class::load($item->id);
            $updated = $model->setPosition($position);
            $isUpdated = $isUpdated || $updated;
            $position++;
        }
        if ($isUpdated && method_exists($class, 'clearCache')) {
            $class::clearCache();
        }
        return $isUpdated;
    }

    /**
     * Make row.
     * @param int $position
     * @param int $id
     * @param string $title
     * @param boolean $up
     * @param boolean $down
     * @param boolean $hasLink
     */
    private function _makeRow($position, $id, $title, $up = false, $down = false, $hasLink = true)
    {
        $mainLink = $hasLink ? $this->_makeLink($id, $title) : $this->_fakeLink($title);
        $upLink = !$up ? '' : $this->_makeLink($this->_currentId, '&uArr;', $id . '-' . ($position - 1));
        $downLink = !$down ? '' : $this->_makeLink($this->_currentId, '&dArr;', $id . '-' . ($position + 1));
        return "<tr><td>{$mainLink}</td><td>{$upLink}</td><td>{$downLink}</td></tr>";
    }

    /**
     * Make link to move or go to parent.
     * @param int $id
     * @param string $title
     * @param int $move
     * @return string
     */
    private function _makeLink($id, $title, $moveData = null)
    {
        $url = $this->_getOrderUrl($id);
        $class = 'btn btn-clean';
        if ($moveData) {
            $url .= '?move=' . urlencode($moveData);
            $class .= ' btn-block';
        }
        return "<a href=\"{$url}\" class=\"{$class}\">{$title}</a>";
    }

    /**
     * Fake link, for items without children.
     * @param string $title
     * @return string
     */
    private function _fakeLink($title)
    {
        return "<span class=\"btn btn-clean text-muted\">{$title}</span>";
    }

    /**
     * Get order url, with id if needed.
     * @param int $id
     * @return string
     */
    private function _getOrderUrl($id)
    {
        $url = $this->_orderLink;
        if ($id > 0) {
            $url .= '/' . $id;
        }
        return $url;
    }
}
