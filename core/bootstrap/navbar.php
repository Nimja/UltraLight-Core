<?php namespace Core\Bootstrap;
/**
 * Quick & dirty Navbar in bootstrap.
 *
 * This class returns an object that will generate a bootstrap navbar with __toString.
 */
class Navbar
{
    /**
     *
     * @var string
     */
    private $_title;
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

    public function __construct($title, $menuArray, $curPage, $barClass = 'navbar-fixed-top')
    {
        $this->_title = $title;
        $this->_menuArray = $menuArray;
        $this->_curPage = "/{$curPage}";
        $this->_barClass = $barClass;
    }

    /**
     * Render buttons.
     * @param array $buttons
     * @param string $curPage
     * @return string
     */
    private function _renderButtons($buttons)
    {
        $result = array();
        $hasActive = false;
        foreach ($buttons as $label => $link) {
            $isActive = ($link == $this->_curPage);
            if (is_array($link)) {
                $buttonResult = $this->_renderButtons($link);
                $isActive = $buttonResult['active'];
                $class =  $isActive ? ' dropdown active' : 'dropdown';
                $item = "<li class=\"{$class}\">";
                $item .= "<a class=\"dropdown-toggle\" data-toggle=\"dropdown\">{$label} <span class=\"caret\"></span></a>";
                $item .= '<ul class="dropdown-menu" role="menu">';
                $item .= $buttonResult['buttons'];
                $item .= '</ul>';
            } else {
                $active = $isActive ? ' class="active"' : '';
                $item = "<li{$active}><a href=\"$link\">{$label}</a>";
            }
            $hasActive = $hasActive || $isActive;
            $item .= '</li>';
            $result[] = $item;
        }
        return array('buttons' => implode("\n", $result), 'active' => $hasActive);
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
            <a class="navbar-brand" href="/">' . $this->_title . '</a>
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