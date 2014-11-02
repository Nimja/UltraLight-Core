<?php

/**
 * Replacement function for "empty", easier to type and returns true when var is "0" or 0
 *
 * @param string $var The variable you want to show.
 * @return boolean true when it is empty AND NOT numeric.
 */
function blank($var)
{
    return empty($var) && !is_numeric($var);
}

/**
 * Nice way to get an 'unknown' value from an array without having inline ifs everywhere.
 * @param array $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getKey($array, $key, $default = false)
{
    if (!is_array($array)) {
        throw new \Exception("Not an array.");
    }
    return isset($array[$key]) && !blank($array[$key]) ? $array[$key] : $default;
}

/**
 * Nice way to get an 'unknown' value from an object without having inline ifs everywhere.
 * @param object $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getAttr($obj, $attr, $default = false)
{
    if (!is_object($obj)) {
        throw new \Exception("Not an object.");
    }
    return isset($obj->$attr) && !blank($obj->$attr) ? $obj->$attr : $default;
}
/**
 * The core class for UltraLight.
 *
 * This prepares the autoloading, controller and a few safeties.
 */
class Core
{
    /**
     * The used PHP extension, must be idential accross project.
     */
    const PHP_EXT = '.php';
    /**
     * Namespace for the core library, only exception are the base classes (Config, Core, Request, Sanitize & Show).
     */
    const NAMESPACE_CORE = '\Core';
    /**
     * Namespace for the app.
     */
    const NAMESPACE_APP = '\App';
    /**
     * Part of the url that we use when using versioned JS/CSS
     */
    const URL_VERSION = 'version';
    /**
     * List of included files
     * @var string
     */
    private static $_included;
    /**
     * Actual request url.
     * @var string
     */
    public static $url = '';
    /**
     * Remainder of the url after routing. (url - route)
     * @var string
     */
    public static $rest = '';
    /**
     * Current controller we're loading.
     * @var string
     */
    public static $page = '';
    /**
     * THe route we used for this controller (url - rest);
     * @var string
     */
    public static $route = '';
    /**
     * Storing execution time.
     * @var float
     */
    public static $start = 0;
    /**
     * Loaded classes.
     * @var array
     */
    public static $classes = array();
    /**
     * Enable debugging on the fly.
     * @var boolean
     */
    public static $debug = false;
    /**
     * If we use cache or not.
     * @var boolean
     */
    private static $_useCache = false;

    /**
     * Output compression enabled or no.
     * @var boolean
     */
    private static $_allowOutputCompression = false;

    /**
     * Server variables.
     * @var array
     */
    private $_server;

    /**
     * The main initialization function, can only be called ONCE!
     */
    public static function start($pathOptions)
    {
        self::$start = microtime(true);
        if (!empty(self::$_included)) {
            throw new \Exception("Core::start() called manually!");
        }
        $core = new self($pathOptions);
        try {
            header('X-Powered-By: UltraLight');
            header('Server: UltraLight');
            $page = $core->_startSession()
                ->_setSiteUrl()
                ->_setRemoteIp()
                ->_loadPage();
            if ($page) {
                $page->display();
            }
        } catch (\Exception $e) {
            Show::error($e);
        }
        self::debug(self::$classes, 'Loaded classes');
        self::debug('Done.');
    }

    /**
     * Private constructor, to set up basic environment.
     * @param mixed $options
     */
    private function __construct($options = null)
    {
        $this->_server = $_SERVER ? : array();
        $this->_setOutputCompression();
        $this->_setPathConstants($options);
        spl_autoload_register('Core::loadClass');
        set_error_handler('Show::handleError', E_ALL);
        Config::system(PATH_CORE . 'config.ini');
        $appConfig = PATH_APP . 'config.ini';
        if (file_exists($appConfig)) {
            Config::system($appConfig);
        }
        self::$debug = Config::system()->get('system', 'debug', false);
    }

    /**
     * Set output compression, default on.
     */
    private function _setOutputCompression()
    {
        $accepted = explode(',', getKey($this->_server, 'HTTP_ACCEPT_ENCODING'));
        self::$_allowOutputCompression = in_array('gzip', $accepted);
        self::setOutputCompression(true);
    }

