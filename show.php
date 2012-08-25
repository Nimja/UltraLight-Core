<?php

/**
 * Nice global class to show error/info messages in nice HTML.
 */
class Show
{

    /**
     * Return a nice array of lines for the backtrace, much simpler than the real backtrace.
     * 
     * The function doesn't return full paths on purpose,
     * cleaning away pathnames to a nicer visual representation
     * and cleans away classes that are pointless to show (like this one)
     * 
     * @return array 
     */
    private static function getDebug()
    {
        $option = defined('DEBUG_BACKTRACE_IGNORE_ARGS') ? DEBUG_BACKTRACE_IGNORE_ARGS : false;
        $traces = debug_backtrace($option);
        $lines = array();
        foreach ($traces as $trace) {
            //Skip closure functions (they give us no info anyway.
            if (!empty($trace['function']) && $trace['function'] == '{closure}') {
                continue;
            }
            $info = pathinfo($trace['file']);
            $dir = $info['dirname'];
            $core = strpos($dir . '/', PATH_CORE);
            $app = strpos($dir . '/', PATH_APP);

            //Skip the auto-class loading and the show class itself.
            if ($core !== false
                    && (
                    $info['basename'] == 'show.php'
                    || ($info['basename'] == 'core.php' && $trace['line'] < 20)
                    )) {
                continue;
            }

            //Add nice dir identifiers.
            if ($core !== false) {
                $dir = '[Core] ' . substr($dir, strlen(PATH_CORE)) . '/';
            } else if ($app !== false) {
                $dir = '[App] ' . substr($dir, strlen(PATH_APP)) . '/';
            } else {
                $dir = '';
            }

            $lines[] = $dir . $info['basename'] . ' - line: ' . $trace['line'];
        }

        return $lines;
    }

    /**
     * Show a variable in a neat HTML friendly way. - VERY handy. 
     * 
     * @param string $var The variable you want to show.
     * @param string $title The optional title for this variable.
     * @param string $color One of fatal, error, neutral, good or success. CSS colors are also accepted.
     * @param boolean $return Return the export as a string instead of echoing.
     * @return string Optional return value, if $return is true.
     */
    public static function info($var, $title = 'Export Variable', $color = 'neutral', $return = false)
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

        $locations = self::getDebug();

        $location = $locations[0];
        $locations = implode("<br />", $locations);

        //Make the content HTML compatible. 
        $display = htmlentities(trim(print_r($var, true)));
        //Format content per line.
        $lines = explode("\n", $display);
        $display = '';
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

            $display .= '<div style="' . $bg . ' margin: 0px; padding: 1px 5px;" >' . $line . '</div>';
        }

        //Create result.

        $cur = !empty($GLOBALS['curinfo']) ? $GLOBALS['curinfo'] : 0;
        $cur++;
        $GLOBALS['curinfo'] = $cur;

        $result = '<div style="font-family: arial; font-size: 14px; text-align: left; color: black; background: '
                . $color . '; margin: 5px; padding: 3px 5px; border-radius: 5px; border: 2px solid #999; ">'
                //Start the location block.
                . '<div style="float: right; color: #999; width: 250px;">'
                //Detailed trace
                . '<div style="position: absolute; display: none; width: 250px; padding: 3px; margin: -4px 0px 0px -4px; background: white; border: 1px solid black;" id="trace-' . $cur . '" onclick="document.getElementById(\'trace-' . $cur . '\').style.display=\'none\'">' . $locations . '</div>'
                //Single line trace
                . '<div onclick="document.getElementById(\'trace-' . $cur . '\').style.display=\'block\'">' . $location . '</div></div>'
                //Title
                . $title . '<div style="font-family: courier; font-size: 11px; margin:0px; padding: 0px; border: 1px solid #ccc; background: #f9f9f9;">'
                //Actual content.
                . $display . '</div></div>';

        //Switch between returning or echoing. (echo is default);
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