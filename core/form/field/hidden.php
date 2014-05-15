<?php
namespace Core\Form\Field;
/**
 * Hidden form field.
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
            $this->value,
            $this->_extra($this->_extra)
        );
    }
}