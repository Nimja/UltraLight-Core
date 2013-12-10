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
     * Current values from the config file.
     * @var array
     */
    private $_values;

    /**
     * Create a config object, using an config file.
     * @param string $file
     * @param int $depth
     */
    public function __construct($file, $depth = 99)
    {
        $this->_values = self::parseConfig($file, $depth);
    }

    /**
     * Get a section from the settings.
     * @param string $section
     * @return mixed
     */
    public function section($section)
    {
        return getKey($this->_values, $section, array());
    }

    /**
     * Return the sections in this config.
     * @return array
     */
    public function sections()
    {
        return array_keys($this->_values);
    }

    /**
     * See if a section (and key) exists.
     * @param string $section
     * @param string $key
     */
    public function exists($section, $key = null)
    {
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
    public function get($section, $key, $default = null)
    {
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
    public function set($section, $key, $value)
    {
        $this->_values[$section][$key] = $value;
    }

    /**
     * Add config file to the current settings.
     * @param type $file
     */
    public function add($file, $depth = 99)
    {
        $settings = self::parseConfig($file, $depth);
        $this->_values = self::mergeRecursive($this->_values, $settings);
    }

    /**
     * Get the system settings (or load more through config file).
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
            self::_loadSystemDefines();
            self::_loadPHPSettings();
            Core::debug($file, "Config loaded.");
        }
        return self::$_system;
    }

    /**
     * Put stuff in [system] block into the definitions, as uppercase.
     */
    private static function _loadSystemDefines()
    {
        $values = self::system()->section('system');
        if (isset($values['debug'])) {
            Core::$debug = !empty($values['debug']);
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
    private static function _loadPHPSettings()
    {
        $values = self::system()->section('php');
        $ini = getKey($values, 'ini');
        if (is_array($ini)) {
            foreach ($ini as $key => $value) {
                ini_set($key, $value);
            }
        }
    }

    /**
     *
     * @param string $file
     * @param type $depth
     */
    public static function parseConfig($file, $depth = 99)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'ini':
                $parser = '\Core\Parse\Ini';
                break;
            case 'yaml':
            case 'yml':
                $parser = '\Core\Parse\Yaml';
                break;
            default:
                throw new Exception("No parser for: $extension");
        }
        return \Core::wrapCache(array($parser, 'parse'), array($file, $depth), filemtime($file));
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
