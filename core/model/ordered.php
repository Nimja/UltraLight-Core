<?php

namespace Core\Model;

/**
 * Ordered parent class makes models compatible with the simple order tool.
 *
 * @author Nimja
 * @db-database live
 */
abstract class Ordered extends \Core\Model {

    const POSITION = 'position';
    /**
     * Position, lower means higher.
     * @db-type int
     * @db-unsigned
     * @var int
     */
    public $position;

    /**
     * Override save to clear cache after.
     *
     * Also, if position is blank, we add it to the end.
     * @return self
     */
    public function save()
    {
        if (blank($this->position)) {
            $this->position = $this->_getCurrentCount() + 1;
        }
        return parent::save();
    }

    /**
     * Basic counter for all items in this class. Extend this for more finegrained control.
     *
     * @return int
     */
    protected function _getCurrentCount()
    {
        $re = $this->re();
        $db = $re->db();
        $table = $db->escape($re->table, true);
        $sql = "SELECT COUNT(id) FROM {$table};";
        return intval($db->fetchFirstValue($sql));
    }

    /**
     * Update position if changed.
     * @param int $newPosition
     */
    public function setPosition($newPosition)
    {
        $result = false;
        if ($newPosition != $this->position && $this->id) {
            $this->position = $newPosition;
            $this->_saveValues(['position' => $this->position]);
            $result = true;
        }
        return $result;
    }


    /* ------------------------------------------------------------
     * 			STATIC FUNCTIONS
     * ------------------------------------------------------------
     */

    /**
     * Get list for ordering, optionally by a search id like parentId.
     * @param int $searchId
     * @return Tool\Order\Item[]
     */
    public static function getOrdered($searchId = null)
    {
        $items = self::find(null, self::POSITION . ' ASC, id ASC');
        return self::_getResultFromEntities($items);
    }

    /**
     * Get result from an basic entity list.
     * @param \Model[] $items
     * @return Tool\Order\Item[]
     */
    protected static function _getResultFromEntities($items)
    {
        $result = [];
        $listField = self::re()->listField;
        foreach ($items as $item) {
            $result[] = new Tool\Order\Item(
                $item->id,
                $item->{$listField}
            );
        }
        return $result;
    }
}
