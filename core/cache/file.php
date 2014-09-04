<?php
namespace Core\Cache;
/**
 * Basic caching function, using the file system and cache folder.
 */
class File extends \Core\Cache
{
    const EXTENSION = '.cache';

    /**
     * Load from the cache.
     * @param string $cacheKey
     * @param int $expireTime
     * @return mixed
     */
    public function load($key, $expireTime = 0)
    {
        $fileName = $this->_makeFileName($key);
        $result = null;
        if (file_exists($fileName) && filemtime($fileName) >= $expireTime) {
            $result = unserialize(file_get_contents($fileName));
        }
        return $result;
    }

    /**
     * Save to the cache.
     * @param string $cacheKey
     * @param mixed $content
     * @return boolean
     */
    public function save($key, $content)
    {
        $fileName = $this->_makeFileName($key);
        $folder = dirname($fileName);
        if (!file_exists($folder)) {
            mkdir($folder, 0777, true);
        }
        if (empty($content) && file_exists($fileName)) {
            unlink($fileName);
        } else {
            file_put_contents($fileName, serialize($content));
            chmod($fileName, 0666);
        }
        return true;
    }
    /**
     * Delete from cache.
     * @param string $key
     * @return boolean
     */
    public function delete($key)
    {
        $fileName = $this->_makeFileName($key);
        $result = false;
        if (file_exists($fileName)) {
            $result = unlink($fileName);
        }
        return $result;
    }

    /**
     * Make the filename.
     * @param type $name
     * @return type
     */
    private function _makeFileName($key)
    {
        if (empty($key) || !is_string($key)) {
            throw new \Exception("Must give name for cache.");
        } else {
            $file = $this->_cleanKey($key) . self::EXTENSION;
        }
        $folder = $this->_group . '/';
        return PATH_CACHE . $folder . $file;
    }
}