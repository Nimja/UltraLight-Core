<?php

/**
 * Static class to work with cookies, get and post.
 */
class Request
{
    const STATUS_REDIRECT_PERMANENT = 301;
    const STATUS_REDIRECT_FOUND = 302;
    const STATUS_REDIRECT_SEE_OTHER = 303;
    const STATUS_NOT_MODIFIED = 304;
    const STATUS_ERROR_BAD_REQUEST = 400;
    const STATUS_ERROR_FORBIDDEN = 403;
    const STATUS_ERROR_NOT_FOUND = 404;
    const STATUS_SERVER_ERROR = 500;
    const STATUS_ERROR_MESSAGES = [
        self::STATUS_ERROR_BAD_REQUEST => 'Bad request.',
        self::STATUS_ERROR_FORBIDDEN => 'Forbidden, you do not have authorization.',
        self::STATUS_ERROR_NOT_FOUND => 'Sorry, this page does not (or never did) exist...',
        self::STATUS_SERVER_ERROR => 'There is a server error?',
    ];

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
     * Check if we have cookies.
     * @var boolean
     */
    private static $_hasCookies = null;

    /**
     * Headers in original form.
     *
     * @var array
     */
    private static $headers = null;
    /**
     * Headers with lowercase keys.
     *
     * @var array
     */
    private static $headersLower = null;

    /**
     * Return true if current request is a post request.
     * @return boolean
     */
    public static function isPost()
    {
        return self::server('REQUEST_METHOD') === 'POST';
    }

