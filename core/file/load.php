<?php namespace Core\File;
/**
 * Load a file and return it with the proper header.
 *
 * You can use folders, but it will *not* go deeper than 1 level for speed.
 *
 * It will also ONLY load files with the same extension.
 */
class Load
{
    /**
     * The array to prevent double-loading of the files.
     * @var array
     */
    private static $_filesArray;
    /**
     * Full path to the file.
     * @var type
     */
    private $_file;
    /**
     * If we're dealing with a folder or not.
     * @var boolean
     */
    private $_folder;
    /**
     * The mimetype, based on the original extension.
     * @var string
     */
    private $_mimeType;

    /**
     *
     * @param string $fileName
     * @param string $path
     * @throws \Exception
     */
    public function __construct($fileName, $path = PATH_ASSETS)
    {
        $this->_file = $path . $fileName;
        $extension = pathinfo($this->_file, PATHINFO_EXTENSION);
        if (!file_exists($this->_file) && $extension) {
            $this->_file = substr($this->_file, 0, -1 * (1 + strlen($extension)));
        }
        if (!file_exists($this->_file)) {
            header('HTTP/1.0 404 Not Found', null, 404);
            throw new \Exception("Unable to find file: {$fileName}");
        }
        switch ($extension) {
            case 'css': $this->_mimeType = 'text/css';
                break;
            case 'js': $this->_mimeType = 'text/javascript';
                break;
            default: $this->_mimeType = 'text/plain';
        }
        $this->_folder = is_dir($this->_file);
    }

    public function output()
    {
        if (!$this->_folder) {
            \Request::outputFile($this->_file, $this->_mimeType);
        } else {
            self::$_filesArray = $this->_getFileList();
            $modifiedTime = $this->_getModifiedTime();
            \Request::ifModifiedSince($modifiedTime);
            $content = \Core::wrapCache('\Core\File\Load::getContent', [$this->_file], $modifiedTime);
            \Request::outputData($content, $this->_mimeType, null, $modifiedTime);
        }
    }

    /**
     * Return full filenames (with paths).
     * @return string[]
     */
    private function _getFileList()
    {
        $files = scandir($this->_file);
        $result = [];
        $basePath = $this->_file . '/';
        foreach ($files as $file) {
            $fullName = $basePath . $file;
            if ($file == '.' || $file == '..' || is_dir($fullName)) {
                continue;
            }
            $result[] = $fullName;
        }
        return $result;
    }

    /**
     * Get modification timestamp.
     * @return int
     */
    private function _getModifiedTime()
    {
        $modifiedTime = 0;
        foreach (self::$_filesArray as $file) {
            $modifiedTime = max(filemtime($file), $modifiedTime);
        }
        return $modifiedTime;
    }

    /**
     * Get file content, wrapped by cache if possible.
     * @param string $file
     * @return string
     */
    public static function getContent($file){
        $result = [];
        foreach (self::$_filesArray as $file) {
            $result[] = file_get_contents($file);
        }
        return implode(PHP_EOL . PHP_EOL, $result);
    }
}
