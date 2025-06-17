<?php namespace Core\Format\Text;

/**
 * Class to get a page by id and return the formatted content.
 * 
 * Placeholders should be parsed recursively.
 */
class PageContent extends Base
{
    /**
     * The static page class, we will use this to get the menu.
     *
     * @var string
     */
    public static $pageClass = \Core\Model\Page::class;

    /**
     * Minimum parameter count.
     * @var int
     */
    protected $_minParameterCount = 1;

    /**
     * Menu.
     *
     * @var \Core\Model\Tool\Menu\Root
     */
    private $menu;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $id = intval($parts[0]);
        $class = \Sanitize::className(self::$pageClass);
        $page = $class::load($id);
        if (!$page) {
            return "ID not found: $id";
        }
        return $page->getString();
    }
}
