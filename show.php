<?php

/**
 * Nice global class to show error/info messages in nice HTML.
 */
class Show
{
    public static $curError = 0;
    /**
     * Show a variable in a neat HTML friendly way. - VERY handy.
     *
     * @param string $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param string $color One of fatal, error, neutral, good or success. CSS colors are also accepted.
     * @param boolean $return Return the export as a string instead of echoing.
     * @return string Optional return value, if $return is true.
     */
    public static function info($var, $title = 'Export Variable',
        $color = 'neutral', $return = false)
    {
        //Choose a color.
        $colors = array(
            'fatal' => '#f99',
            'error' => '#fdd',
            'neutral' => '#eee',
            'good' => '#def',
            'success' => '#dfd',
        );
        $color = !empty($colors[$color]) ? $colors[$color] : $color;

        self::$curError++;
        if ($var instanceof Exception) {
            $title = 'Exception!';
            $info = self::_showVariable($var->getMessage());
            $trace = self::_getTraceInfo(self::_getDebug($var->getTrace()));
        } else {
            $info = self::_showVariable($var);
            $trace = self::_getTraceInfo(self::_getDebug());
        }

        //Create result.

        $result = '<div style="font-family: arial; font-size: 14px; text-align: left; color: black; background: '
            . $color . '; margin: 5px; padding: 3px 5px; border-radius: 5px; border: 2px solid #999; ">'
            //Trace block
            .$trace
            //Title
            . $title . '<div style="font-family: courier; font-size: 11px; margin:0px; padding: 0px; border: 1px solid #ccc; background: #f9f9f9;">'
            //Actual content.
            . $info . '</div></div>';

        //Switch between returning or echoing. (echo is default);
        if ($return) {
            return $result;
        } else {
            echo $result;
        }
    }

    /**
     * Return a nice array of lines for the backtrace, much simpler than the real backtrace.
     *
     * The function doesn't return full paths on purpose,
     * cleaning away pathnames to a nicer visual representation
     * and cleans away classes that are pointless to show (like this one)
     *
     * @return array
     */
    private static function _getDebug($traces = null)
    {
        $option = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : false;
        $traces = $traces ?: debug_backtrace($option);
        $lines = array();
        foreach ($traces as $trace) {
            $function = getKey($trace, 'function');
            $line = getKey($trace, 'line');
            $file = getKey($trace, 'file');
            //Skip closure functions (they give us no info anyway.
            if ($function == '{closure}') {
                continue;
            }
            $info = pathinfo($file);
            $info_dirname = getKey($info, 'dirname');
            $info_basename = getKey($info, 'basename');
            $core = strpos($info_dirname . '/', PATH_CORE);
            $app = strpos($info_dirname . '/', PATH_APP);
            //Skip the auto-class loading and the show class itself.
            if ($core !== false
                && (
                $info_basename == 'show.php'
                || ($info_basename == 'core.php' && $line < 26)
                )) {
                continue;
            }
            //Add nice dir identifiers.
            if ($core !== false) {
                $dir = '[Core] ' . substr($info_dirname, strlen(PATH_CORE)) . '/';
            } else if ($app !== false) {
                $dir = '[App] ' . substr($info_dirname, strlen(PATH_APP)) . '/';
            } else {
                $dir = '';
            }

            $lines[] = $dir . $info['basename'] . ' - line: ' . $line;
        }
        return $lines;
    }
    /**
     * Generate nifty trace HTML.
     * @return type
     */
    private static function _getTraceInfo($locations)
    {
        $curError = self::$curError;
        $location = $locations[0];
        $locations = implode("<br />", $locations);
        $style = 'position: absolute; display: none; width: 250px; padding: 3px; margin: -4px 0px 0px -4px; background: white; border: 1px solid black;';
        return "<div style=\"float: right; color: #999; width: 250px;\">
                    <div style=\"$style\" id=\"trace-$curError\" onclick=\"document.getElementById('trace-$curError').style.display='none'\">$locations</div>
                    <div onclick=\"document.getElementById('trace-$curError').style.display='block'\">$location</div>
                </div>";
    }
    /**
     * Print_R a variable nicely, excluding private/protected stuff.
     *
     * @param mixed $var
     * @return string
     */
    private static function _showVariable($var) {
        if (is_null($var)) {
            $var = '[NULL]';
        } else if (is_bool($var)) {
            $var = $var ? '[TRUE]' : '[FALSE]';
        }

        //Make the content HTML compatible and split per lines.
        $lines = explode("\n", htmlentities(trim(print_r($var, true))));
        $display = array();
        $count = 0;
        $matches = array();

        $hide = 0;
        foreach ($lines as $line) {
            $line = rtrim($line);

            //If we are in a hidden block, check for a [ on the current line.
            if ($hide > 0) {
                if (substr($line, $hide, 1) == '[') {
                    $hide = 0;
                } else {
                    continue;
                }
            }

            //If the current 'block' matches :protected or :private in the first [] thing.
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

            $display[] = "<div style=\"$bg margin: 0px; padding: 1px 5px;\" >$line</div>";
        }
        return implode("\n", $display);
    }

    /**
     * Show a variable/error and stop PHP.
     *
     * @param string $var The variable you want to show.
     * @param string $title The optional title for this variable.
     */
    public static function fatal($var, $title = '<b>Fatal error:</b>')
    {
        self::info($var, $title, 'fatal');
        exit;
    }

    /**
     * Display a basic error.
     *
     * @param string $var The variable you want to show.
     * @param string $title The optional title for this variable.
     */
    public static function error($var, $title = '<b>Error:</b>', $return = false)
    {
        return self::info($var, $title, 'error', $return);
    }

}