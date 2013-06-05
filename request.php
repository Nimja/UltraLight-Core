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
     * Return true if current request is a post request.
     * @return boolean
     */
    public static function isPost() {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
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
    public static function getValues() {
        if (self::$_values === null) {
            self::$_values = sanitize(array_merge($_GET, $_POST));
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
     * get the cookie nicely.
     * @param string $name
     * @param mixed The default value.
     */
    public static function getCookie($name, $default = null)
    {
        return sanitize(getKey($_COOKIE, $name, $default));
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
        $result = setcookie($name, '', strtotime('-2 days'), '/');
        if (isset($_COOKIE[$name])) {
            unset($_COOKIE[$name]);
        }
        return $result;
    }
}
