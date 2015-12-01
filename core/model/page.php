<?php

namespace Core\Model;

/**
 * Page model, this model allows for a tree-based simple page structure.
 *
 * Helper entities help with building (and caching) the menu structure of this entity.
 *
 * @author Nimja
 * @db-database live
 */
class Page extends \Core\Model\Ordered {
    /**
     * When to collapse or hide children.
     * @var int
     */
    public static $childLimit = 10;
    /**
     * Page title.
     * @listfield
     * @db-type varchar
     * @db-length 64
     * @validate empty|3
     * @var string
     */
    public $title;

    /**
     * Partial URL.
     * @db-type varchar
     * @db-length 64
     * @validate url|3
     * @var string
     */
    public $url;

    /**
     * Order, lower means higher.
     * @db-type int
     * @db-unsigned
     * @var int
     */
    public $parentId;

    /**
     * Page content.
     * @db-type text
     * @validate empty|10
     * @var string
     */
    public $content;

    /**
     * Format/parse content.
     * @return string
     */
    public function getString()
    {
        return \Core\Format\Text::parse($this->content);
    }

    /**
     * Get children.
     *
     * @return array
     */
    public function children()
    {
        return self::getChildren($this->id);
    }

    /**
     * Counter per type.
     *
     * @return int
     */
    public function getCount()
    {
        $re = $this->re();
        return $re->db()->getCount($re->table, ['parentId' => $this->id]);
    }

    /* ------------------------------------------------------------
     * 			STATIC FUNCTIONS
     * ------------------------------------------------------------
     */

    /**
     * Clear related caches.
     */
    public static function clearCache()
    {
        $class = get_called_class();
        \Core::clearCache($class . '::buildMenu');
    }

    /**
     * Get page entity for url.
     * @param string $url
     * @return \Core\Model\Page
     */
    public static function getPageForUrl($url)
    {
        $fullUrl = '/' . trim($url, '/');
        $urls = self::getMenu()->urls;
        $pageId = getKey($urls, $fullUrl);
        return !empty($pageId) ? self::load($pageId) : null;
    }

    /**
     * Get menu structure, an array with 2 items.
     * @return Tool\Menu\Root
     */
    public static function getMenu()
    {
        $class = get_called_class();
        return \Core::wrapCache($class . '::buildMenu');
    }

    /**
     * Get menu with 2 items in the array.
     *
     * Do not call directly, use getMenu instead!
     *
     * root => The root object, which includes the full tree.
     * urls => A flat array with key url and value page-id.
     * @return Tool\Menu\Root
     */
    public static function buildMenu()
    {
        $re = self::re();
        $db = $re->db();
        $table = $db->escape($re->table, true);
        $root = new Tool\Menu\Item(['id' => 0, 'title' => 'root', 'url' => '']);
        $items = [0 => $root];
        $res = $db->query("SELECT id, title, url, parentId FROM $table ORDER BY `position` ASC");
        while ($row = $res->fetch_assoc()) {
            $items[$row['id']] = new Tool\Menu\Item($row);
        }
        foreach ($items as $id => $menu) {
            if ($id == 0) {
                continue;
            }
            $items[$menu->parentId]->children[$menu->id] = $menu;
        }
        $root->buildFullUrlAndPath();
        $urls = [];
        $reverse = [];
        foreach ($items as $id => $menu) {
            $urls[$menu->fullUrl] = $id;
            $reverse[$menu->id] = $menu->fullPath;
        }
        return new Tool\Menu\Root($items[0], $urls, $reverse);
    }

    /**
     * Get children pages for parentId.
     * @param int $parentId
     * @return array
     */
    public static function getChildren($parentId)
    {
        return self::find(['parentId' => $parentId], 'position ASC');
    }

