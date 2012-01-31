<?php

# ---- Global Functions ----

/**
 * Show a variable in a neat HTML friendly way. - VERY handy. 
 * 
 * @param string $var The variable you want to show.
 * @param string $title The optional title for this variable.
 * @param string $color One of fatal, error, neutral, good or success. CSS colors are also accepted.
 * @param boolean $return Return the export as a string instead of echoing.
 * @return string Optional return value, if $return is TRUE.
 */
function show($var, $title = 'Export Variable', $color = 'neutral', $return = FALSE)
{
	#Choose a color.
	$colors = array(
		'fatal' => '#f99',
		'error' => '#fdd',
		'neutral' => '#eee',
		'good' => '#ddf',
		'success' => '#dfd',
	);
	$color = !empty($colors[$color]) ? $colors[$color] : $color;

	#Make the content HTML compatible. 
	$display = htmlentities(trim(print_r($var, TRUE)));
	#Format content per line.
	$lines = explode("\n", $display);
	$display = '';
	$count = 0;
	$matches = array();

	$hide = 0;
	foreach ($lines as $line) {
		$line = rtrim($line);

		#If we are in a hidden block, check for a [ on the current line.
		if ($hide > 0) {
			if (substr($line, $hide, 1) == '[') {
				$hide = 0;
			} else {
				continue;
			}
		}

		#If the current 'block' matches :protected or :private in the first [] thing.
		if (preg_match("/^(\s+)\[[^\]]*\:(protected|private)\]/", $line, $matches)) {
			$spaces = $matches[1];
			$hide = strlen($spaces);
			continue;
		}

		$bg = ($count % 2) ? 'background: #f0f2f4;' : '';
		$count++;

		if (empty($line))
			$line = '&nbsp;';

		$line = strtr($line, array(
			'  ' => '&nbsp;&nbsp;',
			"\t" => '&nbsp;&nbsp;&nbsp;&nbsp;',
				));

		$display .= '<div style="' . $bg . ' margin: 0px; padding: 1px 5px;" >' . $line . '</div>';
	}

	#Create result.
	$result = '<div style="border-radius: 5px; border: 2px solid #999; background: '
			. $color . '; margin: 5px; padding: 3px 5px; text-align: left; font-family: verdana; font-size: 14px; ">'
			. $title . '<div style="font-family: courier; font-size: 11px; margin:0px; padding: 0px; border: 1px solid #ccc; background: #f9f9f9;">'
			. $display . '</div></div>';

	#Switch between returning or echoing. (echo is default);
	if ($return) {
		return $result;
	} else {
		echo $result;
	}
}

/**
 * Show a variable/error and stop PHP.
 * 
 * @param string $var The variable you want to show.
 * @param string $title The optional title for this variable.
 */
function show_exit($var, $title = '<b>Fatal error:</b>')
{
	show($var, $title, 'fatal');
	exit;
}

/**
 * Display a basic error.
 * 
 * @param string $var The variable you want to show.
 * @param string $title The optional title for this variable.
 */
function show_error($var, $title = '<b>Error:</b>', $return = FALSE)
{
	show($var, $title, 'error', $return);
}

/**
 * Replacement function for "empty", easier to type and returns true when var is "0" or 0
 * 
 * @param string $var The variable you want to show.
 * @return boolean TRUE when it is empty AND NOT numeric.
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

#Sanitize array.
	if (is_array($value)) {
		array_walk($value, 'sanitize');
		return $value;
	}

#Remove magic quites.
	$string = (ini_get('magic_quotes_gpc')) ? stripslashes($value) : $value;
#fix euro symbol.
	$string = str_replace(chr(226) . chr(130) . chr(172), '&euro;', trim($string));
	$string = utf8_decode($string);
	$string = html_entity_decode($string, ENT_COMPAT, 'ISO-8859-15');
	$string = htmlentities($string, ENT_COMPAT, 'ISO-8859-15');
	return $string;
}

/**
 * Simplified redirect function, needs to be called BEFORE output!
 * 
 * @param type $url The absolute or relative url you wish to redirect to.
 * @param int $code One of 301, 302 or 303
 */
function redirect($url = '', $code = 302)
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

