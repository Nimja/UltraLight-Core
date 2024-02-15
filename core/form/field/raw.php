<?php

namespace Core\Form\Field;

/**
 * Raw field, allows for HTML to be included with the correct layout and possible label.
 */
class Raw extends \Core\Form\Field
{
    /**
     * Raw HTML.
     * @var string
     */
    protected $_html = '';
    /**
     * For raw, name is irrelevant, but kept for compatibility.
     * @param string $name
     * @param array $extra
     */
    public function __construct($name, $extra = null, $params = null)
    {
        if (isset($extra['html'])) {
            $this->_html = strval($extra['html']);
            unset($extra['html']);
        }
        parent::__construct($name, $extra, $params);
    }

    /**
     * Get HTML.
     * @return type
     */
    protected function _getHtml()
    {
        if (!$this->wrapDiv) {
            return $this->_html;
        }
        return sprintf(
            '<div class="form-control-static" data-name="%s" %s >%s</div>',
            $this->name,
            $this->_extra($this->_extra),
            $this->_html
        );
    }
}
