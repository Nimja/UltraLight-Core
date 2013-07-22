<?php
/**
 * Enforce the random numbers to be random, using microtime.
 *
 * I've found this to be a problem on some servers who reused processes, meaning the standard PHP srand will
 * repeat the same random structure within the same second, for the same user.
 */
srand(microtime(true) * 10000 + getmypid());

/**
 * Where the major magic happens. This block ensures that classes are loaded on
 * demand, completely automatically.
 *
 * By putting PATH_APP first, it will check there before the core.
 */
set_include_path(get_include_path() . PATH_SEPARATOR . PATH_APP . PATH_SEPARATOR . PATH_CORE);
spl_autoload_register(function ($class) {
        $filename = str_replace('_', '/', strtolower(trim($class, './ '))) . '.php';
        $file = stream_resolve_include_path($filename);
        if ($file == false) {
            Show::fatal($class, 'Attempted include of a class that does not exist.');
        }
        require_once $file;
        if (!class_exists($class, false)) {
            Show::fatal($filename, "File exists, but class $class not found.");
        }
        Core::$classes[] = $class;
    });
/**
 * Set error handler for when certain errors fall through.
 */
set_error_handler(function ($errNo, $errStr, $errFile, $errLine) {
        Show::error("[$errLine] $errFile", $errStr);
    }, E_ALL);

/**
 * Start session and get config.
 */
session_start();
Config::system(PATH_CORE . 'config.ini');
date_default_timezone_set(Config::system()->get('php', 'timezone', 'Europe/Paris'));
mb_internal_encoding('UTF-8');

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
        throw new Exception("Not an array.");
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
        throw new Exception("Not an object.");
    }
    return isset($obj->$attr) && !blank($obj->$attr) ? $obj->$attr : $default;
}

/**
 * Sanitation function to run on POST/GET variables.
 *
 * @param mixed $value The value you wish to sanitize.
 * @param booelan|array $keepTags
 * @return string Sanitized string.
 */
function sanitize($value, $keepTags = true)
{
    if (blank($value)) {
        $result = null;
    } if (is_numeric($value)) {
        $result = $value;
    } else if (is_array($value)) {
        $result = array();
        foreach ($value as $key => $val) {
            $result[sanitize($key)] = sanitize($val);
        }
    } else {
        $stripHtml = is_array($keepTags) || empty($keepTags);
        //Remove magic quotes.
        $string = (ini_get('magic_quotes_gpc')) ? stripslashes($value) : $value;
        //fix euro symbol.
        $string = str_replace(chr(226) . chr(130) . chr(172), '&euro;', trim($string));
        $string = utf8_decode($string);
        $string = html_entity_decode($string, ENT_COMPAT, 'ISO-8859-15');
        if ($stripHtml) {
            $allowedTags = is_array($keepTags) ? $keepTags : null;
            $string = strip_tags($string, $allowedTags);
        }
        $result = htmlentities($string, ENT_COMPAT, 'ISO-8859-15');
    }
    return $result;
}

# Minimal class for loading libraries, templates, etc.

class Core
{
    /**
     * Part of the url that we use when using versioned JS/CSS
     */

    const URL_VERSION = 'version';

    /**
     * List of included files
     * @var array
     */
    private static $_included = array();

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
     * @var int
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
     * The main initialization function, can only be called ONCE!
     */
    public static function start()
    {
        if (!empty(self::$_included['page'])) {
            throw new Exception("Core::start() called manually!");
        }
        self::$start = microtime(true);
        Config::loadSystemDefines();
        Config::loadPHPSettings();

        self::_setSiteUrl();
        self::_setRemoteIp();
        $request = self::_getRequest();
        self::debug($request, 'Checking route');
        //Laod the default, static file or the relevant controller.
        if (empty($request)) {
            $load = 'index';
        } else if ($request[0] == self::URL_VERSION) {
            array_shift($request);
            self::outputStatic($request);
        } else {
            $load = self::_getController($request);
        }
        self::debug($load, 'Loading page');
        try {
            self::_loadController($load);
        } catch (Exception $e) {
            Show::error($e);
        }
        self::debug(self::$classes, 'Loaded classes');
        self::debug(round(microtime(true) - self::$start, 4), 'Execution time');
    }

