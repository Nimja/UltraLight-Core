<?php namespace Core\Bootstrap;
/**
 * Quick & dirty Navbar in bootstrap.
 *
 * The returned object can be cast to string.
 */
class Navbar
{
    const DIVIDER = '---';
    /**
     * Title of the current site.
     * @var string
     */
    private $_title;
    /**
     * Home link defaults to /.
     * @var string
     */
    private $_homeLink;
    /**
     * Menu array, keys are the links, values the description.
     * @var type
     */
    private $_menuArray;
    /**
     * The current class.
     * @var string
     */
    private $_barClass;
    /**
     * Probably the same as \Core::$url but configurable just in case.
     * @var string
     */
    private $_curPage;

    /**
     * Construct the navbar.
     * @param string $title
     * @param array $menuArray
     * @param string $curUrl
     * @param string $barClass
     */
    public function __construct($title, $menuArray, $curPage, $barClass = 'navbar-fixed-top', $homeLink = '/')
    {
        $this->_title = $title;
        $this->_menuArray = $menuArray;
        $this->_curPage = "/{$curPage}";
        $this->_barClass = $barClass;
        $this->_homeLink = $homeLink;
    }

    /**
     * Render buttons.
     * @param array $buttons
     * @param string $curPage
     * @return string
     */
    private function _renderButtons($buttons)
    {
        $result = [];
        $hasActive = false;
        foreach ($buttons as $label => $link) {
            if (is_array($link)) {
                $buttonResult = $this->_renderButtons($link);
                $isActive = $buttonResult['active'];
                $class =  $isActive ? ' dropdown active' : 'dropdown';
                $item = "<li class=\"{$class}\">";
                $item .= "<a class=\"dropdown-toggle\" data-toggle=\"dropdown\" href=\"#\">{$label} <span class=\"caret\"></span></a>";
                $item .= '<ul class="dropdown-menu" role="menu">';
                $item .= $buttonResult['buttons'];
                $item .= '</ul>';
            } else {
                if ($link == self::DIVIDER) {
                    $item = '<li role="separator" class="divider">';
                    $isActive = false;
                } else {
                    $isActive = (strpos($this->_curPage, $link) === 0) && $link != $this->_homeLink;
                    $active = $isActive ? ' class="active"' : '';
                    $item = "<li{$active}><a href=\"$link\">{$label}</a>";
                    if ($isActive && $hasActive) {
                        $result = $this->_clearActive($result);
                    }
                }
            }
            $hasActive = $hasActive || $isActive;
            $item .= '</li>';
            $result[] = $item;
        }
        return array('buttons' => implode("\n", $result), 'active' => $hasActive);
    }

    /**
     * Clear active flag on same-level items.
     * @param array $result
     * @return array
     */
    private function _clearActive($result)
    {
        foreach ($result as $index => $item) {
            $result[$index] = str_replace(' class="active"', '', $item);
        }
        return $result;
    }

    /**
     *
     * @return type
     */
    public function __toString()
    {
        $buttonResult = $this->_renderButtons($this->_menuArray);
        $buttons = $buttonResult['buttons'];
        return '<div class="navbar ' . $this->_barClass . '" role="navigation">
    <div class="container">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".navbar-collapse">
                <span class="sr-only">Toggle navigation</span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="' . $this->_homeLink . '">' . $this->_title . '</a>
        </div>
        <div class="navbar-collapse collapse">
            <ul class="nav navbar-nav navbar-right">
                ' . $buttons . '
            </ul>
        </div>
    </div>
</div>';
    }
}