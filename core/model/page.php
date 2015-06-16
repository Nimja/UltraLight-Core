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
     * @return self
     */
    public function save()
    {
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
        $menu = self::getMenu();
        $fullUrl = '/' . trim($url, '/');
        $urls = $menu[Tool\Menu::NODE_URLS];
        $pageId = getKey($urls, $fullUrl);
        return !empty($pageId) ? self::load($pageId) : null;
    }

    /**
     * Get menu structure.
     * @return Tool\Menu[]
     */
    public static function getMenu()
    {
        return \Core::wrapCache('\Core\Model\Page::buildMenu');
    }
    /**
     * Get menu with 2 items in the array.
     *
     * Do not call directly, use getMenu instead!
     *
     * root => The root object, which includes the full tree.
     * urls => A flat array with key url and value page-id.
     * @return array
     */
    public static function buildMenu()
    {
        $re = self::re();
        $db = $re->db();
        $table = $db->escape($re->table, true);
        $root = new Tool\Menu(array('id' => 0, 'title' => 'root', 'url' => ''));
        $items = array(0 => $root);
        $res = $db->query("SELECT id, title, url, parentId FROM $table ORDER BY `position` ASC");
        while ($row = $res->fetch_assoc()) {
            $items[$row['id']] = new Tool\Menu($row);
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
        return array(Tool\Menu::NODE_ROOT => $items[0], Tool\Menu::NODE_URLS => $urls);
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
     * @param Tool\Menu $entity
     * @param int $excludeId
     * @param type $level
     * @return array();
     */
    public static function getEntityList($entity = null, $excludeId = 0, $level = 0)
    {
        if (!$entity instanceof Tool\Menu) {
            $list = self::getMenu();
            $entity = $list[Tool\Menu::NODE_ROOT];
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
}