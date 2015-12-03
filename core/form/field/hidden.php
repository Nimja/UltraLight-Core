<?php
namespace Core\Form\Field;
/**
 * Hidden form field, with a value that can only be set in constructor.
 */
class Hidden extends \Core\Form\Field
{
    /**
     * Wrap this in a div.
     *
     * @var type
     */
    public $wrapDiv = false;
    protected function _getHtml()
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%s" %s />',
            $this->name,
            getKey($this->_extra, 'value'),
            $this->_extra($this->_extra)
        );
    }
}