    /**
     * Get form for editing this entity.
     * @param \Core\Model $entity
     * @return \Core\Form
     */
    public static function getForm($entity)
    {
        $form = new \Core\Form(null, ['class' => 'admin form-horizontal']);
        if ($entity instanceof \Core\Model) {
            $form->useValues($entity->getValues());
        } else {
            $form->useValues(['date' => date('Y-m-d')]);
        }
        $currentItemId = $entity ? $entity->id : -1;
        $selectValues = self::getEntityList($currentItemId);
        $form
            ->add(new \Core\Form\Field\Input('title', ['label' => 'Title']))
            ->add(new \Core\Form\Field\Input('url', ['label' => 'Url']))
            ->add(new \Core\Form\Field\Select('parentId', ['label' => 'Parent', 'values' => $selectValues]))
            ->add(new \Core\Form\Field\Text('content', ['label' => 'Content', 'rows' => 20]))
            ->add(new \Core\Form\Field\Submit('submit', ['value' => 'Edit', 'class' => 'btn-primary']));
        return $form;
    }

    /**
     * Get basic list with id and title.
     * @param int $excludeId
     * @return [];
     */
    public static function getEntityList($excludeId = 0, $collapsable = false, $parentId = 0)
    {
        $menu = self::getMenu();
        $entity = empty($parentId) ? $menu->root : $menu->getItem($parentId);
        return self::_getEntityList($entity, $excludeId, 0, $collapsable, $parentId);
    }

    /**
     * Get recursive list with ID and title, or in the case of many children, a link to refine the list.
     * @param Tool\Menu\Item $entity
     * @param int $excludeId
     * @param type $level
     * @param boolean $collapsable
     * @param int $parentId
     * @return [];
     */
    protected static function _getEntityList($entity, $excludeId, $level, $collapsable, $parentId)
    {
        $result = [];
        $title = str_repeat('. . ', $level) . $entity->title;
        $result[$entity->id] = $title;
        $childCount = count($entity->children);
        if ($childCount < self::$childLimit || $entity->id == $parentId) {
            foreach ($entity->children as $child) {
                if ($child->id == $excludeId) {
                    continue;
                }
                $result = $result + self::_getEntityList($child, $excludeId, $level + 1, $collapsable, $parentId);
            }
        } else if ($collapsable) {
            $result['?parentId=' . $entity->id] = str_repeat('. . ', $level + 1) . "&rArr; (children: {$childCount})";
        }
        return $result;
    }

    /**
     * Get list for ordering, optionally by a search id like parentId.
     * @param int $searchId
     * @return Tool\Order\Item[]
     */
    public static function getOrdered($searchId = null)
    {
        $parentId = intval($searchId);
        $pages = self::find(['parentId' => $parentId], self::POSITION . ' ASC, id ASC');
        $result = [];
        if ($parentId) {
            $parent = self::load($parentId);
            $result[] = new \Core\Model\Tool\Order\Item(
                $parent->parentId,
                "&lArr; Back",
                true,
                false
            );
        }
        foreach ($pages as $page) {
            $result[] = new \Core\Model\Tool\Order\Item(
                $page->id,
                $page->title,
                $page->getCount() > 0,
                true
            );
        }
        return $result;
    }

    /**
     * Create multiple items at once with associative arrays.
     *
     * Use the 'children' to automatically set parentId.
     *
     * @param array $items
     * @return int Number of inserted models.
     */
    public static function addMultiple($items)
    {
        $class = get_called_class();
        return self::_addMultipleForParentId($class, $items);
    }

    /**
     * Add multiple for parentId, this allows for a properly constructed array.
     *
     * @param string $class
     * @param array $items
     * @param int $parentId
     * @return int Number of inserted models.
     */
    private static function _addMultipleForParentId($class, $items, $parentId = null)
    {
        if (!is_array($items)) {
            return false;
        }
        // Loop over array and add them.
        $count = 0;
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $children = getKey($item, 'children');
            unset($item['children']);
            if (!blank($parentId)) {
                $item['parentId'] = $parentId;
            }
            $current = new $class($item);
            /* @var $current \Core\Model */
            $current->save();
            $count++;
            if (is_array($children) && !empty($children)) {
                $count += self::_addMultipleForParentId($class, $children, $current->id);
            }
        }
        return $count;
    }

}