    /**
     * This function defines all the PATH constants we need.
     * @param type $options
     * @throws Exception
     */
    private function _setPathConstants($options)
    {
        $isArray = is_array($options);
        if (empty($options) || ($isArray && empty($options['base']))) {
            throw new Exception('Startup requires base path at least.');
        }
        if (!$isArray) {
            $options = array('base' => $options);
        }
        /**
         * Path to the core library, only used internally.
         */
        define('PATH_CORE', $this->_getRealPath(__DIR__));
        /**
         * Path to the vendor library, used to include vendor libraries.
         */
        define('PATH_VENDOR', $this->_getPathForConstant($options, 'vendor', PATH_CORE . 'vendor'));
        /**
         * Path to the index.php file. Normally not used.
         */
        define('PATH_BASE', $this->_getPathForConstant($options, 'base'));
        /**
         * Path to the application files. Used for automatic file inclusion..
         */
        define('PATH_APP', $this->_getPathForConstant($options, 'application', PATH_BASE . 'application'));
        /**
         * Path to the assets, javascript, style sheets, images, etc.
         */
        define('PATH_ASSETS', $this->_getPathForConstant($options, 'assets', PATH_BASE . 'assets'));
        $cachePath = getKey($options, 'cache', PATH_BASE . 'cache');
        if (is_writable($cachePath)) {
            /**
             * Path to the cache, only available if the cache is present.
             */
            define('PATH_CACHE', $this->_getRealPath($cachePath));
            self::$_useCache = true;
        }
    }

    /**
     * Set path constant for the application.
     * @param string $name
     * @param string $path
     * @throws Exception
     */
    private function _getPathForConstant($options, $name, $default = null)
    {
        $path = getKey($options, $name, $default);
        if (!file_exists($path)) {
            throw new Exception("Unable to define $name - $path does not exist.");
        }
        return $this->_getRealPath($path);
    }

    /**
     * Get the real path, with trailing slash.
     * @param string $path
     * @return string
     */
    private function _getRealPath($path)
    {
        return rtrim(realpath($path), '/') . '/';
    }

