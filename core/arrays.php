<?php
namespace Core;
/**
 * A very practical class for dealing with arrays, switching between dimensional and flat.
 *
 * Variations like a.b.c or a[b][c] are possible.
 *
 * Also includes nice recursive merging.
 */
class Arrays
{
    private $_prefix;
    private $_suffix;

    /**
     * Create cache instance for group.
     * @param string $group
     */
    public function __construct($prefix, $suffix = null)
    {
        $this->_prefix = $prefix;
        $this->_suffix = $suffix ?: '';
    }

    /**
     * Go from a flat array to a multidimensional array.
     * @param array $array
     * @return array
     */
    public function split($array) {
        foreach ($array as $realKey => $value) {
            if (strpos($realKey, $this->_prefix) === false) {
                continue;
            }
            unset($array[$realKey]);
            $parts = $this->_split($realKey);
            $key = array_shift($parts);
            if (!isset($array[$key])) {
                $array[$key] = array();
            }
            $array[$key] = self::mergeRecursive($array[$key], $this->_toRecursiveArray($parts, $value));
        }
        return $array;
    }

    /**
     * Create nice simple recursive array.
     * @param array $parts
     * @param string $value
     * @return array
     */
    private function _toRecursiveArray($parts, $value)
    {
        $key = array_shift($parts);
        $value = empty($parts) ? $value : $this->_toRecursiveArray($parts, $value);
        return array($key => $value);
    }

    /**
     * Split the key into parts.
     * @param string $key
     * @return array
     */
    private function _split($key)
    {
        if (!empty($this->_suffix)) {
            $key = str_replace($this->_suffix, '', $key);
        }
        $result = explode($this->_prefix, $key);
        return $result;
    }


    /**
     * Go from a flat array to a multidimensional array.
     * @param array $array
     * @return array
     */
    public function join($array) {
        foreach ($array as $realKey => $value) {
            if (!is_array($value)) {
                continue;
            }
            unset($array[$realKey]);
            $this->_fromRecursiveArray($array, $realKey, $value);
        }
        return $array;
    }

    /**
     * Create nice simple recursive array.
     * @param array $parts
     * @param string $value
     * @return array
     */
    private function _fromRecursiveArray(&$array, $prevKey, $values)
    {
        if (empty($values)) {
            return;
        }
        foreach ($values as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $curKey = "$prevKey{$this->_prefix}{$key}{$this->_suffix}";
            if (is_array($value)) {
                $this->_fromRecursiveArray($array, $curKey, $value);
            } else {
                $array[$curKey] = $value;
            }
        }
    }

    /**
     * Recursively merge arrays, as the PHP function does not overwrite values.
     *
     * The right (second) value overrides the left (first) value.
     *
     * @param mixed $left
     * @param mixed $right
     * @return mixed
     */
    public static function mergeRecursive($left, $right)
    {
        // merge arrays if both variables are arrays
        if (is_array($left) && is_array($right)) {
            // loop through each right array's entry and merge it into $a
            foreach ($right as $key => $value) {
                if (is_int($key)) {
                    $left[] = $value;
                } else if (isset($left[$key])) {
                    $left[$key] = self::mergeRecursive($left[$key], $value);
                } else {
                    $left[$key] = $value;
                }
            }
        } else {
            // one of values is not an array
            $left = $right;
        }
        return $left;
    }
}