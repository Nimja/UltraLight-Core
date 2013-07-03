<?php
/**
 * The configuration class; loading ini files. Can be used by other classes as well and maintains a global
 * settings array.
 */
class Config
{
    /**
     * System (global) settings.
     * @var \Config
     */
    private static $_system;

    /**
     * Current values from the ini file.
     * @var array
     */
    private $_values;

    /**
     * Create a config object, using an ini file.
     * @param string $file
     * @param int $depth
     */
    public function __construct($file, $depth = 99)
    {
        $this->_values = self::parseIni($file, $depth);
    }

    /**
     * Get a section from the settings.
     * @param string $section
     * @return mixed
     */
    public function section($section) {
        return getKey($this->_values, $section, array());
    }

    /**
     * Return the sections in this config.
     * @return array
     */
    public function sections() {
        return array_keys($this->_values);
    }

    /**
     * See if a section (and key) exists.
     * @param string $section
     * @param string $key
     */
    public function exists($section, $key = null) {
        $result = isset($this->_values[$section]);
        if ($result && $key) {
            $result = isset($this->_values[$section][$key]);
        }
        return $result;
    }

    /**
     * Get a specific value from the settings.
     * @param string $section
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get($section, $key, $default = null) {
        $section = $this->section($section);
        return getKey($section, $key, $default);
    }

    /**
     * Set a specific value from the settings.
     * @param string $section
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set($section, $key, $value) {
        $this->_values[$section][$key] = $value;
    }

    /**
     * Add ini file to the current settings.
     * @param type $file
     */
    public function add($file) {
        $settings = self::parseIni($file);
        $this->_values = self::_mergeRecursive($this->_values, $settings);
    }

    /**
     * Get the system settings (or load more through ini file).
     * @param string|null $file
     * @return \Config
     */
    public static function system($file = null)
    {
        if (!empty($file)) {
            if (self::$_system) {
                self::$_system->add($file);
            } else {
                self::$_system = new self($file);
            }
        }
        return self::$_system;
    }

    /**
     * Put stuff in [system] block into the definitions, as uppercase.
     */
    public static function loadSystemDefines() {
        $values = self::system()->section('system');
        if (isset($values['debug'])) {
            Core::$debug = ($values['debug']);
            unset($values['debug']);
        }
        foreach ($values as $key => $value) {
            $key = strtoupper($key);
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }

    /**
     * Load the ini settings we're overriding.
     */
    public static function loadPHPSettings() {
        $values = self::system()->section('php');
        $ini = getKey($values, 'ini');
        if (is_array($ini)) {
            foreach ($ini as $key => $value) {
                ini_set($key, $value);
            }
        }
    }

    /**
     * Simple parser for INI files with a very clean approach.
     *
     * This parser supports multiple inheritance from other sections.
     *
     * @param string $filename
     * @param int $depth How deep you want the recursive parsing to be.
     * @return array
     * @throws Exception
     */
    public static function parseIni($filename, $depth = 99)
    {
        $ini = parse_ini_file($filename, true);
        if ($ini === false) {
            throw new Exception('Unable to parse ini file.');
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
                        throw new Exception("Unable to expand $section from $curName");
                    }
                    $curSection = self::_mergeRecursive($result[$curName], $curSection);
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

    /**
     * Recursively merge arrays, as the PHP function does not overwrite values.
     *
     * The right (second) value overrides the left (first) value.
     *
     * @param mixed $left
     * @param mixed $right
     * @return mixed
     */
    private static function _mergeRecursive($left, $right)
    {
        // merge arrays if both variables are arrays
        if (is_array($left) && is_array($right)) {
            // loop through each right array's entry and merge it into $a
            foreach ($right as $key => $value) {
                if (is_int($key)) {
                    $left[] = $value;
                } else if (isset($left[$key])) {
                    $left[$key] = self::_mergeRecursive($left[$key], $value);
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