# Minimal class for loading libraries, templates, etc.

class Load
{

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
	public static function init()
	{
		self::$start = microtime(TRUE);
		if (!empty(self::$included['page']))
			show_exit(NULL, 'Load Init double called');

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
		if ($uri != '/' . $request)
			redirect($request);

		#Store it away for other uses.
		self::$url = $request;

		#Array of url parts
		$request = !empty($request) ? explode('/', $request) : array();

		if (DEBUG)
			show($request, 'Checking route');

		#Start lookup, this 

		if (empty($request)) {
			$load = 'index';
		} else {
			$load = '';

			if (empty($config['routes']))
				show_exit('You must define routes.');
			$routes = $config['routes'];

			$rest = array();
			# Route will be checked back to front, so /parent/child/sub is checked first, then /parent/child, etc.
			while (!empty($request) && empty($load)) {
				$cur = implode('/', $request);

				if (file_exists(PATH_CONTROLLERS . $cur . '.php')) {
					$load = $cur;
				} elseif (!empty($routes[$cur])) {
					$load = $routes[$cur];
					#Store which route we're taking.
					self::$route = $cur;
				}
				if (empty($load)) {
					#All we don't find, we put into the rest.
					array_unshift($rest, array_pop($request));
					if (DEBUG)
						show($cur, 'Not found');
				}
			}
			#Put the remainder of the url in here.
			self::$rest = implode('/', $rest);

			if (empty($load)) {
				$load = !empty($routes['*']) ? $routes['*'] : '404';
			}
		}

		if (DEBUG)
			show($load, 'Loading page');

		self::controller($load);

		#Autoload libraries that the application has specified.
		if (!empty($config['autoload_libraries'])) {
			self::library($config['autoload_libraries']);
		}
	}

	/**
	 * Returns a specific segment of the url.
	 * @param int $int Segment part.
	 * @return string THe specified segment of the url. 
	 */
	public static function segment($int)
	{
		return!empty(self::$url[$int]) ? self::$url[$int] : FALSE;
	}

	/**
	 * Include a page, can only be done once per page load!
	 * @param string $file 
	 */
	private static function controller($file)
	{
		$filesrc = PATH_CONTROLLERS . self::sanitizeFileName($file) . '.php';
		if (!empty(self::$page))
			show_exit($file, 'Controller already loaded!');

		if (!file_exists($filesrc))
			show_exit($file, 'Controller not found');

		require_once($filesrc);
		self::$page = $file;
		if (empty(self::$route))
			self::$route = $file;

		#Check if the pagefile has the proper definition.
		if (!class_exists('Page'))
			show_exit($file, 'Controller class not defined properly (missing class "Page")');
	}

	/**
	 * Include a library, will only be done once per library. Either single or array with libraries.
	 * @param type $file
	 * @param string $type The type of library we're loading (the directory, basically)
	 * @param boolean $class_force Force loading of this class, skipping the classname check.
	 * @return type 
	 */
	public static function library($file, $type = 'library', $class_force = FALSE)
	{
		$type = strtolower($type);
		$path = NULL;
		/**
		 * Switch between different library paths.
		 */
		switch ($type) {
			case 'library': $path = 'libraries';
				break;
			case 'vendor': $path = 'vendor';
				break;
			case 'model': $path = 'models';
				break;
			default:
				if (!empty($GLOBALS['config']['paths'])) {
					$paths = $GLOBALS['config']['paths'];
					if (is_array($paths)) {
						$path = !empty($paths[$type]) ? $paths[$type] : NULL;
					}
				}
		}

		/**
		 * If we don't have a path show error and stop processing.
		 */
		if (empty($path)) {
			show_exit(ucfirst($type) . ' has no path defined.');
		}

		$files = (!is_array($file)) ? explode(',', $file) : $file;
		foreach ($files as $file) {
			$info = self::getNames($file);
			$file = $info['file'];
			$class = $info['class'];

			$filesrc = $path . '/' . $file . '.php';

			/**
			 * Skip previously loaded libraries.
			 */
			if (!empty(self::$included[$filesrc]))
				continue;

			/**
			 * Load the file from the Core folder OR App folder.
			 */
			$file_core = PATH_CORE . $filesrc;
			$file_app = PATH_APP . $filesrc;
			if (file_exists($file_core)) {
				require_once($file_core);
			} else if (file_exists($file_app)) {
				require_once($file_app);
			} else {
				show_exit($filesrc, ucfirst($type) . ' not found');
			}


			/**
			 * The file should contain a class directly related to the filename.
			 */
			if (!$class_force && !class_exists($class)) {
				show_exit($class, 'Could not find class in ' . $type . ': ' . $file);
			}
			#Debug code.
			if (DEBUG) {
				show($file, ucfirst($type) . ' imported');
			}
			self::$included[$filesrc] = TRUE;
		}
		return TRUE;
	}

