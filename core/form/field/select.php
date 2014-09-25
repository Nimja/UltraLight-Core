<?php
namespace Core\Form\Field;
/**
 * Select field, can switch between dropdown or multiselect with "multiple" extra flag.
 */
class Select extends \Core\Form\Field
{

    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, 'form-control');
        $extra = $this->_extra($this->_extra);
        $tag = $this->_isMultiple ? ' multiple="multiple"' : '';
        $nameExtra = $this->_isMultiple ? '[]' : '';
        $result = array("<select name=\"{$this->name}{$nameExtra}\"{$tag} {$extra}>");
        foreach ($this->_getValues() as $key => $value) {
            $selected = $this->_isSelected($key) ? 'selected="selected"' : '';
            $result[] = "<option value=\"{$key}\" {$selected}>{$value}</option>";
        }
        $result[] = "</select>";
        return implode(PHP_EOL, $result);
    }
}