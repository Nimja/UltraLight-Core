<?php
/**
 * Static class to work with cookies, get and post.
 */
class Request
{
    /**
     * Contains the sanitized POST/GET variables.
     * @var array
     */
    private static $_values = null;
    /**
     * Contains the filtered SERVER variables.
     * @var array
     */
    private static $_server = null;
    /**
     * Contains the filtered COOKIE variables.
     * @var array
     */
    private static $_cookies = null;

    /**
     * Return true if current request is a post request.
     * @return boolean
     */
    public static function isPost()
    {
        return self::server('REQUEST_METHOD') === 'POST';
    }

    /**
     * Return true if current request is an ajax/xhtml request.
     * @return boolean
     */
    public static function isAjax()
    {
        $requestWith = self::server('HTTP_X_REQUESTED_WITH', '');
        return strtolower($requestWith) === 'xmlhttprequest';
    }

    /**
     * Get value from the global INPUT_SERVER
     * @param type $name
     * @param type $default
     */
    public static function server($name, $default = null)
    {
        if (self::$_server === null) {
            self::$_server = filter_input_array(INPUT_SERVER, FILTER_UNSAFE_RAW);
        }
        return getKey(self::$_server, $name, $default);
    }

    /**
     * Retrieve POST/GET value, with sanitation.
     * @param string $name
     * @param mixed $default
     * @param booelan|array $keepTags
     * @return mixed
     */
    public static function value($name, $default = null)
    {
        return getKey(self::getValues(), $name, $default);
    }

    /**
     * Get all the request variables for GET/POST together.
     * @return array
     */
    public static function getValues()
    {
        if (self::$_values === null) {
            $get = filter_input_array(INPUT_GET, FILTER_UNSAFE_RAW) ?: array();
            $post = filter_input_array(INPUT_GET, FILTER_UNSAFE_RAW) ?: array();
            self::$_values = Sanitize::clean(array_merge($get, $post));
        }
        return self::$_values;
    }

    /**
     * Set a cookie with a string timestamp.
     * @param string $name
     * @param mixed $value
     * @param string $time Like +2 months
     */
    public static function setCookie($name, $value, $time = '+2 months')
    {
        setcookie($name, $value, strtotime($time), '/');
    }

    /**
     * Get the sanitized cookies.
     * @return array
     */
    public static function getCookies()
    {
        if (self::$_cookies === null) {
            $cookies = filter_input_array(INPUT_COOKIE, FILTER_UNSAFE_RAW) ?: array();
            self::$_cookies = Sanitize::clean($cookies);
        }
        return self::$_cookies;
    }

    /**
     * get the cookie nicely.
     * @param string $name
     * @param mixed The default value.
     * @return mixed
     */
    public static function getCookie($name, $default = null)
    {
        return getKey(self::getCookies(), $name, $default);
    }

    /**
     * Clear cookie.
     *
     * We set it to 2 days ago, because of time differences.
     *
     * @param string $name
     * @return boolean Clearing cookie success or not.
     */
    public static function clearCookie($name)
    {
        $result = self::setCookie($name, '', '-2 days');
        self::getCookies();
        if (isset(self::$_cookies[$name])) {
            unset(self::$_cookies[$name]);
        }
        return $result;
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
        if (empty($codes[$code])) $code = 302;
        header($codes[$code]);
        header('Location: ' . $url);
        exit;
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
        } else if ($isFile) {
            self::ifModifiedSince(filemtime($data));
        }
        $length = $isFile ? filesize($data) : strlen($data);
        // Two weeks expiration.
        $expires = 60 * 60 * 24 * 14;
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . $length);
        header('Content-Transfer-Encoding: binary');
        if (!empty($filename)) {
            header('Content-Disposition: attachment; filename="' . trim($filename) . '"');
        }
        header("Cache-Control: max-age={$expires}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');
        if (!empty($modified)) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        }
        header('Connection: keep-alive');
        if ($isFile) {
            readfile($data);
        } else {
            echo $data;
        }
        exit;
    }

    /**
     * Handle "if-modified-since" requests.
     * @param int $compareTime
     * @param string $expire
     * @return void
     */
    public static function ifModifiedSince($compareTime, $expires = '+14 days')
    {
        $modified = self::server('HTTP_IF_MODIFIED_SINCE');
        if (empty($compareTime) || empty($modified) || $compareTime > strtotime($modified)) {
            return;
        }
        header('HTTP/1.1 304 Not Modified', null, 304);
        header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime($expires)) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $compareTime) . ' GMT');
        header('Connection: keep-alive');
        $maxAge = strtotime($expires, 0);
        header("Cache-Control: max-age={$maxAge}");
        exit;
    }
}