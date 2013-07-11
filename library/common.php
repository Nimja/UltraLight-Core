<?php

/**
 * Contains a bunch of helper functions that are semi-often used in places.
 */
class Library_Common
{

    const PREG_EMAIL = "/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,6}/i";
    const RECURSIVE = 1;
    const EXCLUDE_DIRS = 2;
    const EXCLUDE_FILES = 4;

    /**
     * list all files in a dir, optional recursivity.
     * @param string $path
     * @param int $options Options can be: self::RECURSIVE, self::EXCLUDE_DIRS, self::EXCLUDE_FILES
     * Combine options self::RECURSIVE | self::EXCLUDE_FILES (retrieves recursive directory tree)
     * @param $include Supply a string for "in string" or an array for specific names.
     * @param $exclude Same as include, but for excluding.
     * @return array Result is a named array with filenames as keys and values or dir contents as value.
     */
    public static function listFiles($path, $options = 0, $include = null, $exclude = null)
    {
        $files = array();
        $dirs = array();
        $recursive = ($options & self::RECURSIVE);
        $exclude_files = ($options & self::EXCLUDE_FILES);
        $exclude_dirs = ($options & self::EXCLUDE_DIRS);
        $inc_func = is_array($include) ? 'in_array' : 'strpos';
        $exc_func = is_array($exclude) ? 'in_array' : 'strpos';
        if (is_dir($path)) {
            if (substr($path, -1) == '/') {
                $path = substr($path, 0, -1);
            }
            $objects = scandir($path);
            foreach ($objects as $entry) {
                if ($entry !== '.'
                        && $entry !== '..'
                        && (empty($include) || $inc_func($entry, $include) !== false)
                        && (empty($exclude) || $exc_func($entry, $exclude) === false)
                ) {
                    $curfile = $path . '/' . $entry;
                    $file = $entry;
                    if (is_dir($curfile)) {
                        $dirs[$entry] = (!$recursive) ? array() : self::listFiles($curfile, $options, $include, $exclude);
                    } else {
                        $files[$entry] = $file;
                    }
                }
            }
        } else {
            Show::error($path, 'Not listing a directory');
        }
        #Combine dirs and files in proper order, maintaining keys.
        $result = array();
        if (!$exclude_dirs)
            $result += $dirs;
        if (!$exclude_files)
            $result += $files;
        return $result;
        ;
    }

    # Remove recursive dir + files

    public static function rrmdir($dir, $self = true)
    {
        $result = true;
        if (is_dir($dir)) {
            $entries = scandir($dir);
            foreach ($entries as $entry) {
                if ($entry != '.' && $entry != '..') {
                    $file = $dir . '/' . $entry;
                    if (is_dir($file)) {
                        self::rrmdir($file);
                    } else {
                        $success = unlink($file);
                        if (!$success)
                            Show::error($file, 'Could not remove directory.');
                    }
                }
            }
            if ($self)
                $result = rmdir($dir);
        }
        if (!$result)
            Show::error($dir, 'Could not remove directory.');
    }

    #Get extension of file, if any, can be any length

    public static function getExtension($file)
    {
        return pathinfo($file, PATHINFO_EXTENSION);
    }

    #Strip extension of file, can be any length

    public static function stripExtension($file)
    {
        $parts = pathinfo($file);
        $len = 0;
        if (!empty($parts['extension'])) {
            $len = strlen($parts['extension']) + 1;
        }
        return substr($file, 0, -$len);
    }

    /**
     * Show time in seconds in a human readable format (1h 2m 3s)
     * @param int $time
     * @return string DAYS H:I:S
     */
    public static function timeToHuman($time, $returnArray = false)
    {
        $parts = explode(':', gmdate('z:H:i:s', $time));
        $result = array(
            'd' => $parts[0],
            'h' => $parts[1],
            'm' => $parts[2],
            's' => $parts[3],
        );

        if ($returnArray) {
            return $result;
        }
        $hrt = array();
        foreach ($result as $key => $value) {
            $value = intval($value);
            if (!empty($value)) {
                $hrt[] = $value . $key;
            }
        }
        return implode(' ', $hrt);
    }

    /**
     * Translate bytes to a human readable format.
     *
     * @param type $size
     * @return type
     */
    public static function bytesToHuman($size)
    {
        $unit = array('b', 'kb', 'mb', 'gb', 'tb', 'pb');
        return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
    }

    //Make new path, or just check it.
    public static function mkpath($path)
    {
        $result = true;
        if (!file_exists($path))
            $result = mkdir($path, 0777);
        return $result;
    }

    /**
     * Write log.
     * @param type $text
     * @param type $log
     */
    public static function log($text, $log = 'log')
    {
        #sanity checks
        $logfile = PATH_LOGS . $log . '.txt';
        if (file_exists($logfile) && !is_writable($logfile)) {
            throw new Exception("Log $log is write protected");
        } else if (!file_exists && !is_writable(PATH_LOGS)) {
            throw new Exception("Log path is not writable and log does not exist: " . PATH_LOGS);
        }

        if (!is_string($text) && !is_numeric($text)) {
            $text = print_r($text, true);
        }
        $text = date('Y-m-d H:i:s') . "\t" . $text . "\n";
        $log = str_replace('/', '-', $log);

        #Put in the log and make sure it's writable
        file_put_contents($logfile, $text, FILE_APPEND);
        chmod($logfile, 0666);
    }

    /**
     * Return sanitized request variables.
     *
     * @return array Post/Get values, POST has preference, but empty post vars can be overwritten.
     */
    public static function request()
    {
        $result = array();

        foreach ($_POST as $name => $value) {
            #Add the value.
            if (!blank($value)) {
                $result[$name] = sanitize($value);
            }
        }
        foreach ($_GET as $name => $value) {
            #Skip values we have.
            if (isset($result[$name]))
                continue;
            #Add the value.
            if (!blank($value)) {
                $result[$name] = sanitize($value);
            }
        }
        return $result;
    }

    #Return true if value is valid e-mail address.

    public static function isEmail($value)
    {
        $result = preg_match("/^" . self::PREG_EMAIL . "$/i", $value);
        return $result;
    }

    public static function insertSwf($file, $width, $height, $bgcolor = '#FFFFFF', $quality = 'medium')
    {
        return '<div class="flashmovie" style="width: ' . $width . 'px; height: ' . $height . 'px;"><object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000" id="' . $file . '" width="' . $width . '" height="' . $height . '" codebase="http://fpdownload.macromedia.com/get/flashplayer/current/swflash.cab">
			<param name="movie" value="' . $file . '" />
			<param name="quality" value="' . $quality . '" />
			<param name="bgcolor" value="' . $bgcolor . '" />
			<param name="allowScriptAccess" value="sameDomain" />
			<param name="allowFullScreen" value="true" />
			<param name="menu" value="false" />

			<embed src="' . $file . '" quality="' . $quality . '" bgcolor="' . $bgcolor . '"
				width="' . $width . '" height="' . $height . '" name="' . $file . '" align="middle"
				quality="' . $quality . '"
				allowScriptAccess="sameDomain"
				allowFullScreen="true"
				menu="false"
				type="application/x-shockwave-flash"
				pluginspage="http://www.adobe.com/go/getflashplayer" />
			</object></div>';
    }

}