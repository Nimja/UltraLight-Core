<?php

namespace Core\Format;

/**
 * Basic String to HTML formatting class.
 *
 */
class Variable
{
    private $_lines = [];
    private $_pad;

    public function __construct($variable, $pad = '  ')
    {
        $this->_pad = $pad;
        $this->_addVariable($variable);
    }

    /**
     * Translate a variable nicely.
     * @param mixed $variable
     * @param int $depth The current depth.
     * @param string $parent When we are in an object or array, the parent/key name.
     * @param boolean $parentIsArray When we are in an array, lines end with a comma.
     */
    private function _addVariable($variable, $depth = 0, $parent = null, $parentIsArray = false)
    {
        $type = gettype($variable);
        switch ($type) {
            case 'boolean':
                $this->_addLine($variable ? 'true' : 'false', $depth, $parent, $parentIsArray);
                break;
            case 'double':
                $this->_addLine("{$variable}", $depth, $parent, $parentIsArray);
                break;
            case 'integer':
                $this->_addInt($variable, $depth, $parent, $parentIsArray);
                break;
            case 'string':
                $this->_addString($variable, $depth, $parent, $parentIsArray);
                break;
            case 'array':
                $this->_addArray($variable, $depth, $parent, $parentIsArray);
                break;
            case 'object':
                $this->_addObject($variable, $depth, $parent, $parentIsArray);
                break;
            case 'resource':
                $this->_addLine($variable, $depth, $parent, $parentIsArray);
                break;
            case 'NULL':
                $this->_addLine('NULL', $depth, $parent, $parentIsArray);
                break;
            default:
                $string = 'UNKNOWN: ' . PHP_EOL . var_export($variable, true);
                $this->_addString($string, $depth, $parent, $parentIsArray);
        }
    }

    /**
     * Add integer.
     * @param integer $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $parentIsArray
     */
    private function _addInt($variable, $depth, $parent, $parentIsArray)
    {
        //Assume date when handling big integers (> 1985).
        if ($variable > 500000000) {
            $variable .= date(' (Y-m-d H:i:s)', $variable);
        }
        $this->_addLine($variable, $depth, $parent, $parentIsArray);
    }

    /**
     * Add string.
     * @param string $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $parentIsArray
     */
    private function _addString($variable, $depth, $parent, $parentIsArray)
    {
        $lines = explode(PHP_EOL, $variable);
        if (count($lines) == 1) {
            $this->_addLine("\"{$variable}\"", $depth, $parent, $parentIsArray);
        } else {
            $this->_addMultipleLines($lines, $depth, $parent, $parentIsArray);
        }
    }

    /**
     * Add multiple lines.
     *
     * If we are in an array/object, the first line will start indented, the last line will have the comma.
     *
     * @param array $lines
     * @param type $depth
     * @param type $parent
     * @param boolean $parentIsArray
     */
    private function _addMultipleLines($lines, $depth, $parent, $parentIsArray)
    {
        $lastIndex = count($lines) - 1;
        $quote = '"';
        foreach ($lines as $index => $line) {
            $isFirst = ($index === 0);
            $isLast = ($index === $lastIndex);
            $curDepth = $isFirst ? $depth : 0;
            $curParent = $isFirst ? $parent : '';
            $curArray = $isLast ? $parentIsArray : false;
            $pre = $isFirst ? $quote : '';
            $post = $isLast ? $quote : '';
            $this->_addLine("{$pre}{$line}{$post}", $curDepth, $curParent, $curArray);
        }
    }

    /**
     * Add array.
     * @param array $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $parentIsArray
     */
    private function _addArray($variable, $depth, $parent, $parentIsArray)
    {
        $count = count($variable);
        $this->_addLine("array ({$count}) (", $depth, $parent, false);
        foreach ($variable as $key => $value) {
            $this->_addVariable($value, $depth + 1, $key, true);
        }
        $this->_addLine(')', $depth, null, $parentIsArray);
    }

    /**
     * Add object.
     * @param object $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $parentIsArray
     */
    private function _addObject($variable, $depth, $parent, $parentIsArray)
    {
        if ($variable instanceof \Throwable) {
            $this->_addLine("Exception: " . get_class($variable), $depth, $parent, false);
            $this->_addLine("file: {$variable->getFile()}", $depth, $parent, false);
            $this->_addLine("line: {$variable->getLine()}", $depth, $parent, false);
            $this->_addLine("message: {$variable->getMessage()}", $depth, $parent, false);
        } else {
            $this->_addGenericObject($variable, $depth, $parent, $parentIsArray);
        }
    }

    /**
     * Add generic object.
     * @param object $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $parentIsArray
     */
    private function _addGenericObject($variable, $depth, $parent, $parentIsArray)
    {
        $class = get_class($variable);
        $this->_addLine("$class {", $depth, $parent, false);
        foreach ($variable as $key => $value) {
            $this->_addVariable($value, $depth + 1, $key, false);
        }
        $this->_addLine('}', $depth, null, $parentIsArray);
    }

    /**
     * Add line to the result.
     * @param string $string
     * @param int $depth
     * @param string $parent
     */
    private function _addLine($string, $depth, $parent, $parentIsArray)
    {
        $prefix = '';
        if (is_numeric($parent) || $parent) {
            $parent = !is_numeric($parent) ? "'{$parent}'" : $parent;
            $prefix = "{$parent} => ";
        }
        $suffix = $parentIsArray ? ',' : '';
        $pad = str_repeat($this->_pad, $depth);
        $this->_lines[] = "{$pad}{$prefix}{$string}{$suffix}";
    }

    /**
     * Return lines.
     * @return array
     */
    public function getLines()
    {
        return $this->_lines;
    }

    /**
     * Return all lines as a single string.
     * @return type
     */
    public function __toString()
    {
        return implode(PHP_EOL, $this->_lines);
    }

    /**
     * Parse variable and return lines of data.
     * @param mixed $variable
     * @return array
     */
    public static function parse($variable)
    {
        $parser = new self($variable);
        return $parser->getLines();
    }
}
