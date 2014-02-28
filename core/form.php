<?php
namespace Core;
/**
 * Beautifully simple form creator.
 */
class Form
{
    const FIELDSET_BEFORE = 'BEFORE';
    /**
     * The current fieldset.
     * @var string
     */
    private $_currentFieldSet = self::FIELDSET_BEFORE;
    /**
     * All the fieldSets and their content.
     * @var type
     */
    private $_fieldSets = array();
    /**
     * The basic settings.
     * @var string
     */
    private $_formTag;
    /**
     * The values in the current form.
     * @var array
     */
    private $_values;
    /**
     * Form contains upload field.
     * @var boolean
     */
    private $_containsUpload = false;

    /**
     * Change the fieldset.
     * @param string $page
     * @param null|array $extra
     * @param string $method
     * @return $this
     */
    public function __construct($page = null, $extra = null, $method = 'post')
    {
        $page = !empty($page) ? $page : '/' . \Core::$url;
        $this->_formTag = sprintf('<form action="%s" method="%s" %s>', $page, $method, $this->_extra($extra));
        $this->_values = \Request::getValues();
    }

    /**
     * Change the fieldset.
     * @param type $legend
     * @param null|array $extra
     * @return \Core\Form
     */
    public function fieldSet($legend, $extra = null)
    {
        $fieldSet = "<fieldset {$this->_extra($extra)}>";
        if (!empty($legend)) {
            $fieldSet .= "<legend>{$legend}</legend>";
        }
        $this->_currentFieldSet = $fieldSet;
        return $this;
    }

    /**
     * Add field.
     * @param string|\Core\Form\Field $field
     * @return \Core\Form
     */
    public function add($field)
    {
        if (empty($this->_fieldSets[$this->_currentFieldSet])) {
            $this->_fieldSets[$this->_currentFieldSet] = array();
        }
        if ($field instanceof \Core\Form\Field) {
            $field->setValue(getKey($this->_values, $field->name));
            if ($field->isUpload) {
                $this->_containsUpload = true;
            }
        }
        $this->_fieldSets[$this->_currentFieldSet][] = $field;
        return $this;
    }

    /**
     * Add class to extra variable, without overwriting existing classes.
     *
     * @param array $extra details
     * @param string $field
     * @return string Extra details for html.
     */
    private function _extra($data)
    {
        $result = '';
        if (!empty($data)) {
            if (!is_array($data)) {
                $result = ' ' . trim($data) . ' ';
            } else {
                $parts = array();
                $fields = array('class', 'style', 'id', 'onchange', 'alt', 'title', 'for', 'ref');
                foreach ($fields as $field) {
                    if (!empty($data[$field])) {
                        $parts[] = $field . '="' . trim($data[$field]) . '"';
                    }
                }
                if (!empty($data['extra'])) {
                    $parts[] = trim($data['extra']);
                }
                $result = ' ' . implode(' ', $parts) . ' ';
            }
        }
        return $result;
    }

    /**
     * Add default values.
     * @param array $values
     * @param boolean $force
     * @return \Core\Form
     */
    public function useValues($values, $force = false)
    {
        if (!empty($values) && is_array($values)) {
            $values = \Sanitize::clean($values);
            foreach ($values as $key => $value) {
                $cur = getKey($this->_values, $key, $value);
                $this->_values[$key] = $force ? $value : $cur;
            }
        }
        return $this;
    }

    /**
     * Very simple toString function.
     * @return string
     */
    public function __toString()
    {
        $tag = $this->_formTag;
        if ($this->_containsUpload) {
            $tag = trim(substr($tag, 0, -1)) . ' enctype="multipart/form-data">';
        }
        $result = array($tag);
        $open = false;
        foreach ($this->_fieldSets as $fieldSet => $fields) {
            if ($fieldSet != self::FIELDSET_BEFORE) {
                if ($open) {
                    $result[] = '</fieldset>';
                }
                $open = true;
                $result[] = $fieldSet;
            }
            foreach ($fields as $field) {
                $result[] = "$field";
            }
        }
        if ($open) {
            $result[] = '</fieldset>';
        }
        $result[] = '</form>';
        return implode(PHP_EOL, $result);
    }
}