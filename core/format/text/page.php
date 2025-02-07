<?php namespace Core\Format\Text;

/**
 * Basic String to HTML formatting class.
 *
 */
class Page extends Base
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
        if (!$this->menu) {
            $class = \Sanitize::className(self::$pageClass);
            $this->menu = $class::getMenu();
        }
        $lookup = $this->menu->lookup;
        if (!array_key_exists($id, $lookup)) {
            return "ID not found: $id";
        }
        $menuParts = $lookup[$id];
        $url = $menuParts['url'];
        $title = $menuParts['title'];
        $class = isset($parts[1]) ? $this->_reverseParse($parts[1]) : false;
        $extra = $class ? "class= \"{$class}\"" : '';
        return "<a href=\"{$url}\" {$extra}>{$title}</a>";
    }
}
