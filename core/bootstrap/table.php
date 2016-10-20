<?php

namespace Core\Bootstrap;

/**
 * Simple table, compatible with bootstrap.
 *
 * The returned object can be cast to string.
 */
class Table
{

    const TAG_HEADER = 'th';
    const TAG_DATA = 'td';
    const CLASS_DEFAULT = 'table-striped table-condensed';

    /**
     * The class for the table.
     *
     * @var string
     */
    private $_class;

    /**
     * The header row.
     *
     * @var array
     */
    private $_header;

    /**
     * The data rows.
     * @var array
     */
    private $_rows;

    /**
     * The rendered string.
     *
     * @var array
     */
    private $_string;

    /**
     * Construct the table.
     *
     * @param array $header
     * @param array $rows
     * @param string $class
     */
    public function __construct($header, $rows, $class = self::CLASS_DEFAULT)
    {
        $this->_class = 'table ' . $class;
        $this->_header = $header;
        $this->_rows = $rows;
    }

    /**
     * Get string, but only render once.
     *
     * @return type
     */
    public function getString()
    {
        if (!$this->_string) {
            $this->_string = $this->_makeTable();
        }
        return $this->_string;
    }

    /**
     * Render the table.
     *
     * @return string
     */
    private function _makeTable()
    {
        $result = ['<table class="' . $this->_class . '">'];
        if (!empty($this->_header)) {
            $result[] = $this->_makeRow($this->_header, true);
        }
        foreach ($this->_rows as $row) {
            $result[] = $this->_makeRow($row, false);
        }
        $result[] = '</table>';
        return implode(PHP_EOL, $result);
    }

    /**
     * Make a single row.
     *
     * @param array $row
     * @param boolean $isHeader
     * @return type
     */
    private function _makeRow($row, $isHeader)
    {
        if (!is_array($row)) {
            return '';
        }
        $result = [];
        $tag = $isHeader ? self::TAG_HEADER : self::TAG_DATA;
        foreach ($row as $column) {
            if (is_array($column)) {
                $result[] = $this->_getColumnFromArray($column, $tag);
            } else {
                $result[] = "<{$tag}>{$column}</{$tag}>";
            }
        }

        return '<tr>' . implode('', $result) . '</tr>';
    }

    /**
     * Get column from array.
     *
     * @param array $column
     * @param string $tag
     * @return string
     */
    private function _getColumnFromArray($column, $tag)
    {
        $value = '';
        if (isset($column['value'])) {
            $value = $column['value'];
            unset($column['value']);
        }
        if (isset($column['tag'])) {
            $tag = $column['tag'];
            unset($column['tag']);
        }
        $attributes = [];
        foreach ($column as $key => $val) {
            $escaped = \Sanitize::clean($val);
            $attributes[] = "{$key}=\"$escaped\"";
        }
        $attributeString = implode(' ', $attributes);
        return "<{$tag} {$attributeString}>{$value}</{$tag}>";
    }

    /**
     * Cast to string.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->getString();
    }

}
