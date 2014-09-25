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
        $this->_extra = $this->_addClass($this->_extra, 'form-control');
        return sprintf(
            '<input type="file" name="%s" %s />',
            $this->name,
            $this->_extra($this->_extra)
        );
    }
}