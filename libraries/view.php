<?php

if (!defined('DEBUG') || !class_exists('Load')) {
	show_exit('Do not load this file directly');
}

#All libraries should be self-contained and independant, but they can use the defined paths.

class View
{

	/**
	 * Enter a string and an associative array of values.
	 * @param string $string
	 * @param array $values
	 * @return array 
	 */
	public static function fillValues($string, $values)
	{
		self::$_values = &$values;
		return preg_replace_callback('/\[\[.*\]\]/U', 'View::doReplace', $string);
	}

	/**
	 * Holder of the values.
	 * @var array
	 */
	protected static $_values = array();

	/**
	 * Does a replace of strings based on the values array.
	 * @param array $matches preg_replace match.
	 * @return string The placeholder result. 
	 */
	protected static function doReplace($matches)
	{
		#Strip the [[ and ]]
		$match = substr($matches[0], 2, -2);
		#Array we're working with.
		$vals = &self::$_values;
		return (empty($vals[$match]) && !is_numeric($vals[$match])) ? '' : $vals[$match];
	}

	/**
	 * Load a view and fill the data.
	 * 
	 * @param string $view
	 * @param array $data
	 * @return string Translated view.
	 */
	public static function show($view, $data = NULL)
	{
		if (!empty($data) && !is_array($data)) {
			show_error($data, 'Variable is not an array');
			return FALSE;
		}
		#Load the template
		$view = Load::view($view);
		if (empty($view))
			return FALSE;

		#Fill in the data, if there is any.
		if (!empty($data)) {
			return self::fillValues($view, $data);
		} else {
			return $view;
		}
		return $result;
	}

	#Similar to load, but fills a list with items.

	public static function showList($view, $datas)
	{
		if (!empty($datas) && !is_array($datas)) {
			show_error($data, 'Variable is not an array');
			return FALSE;
		}

		#Load the template
		$view = Load::view($view);
		if (empty($view))
			return FALSE;

		$results = '';
		foreach ($datas as $data) {
			if (empty($data))
				continue;

			$results .= self::fillValues($view, $data);
			#Fill in the placeholders with data.
		}
		return $results;
	}

	public function load($view, $data = NULL)
	{
		return self::show($view, $data);
	}

	public function loadList($view, $datas)
	{
		return self::showList($view, $datas);
	}

}