    /**
     * Automatically set the site.url if not alreayd set.
     */
    private static function _setSiteUrl()
    {
        $config = Config::system()->section('site');
        if (!isset($config['url'])) {
            $site_url = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $site_url .= $_SERVER['HTTP_HOST'] . '/';
            Config::system()->set('site', 'url', $site_url);
        }
    }

    /**
     * Set the REMOTE_IP constant.
     */
    private static function _setRemoteIp()
    {
        #Get IP address of visitor.
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
            if (!in_array(REMOTE_IP, $ips)) {
                self::redirect($redirect);
            }
        }
    }

    /**
     * Get the request parts.
     * @return array
     */
    private static function _getRequest()
    {
        //Start logic to find what page we load (without starting slash)
        $request_uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $pos = strpos($request_uri, '?');
        if (!$pos) {
            $pos = strlen($request_uri);
        }
        $uri = parse_url(substr($request_uri, 0, $pos), PHP_URL_PATH);
        $request = preg_replace('/\/+/', '/', trim($uri, '/ '));
        //Redirect for double, trailing and leading slashes.
        if ($uri != '/' . $request) {
            Core::redirect($request);
        }
        //Store it away for other uses.
        self::$url = $request;
        //Array of url parts
        return !empty($request) ? explode('/', $request) : array();
    }

    /**
     * Get the controller for this request.
     * @param array $request
     * @return string
     */
    private static function _getController($request)
    {
        $routes = Config::system()->section('routes');
        $load = '';
        if (empty($routes)) {
            throw new Exception("Routes must be defined.");
        }
        $rest = array();
        // Route will be checked back to front, so /parent/child/sub is checked first, then /parent/child, etc.
        while (!empty($request) && empty($load)) {
            $cur = implode('/', $request);
            if (stream_resolve_include_path('controller/' . $cur . '.php')) {
                $load = $cur;
            } elseif (!empty($routes[$cur])) {
                $load = $routes[$cur];
                #Store which route we're taking.
                self::$route = $cur;
            }
            if (empty($load)) {
                #All we don't find, we put into the rest.
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
     * For noting debug messages, they are not shown (and Show is not loaded) if disabled.
     *
     * @param mixed $message
     * @param string $title
     */
    public static function debug($message, $title = 'Debug')
    {
        if (self::$debug) {
            Show::debug($message, $title);
        }
    }

    /**
     * Returns a specific segment of the url.
     * @param int $int Segment part.
     * @return string THe specified segment of the url.
     */
    public static function segment($int)
    {
        return !empty(self::$url[$int]) ? self::$url[$int] : false;
    }

    /**
     * Include a page, can only be done once per page load!
     * @param string $file
     */
    private static function _loadController($file)
    {
        $fileName = 'controller/' . self::sanitizeFileName($file) . '.php';
        if (!empty(self::$page)) {
            throw new Exception('Controller already loaded!');
        }
        $fileSrc = stream_resolve_include_path($fileName);

        if (!file_exists($fileSrc)) {
            throw new Exception("Controller not found: $fileName");
        }
        require_once($fileSrc);
        self::$page = $file;
        if (empty(self::$route)) {
            self::$route = $file;
        }
        #Check if the pagefile has the proper definition.
        if (!class_exists('Page')) {
            throw new Exception("Controller missing class Page: $file");
        }
        if (!is_subclass_of('Page', 'Controller_Abstract')) {
            throw new Exception("Controller not extended from abstract: $file");
        }
        return Page::load();
    }

    /**
     * Load template file or reuse the one in memory.
     *
     * @param string $file
     * @return string content
     */
    public static function loadView($file)
    {
        $fileName = 'view/' . self::sanitizeFileName($file) . '.html';

        $result = '';
        if (isset(self::$_included[$fileName])) {
            $result = self::$_included[$fileName];
        } else {

            $fileSrc = stream_resolve_include_path($fileName);
            if ($fileSrc === false) {
                throw new Exception("View not found for $file");
            }
            $result = file_get_contents($fileSrc);
            self::debug($fileName, 'Loading view');
            self::$_included[$fileName] = $result;
        }

        if (empty($result)) {
            throw new Exception("View empty for $file");
        }

        return $result;
    }

    /**
     * Output data with proper headers (length, etc.) and mime type.
     *
     * @param string $mime The mime-type. (ie. image/jpeg, text/plain, ...)
     * @param mixed $data File data or filename to output .
     * @param int $modified Unix timestamp of last modified date.
     * @param string $filename Filename for the output
     * @param boolean $isFile True if data is a filename. (if true, it will output a file directly to the browser)
     */
    public static function output($mime, $data, $modified = 0, $filename = null, $isFile = false)
    {
        if (ob_get_contents() || headers_sent()) {
            throw new Exception("Headers already sent.");
        }
        if ($isFile && !file_exists($data)) {
            throw new Exception("File does not exist: $data");
        }
        $length = $isFile ? filesize($data) : strlen($data);
        // Two weeks expiration.
        $expires = 60 * 60 * 24 * 14;
        header('Content-type: ' . $mime);
        header('Content-Length: ' . $length);
        header('Content-Transfer-Encoding: binary');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
        if (!empty($filename)) {
            header('Content-Disposition: attachment; filename="' . trim($filename) . '"');
        }
        if (!empty($modified)) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        }
        header('Connection: close');
        header('Vary: Accept');
        if ($isFile) {
            readfile($data);
        } else {
            echo $data;
        }
        exit;
    }

    /**
     * Output data with a 304 not modified header.
     * @param string $expires Expiration time.
     */
    public static function output_same($expires = '+30 days')
    {
        header('HTTP/1.1 304 Not Modified', null, 304);
        header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime($expires)) . ' GMT');
        header('Connection: close');
        exit;
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
    private static function outputStatic($request)
    {
        if (is_numeric($request[0])) {
            array_shift($request);
        }
        $url = implode('/', $request);

        $original = PATH_ASSETS . $url;
        if (!file_exists($original)) {
            header('HTTP/1.0 404 Not Found', null, 404);
            throw new Exception("Unable to find file: $request");
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
        self::output($mime, $original, filemtime($original), null, true);
    }

    /**
     * Remove unwanted characters from a filename, only allowing underscores, slashes and periods, next to letters.
     *
     * @param string $string
     * @return string The cleaned filename.
     */
    public static function sanitizeFileName($string, $toLower = true)
    {
        $string = ($toLower) ? strtolower(trim($string)) : trim($string);
        //Strip unwanted characters.
        $string = preg_replace('/[^A-Za-z0-9\_\/\.]/', '', $string);
        //Remove double slashes.
        $string = preg_replace('/\/+/', '/', $string);
        //Remove leading/trailing dots, slashes. So ../ is removed.
        $string = trim($string, './');
        //Return cleaned string.
        return $string;
    }

    /**
     * Does a redirect if desiredUrl is different from the current Url.
     * @param type $desiredUrl
     */
    public static function force_url($desiredUrl = '')
    {
        $desiredUrl = ltrim($desiredUrl, '/');
        if ($desiredUrl != self::$url)
            self::redirect($desiredUrl);
    }

    /**
     * Simplified redirect function, needs to be called BEFORE output!
     *
     * @param type $url The absolute or relative url you wish to redirect to.
     * @param int $code One of 301, 302 or 303
     */
    public static function redirect($url = '', $code = 302)
    {
        $url = empty($url) ? '' : trim($url);
        if (substr($url, 0, 4) != 'http') {
            $url = ltrim($url, '/');
            $site_url = Config::system()->get('site', 'url');
            $url = $site_url . $url;
        }
        $codes = array(
            301 => 'HTTP/1.1 301 Moved Permanently',
            302 => 'HTTP/1.1 302 Found',
            303 => 'HTTP/1.1 303 See Other',
        );
        if (empty($codes[$code]))
            $code = 302;
        header($codes[$code]);
        header('Location: ' . $url);
        exit;
    }
}
