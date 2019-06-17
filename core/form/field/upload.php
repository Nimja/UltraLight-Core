<?php
namespace Core\Form\Field;
/**
 * Uplaod field.
 */
class Upload extends \Core\Form\Field
{
    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, 'form-control');
        return sprintf(
            '<input type="file" name="%s" %s />',
            $this->name,
            $this->_extra($this->_extra)
        );
    }
}