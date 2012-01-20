<?php

#All libraries should be self-contained and independant, but they can use the defined paths.
#Common are a bunch of helper functions that are semi-often used.

class Common {

	const PREG_EMAIL = "([\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+\.)*[\w\!\#$\%\&\'\*\+\-\/\=\?\^\`{\|\}\~]+@((((([a-z0-9]{1}[a-z0-9\-]{0,62}[a-z0-9]{1})|[a-z])\.)+[a-z]{2,6})|(\d{1,3}\.){3}\d{1,3}(\:\d{1,5})?)";
	const RECURSIVE = 1;
	const EXCLUDE_DIRS = 2;
	const EXCLUDE_FILES = 4;

	/**
	 * list all files in a dir, optional recursivity.
	 * @param string $path
	 * @param int $options Options can be: Common::RECURSIVE, Common::EXCLUDE_DIRS, Common::EXCLUDE_FILES
	 * Combine options Common::RECURSIVE | Common::EXCLUDE_FILES (retrieves recursive directory tree)
	 * @param string $filter Filter works like "in string"
	 * @return array Result is a named array with filenames as keys and values or dir contents as value.
	 */
	public static function listFiles($path, $options = 0, $filter = NULL)
	{
		$files = array();
		$dirs = array();

		$recursive = ($options & self::RECURSIVE);
		$exclude_files = ($options & self::EXCLUDE_FILES);
		$exclude_dirs = ($options & self::EXCLUDE_DIRS);

		#If the path is an actual (existing) dir.
		#Check if it is an actual folder.
		if (is_dir($path)) {
			#Cut off trailing slash, when ever it has been provided.
			if (substr($path, -1) == '/')
				$path = substr($path, 0, -1);

			#Gets the contents as an alphabetically sorted string.
			$objects = scandir($path);

			#While there are files to read.
			foreach ($objects as $entry) {
				if ($entry !== '.'
						&& $entry !== '..'
						#Use the filter, if required. Filter works like 'in string'
						&& (empty($filter) || strpos($entry, $filter) !== FALSE)
				) {
					$curfile = $path . '/' . $entry;
					$file = $entry;

					#Is this a dir?
					if (is_dir($curfile)) {
						#Switch between recursive or an empty array (so we can recognize dirs)
						$dirs[$entry] = (!$recursive) ? array() : self::listFiles($curfile, $recursive, $filter);

						#list all files (including dirs)
					} else {
						$files[$entry] = $file;
					}
				}
			}
		} else {
			show_error($path, 'Not listing a directory');
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

	public static function rrmdir($dir, $self = TRUE)
	{
		$result = TRUE;
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
							show_error($file, 'Could not remove directory.');
					}
				}
			}
			if ($self)
				$result = rmdir($dir);
		}
		if (!$result)
			show_error($dir, 'Could not remove directory.');
	}

	#Get extension of file, if any, can be any length

	public static function getExtension($file)
	{
		$parts = pathinfo($file);
		return $parts['extension'];
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

	//Bytes to Human readable format.
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

	#Write log.

	public static function log($text, $log = 'log')
	{
		#sanity checks
		$logfile = PATH_LOGS . $log . '.txt';
		if (file_exists($logfile) && !is_writable($logfile)) {
			show_exit($log, 'Log is write protected');
		} else if (!file_exists && !is_writable(PATH_LOGS)) {
			show_exit(PATH_LOGS, 'Log path is not writable and log does not exist');
		}


		if (!is_string($text) && !is_numeric($text)) {
			$text = print_r($text, TRUE);
		}
		$text = date('Y-m-d H:i:s') . "\t" . $text . "\n";
		$log = str_replace('/', '-', $log);

		#Put in the log and make sure it's writable
		file_put_contents($logfile, $text, FILE_APPEND);
		chmod($logfile, 0666);
	}

	#Easy get session var function.

	public static function session($name, $default = '')
	{
		$result = !empty($_SESSION[$name]) ? $_SESSION[$name] : $default;
		return $result;
	}

	public static function session_set($name, $value)
	{
		$_SESSION[$name] = $value;
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
			$value = sanitize($value);

			#Add the value.
			if (!blank($value))
				$result[$name] = $value;
		}
		foreach ($_GET as $name => $value) {
			#Skip values we have.
			if (isset($result[$name]))
				continue;

			$value = sanitize($value);

			#Add the value.
			if (!blank($value))
				$result[$name] = $value;
		}
		return $result;
	}

	public static function value($name, $default = '')
	{
		return isset($_POST[$name]) ? self::value_post($name, $default) : self::value_get($name, $default);
	}

	public static function value_post($name, $default = '')
	{
		$result = !blank($_POST[$name]) ? $_POST[$name] : $default;
		return sanitize($result);
	}

	public static function value_get($name, $default = '')
	{
		$result = !blank($_GET[$name]) ? $_GET[$name] : $default;
		return sanitize($result);
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