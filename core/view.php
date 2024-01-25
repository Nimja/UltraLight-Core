<?php

namespace Core;

/**
 * The basic view function, containing possibilities for filling variables into a another string.
 */
class View
{
    /**
     * Variables like __varname:operator__ or legacy with +s.
     *
     * Valid characters are: letters, numbers, dot, dash, underscore and colon.
     */
    const REGEX_VARIABLES = '/\+([a-zA-Z0-9\.\_\-\:]+)\+|__([a-zA-Z0-9\.\_\-\:]+)__/U';
    /**
     * The transform/value separator.
     */
    const TRANSFORM = ':';
    /**
     * How to escape characters.
     */
    const ESCAPING = [
        '+' => '-|',
        '__' => '**|',
    ];
    /**
     * List of included files
     * @var array
     */
    private static $_included = [];
    /**
     * The current instance.
     * @var \Core\View
     */
    private static $_instance;

    /**
     * Breadcrumbs to avoid recursion.
     *
     * @var array
     */
    private $breadcrumbs = [];

    /**
     * Errors for missing varnames.
     *
     * @var array
     */
    private $errors = [];

    /**
     * Errors for missing varnames.
     *
     * @var array
     */
    private static $reported = [];

    /**
     * The current transformers, instantiated when needed.
     *
     * @var array
     */
    private static $_transformers = [
        'glitch' => \Core\View\Transform\Glitch::class,
        'limit' => \Core\View\Transform\Limit::class,
        'math' => \Core\View\Transform\Math::class,
        'number' => \Core\View\Transform\Number::class,
        'url' => \Core\View\Transform\Url::class,
        'random' => \Core\View\Transform\Random::class,
    ];
    /**
     * Instantiated transformers.
     * @var array
     */
    private static $_transformerInstances = [];
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
     * Get all variables present in a string.
     *
     * @param string $string
     * @return array
     */
    function getVariables($string)
    {
        $matches = null;
        if (preg_match_all(self::REGEX_VARIABLES, $string, $matches)) {
            $all_matches = array_merge($matches[1], $matches[2]);
            return array_values(array_unique(array_filter($all_matches)));
        }
        return [];
    }

    /**
     * Fill values in a string.
     *
     * @param string $string
     * @param array $values
     * @return string
     */
    public function fillValues($string, $values)
    {
        if (empty($string)) { // If there is nothing to replace, do nothing.
            return $string;
        }
        $result = preg_replace_callback(
            self::REGEX_VARIABLES,
            function ($matches) use ($values) {
                return $this->translateValue($matches, $values);
            },
            $string
        );
        $this->errors = array_diff($this->errors, self::$reported);
        if (!empty($this->errors)) {
            $varNames = implode(', ', $this->errors);
            error_log("$varNames: NOT FOUND in " . \Core::$requestUrl . PHP_EOL . \Show::getTraceString(1));
            self::$reported = array_merge(self::$reported, $this->errors);
            $this->errors = [];
        }
        return $result;
    }

    /**
     * Translate value, used as regex callback.
     *
     * @param array $matches
     * @param array $values
     * @return void
     */
    private function translateValue(array $matches, array $values)
    {
        $name = count($matches) == 3 ? $matches[2] : $matches[1];
        // First get the name and possible transforms.
        if (strpos($name, self::TRANSFORM) !== false) {
            $commands = explode(self::TRANSFORM, $name);
            $varName = array_shift($commands);
            $transform = array_shift($commands);
        } else {
            $transform = false;
            $varName = $name;
        }
        if (!array_key_exists($varName, $values)) {
            $this->errors[] = $varName;
            return $matches[0]; // Return the original string.
        }
        if (array_key_exists($varName, $this->breadcrumbs)) {
            throw new \Exception("Recursion found in '{$varName}'");
        }
        // Set the breadcrumb to avoid recursion.
        $this->breadcrumbs[$varName] = true;
        $result = $this->fillValues($values[$varName], $values);
        // Clear the breadcrumb.
        unset($this->breadcrumbs[$varName]);
        if ($transform && !blank($result)) {
            $result = $this->_transform($transform, $result, $commands);
        }
        return $result;
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
            \Show::error($data, 'Variable is not an array');
            return false;
        }
        //Make sure data is an array and filled with default values.
        $data = $data ?: [];
        $site_vars = \Config::system()->section('site');
        foreach ($site_vars as $key => $value) {
            $data['site.' . $key] = $value;
        }
        $data['site.page.route'] = \Core::$route;
        $data['site.page.rest'] = \Core::$rest;
        $data['site.page.url'] = \Core::$url;
        $data['site.page.requestUrl'] = \Core::$requestUrl;
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
                $transformer = self::_getTransformer($function);
                $result = $transformer->parse($commands, $string);
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
     * Get transformer instance.
     * @param string $type
     * @return \Core\View\Transform\Base
     * @throws \Exception
     */
    private static function _getTransformer($type)
    {
        if (empty(self::$_transformerInstances[$type])) {
            if (!isset(self::$_transformers[$type])) {
                throw new \Exception("No transformer registered for type: $type");
            }
            $class = self::$_transformers[$type];
            self::$_transformerInstances[$type] = new $class();
        }
        return self::$_transformerInstances[$type];
    }

    /**
     * Register transformer.
     * @param string $type
     * @param string $class
     */
    public static function registerTransformer($type, $class)
    {
        self::$_transformers[$type] = $class;
        unset(self::$_transformerInstances[$type]);
    }

    /**
     * Escape placeholder values.
     * @param string $string
     * @return string
     */
    public static function escape($string)
    {
        if (empty($string)) { // If there is nothing to replace, do nothing.
            return $string;
        }
        return str_replace(
            array_keys(self::ESCAPING),
            array_values(self::ESCAPING),
            $string
        );
    }

    /**
     * Unescape placeholder values.
     * @param string $string
     * @return string
     */
    public static function unescape($string)
    {
        return str_replace(
            array_values(self::ESCAPING),
            array_keys(self::ESCAPING),
            $string
        );
    }
}
