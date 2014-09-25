<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Text extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, 'form-control');
        if (!isset($this->_extra['rows'])) {
            $this->_extra['rows'] = 3;
        }
        return sprintf(
            '<textarea name="%s" %s>%s</textarea>',
            $this->name,
            $this->_extra($this->_extra),
            $this->value
        );
    }
}