    /**
     * Check if it is a post and return error page if the referrer is NOT this domain.
     *
     * @return boolean
     */
    public static function isSafePost()
    {
        if (!self::isPost()) {
            return false;
        }
        $domain = strtolower(parse_url(self::server('HTTP_REFERER'), PHP_URL_HOST));
        $ourdomain = strtolower(parse_url(\Config::system()->get('site', 'url'), PHP_URL_HOST));
        if ($domain !== $ourdomain) {
            return self::showError(self::STATUS_ERROR_BAD_REQUEST);
        }
        return true;
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
     * Return true if we are on HTTPS connection.
     *
     * @return boolean
     */
    public static function isSecure()
    {
        // In case of forwarding.
        if (self::server('HTTP_X_PROTO') === 'SSL' || self::server('HTTP_X_PORT') === '443') {
            return true;
        }
        // In case of SSL.
        $https = self::server('HTTPS');
        $port = self::server('SERVER_PORT');
        return (!empty($https) && $https !== 'off') || self::server('SERVER_PORT') === '443';
    }

    /**
     * Get value from the global INPUT_SERVER
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function server($name, $default = null)
    {
        if (self::$_server === null) {
            self::$_server = filter_input_array(INPUT_SERVER);
        }
        return getKey(self::$_server, $name, $default);
    }

    /**
     * Get all headers in original format or with lowercase keys.
     *
     * @param boolean $lowerKeys
     * @return void
     */
    public static function getHeaders($lowerKeys = false)
    {
        if (self::$headers === null) {
            self::$headers = apache_request_headers();
            self::$headersLower = [];
            foreach (self::$headers as $key => $value) {
                self::$headersLower[strtolower($key)] = $value;
            }
        }
        return $lowerKeys ? self::$headersLower : self::$headers;
    }

    /**
     * Get a value from the headers in case-insensitive manner.
     *
     * @param string $key
     * @param mixed $default
     * @return
     */
    public static function getHeader($key, $default = null)
    {
        return getKey(self::getHeaders(true), strtolower($key), $default);
    }

    /**
     * Old way of getting value, alias for getValue.
     * @deprecated version
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
     * Retrieve POST/GET value, with sanitation.
     * @param string $name
     * @param mixed $default
     * @param booelan|array $keepTags
     * @return mixed
     */
    public static function getValue($name, $default = null)
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
            $get = filter_input_array(INPUT_GET, FILTER_UNSAFE_RAW) ?: [];
            $post = filter_input_array(INPUT_POST, FILTER_UNSAFE_RAW) ?: [];
            self::$_values = Sanitize::clean(array_merge($get, $post));
        }
        return self::$_values;
    }

    /**
     * Move upload file to destination, return full path of the moved file.
     * @param string $name
     * @param string $destination
     * @param string $overrideFileName
     * @return string
     * @throws \Exception
     */
    public static function moveUploadFile($name, $destination, $overrideFileName = '')
    {
        $file = getKey($_FILES, $name);
        if (empty($file)) {
            throw new \Exception("No file found for {$name}?");
        }
        $originalName = getKey($file, 'name', 'unknown');
        $tmpName = getKey($file, 'tmp_name');
        $size = getKey($file, 'size', 0);
        if (!$tmpName || $size == 0) {
            throw new \Exception("File upload not successful?");
        }
        $destinationFile = $overrideFileName ? $destination . $overrideFileName : $destination . $originalName;
        if (file_exists($destinationFile)) {
            if (!unlink($destinationFile)) {
                throw new \Exception("Unable to remove old file {$overrideFileName}!");
            }
        }
        if (!move_uploaded_file($tmpName, $destinationFile)) {
            throw new \Exception("Unable to move new file to {$destinationFile}!");
        }
        return $destinationFile;
    }

    /**
     * Check if user has any cookies, should be true as we always set php session.
     *
     * @return boolean
     */
    public static function hasCookies()
    {
        if (self::$_hasCookies === null) {
            self::getCookies();
        }
        return self::$_hasCookies;
    }

    /**
     * Set a cookie with a string timestamp.
     * @param string $name
     * @param mixed $value
     * @param string $time Like +2 months
     */
    public static function setCookie($name, $value, $time = '+2 months')
    {
        setcookie($name, $value, strtotime($time), '/', "", true);
        self::getCookies();
        self::$_cookies[$name] = $value;
    }

    /**
     * Get the sanitized cookies.
     * @return array
     */
    public static function getCookies()
    {
        if (self::$_cookies === null) {
            $cookies = filter_input_array(INPUT_COOKIE, FILTER_UNSAFE_RAW) ?: [];
            self::$_cookies = Sanitize::clean($cookies);
            self::$_hasCookies = !empty($cookies);
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
     * Simply send a 404 to the user, with a basic (funny?) error message.
     */
    public static function notFound()
    {
        self::showError(self::STATUS_ERROR_NOT_FOUND);
    }

    /**
     * Show an error and exit/stop.
     *
     * @param int $errorCode
     * @return void
     */
    public static function showError($errorCode)
    {
        http_response_code($errorCode);
        $errorMessage = getKey(self::STATUS_ERROR_MESSAGES, $errorCode, 'Unknown error');
        $title = $errorCode . ': ' . $errorMessage;
        $html = [
            "<html>",
            "<head><title>{$title}</title></head>",
            "<body><center>",
            "<h1>HTTP Error {$errorCode}</h1><h2>{$errorMessage}</h2>",
            "</center></body>",
            "</html>",
        ];
        echo implode(PHP_EOL, $html);
        exit;
    }

    /**
     * Simplified redirect function, needs to be called BEFORE output!
     *
     * @param string $url The absolute or relative url you wish to redirect to.
     * @param int $code One of 301, 302 or 303
     */
    public static function redirect($url = '', $code = self::STATUS_REDIRECT_FOUND, $includeRequest = false)
    {
        if (\Core::$console) {
            \Show::fatal("Redirect {$code} to: {$url}");
        }
        $targetUrl = empty($url) ? '' : trim($url);
        if (substr($targetUrl, 0, 4) != 'http') {
            $targetUrl = Config::system()->get('site', 'url') . ltrim($targetUrl, '/');
        }
        $validCodes = [self::STATUS_REDIRECT_PERMANENT, self::STATUS_REDIRECT_FOUND, self::STATUS_REDIRECT_SEE_OTHER];
        $code = in_array($code, $validCodes) ? $code : self::STATUS_REDIRECT_FOUND;
        $sameRequest = $targetUrl == \Core::$url && self::isPost();
        $query = self::server('QUERY_STRING');
        if ($includeRequest) {
            if ($query) {
                $targetUrl .= '?' . $query;
            }
        } else if ($query) {
            $sameRequest = false;
        }
        if ($sameRequest) {
            \Show::fatal($targetUrl, "Redirect to itself.");
        }
        http_response_code($code);
        header('Location: ' . $targetUrl, true);
        exit;
    }

    /**
     * Output file to browser.
     * @param string $file
     * @param string $mimeType
     * @param string $fileName
     * @throws \Exception
     */
    public static function outputFile($file, $mimeType = 'application/octet-stream', $fileName = null)
    {
        if (ob_get_contents() || headers_sent()) {
            throw new \Exception("Headers already sent.");
        }
        if (!file_exists($file)) {
            throw new \Exception("File does not exist: $file");
        }
        $modifiedDate = filemtime($file);
        self::ifModifiedSince($modifiedDate);
        self::_sendOutputHeaders($mimeType, filesize($file), $fileName, $modifiedDate);
        readfile($file);
        exit;
    }

    /**
     * Output file to browser.
     * @param string $data
     * @param string $mimeType
     * @param string $fileName
     * @param string $modified
     * @throws \Exception
     */
    public static function outputData($data, $mimeType = 'application/octet-stream', $fileName = null, $modified = 0)
    {
        if (ob_get_contents() || headers_sent()) {
            throw new \Exception("Headers already sent.");
        }
        if (empty($data)) {
            throw new \Exception("No data to send.");
        }
        $modifiedDate = $modified ?: time();
        self::ifModifiedSince($modifiedDate);
        self::_sendOutputHeaders($mimeType, strlen($data), $fileName, time());
        echo $data;
        exit;
    }

    /**
     * Send output headers for inline or attached files.
     * @param string $mimeType
     * @param int $length
     * @param string $fileName
     * @param string $modifiedDate
     * @param string $expireString
     * @throws \Exception
     */
    private static function _sendOutputHeaders($mimeType, $length, $fileName, $modifiedDate, $expireString = '+2 weeks')
    {
        $expireDate = strtotime($expireString);
        $expireSeconds = $expireDate - time();
        header('Content-Type: ' . $mimeType);
        if (!empty($fileName)) {
            \Core::setOutputCompression(false);
            header('Content-Length: ' . $length);
            header('Content-Transfer-Encoding: binary');
            header('Content-Disposition: attachment; filename="' . trim($fileName) . '"');
        }
        header("Cache-Control: max-age={$expireSeconds}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', $expireDate) . ' GMT');
        if (!empty($modifiedDate)) {
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modifiedDate) . ' GMT');
        }
    }

    /**
     * Handle "if-modified-since" requests.
     * @param int $compareTime
     * @param string $expires
     * @return void
     */
    public static function ifModifiedSince($compareTime, $expires = '+14 days')
    {
        $modified = self::server('HTTP_IF_MODIFIED_SINCE');
        if (empty($compareTime) || empty($modified) || $compareTime > strtotime($modified)) {
            return;
        }
        http_response_code(self::STATUS_NOT_MODIFIED);
        header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime($expires)) . ' GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $compareTime) . ' GMT');
        $maxAge = strtotime($expires, 0);
        header("Cache-Control: max-age={$maxAge}");
        exit;
    }
}
