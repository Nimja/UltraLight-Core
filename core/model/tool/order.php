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
     * Which ID field we are using, defaults to parentId.
     * @var string
     */
    protected $_fieldId;
    /**
     * Which title field we are using, defaults to title.
     * @var string
     */
    protected $_fieldTitle;
    /**
     * The item we're currently viewing children of.
     * @var int
     */
    protected $_currentId;

    /**
     * Basic constructor.
     * @param string $class
     * @param string $orderLink
     * @param string $fieldId
     * @param string $fieldTitle
     */
    public function __construct($class, $orderLink, $fieldId = 'parentId', $fieldTitle = 'title')
    {
        $this->_entityClass = $class;
        $this->_orderLink = $orderLink;
        $this->_fieldId = $fieldId;
        $this->_fieldTitle = $fieldTitle;
        $this->_currentId = intval(\Core::$rest);
    }

    /**
     * Get current parent item.
     * @return \Core\Model
     */
    public function getParent()
    {
        $class = $this->_entityClass;
        return $class::load($this->_currentId);
    }

    /**
     * Get items of current page.
     * @return type
     */
    public function getItems()
    {
        $class = $this->_entityClass;
        $items = $class::getChildren($this->_currentId);
        if (empty($items)) {
            \Request::redirect($this->_orderLink);
        }
        return $items;
    }

    /**
     * Get table output, with current children.
     * @return string
     */
    public function getOutput()
    {
        $items = $this->getItems();
        if ($this->_checkMove($items)) {
            \Request::redirect($this->_getOrderUrl($this->_currentId));
        }
        $result = array(
            '<table class="table table-striped table-bordered table-condensed">',
            '<tr><th>Title</th><th>Up &uArr;</th><th>Down &dArr;</th></tr>',
        );
        if ($this->_currentId > 0) {
            $item = $this->getParent();
            $result[] = $this->_makeRow($item ? $item->{$this->_fieldId} : 0, '&uArr; Level up &uArr;');
        }
        $max = count($items) - 1;
        $order = 0;
        foreach ($items as $item) {
            $up = ($order > 0);
            $down = ($order < $max);
            $result[] = $this->_makeRow($item->id, $item->{$this->_fieldTitle}, $up, $down);
            $order++;
        }
        $result[] = '</table>';
        return implode(PHP_EOL, $result);
    }

    /**
     * Check if we are moving an item.
     * @param type $items
     */
    private function _checkMove($items)
    {
        $move = \Request::value('move');
        if (empty($move)) {
            return false;
        }
        $ids = array_keys($items);
        $moveId = abs($move);
        $currentIndex = array_search($moveId, $ids);
        if ($currentIndex === false) {
            return true;
        }
        $newIndex = ($move < 0) ? $currentIndex - 1 : $currentIndex + 1;
        $newIndexLimited = min(count($ids) - 1, max($newIndex, 0));
        $keep = array_splice($ids, $currentIndex, 1);
        array_splice($ids, $newIndexLimited, 0, $keep);
        return $this->_updatePositions($ids);
    }

    /**
     * Update positions for ids, in the order they are supplied.
     * @param array $ids
     * @return boolean
     */
    private function _updatePositions($ids)
    {
        $class = $this->_entityClass;
        $re = $class::re();
        /* @var $re \Core\Model\Reflect */
        $db = $re->db();
        $table = $re->table;
        $current = 0;
        foreach ($ids as $id) {
            $db->update($table, array('position' => $current), $id);
            $current++;
        }
        if (method_exists($class, 'clearCache')) {
            $class::clearCache();
        }
        return true;
    }

    /**
     * Make row.
     * @param type $id
     * @param string $title
     * @param boolean $up
     * @param boolean $down
     */
    private function _makeRow($id, $title, $up = false, $down = false)
    {
        $mainLink = $this->_makeLink($id, $title);
        $upLink = !$up ? '' : $this->_makeLink($this->_currentId, '&uArr;', -$id);
        $downLink = !$down ? '' : $this->_makeLink($this->_currentId, '&dArr;', $id);
        return "<tr><td>{$mainLink}</td><td>{$upLink}</td><td>{$downLink}</td></tr>";
    }

    /**
     * Make link to move or go to parent.
     * @param int $id
     * @param string $title
     * @param int $move
     * @return string
     */
    private function _makeLink($id, $title, $move = 0)
    {
        $url = $this->_getOrderUrl($id);
        $class = 'btn';
        if ($move != 0) {
            $url .= '?move=' . intval($move);
            $class .= ' btn-block';
        }
        return "<a href=\"{$url}\" class=\"{$class}\">{$title}</a>";
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
