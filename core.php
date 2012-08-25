<?php

require_once PATH_CORE . 'config.php';
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
                Show::fatal($filename, sprintf('File exists, but class <b>%s</b> not found.', $class));
            }
        });

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
 * Sanitation function to run on POST/GET variables.
 * 
 * @param mixed $value The value you wish to sanitize.
 * @return string Sanitized string.
 */
function sanitize($value)
{
    if (blank($value))
        return '';

    //Sanitize array.
    if (is_array($value)) {
        array_walk($value, 'sanitize');
        return $value;
    }

    //Remove magic quites.
    $string = (ini_get('magic_quotes_gpc')) ? stripslashes($value) : $value;
    //fix euro symbol.
    $string = str_replace(chr(226) . chr(130) . chr(172), '&euro;', trim($string));
    $string = utf8_decode($string);
    $string = html_entity_decode($string, ENT_COMPAT, 'ISO-8859-15');
    $string = htmlentities($string, ENT_COMPAT, 'ISO-8859-15');
    return $string;
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
    private static $included = array();

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
     * The main initialization function, can only be called ONCE!
     */
    public static function start()
    {
        self::$start = microtime(true);
        if (!empty(self::$included['page']))
            Show::fatal(null, 'Load Init double called');

        #Set Base URL.
        $config = &$GLOBALS['config'];
        if (empty($config['base_url'])) {
            $base_url = !empty($_SERVER['HTTPS']) ? 'https://' : 'http://';
            $base_url .= $_SERVER['HTTP_HOST'] . '/';
            $config['base_url'] = $base_url;
        }

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

        #Start logic to find what page we load (without starting slash)
        $uri = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

        $pos = strpos($uri, '?');
        if (!$pos)
            $pos = strlen($uri);
        $uri = parse_url(substr($uri, 0, $pos), PHP_URL_PATH);

        $request = preg_replace('/\/+/', '/', trim($uri, '/ '));

        #Redirect for double, trailing and leading slashes.
        if ($uri != '/' . $request) {
            Core::redirect($request);
        }

        #Store it away for other uses.
        self::$url = $request;

        #Array of url parts
        $request = !empty($request) ? explode('/', $request) : array();

        if (DEBUG) {
            Show::info($request, 'Checking route');
        }

        //Only 'automatic' url 
        if ($request[0] == self::URL_VERSION) {
            array_shift($request);
            self::output_static($request);
        }

        #Start lookup, this 

        if (empty($request)) {
            $load = 'index';
        } else {
            $load = '';

            if (empty($config['routes']))
                Show::fatal('You must define routes.');
            $routes = $config['routes'];

            $rest = array();
            # Route will be checked back to front, so /parent/child/sub is checked first, then /parent/child, etc.
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
                    if (DEBUG) {
                        Show::info($cur, 'Not found');
                    }
                }
            }
            #Put the remainder of the url in here.
            self::$rest = implode('/', $rest);

            if (empty($load)) {
                $load = !empty($routes['*']) ? $routes['*'] : '404';
            }
        }

        if (DEBUG) {
            Show::info($load, 'Loading page');
        }

        self::controller($load);

        $page = new Page();
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
    private static function controller($file)
    {
        $fileName = 'controller/' . self::sanitizeFileName($file) . '.php';
        if (!empty(self::$page)) {
            Show::fatal($file, 'Controller already loaded!');
        }
        $fileSrc = stream_resolve_include_path($fileName);

        if (!file_exists($fileSrc)) {
            Show::fatal($fileName, 'Controller not found');
        }

        require_once($fileSrc);
        self::$page = $file;
        if (empty(self::$route)) {
            self::$route = $file;
        }

        #Check if the pagefile has the proper definition.
        if (!class_exists('Page')) {
            Show::fatal($file, 'Controller class not defined properly (missing class "Page")');
        }
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
        if (isset(self::$included[$fileName])) {
            $result = self::$included[$fileName];
        } else {

            $fileSrc = stream_resolve_include_path($fileName);
            if ($fileSrc === false) {
                Show::fatal($fileName, 'View not found for ' . $file);
            }
            $result = file_get_contents($fileSrc);
            if (DEBUG) {
                Show::info($fileName, 'Loading view');
            }
            self::$included[$fileName] = $result;
        }

        if (empty($result)) {
            Show::fatal($file, 'View file empty?');
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
        #Check we're the first data.
        if (ob_get_contents() || headers_sent()) {
            Show::fatal(ob_get_contents(), 'Headers already sent');
        }

        #Check the file exists, if needed.
        if ($isFile && !file_exists($data)) {
            Show::fatal('Cannot send file');
        }

        $length = $isFile ? filesize($data) : strlen($data);
        $expires = 60 * 60 * 24 * 14; //Give it 2 weeks.

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

        ob_clean();
        flush();

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
        //Status Code:304 Not Modified
        header('HTTP/1.1 304 Not Modified', null, 304);

        $expires = intval($expires);
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
    public static function output_static($request)
    {
        if (is_numeric($request[0])) {
            array_shift($request);
        }
        $url = implode('/', $request);

        $original = PATH_ASSETS . $url;
        if (!file_exists($original)) {
            header('HTTP/1.0 404 Not Found', null, 404);
            echo 'File not found!';
            return;
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
            $url = $GLOBALS['config']['base_url'] . $url;
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

    /**
     * Set a cookie with a string timestamp.
     * @param string $name
     * @param mixed $value
     * @param string $time Like +2 months
     */
    public static function setCookie($name, $value, $time)
    {
        setcookie($name, $value, strtotime($time), '/');
    }

    /**
     * Clear cookie.
     * @param string $name 
     * @return boolean Clearing cookie success or not.
     */
    public static function clearCookie($name)
    {
        //We set it to 2 days ago, because of time differences.
        $result = setcookie($name, '', strtotime('-2 days'), '/');
        if (isset($_COOKIE[$name])) {
            unset($_COOKIE[$name]);
        }
        return $result;
    }

}

