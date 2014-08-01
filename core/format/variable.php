<?php
namespace Core\Format;
/**
 * Basic String to HTML formatting class.
 *
 */
class Variable
{
    private $_lines = array();
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
     * @param boolean $array When we are in an array, lines end with a comma.
     */
    private function _addVariable($variable, $depth = 0, $parent = null, $array = false)
    {
        $type = gettype($variable);
        switch ($type) {
            case 'boolean': $this->_addLine($variable ? 'true' : 'false', $depth, $parent, $array);
                break;
            case 'double': $this->_addLine("{$variable}", $depth, $parent, $array);
                break;
            case 'integer':$this->_addInt($variable, $depth, $parent, $array);
                break;
            case 'string':$this->_addString($variable, $depth, $parent, $array);
                break;
            case 'array':$this->_addArray($variable, $depth, $parent, $array);
                break;
            case 'object': $this->_addObject($variable, $depth, $parent, $array);
                break;
            case 'resource': $this->_addLine($variable, $depth, $parent, $array);
                break;
            default: $this->_addLine('UNKNOWN: ' . var_export($variable, true), $depth, $parent, $array);
        }
    }

    /**
     * Add integer.
     * @param integer $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $array
     */
    private function _addInt($variable, $depth, $parent, $array)
    {
        //Assume date when handling big integers (> 1985).
        if ($variable > 500000000) {
            $variable .= date(' (Y-m-d H:i:s)', $variable);
        }
        $this->_addLine($variable, $depth, $parent, $array);
    }

    /**
     * Add string.
     * @param string $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $array
     */
    private function _addString($variable, $depth, $parent, $array)
    {
        $lines = explode(PHP_EOL, $variable);
        $lastIndex = count($lines) - 1;
        foreach ($lines as $index => $line) {
            $isLastIndex = ($lastIndex == $index);
            $isArray = $isLastIndex ? $array : false;
            $eol = $isLastIndex ? '' : '.';
            $this->_addLine("\"{$line}\"$eol", $depth, $parent, $isArray);
            if ($index == 0) {
                $depth += 2;
                $parent = '';
            }
        }
    }

    /**
     * Add array.
     * @param array $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $array
     */
    private function _addArray($variable, $depth, $parent, $array)
    {
        $count = count($variable);
        $this->_addLine("array ({$count}) (", $depth, $parent, false);
        foreach ($variable as $key => $value) {
            $this->_addVariable($value, $depth + 1, $key, true);
        }
        $this->_addLine(')', $depth, null, $array);
    }

    /**
     * Add object.
     * @param object $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $array
     */
    private function _addObject($variable, $depth, $parent, $array)
    {
        if ($variable instanceof \Exception) {
            $this->_addLine("Exception: ", $depth, $parent, false);
            $this->_addLine("file: {$variable->getFile()}", $depth, $parent, false);
            $this->_addLine("line: {$variable->getLine()}", $depth, $parent, false);
            $this->_addLine("message: {$variable->getMessage()}", $depth, $parent, false);
        } else {
            $this->_addGenericObject($variable, $depth, $parent, $array);
        }
    }

    /**
     * Add generic object.
     * @param object $variable
     * @param int $depth
     * @param string $parent
     * @param boolean $array
     */
    private function _addGenericObject($variable, $depth, $parent, $array)
    {
        $class = get_class($variable);
        $this->_addLine("$class {", $depth, $parent, false);
        foreach ($variable as $key => $value) {
            $this->_addVariable($value, $depth + 1, $key, false);
        }
        $this->_addLine('}', $depth, null, $array);
    }

    /**
     * Add line to the result.
     * @param string $string
     * @param int $depth
     * @param string $parent
     */
    private function _addLine($string, $depth, $parent, $array)
    {
        $prefix = '';
        if (is_numeric($parent) || $parent) {
            $parent = !is_numeric($parent) ? "'{$parent}'" : $parent;
            $prefix = "{$parent} => ";
        }
        $suffix = $array ? ',' : '';
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
     * @param mixed $str
     * @return array
     */
    public static function parse($variable)
    {
        $parser = new self($variable);
        return $parser->getLines();
    }
}