<?php

namespace Core\Model\Tool\Menu;

/**
 * Menu root entity for the page model.
 *
 * @author Nimja
 */
class Root {

    /**
     * The root menu item.
     * @var Item
     */
    public $root;

    /**
     * Full urls with their ID, for easy flat lookup.
     * @var array
     */
    public $urls;

    /**
     * Per id, contains the list of parentIds leading to that object.
     * @var array
     */
    public $reverse;

    /**
     * Basic constructor.
     * @param Item $root
     * @param array $urls
     * @param array $reverse
     */
    public function __construct($root, $urls, $reverse)
    {
        $this->root = $root;
        $this->urls = $urls;
        $this->reverse = $reverse;
    }

    /**
     * Find a menu item by Id, using reverse lookup.
     * @param int $id
     * @return Item
     */
    public function getItem($id)
    {
        if ($id < 1) {
            $result = $this->root;
        } else {
            $parent = $this->getParent($id);
            $result = $parent->children[$id];
        }
        return $result;
    }

    /**
     * Get parent for id.
     * @param int $id
     * @return Item
     * @throws \Exception
     */
    public function getParent($id)
    {
        $result = $this->root;
        if (isset($this->reverse[$id])) {
            $reverse = $this->reverse[$id];
            while (!empty($reverse)) {
                $nextParentId = array_shift($reverse);
                if (!isset($result->children) || !isset($result->children[$nextParentId])) {
                    throw new \Exception("Unable to find child?");
                }
                $result = $result->children[$nextParentId];
            }
        }
        return $result;
    }

}
