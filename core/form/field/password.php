<?php
namespace Core\Form\Field;
/**
 * A model with some automatic 'forms' based on the types.
 */
class Password extends Input
{
    protected function _getHtml()
    {
        $this->_extra['type'] = 'password';
        return parent::_getHtml();
    }
}