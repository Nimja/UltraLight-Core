<?php
namespace Core\Parse;
/**
 * Ini parser, parses an ini file to associative array.
 */
class Ini
{

    /**
     * Simple parser for INI files with a very clean approach.
     *
     * This parser supports multiple inheritance from other sections.
     *
     * @param string $filename
     * @param int $depth How deep you want the recursive parsing to be.
     * @return array
     * @throws \Exception
     */
    public static function parse($filename, $depth = 99)
    {
        $ini = parse_ini_file($filename, true);
        if ($ini === false) {
            throw new \Exception('Unable to parse ini file.');
        }
        $result = array();
        foreach ($ini as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            unset($ini[$section]);
            $expand = explode(':', $section);
            $section = trim(array_shift($expand));
            $max = count($expand);
            if ($max > 0) {
                $curSection = self::_processSection($values, $depth);
                for ($i = 0; $i < $max; $i++) {
                    $curName = trim($expand[$i]);
                    if (!isset($result[$curName])) {
                        throw new \Exception("Unable to expand $section from $curName");
                    }
                    $curSection = \Core\Arrays::mergeRecursive($result[$curName], $curSection);
                }
                $result[$section] = $curSection;
            } else {
                $result[$section] = self::_processSection($values, $depth);
            }
        }
        $result += $ini;
        return $result;
    }

    /**
     * Process a single section with values.
     * @param array $values
     * @return array With the result.
     */
    private static function _processSection($values, $depth = 99)
    {
        $result = array();
        foreach ($values as $key => $value) {
            $keys = explode('.', $key, $depth);
            $result = self::_recurseValue($result, $keys, $value);
        }
        return $result;
    }

    /**
     * Create the values recursively.
     * @param array $array
     * @param array $keys
     * @param mixed $value
     * @return array The original array, with changes.
     */
    private static function _recurseValue($array, $keys, $value)
    {
        $key = array_shift($keys);
        if (count($keys) > 0) {
            if (!isset($array[$key])) {
                $array[$key] = array();
            }
            $array[$key] = self::_recurseValue($array[$key], $keys, $value);
        } else {
            $array = self::_mergeValue($array, $key, $value);
        }
        return $array;
    }

    /**
     * Merge a value with the previous value.
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return array The original array, with changes.
     */
    private static function _mergeValue($array, $key, $value)
    {
        if (!isset($array[$key])) {
            $array[$key] = $value;
        } else {
            if (is_array($value)) {
                $array[$key] += $value;
            } else {
                $array[$key][] = $value;
            }
        }
        return $array;
    }
}
