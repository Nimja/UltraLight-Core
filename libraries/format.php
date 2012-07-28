<?php

/**
 * Basic String to HTML formatting class.
 * 
 */
class Format
{

	/**
	 * This strips HTML and parses basic characters
	 * 
	 * * - bullet list<br /># - numbered list<br />= - title<br />
	 * ! - Forced line (to avoid bold as the first word)<br />
	 * In-sentence options: *bold*, _italic_, -striked-<br />
	 * Links: "Text for the link"=>/stories/frozen-youth (url you desire, without spaces)
	 * 
	 * @param string $str In the format described above.
	 * @return string HTML in a nice format.
	 */
	static function parse($str, $makeLinks = TRUE)
	{
		#Decode HTML entities
		$str = html_entity_decode($str);

		#Remove tags.
		$str = strip_tags($str);

		#Encode HTML entities, like <, &, >, etc.
		$str = htmlentities($str);

		#Split into lines.
		$lines = explode("\n", $str);

		$open = '';
		$prevopen = '';
		$result = '';
		foreach ($lines as $line) {
			#Parts for this line.
			$line = trim($line);
			$first = substr($line, 0, 1);
			$rest = trim(substr($line, 1));

			#Switch, based on first character.
			switch ($first) {
				#Basic H5 title.
				case '=': $open = '';
					$size = 3;
					while (substr($rest, 0, 1) == '=') {
						$rest = trim(substr($rest, 1));
						$size++;
					}
					$line = '<h' . $size . '>' . $rest . '</h' . $size . '>';
					break;
				#Unordered list.
				case '*': $open = 'ul';
					$line = '<li>' . $rest . '</li>';
					break;
				#Ordered list.
				case '#': $open = 'ol';
					$line = '<li>' . $rest . '</li>';
					break;
				#Empty lines, close open tags.
				case '': $open = '';
					break;

				#Forcing a normal line (for example, if we want to start with bold)
				case '!': $line = $rest;
				#Normal text lines, if we're already in a paragraph, use a linebreak.
				default: $open = 'p';
					if ($prevopen == $open) {
						$line = '<br />' . $line;
					}
			}
			#If our open tag changed, apply it.
			if ($open != $prevopen) {
				if (!empty($prevopen))
					$result .= '</' . $prevopen . '>';

				if (!empty($open))
					$result .= '<' . $open . '>';
			}
			#Remember the previous tag.
			$prevopen = $open;

			if (!empty($line))
				$result .= $line . "\n";
		}
		#Close any open tag.
		if (!empty($open))
			$result .= '</' . $open . '>';

		#Do bold/italic
		if (!empty($result)) {
			$result = preg_replace('/\*(.+)\*/U', '<b>$1</b>', $result);
			$result = preg_replace('/\_(.+)\_/U', '<i>$1</i>', $result);
			$result = preg_replace('/\-\-(.+)\-\-/U', '<s>$1</s>', $result);

			#Add links.
			if ($makeLinks) {
				$result = preg_replace_callback('/&quot;(.+)&quot;=&gt;([^\s]+)/', 'Format::makeLink', $result);
			} else {
				$result = preg_replace('/&quot;(.+)&quot;=&gt;([^\s]+)/', '$1', $result);
			}
		}


		return $result;
	}

	/**
	 * Return a nicely formatted snippet of the string, using the parser after cutting off.
	 * @param string $string
	 * @param int $maxLength
	 * @return string 
	 */
	public static function snippet($string, $maxLength)
	{
		$string = trim($string);
		if (strlen($string) > $maxLength) {
			$string = substr($string, 0, $maxLength);
			$string = substr($string, 0, strrpos($string, " "));
			$string .= '...';
		}
		return self::parse($string, FALSE);
	}
	
	/**
	 * Make link.
	 */
	public static function makeLink() {
		return '--';
	}

}
