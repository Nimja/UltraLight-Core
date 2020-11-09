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
class Page extends \Core\Model\Ordered
{
    const AUTOSORT_NO = 'no';
    const AUTOSORT_ALL = 'all';
    const AUTOSORT_ROOT = 'root';
    /**
     * Set method for specific parents, or default method with "all".
     *
     * By default, the root is NOT sorted, you have to explicitly set it with the "root" key.
     *
     * Methods have to be a valid order method, like "date ASC".
     *
     * @var array
     */
    public static $autoSorting = [];

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
     * Description.
     * @db-type text
     * @var string
     */
    public $description;

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
     * More performant child counter.
     *
     * @return int
     */
    public function getChildCount()
    {
        $re = $this->re();
        $class = get_called_class();
        return $re->db()->getCount($re->table, $class::getChildWhere($this->id));
    }

    /**
     * How many siblings this page has.
     *
     * @return int
     */
    public function getCount()
    {
        $re = $this->re();
        $class = get_called_class();
        return $re->db()->getCount($re->table, $class::getChildWhere($this->parentId));
    }

    /**
     * Add support for autosort.
     *
     * @return self
     */
    public function save()
    {
        $result = parent::save();
        $this->autoSort();
        return $result;
    }

    /**
     * Get values that are used as saving. Easy to overwrite.
     * @param Model\Reflect $re
     * @return array
     */
    protected function _getValuesForSave($re)
    {
        $values = parent::_getValuesForSave($re);
        if (empty($values['url'])) {
            $values['url'] = $this->makeUrlFromTitle((string)$values['title']);
        }
        return $values;
    }

    /**
     * Execute autosort after save.
     *
     * Autosort cannot happen with root, at this moment.
     *
     * @return void
     */
    protected function autoSort()
    {
        $order = self::getAutoSortOrder($this->parentId);
        if (!$order) {
            return;
        }
        $siblings = $this->getSiblings($order);
        $count = 1;
        foreach ($siblings as $child) {
            $child->setPosition($count);
            $count++;
        }
    }

    /**
     * Make url from title.
     *
     * @param string $title
     * @return string
     */
    protected function makeUrlFromTitle(string $title): string
    {
        $decoded = strtolower(html_entity_decode($title));
        $replaced = preg_replace("/[^a-z\-]/", ' ', $decoded);
        $underscored = preg_replace("/\s+/", '_', trim($replaced));
        return $underscored;
    }


    /**
     * Get single sibling efficiently.
     *
     * @param boolean $left True for previous, false for next.
     * @param boolean $end True for first/last.
     * @return void
     */
    public function getSibling(bool $left, bool $end = false)
    {
        $search = sprintf(
            'parentId|=%s;position|%s%s',
            $this->parentId,
            $left ? '<' : '>',
            $this->position
        );
        if ($left) {
            $order = $end ? ' ASC, id ASC' : ' DESC, id DESC';
        } else {
            $order = $end ? ' DESC, id DESC' : ' ASC, id ASC';
        }
        return self::findOne($search, self::POSITION . $order);
    }

    /**
     * Get siblings.
     */
    public function getSiblings($order = self::POSITION . ' ASC, id ASC')
    {
        return self::getChildren($this->parentId, $order);
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
        $class = get_called_class();
        $re = $class::re();
        $db = $re->db();
        $table = $db->escape($re->table, true);
        $root = new Tool\Menu\Item(['id' => 0, 'title' => 'root', 'url' => '']);
        $items = [0 => $root];
        $where = $db->searchToSql($class::getPublishedWhere());
        $res = $db->query("SELECT id, title, url, parentId FROM $table WHERE $where ORDER BY `position` ASC");
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
        $lookup = [];
        foreach ($items as $id => $menu) {
            $urls[$menu->fullUrl] = $id;
            $lookup[$id] = ['url' => $menu->fullUrl, 'title' => $menu->title];
            $reverse[$menu->id] = $menu->fullPath;
        }
        return new Tool\Menu\Root($items[0], $urls, $reverse, $lookup);
    }

    /**
     * Can be overridden for additional complexities for published pages.
     *
     * @return void
     */
    public static function getPublishedWhere()
    {
        return [];
    }

    /**
     * Get children pages for parentId.
     * @param int $parentId
     * @return array
     */
    public static function getChildren($parentId, $order = self::POSITION . ' ASC, id ASC')
    {
        $class = get_called_class();
        return self::find($class::getChildWhere($parentId), $order);
    }

    /**
     * Get where query for children.
     *
     * @param int $parentId
     * @return void
     */
    public static function getChildWhere($parentId)
    {
        $class = get_called_class();
        $where = array_merge(['parentId' => $parentId], $class::getPublishedWhere());
        return $where;
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
        $parentId = $form->getValue('parentId');
        $parentLabel = 'Parent';
        if (!empty($parentId)) {
            $url = substr(\Core::$url, 0, strpos(\Core::$url, '/edit'));
            $parentLabel = '<a href="/'. $url .'?parentId='. $parentId . '">Parent</a>';
        }
        $form
            ->add(new \Core\Form\Field\Input('title', ['label' => 'Title']))
            ->add(new \Core\Form\Field\Input('url', ['label' => 'Url']))
            ->add(new \Core\Form\Field\Select('parentId', ['label' => $parentLabel, 'values' => $selectValues]))
            ->add(new \Core\Form\Field\Input('description', ['label' => 'Description']))
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
        $class = get_called_class();
        $pages = self::find($class::getChildWhere($parentId), self::POSITION . ' ASC, id ASC');
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
                $page->getChildCount() > 0,
                true
            );
        }
        return $result;
    }

    /**
     * Return the order for sorting, or false if we use positions explicitly.
     *
     * By default, this is based on the autoSorting array.
     *
     * @param integer $id
     * @return string|null
     */
    public static function getAutoSortOrder(int $id): ?string
    {
        $all = $id ? self::AUTOSORT_ALL : self::AUTOSORT_ROOT;
        $order = getKey(self::$autoSorting, $id, getKey(self::$autoSorting, $all, null));
        return $order == self::AUTOSORT_NO ? null : $order;
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
