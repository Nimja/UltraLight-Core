<?php namespace Core\Format;
/**
 * Basic String to HTML formatting class.
 *
 */
class Text
{
    /**
     * Registered blockparsers.
     * @var array
     */
    private static $_blockClasses = array(
        'link' => '\Core\Format\Text\Link',
        'tooltip' => '\Core\Format\Text\Tooltip',
    );
    /**
     * Instantiated blockparsers.
     * @var array
     */
    private static $_blockParsers = array();

    /**
     * This strips HTML and parses basic characters
     *
     * * - bullet list<br /># - numbered list<br />= - title<br />
     * ! - Forced line (to avoid bold as the first word)<br />
     * In-sentence options: ^bold^, _italic_, -striked-<br />
     * [type|data|separated|by|pipes]
     *
     * @param string $str In the format described above.
     * @param boolean $parseBlocks Parse blocks styled like.
     * @return string HTML in a nice format.
     */
    public static function parse($str, $parseBlocks = true)
    {
        // Decode HTML entities
        $str = html_entity_decode($str);
        // Remove tags.
        $str = strip_tags($str);
        // Encode HTML entities, like <, &, >, etc.
        $str = htmlentities($str);
        // Remove multiple spaces.
        $str = preg_replace('/ {2,}/', ' ', $str);
        // Split into lines.
        $lines = explode("\n", $str);
        $open = '';
        $prevopen = '';
        $result = '';
        foreach ($lines as $line) {
            // Parts for this line.
            $line = trim($line);
            $first = substr($line, 0, 1);
            $rest = trim(substr($line, 1));

            // Switch, based on first character.
            switch ($first) {
                // Basic H5 title.
                case '=': $open = '';
                    $size = 3;
                    while (substr($rest, 0, 1) == '=') {
                        $rest = trim(substr($rest, 1));
                        $size++;
                    }
                    $line = '<h' . $size . '>' . $rest . '</h' . $size . '>';
                    break;
                // Unordered list.
                case '*': $open = 'ul';
                    $line = '<li>' . $rest . '</li>';
                    break;
                // Ordered list.
                case '#': $open = 'ol';
                    $line = '<li>' . $rest . '</li>';
                    break;
                // Empty lines, close open tags.
                case '': $open = '';
                    break;

                // Forcing a normal line (for example, if we want to start with bold)
                case '!': $line = $rest;
                // Normal text lines, if we're already in a paragraph, use a linebreak.
                default: $open = 'p';
                    if ($prevopen == $open) {
                        $line = '<br />' . $line;
                    }
            }
            // If our open tag changed, apply it.
            if ($open != $prevopen) {
                if (!empty($prevopen)) $result .= '</' . $prevopen . '>';

                if (!empty($open)) $result .= '<' . $open . '>';
            }
            // Remember the previous tag.
            $prevopen = $open;

            if (!empty($line)) $result .= $line . "\n";
        }
        // Close any open tag.
        if (!empty($open)) $result .= '</' . $open . '>';

        #// o bold/italic
        if (!empty($result)) {
            $translate = array(
                '/\^(.+)\^/U' => '<b>$1</b>',
                '/\_(.+)\_/U' => '<i>$1</i>',
                '/\-\-(.+)\-\-/U' => '<s>$1</s>',
            );
            $result = preg_replace(array_keys($translate), $translate, $result);
            // Add links.
            if ($parseBlocks) {
                $result = self::_parseBlocks($result);
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
     * Parse all the blocks.
     * @param string $string
     * @return string
     */
    private static function _parseBlocks($string)
    {
        return preg_replace_callback(
            '/\[([]a-z]+)\|([^\]]+)\]/',
            function ($matches)
            {
                $parser = self::_getBlockParser($matches[1]);
                return $parser->parse($matches[2]);
            }, $string
        );
    }

    /**
     * Get block parser, will only instantiate once.
     * @param string $type
     * @return \Core\Format\Text\Link
     * @throws \Exception
     */
    private static function _getBlockParser($type)
    {
        if (empty(self::$_blockParsers[$type])) {
            if (!isset(self::$_blockClasses[$type])) {
                throw new \Exception("No parser registered for type: $type");
            }
            $class = self::$_blockClasses[$type];
            self::$_blockParsers[$type] = new $class();
        }
        return self::$_blockParsers[$type];
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
}
