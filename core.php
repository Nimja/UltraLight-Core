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
     * Request url, WITH get parameters.
     * @var string
     */
    public static $requestFull = '';
    /**
     * Request url, WITHOUT get parameters.
     * @var string
     */
    public static $requestUrl = '';
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
    public static $classes = [];
    /**
     * If we are currently on HTTPS.
     *
     * @var boolean
     */
    public static $isHttps = false;
    /**
     * Enable debugging on the fly.
     * @var boolean
     */
    public static $debug = false;

    /**
     * If we are running from console.
     *
     * @var boolean
     */
    public static $console = false;

    /**
     * If we use cache or not.
     *
     * @var boolean
     */
    private static $_useCache = false;

    /**
     * Output compression enabled or no.
     *
     * @var boolean
     */
    private static $_allowOutputCompression = false;

    /**
     * Server variables.
     *
     * @var array
     */
    private $_server;

    /**
     * The main initialization function, can only be called ONCE!
     */
    public static function start($pathOptions)
    {
        try {
            if (!empty(self::$start)) {
                throw new \Exception("Core::start() called manually!");
            }
            self::$start = microtime(true);
            self::$console = PHP_SAPI === 'cli';
            $core = new self($pathOptions);
            header('X-Powered-By: UltraLight');
            header('Server: UltraLight');
            $page = $core->_startSession()
                ->_setSiteUrl()
                ->_setRemoteIp()
                ->_loadPage();
            if ($page) {
                $page->display();
            }
        } catch (\Throwable $e) {
            if (self::$console) {
                throw $e;
            } else if (class_exists('\Show', true)) {
                Show::error($e, "Uncaught exception: " . get_class($e), false, Show::COLOR_FATAL);
            } else {
                echo '<pre>';
                echo $e->getMessage() . PHP_EOL . PHP_EOL;
                echo $e->getTraceAsString();
                echo '</pre>';
                exit;
            }
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
        $this->_server = filter_input_array(INPUT_SERVER);
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
            $options = ['base' => $options];
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
        if (is_writable($cachePath) && !self::$console) {
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
        return rtrim(realpath($path), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Set up the session.
     * @return \Core
     */
    private function _startSession()
    {
        if (!isset($_SESSION)) {
            //Set session timeout to be 1 hour.
            session_set_cookie_params(3600);
            session_start();
        }
        date_default_timezone_set(Config::system()->get('php', 'timezone', 'Europe/Paris'));
        mb_internal_encoding(Config::system()->get('php', 'encoding', 'UTF8'));
        return $this;
    }

    /**
     * Load the file for this class.
     * @param string $class
     * @return boolean True if class was loaded successfully.
     */
    public static function loadClass($class)
    {
        if (isset(self::$classes[$class])) {
            return true;
        }
        $parts = explode('\\', strtolower(trim($class, './\\ ')));
        $first = array_shift($parts);
        $nameSpace = '\\' . ucfirst($first);
        $error = null;
        //No namespace, only allowed for core base classes.
        if (empty($parts) && file_exists(PATH_CORE . $first . self::PHP_EXT)) {
            $fileName = PATH_CORE . $first;
        } else if ($nameSpace == self::NAMESPACE_CORE) {
            $fileName = PATH_CORE . 'core/' . implode(DIRECTORY_SEPARATOR, $parts);
        } else if ($nameSpace == self::NAMESPACE_APP) {
            $fileName = PATH_APP . implode(DIRECTORY_SEPARATOR, $parts);
        } else {
            $replace = strpos($class, '\\') !== false ? '\\' : '_';
            $fileName = PATH_VENDOR . str_replace($replace, DIRECTORY_SEPARATOR, trim($class, './\\ '));
        }
        $fileName .= self::PHP_EXT;
        if (!$error && !file_exists($fileName)) {
            $error = "Unable to load file: $fileName";
        }
        if (!$error) {
            require $fileName;
            self::$classes[$class] = ['file' => $fileName, 'time' => self::time()];
        } else {
            self::debug($error);
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
        self::$isHttps = $this->_requestIsSsl();
        if (!Config::system()->exists('site', 'url')) {
            $host = getKey($this->_server, 'HTTP_HOST');
            $site_url = self::$isHttps ? 'https://' : 'http://';
            $site_url .= $host . '/';
            Config::system()->set('site', 'url', $site_url);
            Config::system()->set('site', 'host', $host);
        } else {
            $site_url = Config::system()->get('site', 'url');
            //Make sure we have a trailing slash.
            if (substr($site_url, -1) !== '/') {
                $site_url .= '/';
                Config::system()->set('site', 'url', $site_url);
            }
            Config::system()->set('site', 'host', parse_url($site_url, PHP_URL_HOST));
        }
        return $this;
    }

    /**
     * Return true if on https.
     *
     * @return boolean
     */
    private function _requestIsSsl()
    {
        return getKey($this->_server, 'HTTPS') === 'on'
            || getKey($this->_server, 'HTTP_X_FORWARDED_PROTO') === 'https'
            || getKey($this->_server, 'HTTP_X_PROTO') === 'SSL'
            || getKey($this->_server, 'HTTP_X_PORT') === '443'
            || getKey($this->_server, 'HTTP_FRONT_END_HTTPS') === 'on'
            || getKey($this->_server, 'HTTPS', false) !== false;
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
        if (($remote_addr == '127.0.0.1' || $remote_addr == $server_addr) && $forwarded_for) {
            $ips = explode(',', $forwarded_for);
            $remote_addr = end($ips);
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
        if (self::$console) {
            $siteUrl = 'CONSOLE';
            $options = getopt('p:');
            $uri = ltrim(getKey($options, 'p', ''), '/');
        } else {
            $siteUrl = rtrim(Config::system()->get('site', 'url'), '/');
            self::$requestFull = $siteUrl . getKey($this->_server, 'REQUEST_URI');
            $uri = parse_url(self::$requestFull, PHP_URL_PATH);
            self::$requestUrl = $siteUrl . $uri;
        }
        // Prevent looping on url decoded urls.
        $uri = urldecode($uri);
        $originalUri = str_replace(' ', '+', $uri); // Keep the +s.
        // Remove leading, trailing and double slashes.
        $clean = preg_replace('/\/{2,}/', '/', trim(urldecode($uri), '/ '));
        // We unify the url to use + instead of %20.
        $clean2 = str_replace(' ', '+', $clean);
        // Remove /index.
        $final = str_replace('/index', '/', $clean2);

        // Original uri;
        if ($originalUri != '/' . $final && !self::$console) {
            Request::redirect($final, 302, true);
        }
        self::$url = $final;
        Config::system()->set('site', 'pageurl', $final);
        return empty($final) ? [] : explode('/', $final);
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
        $rest = [];
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
     * @param array $request
     */
    private function _outputStatic($request)
    {
        if (is_numeric($request[0])) {
            array_shift($request);
        }
        $load = new \Core\File\Load(implode('/', $request));
        $load->output();
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
            ini_set("zlib.output_compression", true);
        } else {
            ini_set("zlib.output_compression", false);
        }
    }

    /**
     * Does a redirect if desiredUrl is different from the current Url.
     * @param string $desiredUrl
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
     * Clean full paths from a string, improving readability.
     * @param string $string
     * @return string
     */
    public static function cleanPath($string)
    {
        return str_replace(
            [PATH_APP, PATH_CORE, PATH_ASSETS, PATH_BASE],
            ['APP/', 'CORE/', 'ASSETS/', 'BASE/'],
            $string
        );
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
    public static function wrapCache($callable, $args = [], $time = 0)
    {
        if (!is_string($callable)) {
            throw new \Exception("Please use \Class::method for wrapCache.");
        }
        if (self::$_useCache) {
            $key = \Core\Cache\File::createKey($args);
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
     *
     * @param string $callable The callable as a string.
     * @param array|boolean Arguments, or false for deleting all for this callable.
     * @return boolean
     */
    public static function clearCache($callable = null, $args = [])
    {
        $result = false;
        if (self::$_useCache) {
            if (!empty($callable)) {
                $cache = \Core\Cache\File::getInstance($callable);
                if ($args === false) {
                    $result = $cache->deleteAll();
                } else {
                    $result = $cache->delete(\Core\Cache\File::createKey($args));
                }
            } else {
                $result = \Core\File\System::rrmdir(PATH_CACHE, false);
            }
        }
        return $result;
    }
}
