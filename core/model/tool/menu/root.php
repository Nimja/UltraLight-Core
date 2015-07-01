<?php namespace Core\Model\Tool\Menu;
/**
 * Menu root entity for the page model.
 *
 * @author Nimja
 */
class Root
{
    /**
     * Basic constructor.
     * @param Item $root
     * @param array $urls
     */
    public function __construct($root, $urls)
    {
        $this->root = $root;
        $this->urls = $urls;
    }
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
}
