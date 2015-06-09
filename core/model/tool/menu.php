<?php namespace Core\Model\Tool;
/**
 * Helper entity for page model, but can be used more broad.
 *
 * @author Nimja
 */
class Menu
{
    const NODE_ROOT = 'root';
    const NODE_URLS = 'urls';
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
