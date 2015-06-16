<?php namespace Core\Bootstrap;
/**
 * Bootstrap panel, with optional extra part (for table and list group).
 */
class Panel
{
    /**
     * The wrapper around the panel.
     * @var string
     */
    public $wrapPanel = "<div class=\"panel %s\">\n%s\n%s\n%s%s</div>";
    /**
     * The wrapper around the content.
     * @var string
     */
    public $wrapContent = '<div class="panel-body">%s</div>';
    /**
     * The wrapper around the header.
     * @var string
     */
    public $wrapHeader = '<div class="panel-heading">%s</div>';
    /**
     * The wrapper around the footer.
     * @var string
     */
    public $wrapFooter = '<div class="panel-footer">%s</div>';
    /**
     * Content for this panel.
     * @var string
     */
    private $_content;
    /**
     * Header for this panel.
     * @var string
     */
    private $_header;
    /**
     * Footer for this panel.
     * @var string
     */
    private $_footer;
    /**
     * Class for this panel.
     * @var string
     */
    private $_class;
    /**
     * Extra for this panel, like table or list group.
     * @var string
     */
    private $_extra = '';

    /**
     * Basic constructor.
     * @param string $content
     * @param string $header
     * @param string $footer
     * @param string $class
     */
    public function __construct($content, $header = null, $footer = null, $class = null)
    {
        $this->_content = $content;
        $this->_header = $header;
        $this->_footer = $footer;
        $this->_class = $class;
    }

    /**
     * Add extra HTML between content and footer, like table or list group.
     */
    public function setExtra($string)
    {
        $this->_extra = strval($string);
    }

    /**
     * Render the panel.
     * @return type
     */
    public function __toString()
    {
        $content = empty($this->_content) ? '' : sprintf($this->wrapContent, $this->_content);
        $header = empty($this->_header) ? '' : sprintf($this->wrapHeader, $this->_header);
        $footer = empty($this->_footer) ? '' : sprintf($this->wrapFooter, $this->_footer);
        $class = empty($this->_class) ? 'panel-default' : $this->_class;
        return sprintf($this->wrapPanel, $class, $header, $content, $this->_extra, $footer);
    }
}
