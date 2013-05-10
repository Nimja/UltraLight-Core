<?php
/**
 * The basic view function, containing possibilities for filling variables into a another string.
 */
class Library_View
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
     * The current instance.
     * @var Library_View
     */
    private static $_instance;

    /**
     * Get the current instance.
     * @return Library_View
     */
    public static function getInstance()
    {
        if (!self::$_instance) {
            self::$_instance = new Library_View();
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
            $transform = false;
            $varname = $name;
            // First translate the name of the variable, if need be.
            if (strpos($name, self::TRANSFORM) !== false) {
                list($varname, $transform) = explode(self::TRANSFORM, $name);
            }
            $value = getKey($values, $varname, '');
            if (self::hasVars($value)) {
                $value = $this->fillValues($value, $values);
            }
            if ($transform) {
                $value = $this->_transform($transform, $value, $name);
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
        $site_vars = Config::system()->section('site');
        foreach ($site_vars as $key => $value) {
            $data['site.' . $key] = $value;
        }
        // Load the template.
        $view = Core::loadView($view);
        // Fill in the data if all goes well.
        return empty($view) ? false : $this->fillValues($view, $data);
    }

    /**
     * Transform a string using a common function.
     * @param string $function
     * @param string $string
     * @param string $command The full command, allowing extra parameters after the second :.
     * @return string
     */
    private function _transform($function, $string, $command)
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
            case 'random': // Select random value from string, seperated by |.
                $parts = explode('|', $string);
                $value = $parts[array_rand($parts)];
                $result = trim($value);
                break;
            default:
                $class = 'Library_Transform_' . ucfirst($function);
                $result = $class::instance()->parse($command, $string);
                break;
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