    /**
     * Set up the session.
     * @return \Core
     */
    private function _startSession()
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        date_default_timezone_set(Config::system()->get('php', 'timezone', 'Europe/Paris'));
        mb_internal_encoding(Config::system()->get('php', 'encoding', 'UTF8'));
        /**
         * Enforce the random numbers to be random, using microtime.
         */
        srand(microtime(true) * 10000 + getmypid());
        return $this;
    }

    /**
     * Load the file for this class.
     * @param string $class
     * @param boolean $returnSuccess Normally the class throws fatals, with this enabled you can scan for classes.
     * @return boolean if $returnSuccess is set to true, this will return the success.
     */
    public static function loadClass($class, $returnSuccess = false)
    {
        $parts = explode('\\', strtolower(trim($class, './\\ ')));
        $first = array_shift($parts);
        $nameSpace = '\\' . ucfirst($first);
        $error = null;
        //No namespace, only allowed for core base classes.
        if (empty($parts) && file_exists(PATH_CORE . $first . self::PHP_EXT)) {
            $fileName = PATH_CORE . $first;
        } else if ($nameSpace == self::NAMESPACE_CORE) {
            $fileName = PATH_CORE . 'core/' . implode('/', $parts);
        } else if ($nameSpace == self::NAMESPACE_APP) {
            $fileName = PATH_APP . implode('/', $parts);
        } else {
            $replace = strpos($class, '\\') !== false ? '\\' : '_';
            $fileName = PATH_VENDOR . str_replace($replace, '/', trim($class, './\\ '));
        }
        $fileName .= self::PHP_EXT;
        if (!$error && !file_exists($fileName)) {
            $error = "Unable to load file: $fileName";
        }
        if (!$error) {
            require $fileName;
            self::$classes[$class] = array('file' => $fileName, 'time' => self::time());
        }
        if ($error && !$returnSuccess) {
            \Show::fatal($class, $error);
        }
        return !$error;
    }

    /**
     * Returns controller if possible.
     * @return \Core\Controller
     */
    private function _loadPage()
    {
        $parts = $this->_getParsedUri();
        self::debug($parts, 'Parts, checking route.');
        $result = null;
        if (!empty($parts) && $parts[0] == self::URL_VERSION) {
            array_shift($parts);
            $this->_outputStatic($parts);
        } else {
            $controller = empty($parts) ? 'index' : $this->_getController($parts);
            self::debug($controller, 'Controller, loading page');
            $result = self::_loadController($controller);
        }
        return $result;
    }

    /**
     * Automatically set the site.url if not alreayd set.
     */
    private function _setSiteUrl()
    {
        $host = getKey($this->_server, 'HTTP_HOST');
        if (!Config::system()->exists('site', 'url')) {
            $https = getKey($this->_server, 'HTTPS');
            $site_url = !empty($https) ? 'https://' : 'http://';
            $site_url .= $host . '/';
            Config::system()->set('site', 'url', $site_url);
        }
        Config::system()->set('site', 'host', $host);
        return $this;
    }

    /**
     * Set the REMOTE_IP constant, the visitor's IP.
     *
     * Full support for ipv6.
     */
    private function _setRemoteIp()
    {
        $remote_addr = getKey($this->_server, 'REMOTE_ADDR');
        $server_addr = getKey($this->_server, 'SERVER_ADDR');
        $forwarded_for = getKey($this->_server, 'HTTP_X_FORWARDED_FOR');
        /**
         * There may be multiple comma-separated IPs for the X-Forwarded-For header
         * if the traffic is passing through more than one explict proxy.  Take the
         * last one as being valid.  This is arbitrary, but there is no way to know
         * which IP relates to the client computer.  We pick the first client IP as
         * this is the client closest to our upstream proxy.
         */
        if (( $remote_addr == '127.0.0.1' || $remote_addr == $server_addr ) && $forwarded_for) {
            $remote_addr = substr($forwarded_for, 0, strpos($forwarded_for, ','));
        }
        /**
         * Remote IP address.
         * @var string
         */
        define('REMOTE_IP', $remote_addr);
        //Limit access, for example on test sites.
        if (Config::system()->exists('limit')) {
            $limit = Config::system()->section('limit');
            $redirect = getKey($limit, 'redirect');
            $ips = getKey($limit, 'ips');
            if (!in_array(REMOTE_IP, $ips) && !empty($redirect)) {
                Request::redirect($redirect);
            }
        }
        return $this;
    }

    /**
     * Get the request parts.
     * @return array
     */
    private function _getParsedUri()
    {
        $siteUrl = Config::system()->get('site', 'url');
        $request = rtrim($siteUrl, '/') . getKey($this->_server, 'REQUEST_URI');
        $uri = parse_url($request, PHP_URL_PATH);
        //Remove /index.
        $withoutIndex = str_replace('/index', '/', strtolower($uri));
        //Remove leading, trailing and double slashes.
        $clean = preg_replace('/\/{2,}/', '/', trim(urldecode($withoutIndex), '/ '));
        //We only allow alphanumeric, underscores, dashes, periods and slashes.
        $clean2 = preg_replace('/[^a-z0-9\_\-\/\.]/', '', strtolower($clean));
        //We replace multiple periods by a single one.
        $clean3 = preg_replace('/\.{2,}/', '.', strtolower($clean2));
        //We unify the url to use + instead of %20.
        $final = str_replace('%20', '+', $clean3);
        if ($uri != '/' . $final) {
            Request::redirect($final);
        }
        self::$url = $final;
        Config::system()->set('site', 'pageurl', $final);
        return empty($final) ? array() : explode('/', $final);
    }

    /**
     * Get the controller for this request.
     * @param array $request
     * @return string
     */
    private function _getController($request)
    {
        $routes = Config::system()->section('routes');
        $load = '';
        $rest = array();
        // Route will be checked back to front, so /parent/child/sub is checked first, then /parent/child, etc.
        while (!empty($request) && empty($load)) {
            $cur = implode('/', $request);
            self::debug($cur, "Attempting path.");
            if ($this->_getControllerFileName($cur)) {
                $load = $cur;
            } else if ($this->_getControllerFileName($cur . '/index')) {
                $load = $cur . '/index';
            } else if (!empty($routes[$cur])) {
                $load = $routes[$cur];
                //Store which route we're taking.
                self::$route = $cur;
            }
            if (empty($load)) {
                //All we don't find, we put into the rest.
                array_unshift($rest, array_pop($request));
                self::debug($cur, 'Not found');
            }
        }
        //Put the remainder of the url in here.
        self::$rest = implode('/', $rest);
        if (empty($load)) {
            $load = !empty($routes['*']) ? $routes['*'] : '404';
        }
        return $load;
    }

    /**
     * Get the filename for a controller, if it exists.
     * @param string $controller
     * @return string|boolean
     */
    private function _getControllerFileName($controller)
    {
        $result = PATH_APP . "controller/{$controller}.php";
        if (!file_exists($result)) {
            $result = false;
        }
        return $result;
    }

    /**
     * Get the derived classname for a controller.
     * @param string $controller
     * @return string
     */
    private function _getControllerClass($controller)
    {
        $spaced = 'app controller ' . str_replace('/', ' ', $controller);
        return '\\' . str_replace(' ', '\\', ucwords($spaced));
    }

    /**
     * Output a static file, based on the rest of the request.
     *
     * The idea for this is that you can add /version/(any number)/
     * instead of /assets in front of CSS/JS files, and use a version number
     * in your code.
     *
     * @param type $request
     * @return type
     */
    private function _outputStatic($request)
    {
        if (is_numeric($request[0])) {
            array_shift($request);
        }
        $url = implode('/', $request);
        $original = PATH_ASSETS . $url;
        if (!file_exists($original)) {
            header('HTTP/1.0 404 Not Found', null, 404);
            throw new \Exception("Unable to find file: $url");
        }
        $extension = pathinfo($url, PATHINFO_EXTENSION);
        switch ($extension) {
            case 'css': $mimeType = 'text/css';
                break;
            case 'js': $mimeType = 'text/javascript';
                break;
            default: $mimeType = 'text/plain';
        }
        //Add minify/output_same here in the future.
        Request::outputFile($original, $mimeType);
    }

    /**
     * For noting debug messages, they are not shown (and Show is not loaded) if disabled.
     *
     * @param mixed $message
     * @param string $title
     */
    public static function debug($message, $title = 'Debug')
    {
        if (self::$debug) {
            Show::debug($message, self::time() . ' - ' . $title);
        }
    }

    /**
     * Include a page, can only be done once per page load!
     * @param string $controller
     * @return \Core\Controller
     */
    private function _loadController($controller)
    {
        $class = $this->_getControllerClass($controller);
        self::$_included = $class;
        self::$page = $controller;
        if (empty(self::$route)) {
            self::$route = $controller;
        }
        self::loadClass($class);
        if (!is_subclass_of($class, '\Core\Controller')) {
            throw new \Exception("Controller: \"{$controller}\" not extended from abstract controller.");
        }
        return $class::create();
    }

    /**
     * Enable output compression.
     * @param type $enable
     */
    public static function setOutputCompression($enable = true)
    {
        if ($enable && self::$_allowOutputCompression) {
            ini_set("zlib.output_compression", "On");
        } else {
            ini_set("zlib.output_compression", "Off");
        }
    }

    /**
     * Does a redirect if desiredUrl is different from the current Url.
     * @param type $desiredUrl
     */
    public static function forceUrl($desiredUrl = '')
    {
        $desiredUrl = ltrim($desiredUrl, '/');
        if ($desiredUrl != self::$url) {
            Request::redirect($desiredUrl);
        }
    }

    /**
     * Get the current time in microseconds from the start of this call.
     * @return type
     */
    public static function time()
    {
        return round(microtime(true) - self::$start, 5);
    }

    /**
     * Clean the paths from the filename.
     * @param type $fileName
     * @return type
     */
    public static function cleanPath($string)
    {
        $paths = array(
            PATH_APP => 'APP/',
            PATH_CORE => 'CORE/',
            PATH_BASE => 'BASE/',
            PATH_ASSETS => 'ASSETS/',
        );
        return strtr($string, $paths);
    }

    /**
     * Wrap a function for caching.
     *
     * The beauty here is that the cache class will not be loaded if not needed.
     * @param string $callable Using \Class::method
     * @param array $args
     * @param int $time
     * @return mixed
     */
    public static function wrapCache($callable, $args = array(), $time = 0)
    {
        if (!is_string($callable)) {
            throw new \Exception("Please use \Class::method for wrapCache.");
        }
        if (self::$_useCache) {
            $key = !empty($args) ? strval(implode('_', $args)) : 'call';
            $cache = \Core\Cache\File::getInstance($callable);
            $result = $cache->load($key, $time);
            if (empty($result)) {
                $result = call_user_func_array($callable, $args);
                $cache->save($key, $result);
            }
        } else {
            $result = call_user_func_array($callable, $args);
        }
        return $result;
    }

    /**
     * Clear cache, either specifically or completely.
     * @return boolean
     */
    public static function clearCache($callable = null, $args = array())
    {
        $result = false;
        if (self::$_useCache) {
            if (!empty($callable)) {
                $cache = \Core\Cache\File::getInstance($callable);
                $key = !empty($args) ? strval(implode('_', $args)) : 'call';
                $result = $cache->delete($key);
            } else {
                $result = \Core\File\System::rrmdir(PATH_CACHE, false);
            }
        }
        return $result;
    }
}