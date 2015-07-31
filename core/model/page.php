<?php namespace Core\Model;
/**
 * Page model, this model allows for a tree-based simple page structure.
 *
 * Helper entities help with building (and caching) the menu structure of this entity.
 *
 * @author Nimja
 * @db-database live
 */
class Page extends \Core\Model
{
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
     * Position, lower means higher.
     * @db-type tinyint
     * @var int
     */
    public $position;
    /**
     * Page content.
     * @db-type text
     * @validate empty|10
     * @var string
     */
    public $content;

    /**
     * Override save to clear cache after.
     *
     * Also, if position is blank, we add it to the end.
     * @return self
     */
    public function save()
    {
        if (blank($this->position)) {
            $this->position = self::getChildCount($this->parentId);
        }
        $result = parent::save();
        self::clearCache();
        return $result;
    }

    /**
     * Get the nicely formatted content.
     * @return string
     */
    public function __toString()
    {
        return \Core\Format\Text::parse($this->content);
    }

    /**
     * Clear related caches.
     */
    public static function clearCache()
    {
        \Core::clearCache('\Core\Model\Page::buildMenu');
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
        $root = new Tool\Menu\Item(array('id' => 0, 'title' => 'root', 'url' => ''));
        $items = array(0 => $root);
        $res = $db->query("SELECT id, title, url, parentId FROM $table ORDER BY `position` ASC");
        while ($row = $res->fetch_assoc()) {
            $items[$row['id']] = new Tool\Menu\Item($row);
        }
        foreach ($items as $id => $menu) {
            if ($id == 0) {
                continue;
            }
            $items[$menu->parentId]->children[] = $menu;
        }
        $root->buildFullUrl();
        $urls = array();
        foreach ($items as $id => $menu) {
            $urls[$menu->fullUrl] = $id;
        }
        return new Tool\Menu\Root($items[0], $urls);
    }

    /**
     * Get children pages for parentId.
     * @param int $parentId
     * @return array
     */
    public static function getChildren($parentId)
    {
        return self::find(array('parentId' => $parentId), 'position ASC');
    }

    /**
     * Get child count, as efficiently as possible.
     * @param int $parentId
     * @return int
     */
    public static function getChildCount($parentId)
    {
        $re = self::re();
        $db = $re->db();
        $table = $db->escape($re->table, true);
        $parentInt = intval($parentId);
        $sql = "SELECT COUNT(id) FROM {$table} WHERE parentId = {$parentInt};";
        return intval($db->fetchFirstValue($sql));
    }

    /**
     * Get form for editing this entity.
     * @param \Core\Model $entity
     * @return \Core\Form
     */
    public static function getForm($entity)
    {
        $form = new \Core\Form(null, array('class' => 'admin form-horizontal'));
        if ($entity instanceof \Core\Model) {
            $form->useValues($entity->getValues());
        } else {
            $form->useValues(array('date' => date('Y-m-d')));
        }
        $currentItemId = $entity ? $entity->id : -1;
        $selectValues = self::getEntityList(null, $currentItemId);
        $form
            ->add(new \Core\Form\Field\Input('title', array('label' => 'Title')))
            ->add(new \Core\Form\Field\Input('url', array('label' => 'Url')))
            ->add(new \Core\Form\Field\Select('parentId', array('label' => 'Parent', 'values' => $selectValues)))
            ->add(new \Core\Form\Field\Text('content', array('label' => 'Content', 'rows' => 20)))
            ->add(new \Core\Form\Field\Submit('submit', array('value' => 'Edit', 'class' => 'btn-primary')));
        return $form;
    }

    /**
     * Get basic list with id and title.
     * @param Tool\Menu\Item $entity
     * @param int $excludeId
     * @param type $level
     * @return array();
     */
    public static function getEntityList($entity = null, $excludeId = 0, $level = 0)
    {
        if (!$entity instanceof Tool\Menu\Item) {
            $entity = self::getMenu()->root;
        }
        $result = array();
        $title = str_repeat('. . ', $level) . $entity->title;
        $result[$entity->id] = $title;
        foreach ($entity->children as $child) {
            if ($child->id == $excludeId) {
                continue;
            }
            $result = $result + self::getEntityList($child, $excludeId, $level + 1);
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