	/**
	 * Load template file or reuse the one in memory.
	 * @param string $file
	 * @return string content 
	 */
	public static function view($file)
	{
		$filesrc = PATH_VIEWS . self::sanitizeFileName($file) . '.html';
		$result = '';

		#Reuse the one in memory (if we have it)
		if (!empty(self::$included[$filesrc]))
			return self::$included[$filesrc];

		if (!file_exists($filesrc))
			show_exit($file, 'View not found');

		$result = file_get_contents($filesrc);

		if (empty($result))
			show_error($file, 'View file empty?');

		self::$included[$filesrc] = $result;

		return $result;
	}

	/**
	 * Output data with proper headers (length, etc.) and mime type.
	 * 
	 * @param string $mime The mime-type. (ie. image/jpeg, text/plain, ...)
	 * @param mixed $data File data or filename to output .
	 * @param int $modified Unix timestamp of last modified date.
	 * @param string $filename Filename for the output
	 * @param boolean $isFile True if data is a filename. (if TRUE, it will output a file directly to the browser)
	 */
	public static function output($mime, $data, $modified = 0, $filename = NULL, $isFile = FALSE)
	{
		#Check we're the first data.
		if (ob_get_contents() || headers_sent())
			show_exit(ob_get_contents(), 'Headers already sent');

		#Check the file exists, if needed.
		if ($isFile && !file_exists($data))
			show_exit('Cannot send file');

		$length = $isFile ? filesize($data) : strlen($data);
		$expires = 60 * 60 * 24 * 14; //Give it 2 weeks.

		header('Content-type: ' . $mime);
		header('Content-Length: ' . $length);
		header('Content-Transfer-Encoding: binary');
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $expires) . ' GMT');

		if (!empty($filename))
			header('Content-Disposition: attachment; filename="' . trim($filename) . '"');

		if (!empty($modified))
			header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');

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
		header('HTTP/1.1 304 Not Modified', NULL, 304);

		$expires = intval($expires);
		header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime($expires)) . ' GMT');
		header('Connection: close');
		exit;
	}

	/**
	 * Remove unwatned characters from a filename, only allowing underscores, slashes and periods, next to letters. NO NUMBERS!
	 * @param string $string
	 * @return string The cleaned filename. 
	 */
	public static function sanitizeFileName($string)
	{
		$string = preg_replace('/[^a-z0-9\_\/\.]/', '', strtolower(trim($string)));
		$string = trim($string, './');
		return $string;
	}

	/**
	 * Insert a classname and/or filename, and get the filename/classname in proper format back.
	 * 
	 * @param type $string 
	 * @return array With 'class' and 'file'
	 */
	public static function getNames($string)
	{
		$filename = self::sanitizeFileName($string);
		$classname = ucwords(str_replace(array('_', '/', '.'), ' ', $filename));
		$classname = str_replace(' ', '_', $classname);
		return array(
			'file' => $filename,
			'class' => $classname
		);
	}

	/**
	 * Does a redirect if desiredUrl is different from the current Url.
	 * @param type $desiredUrl 
	 */
	public static function force_url($desiredUrl = '')
	{
		$desiredUrl = ltrim($desiredUrl, '/');
		if ($desiredUrl != self::$url)
			redirect($desiredUrl);
	}

	/**
	 * Get feature file.
	 * @return type 
	 */
	public static function getFeatures()
	{
		$features = PATH_CORE . 'base/features.php';
		if (!file_exists($features))
			return '';
		return file_get_contents($features);
	}

}

