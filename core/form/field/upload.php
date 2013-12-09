<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Upload extends \Core\Form\Field
{
    public $isUpload = true;
    protected function _getHtml()
    {
        return sprintf(
            '<input type="file" name="%s" value="%s" %s />',
            $this->name,
            $this->value,
            $this->_extra($this->_extra)
        );
    }
}