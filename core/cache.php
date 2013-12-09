<?php
namespace Core;
/**
 * The basic view function, containing possibilities for filling variables into a another string.
 */
class Cache
{

    /**
     * Load from the cache.
     * @param string $name
     * @param int $expireTime
     * @return mixed
     */
    public static function load($name, $expireTime = 0)
    {
        $fileName = self::_makeFileName($name);
        $result = null;
        if (file_exists($fileName) && filemtime($fileName) >= $expireTime) {
            $result = unserialize(file_get_contents($fileName));
        }
        return $result;
    }

    /**
     * Save to the cache.
     * @param string $name
     * @param mixed $content
     * @return boolean
     */
    public static function save($name, $content)
    {
        $fileName = self::_makeFileName($name);
        if (empty($content) && file_exists($fileName)) {
            unlink($fileName);
        } else {
            file_put_contents($fileName, serialize($content));
            chmod($fileName, 0666);
        }
        return true;
    }

    /**
     * Make the filename.
     * @param type $name
     * @return type
     */
    private static function _makeFileName($name)
    {
        if (empty($name)) {
            throw new \Exception("Must give name for cache.");
        }
        return PATH_CACHE . str_replace(array('/', '\\'), '+', \Core::cleanPath($name)) . '.cache';
    }
}