<?php namespace Core\Model\Tool\Menu;
/**
 * Menu item entity for the page model.
 *
 * @author Nimja
 */
class Item
{
    public static $_class = '\Core\Model\Page';
    /**
     * Page id.
     * @var int
     */
    public $id;
    /**
     * Page title.
     * @var string
     */
    public $title;
    /**
     * Partial URL.
     * @var string
     */
    public $url;
    /**
     * Full URL.
     * @var string
     */
    public $fullUrl = '';
    /**
     * ParentId
     * @var int
     */
    public $parentId;
    /**
     * Children of this item.
     * @var array
     */
    public $children = array();
    /**
     * Create the menu item.
     * @param type $values
     */
    public function __construct($values)
    {
        foreach ($values as $key => $value) {
            $this->{$key} = $value;
        }
    }

    /**
     * Get entity object, defaults to page.
     * @return \Core\Model\Page
     */
    public function getEntity()
    {
        $class = self::$_entityClass;
        return $class::load($this->id);
    }

    /**
     * Get a child item by url.
     * @param string $url
     * @return Item|null
     */
    public function getChildByUrl($url) {
        $result = null;
        foreach ($this->children as $child) {
            if ($child->url == $url) {
                $result = $child;
                break;
            }
        }
        return $result;
    }

    /**
     * Build full URLS.
     */
    public function buildFullUrl()
    {
        foreach ($this->children as $child) {
            $child->fullUrl = $this->fullUrl . '/' . $child->url;
            $child->buildFullUrl();
        }
    }
}
