<?php namespace Core\Parse;
/**
 * Ini parser, parses an ini file to associative array.
 */
class Ini
{
    private static $_depth = 99;

    /**
     * Set depth for parsing.
     * @param type $depth
     */
    public static function setDepth($depth = 99)
    {
        self::$_depth = $depth;
    }

    /**
     * Parse ini string.
     * @param type $string
     * @return array
     */
    public static function parseString($string)
    {
        $ini = parse_ini_string($string, true);
        return self::_parse($ini);
    }

    /**
     * Parse ini file.
     * @param type $fileName
     * @return array
     */
    public static function parse($fileName)
    {
        $ini = parse_ini_file($fileName, true);
        return self::_parse($ini);
    }

    /**
     * Simple parser for INI files with a very clean approach.
     *
     * This parser supports multiple inheritance from other sections.
     *
     * @param array $ini
     * @return array
     * @throws \Exception
     */
    private static function _parse($ini)
    {
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
                $curSection = self::_processSection($values, self::$_depth);
                for ($i = 0; $i < $max; $i++) {
                    $curName = trim($expand[$i]);
                    if (!isset($result[$curName])) {
                        throw new \Exception("Unable to expand $section from $curName");
                    }
                    $curSection = \Core\Arrays::mergeRecursive($result[$curName], $curSection);
                }
                $result[$section] = $curSection;
            } else {
                $result[$section] = self::_processSection($values, self::$_depth);
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