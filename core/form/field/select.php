<?php

namespace Core\Form\Field;

/**
 * Select field, can switch between dropdown or multiselect with "multiple" extra flag.
 */
class Select extends \Core\Form\Field
{

    /**
     * Get HTML for htis field.
     *
     * @return string
     */
    protected function _getHtml()
    {
        $this->_extra = $this->_addClass($this->_extra, 'form-control');
        $extra = $this->_extra($this->_extra);
        $tag = $this->_isMultiple ? ' multiple="multiple"' : '';
        $nameExtra = $this->_isMultiple ? '[]' : '';
        $result = array("<select name=\"{$this->name}{$nameExtra}\"{$tag} {$extra}>");
        $values = $this->_getValues();
        foreach ($values as $key => $value) {
            if (is_array($value) && !array_key_exists('isOption', $value)) { // Way to make NOT opt-group subarray.
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

    /**
     * Render a single option.
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    private function renderOption($key, $value)
    {
        $selected = $this->_isSelected($key) ? ' selected="selected"' : '';
        $extra = '';
        if (is_array($value)) { // Third depth assumes that there are other options.
            $data = getKey($value, 'data');
            $value = getKey($value, 'value');
            if (!empty($data) && is_array($data)) {
                $dataFields = [];
                foreach ($data as $n => $v) { // Add data fields for an option, values are already escaped.
                    $dataFields[] = "data-{$n}=\"{$v}\"";
                }
                $extra = ' ' . implode(' ', $dataFields);
            }
        }
        return "<option value=\"{$key}\"{$selected}{$extra}>{$value}</option>";
    }
}
