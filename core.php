<?php
/**
 * Make sure PATH_VENDOR is always defined, it is an optional path.
 */
if (!defined('PATH_VENDOR')) {
    define('PATH_VENDOR', PATH_CORE . 'vendor/');
}

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
 * Nice way to get an 'unknown' value from an array without having inline iffs everywhere.
 * @param array $array
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function getKey(&$array, $key, $default = false)
{
    if (!is_array($array)) {
        throw new \Exception("Not an array.");
    }
    return isset($array[$key]) && !blank($array[$key]) ? $array[$key] : $default;
}

/**
 * Nice way to get an 'unknown' value from an object without having inline iffs everywhere.
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
     * The main initialization function, can only be called ONCE!
     */
    public static function start()
    {
        self::$start = microtime(true);
        if (!empty(self::$_included)) {
            throw new \Exception("Core::start() called manually!");
        }
        $core = new self();
        try {
            header('X-Powered-By: UltraLight');
            header('Server: UltraLight');
            $page = $core->_startSession()
                ->_setSiteUrl()
                ->_setRemoteIp()
                ->_loadPage();
            $page->display();
        } catch (\Exception $e) {
            Show::error($e);
        }
        self::debug(self::$classes, 'Loaded classes');
        self::debug('Done.');
    }

    /**
     * Private constructor, to set up basic environment.
     */
    private function __construct()
    {
        self::$_useCache = (defined('PATH_CACHE') && is_writable(PATH_CACHE));
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
            $fileName = PATH_VENDOR . str_replace('\\', '/', trim($class, './\\ '));
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
     *
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
        if (!Config::system()->exists('site', 'url')) {
            $site_url = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $site_url .= $_SERVER['HTTP_HOST'] . '/';
            Config::system()->set('site', 'url', $site_url);
        }
        return $this;
    }

    /**
     * Set the REMOTE_IP constant, the visitor's IP.
     *
     * Full support for ipv6.
     */
    private function _setRemoteIp()
    {
        $remote_addr = $_SERVER['REMOTE_ADDR'];
        /**
         * There may be multiple comma-separated IPs for the X-Forwarded-For header
         * if the traffic is passing through more than one explict proxy.  Take the
         * last one as being valid.  This is arbitrary, but there is no way to know
         * which IP relates to the client computer.  We pick the first client IP as
         * this is the client closest to our upstream proxy.
         */
        if (( $remote_addr == '127.0.0.1' || $remote_addr == $_SERVER['SERVER_ADDR'] ) && $_SERVER['HTTP_X_FORWARDED_FOR']) {
            $remote_addrs = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $remote_addr = $remote_addrs[0];
        }
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
        $request = getKey($_SERVER, 'REQUEST_URI');
        $uri = parse_url($request, PHP_URL_PATH);
        //Remove trailing, leading and double slashes.
        $clean = preg_replace('/\/{2,}/', '/', trim($uri, '/ '));
        $clean2 = preg_replace('/[^a-z0-9\/\.]/', '', strtolower($clean));
        $final = str_replace('%20', '+', $clean2);
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
            if ($this->_getControllerFileName($cur)) {
                $load = $cur;
            } elseif (!empty($routes[$cur])) {
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
            throw new \Exception("Unable to find file: $request");
        }

        $extension = pathinfo($url, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'css': $mime = 'text/css';
                break;
            case 'js': $mime = 'text/javascript';
                break;
            default: $mime = 'text/plain';
        }

        //Add minify/output_same here in the future.
        Request::output($mime, $original, filemtime($original), null, true);
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
    public static function wrapCache($callable, $args, $time = 0)
    {
        if (!is_string($callable)) {
            throw new \Exception("Please use \Class::method for wrapCache.");
        }
        if (self::$_useCache) {
            $key = strval(implode('_', $args));
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
}
