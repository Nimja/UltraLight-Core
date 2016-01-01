<?php

namespace Core\Bootstrap;

/**
 * Bootstrap row, with .
 */
class Row
{

    const MAX_SIZE = 12;
    const RESIZE_LARGE = 'lg';
    const RESIZE_MEDIUM = 'md';
    const RESIZE_SMALL = 'sm';
    const RESIZE_XSMALL = 'xs';

    /**
     * Main header for rows.
     * @var string
     */
    public $mainHeader = '<div class="row">';

    /**
     * Main footer for rows.
     * 
     * @var string
     */
    public $mainFooter = '</div>';

    /**
     * The wrapper around the panel.
     * @var string
     */
    public $wrapRow = '<div class="row">%s</div>';

    /**
     * The bootstrap type we use for each column.
     * @var string
     */
    private $_type = self::RESIZE_MEDIUM;

    /**
     * The rows we are rendering.
     * @var array
     */
    private $_rows = [];

    /**
     * Current row.
     * @var int
     */
    private $_curRow = -1;

    /**
     * Current row size.
     * @var int
     */
    private $_curSize = 0;

    public function __construct($type = self::RESIZE_MEDIUM)
    {
        $this->_type = $type;
    }

    /**
     * Add a column to the row.
     * @param string $content
     * @param int $size
     * @return \Core\Bootstrap\Row
     */
    public function addColumn($content, $size = 0)
    {
        if ($this->_curSize == 0) {
            $this->_curRow++;
            $this->_rows[$this->_curRow] = [];
        }
        $intSize = intval($size);
        if ($intSize <= 0) {
            $intSize = 0;
            $this->_curSize++;
        } else {
            $this->_curSize += $intSize;
        }
        $this->_rows[$this->_curRow][] = ['size' => $intSize, 'content' => $content];
        return $this;
    }

    /**
     * End the current row, resizing the parts as needed.
     * @return \Core\Bootstrap\Row
     */
    public function endRow()
    {
        if ($this->_curSize == 0 || $this->_curRow < 0) {
            return;
        }
        $currentRow = $this->_rows[$this->_curRow];
        $used = 0;
        $unsized = 0;
        foreach ($currentRow as $item) {
            $used += $item['size'];
            if ($item['size'] == 0) {
                $unsized++;
            }
        }
        if ($used < self::MAX_SIZE && $unsized > 0) {
            $rest = self::MAX_SIZE - $used;
            $part = floor($rest / $unsized);
            foreach ($currentRow as $index => $item) {
                if ($item['size'] == 0) {
                    $currentRow[$index]['size'] = $part;
                }
            }
        }
        $this->_rows[$this->_curRow] = $currentRow;
        $this->_curSize = 0;
        return $this;
    }

    /**
     * Render row.
     * @param array $row
     * @return string
     */
    private function _renderRow($row)
    {
        $result = [];
        foreach ($row as $item) {
            $result[] = $this->getRowStart($item['size'])
                    . $item['content']
                    . $this->getRowEnd();
        }
        return implode(PHP_EOL, $result);
    }

    /**
     * Get row start.
     * @param int $width
     * @return string
     */
    public function getRowStart($width)
    {
        $class = "col-{$this->_type}-{$width}";
        return "<div class=\"{$class}\">";
    }

    /**
     * Get end of row.
     * @return string
     */
    public function getRowEnd()
    {
        return "</div>";
    }

    /**
     * Render as string.
     */
    public function __toString()
    {
        $this->endRow();
        $rows = [];
        foreach ($this->_rows as $row) {
            $rows[] = $this->mainHeader . $this->_renderRow($row) . $this->mainFooter;
        }
        return implode(PHP_EOL, $rows);
    }

}
