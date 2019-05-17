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
     * Add position if not set and, optionally, clear cache.
     *
     * @return \self
     */
    public function save()
    {
        if (blank($this->position)) {
            $this->position = $this->getCount() + 1;
        }
        if (method_exists($this->_class, 'clearCache')) {
            $class = $this->_class;
            $class::clearCache();
        }
        return parent::save();
    }

    /**
     * Basic counter for all items in this class. Extend this for more finegrained control.
     *
     * @return int
     */
    public function getCount()
    {
        $re = $this->re();
        return $re->db()->getCount($re->table);
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

    /**
     * Get siblings.
     */
    public function getSiblings($order = self::POSITION . ' ASC, id ASC')
    {
        return self::find(null, $order);
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

    /**
     * Return the order for sorting, or false if we use positions explicitly, implemented in child.
     *
     * @param integer $id
     * @return string|null
     */
    public static function getAutoSortOrder(int $id): ?string
    {
       return null;
    }
}
