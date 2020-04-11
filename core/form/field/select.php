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
        $values = $this->_getValues();
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $result[] = "<optgroup label=\"{$key}\">";
                foreach ($value as $skey => $svalue) {
                    $result[] = $this->renderOption($skey, $svalue);
                }
                $result[] = "</optgroup>";
            } else {
                $result[] = $this->renderOption($key, $value);
            }
        }
        $result[] = "</select>";
        return implode(PHP_EOL, $result);
    }

    private function renderOption($key, $value) {
        $selected = $this->_isSelected($key) ? 'selected="selected"' : '';
        return "<option value=\"{$key}\" {$selected}>{$value}</option>";
    }
}