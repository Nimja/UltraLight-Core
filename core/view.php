<?php
namespace Core;
/**
 * The basic view function, containing possibilities for filling variables into a another string.
 */
class View
{
    /**
     * Variables like +varname:operator+.
     *
     * Valid characters are: letters, numbers, dot, dash, underscore and colon.
     */
    const PREG_VARIABLES = '/\+([a-zA-Z0-9\.\_\-\:]+)\+/';
    const VAR_PREFIX = '+';
    const VAR_SUFFIX = '+';
    const TRANSFORM = ':';
    /**
     * List of included files
     * @var array
     */
    private static $_included = array();
    /**
     * The current instance.
     * @var \Core\View
     */
    private static $_instance;

    /**
     * Get the current instance.
     * @return \Core\View
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        //Only allow this to be instanced.
    }

    /**
     * Enter a string and an associative array of values.
     * @param string $string
     * @param array $values
     * @return string
     */
    public function fillValues($string, $values)
    {
        $variables = self::getVariables($string);
        if (!empty($variables)) {
            $translate = $this->getTranslateArray($variables, $values);
            //str_replace like this is about 30% faster than strtr.
            $string = str_replace(array_keys($translate), $translate, $string);
        }
        return $string;
    }

    /**
     * Get any variables present in the string.
     * @param string $string
     * @return null|array
     */
    public function getVariables($string)
    {
        $matches = null;
        preg_match_all(self::PREG_VARIABLES, $string, $matches);
        return !empty($matches) ? array_unique($matches[1]) : null;
    }

    /**
     * Create a translation array for this.
     *
     * This function is recursive together with fillValues, used when the values contain a placeholder.
     *
     * @param array $variables
     * @param array $values
     * @return array
     */
    public function getTranslateArray($variables, $values)
    {
        $translate = array();
        foreach ($variables as $name) {
            // First translate the name of the variable, if need be.
            if (strpos($name, self::TRANSFORM) !== false) {
                $commands = explode(self::TRANSFORM, $name);
                $varname = array_shift($commands);
                $transform = array_shift($commands);
            } else {
                $transform = false;
                $varname = $name;
            }
            $value = getKey($values, $varname, '');
            if (self::hasVars($value)) {
                $value = $this->fillValues($value, $values);
            }
            if ($transform && !empty($value)) {
                $value = $this->_transform($transform, $value, $commands);
            }
            $translate[self::VAR_PREFIX . $name . self::VAR_SUFFIX] = $value;
        }
        return $translate;
    }

    /**
     * Load a view and fill the data.
     *
     * @param string $view
     * @param array $data
     * @return string Translated view.
     */
    public function show($view, $data = null)
    {
        if (!empty($data) && !is_array($data)) {
            Show::error($data, 'Variable is not an array');
            return false;
        }
        //Make sure data is an array and filled with default values.
        $data = $data ? : array();
        $site_vars = \Config::system()->section('site');
        foreach ($site_vars as $key => $value) {
            $data['site.' . $key] = $value;
        }
        // Load the template.
        $view = $this->loadView($view);
        // Fill in the data if all goes well.
        return empty($view) ? false : $this->fillValues($view, $data);
    }

    /**
     * Load template file or reuse the one in memory.
     *
     * @param string $file
     * @return string content
     */
    public function loadView($file)
    {
        $fileName = 'view/' . \Sanitize::fileName($file) . '.html';
        $fileSrc = PATH_APP . $fileName;
        if (isset(self::$_included[$fileName])) {
            $result = self::$_included[$fileName];
        } else {
            if (!file_exists($fileSrc)) {
                throw new \Exception("View not found for $file");
            }
            $result = file_get_contents($fileSrc);
            \Core::debug($fileName, 'Loading view');
            self::$_included[$fileName] = $result;
        }
        if (empty($result)) {
            throw new \Exception("View empty for $file");
        }
        return $result;
    }

    /**
     * Transform a string using a common function.
     * @param string $function
     * @param string $string
     * @param array $commands The remainder of the commands.
     * @return string
     */
    private function _transform($function, $string, $commands)
    {
        switch ($function) {
            case 'ucfirst':
                $result = ucfirst($string);
                break;
            case 'upper':
                $result = strtoupper($string);
                break;
            case 'lower':
                $result = strtolower($string);
                break;
            default:
                $class = '\\View\\Transform\\' . ucfirst($function);
                if (\Core::loadClass(\Core::NAMESPACE_CORE . $class, true)) {
                    $class = \Core::NAMESPACE_CORE . $class;
                } else if (\Core::loadClass(\Core::NAMESPACE_APP . $class, true)) {
                    $class = \Core::NAMESPACE_APP . $class;
                } else {
                    throw new \Exception("Unable to find transformer for $class");
                }
                $transformer = new $class($commands, $string);
                $result = $transformer->parse();
                $commands = $transformer->getCommands();
                break;
        }
        if (!empty($commands)) {
            $nextCommand = array_shift($commands);
            $result = $this->_transform($nextCommand, $result, $commands);
        }
        return $result;
    }

    /**
     * Quick check if a string contains variables.
     * @param string $string
     * @return boolean
     */
    public static function hasVars($string)
    {
        return preg_match(self::PREG_VARIABLES, $string);
    }
}