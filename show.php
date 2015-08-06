<?php
/**
 * Nice global class to show error/info messages in nice HTML.
 */
class Show
{
    CONST STR_PAD = '  ';
    const COLOR_SUCCESS = '#dfd'; // Green.
    const COLOR_NICE = '#def'; // Blue.
    const COLOR_NEUTRAL = '#eee'; // Gray.
    const COLOR_ERROR = '#fdd'; // Orange.
    const COLOR_FATAL = '#f99'; // Red.
    const COLOR_DEBUG = '#ff9'; // Yellow.
    public static $curError = 0;

    /**
     * Show a variable in a neat HTML friendly way. - VERY handy.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param string $color A CSS color.
     * @param boolean $return Return the export as a string instead of echoing.
     * @return string Optional return value, if $return is true.
     */
    public static function info($var, $title = 'Export Variable', $color = self::COLOR_NEUTRAL, $return = false)
    {
        self::$curError++;
        if ($var instanceof \Exception) {
            $trace = self::_getTraceInfo(self::_getDebug($var->getTrace()));
        } else {
            $trace = self::_getTraceInfo(self::_getDebug());
        }
        $space = "\n\n";
        $title = $space. Sanitize::clean($title) . $space;
        $content = $space . self::_showVariable($var) . $space;
        // Create result.
        $result = '<div style="font-family: arial; font-size: 14px; text-align: left; color: black; background: '
            . $color . '; margin: 2px; padding: 2px; border: 1px solid gray; ">'
            // Trace block
            . $trace
            // Title
            . '<b>' . $title . '</b><div style="font-family: courier; font-size: 11px; margin:0px; padding: 0px; border: 1px solid gray; background: #f9f9f9;">'
            // Actual content.
            . $content . '</div></div>';

        // Switch between returning or echoing. (echo is default);
        $result = Core::cleanPath($result);
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
    private static function _getDebug($fullTrace = null)
    {
        $option = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : false;
        $traceArray = $fullTrace ? : debug_backtrace($option);
        $lines = array();
        foreach ($traceArray as $trace) {
            $isCore = false;
            $post = '';
            if (!empty($trace['file'])) {
                $pre = Core::cleanPath(getKey($trace, 'file'));
                $isCore = substr($pre, 0, 5) == 'CORE/';
                //Skip the auto-class loading and the show class itself.
                if ($pre == 'CORE/show.php') {
                    continue;
                }
                $post = getKey($trace, 'line');
            } else if (!empty($trace['class'])) {
                $pre = $trace['class'];
                $isCore = substr($pre, 0, 6) == '\\Core\\';
                $post = getKey($trace, 'type', '') . getKey($trace, 'function', '');
            } else {
                $pre = implode(', ', $trace);
            }
            $preLine = $isCore ? $pre : '<span style="color: black">' . $pre . '</span>';
            $lines[] = $preLine . ' : ' . $post;
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
        $style = 'position: absolute; display: none; width: 350px; padding: 3px; margin: -4px 0px 0px -4px; background: white; border: 1px solid black;';
        return "<div style=\"float: right; color: #999; width: 350px;\">
                    <div style=\"$style\" id=\"trace-$curError\" onclick=\"document.getElementById('trace-$curError').style.display='none'\">$locations</div>
                    <div onclick=\"document.getElementById('trace-$curError').style.display='block'\">$location</div>
                </div>";
    }

    /**
     * Show a variable in a nice strict format.
     *
     * @param mixed $var
     * @return string
     */
    private static function _showVariable($var)
    {
        $lines = \Core\Format\Variable::parse($var);
        $result = array();
        foreach ($lines as $index => $line) {
            $line = htmlentities($line);
            $bg = ($index % 2) ? 'background: #f0f2f4;' : '';
            $result[] = "<div style=\"$bg margin: 0px; padding: 1px 5px;\" >$line</div>";
        }
        $resultString = implode(PHP_EOL, $result);
        return strtr(
            $resultString,
            array(
            '  ' => '&nbsp;&nbsp;',
            "\t" => '&nbsp;&nbsp;&nbsp;&nbsp;',
            )
        );
    }

    /**
     * Show a variable/error and stop PHP.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     */
    public static function fatal($var, $title = 'Fatal error')
    {
        self::info($var, $title, self::COLOR_FATAL);
        exit;
    }

    /**
     * Display a basic (orange) error.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param boolean $return
     */
    public static function error($var, $title = 'Error', $return = false)
    {
        return self::info($var, $title, self::COLOR_ERROR, $return);
    }

    /**
     * Display a basic (green) success message.
     *
     * @param string $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param boolean $return
     */
    public static function success($var, $title = 'Error', $return = false)
    {
        return self::info($var, $title, self::COLOR_SUCCESS, $return);
    }

    /**
     * Display a basic (light blue) nice message.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param boolean $return
     */
    public static function nice($var, $title = 'Error', $return = false)
    {
        return self::info($var, $title, self::COLOR_NICE, $return);
    }

    /**
     * Display a basic (yellow) debug message.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param boolean $return
     */
    public static function debug($var, $title = 'Debug', $return = false)
    {
        return self::info($var, $title, self::COLOR_DEBUG, $return);
    }

    /**
     * Return formatted HTML output.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     */
    public static function output($var, $title = 'Debug', $color = self::COLOR_NEUTRAL)
    {
        return self::info($var, $title, $color, true);
    }

    /**
     * Can be registered as the error handler, to display errors inline.
     * @param int $errNo
     * @param string $errStr
     * @param string $errFile
     * @param int $errLine
     */
    public static function handleError($errNo, $errStr, $errFile, $errLine)
    {
        self::error("Code: $errNo, Line: $errLine, File: $errFile", $errStr);
    }
}
