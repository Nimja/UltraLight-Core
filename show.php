<?php

/**
 * Nice global class to show error/info messages in nice HTML.
 */
class Show
{
    /**
     * We only insert this style once, this allows the actual error to be human readable in pure HTML.
     *
     * @var string
     */
    private static $_style = '<style>'
        . 'ul-error {z-index: 1000; border-radius: 7px; display: block; font-family: "Lucida Console", Monaco, monospace; font-size: 14px; text-align: left; color: black; '
        . 'background: white; margin: 10px; padding: 3px; border: 1px solid gray;}' . PHP_EOL
        . 'ul-error title {display: block; font-weight: bold; height: 25px; padding-left: 5px; }' . PHP_EOL
        . 'ul-error trace {display: block; float: right; width: auto; height: 23px; padding: 1px 5px; background: white; border: 1px solid black; opacity: .8; }' . PHP_EOL
        . 'ul-error trace label {cursor: pointer;}' . PHP_EOL
        . 'ul-error trace rest {position: absolute; display: none; margin: -5px; white-space: pre; background: white; border: 1px solid black;}' . PHP_EOL
        . 'ul-error trace input[type=checkbox] {display: none;}' . PHP_EOL
        . 'ul-error trace input[type=checkbox]:checked ~ rest {display: block;}' . PHP_EOL
        . 'ul-error msg {display: block; border-radius: 5px; background: #fff; font-size: 11px; margin:0px; padding: 0px; border: 1px solid gray;}' . PHP_EOL
        . 'ul-error msg c {display: block; margin: 0px; padding: 1px 5px; min-height: 15px; white-space: pre-wrap; border-bottom: 1px solid #eee;}' . PHP_EOL
        . 'ul-error msg c:nth-child(even) { background: #f5f5f5;}' . PHP_EOL
        . '</style>' . PHP_EOL . PHP_EOL;

    const STR_PAD = '  ';
    const COLOR_SUCCESS = '#dfd'; // Green.
    const COLOR_NICE = '#def'; // Blue.
    const COLOR_NEUTRAL = '#eee'; // Gray.
    const COLOR_ERROR = '#fdd'; // Orange.
    const COLOR_FATAL = '#f99'; // Red.
    const COLOR_DEBUG = '#ff9'; // Yellow.

    /**
     * Current error counter.
     *
     * @var int
     */
    public static $curError = 0;

    /**
     * Level to log (and below).
     *
     * @var int
     */
    public static $errorLogLevel = LOG_ERR;

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
        return self::render($var, $title, $color, $return, LOG_INFO);
    }

    /**
     * Show a variable in a neat HTML friendly way. - VERY handy.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param string $color A CSS color.
     * @param boolean $return Return the export as a string instead of echoing.
     * @return string Optional return value, if $return is true.
     * @return int Optional error level.
     */
    private static function render($var, $title = 'Export Variable', $color = self::COLOR_NEUTRAL, $return = false, $level = 0)
    {
        self::$curError++;
        $result = \Core::$console ?
            self::renderForConsole($var, $title) :
            self::renderForWeb($var, $title, $color, $level);
        // Switch between returning or echoing. (echo is default);
        $resultClean = Core::cleanPath($result);
        if ($return) {
            return $resultClean;
        }
        echo $resultClean;
    }

    /**
     * Simplified output when running in console.
     *
     * @param mixed $var
     * @param string $title
     * @return string
     */
    private static function renderForConsole($var, $title)
    {
        $output = is_object($var) ? get_class($var) . ': ' : gettype($var) . ': ';
        if ($var instanceof \Exception) {
            $output .= $var->getMessage() . PHP_EOL . $var->getTraceAsString();
        } else {
            $output .= print_r($var, true);
        }
        return html_entity_decode($title) . PHP_EOL . $output . PHP_EOL;
    }

    /**
     * Complex HTML output when running in web.
     *
     * @param mixed $var
     * @param string $title
     * @param string $color
     * @return string
     */
    private static function renderForWeb($var, $title, $color, $level)
    {
        // Get debuglines.
        $traceLines = self::_getDebug($var instanceof \Exception ? $var->getTrace() : null);
        // Log to error log when errors happen.
        if ($level <= self::$errorLogLevel) {
            $parts = [
                $title,
                print_r($var, true),
                \Core::$requestFull,
                self::getMiniTrace($traceLines)
            ];
            error_log(implode(' | ', $parts));
        }
        $trace = self::_getTraceInfo($traceLines);
        $cleanTitle = Sanitize::clean($title);
        // Render content.
        $content = self::_showVariable($var);
        // Create result.
        $result = "
            <ul-ERROR style=\"background: $color; \">
            {$trace}

            <title>{$cleanTitle}</title>

            <msg>

            {$content}

            </msg>
            </ul-ERROR>
        ";
        if (self::$curError === 1) {
            $result = self::getStyle() . $result;
        }
        return $result;
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
        $traceArray = $fullTrace ?: debug_backtrace($option);
        $lines = [];
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
            $preLine = $isCore ? $pre : '<b>' . $pre . '</b>';
            $lines[] = $preLine . ' : ' . $post;
        }
        return $lines;
    }

    /**
     * Get trace as string, skipping the first X lines.
     *
     * It might be useful to skip the first X lines for example because we already know where we are.
     *
     * @param integer $skiplines
     * @return string
     */
    public static function getTraceString($skiplines = 0)
    {
        $lines = self::_getDebug();
        if ($skiplines > 0) {
            $lines = array_slice($lines, $skiplines);
        }
        return strip_tags(implode(PHP_EOL, $lines));
    }

    /**
     * Generate nifty trace HTML.
     * @return string
     */
    private static function _getTraceInfo($locations)
    {
        $id = 't-' . self::$curError;
        $location = array_shift($locations);
        $rest = implode(PHP_EOL, $locations);
        return "<trace>
    <label for=\"{$id}\">{$location}</label>
    <input type=\"checkbox\" id=\"{$id}\"><rest>{$rest}</rest>
</trace>";
    }

    /**
     * Generate nifty trace HTML.
     * @return string
     */
    private static function getMiniTrace($locations)
    {
        return implode(PHP_EOL, array_slice($locations, 0, 3));
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
        $result = [];
        foreach ($lines as $line) {
            $line = htmlentities($line);
            $result[] = "<c>{$line}</c>";
        }
        return implode(PHP_EOL, $result);
    }

    /**
     * Show a variable/error and stop PHP.
     *
     * @param mixed $var The variable you want to show.
     * @param string $title The optional title for this variable.
     */
    public static function fatal($var, $title = 'Fatal error')
    {
        self::render($var, $title, self::COLOR_FATAL, false, LOG_CRIT);
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
        return self::render($var, $title, self::COLOR_ERROR, $return, LOG_ERR);
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
        return self::render($var, $title, self::COLOR_SUCCESS, $return, LOG_USER);
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
        return self::render($var, $title, self::COLOR_NICE, $return, LOG_USER);
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
        return self::render($var, $title, self::COLOR_DEBUG, $return, LOG_DEBUG);
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
        $printout = $errNo <= E_USER_WARNING;
        self::error("Code: $errNo, Line: $errLine, File: $errFile", $errStr, !$printout);
    }

    /**
     * If you manually want to insert it.
     *
     * @return string
     */
    public static function getStyle()
    {
        return self::$_style;
    }
}
