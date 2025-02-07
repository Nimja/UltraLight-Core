<?php

/**
 * Sanitation class for variables.
 */
class Sanitize
{

    /**
     * Sanitize classname, also make sure that there is a preceding backslash.
     *
     * Ideally to use it, you pass \App\Class::class
     *
     * @param string $className
     * @return string
     */
    public static function className($className)
    {
        $backslash = "\\";
        return $backslash . trim($className, $backslash);
    }

    /**
     * Get proper string for class + method.
     *
     * @param string $className
     * @param string $method
     * @return string
     */
    public static function classMethod($className, $method)
    {
        $className = self::className($className);
        return "{$className}::{$method}";
    }

    /**
     * Sanitation function to run on POST/GET variables.
     *
     * @param mixed $value The value you wish to sanitize.
     * @param booelan|array $keepTags
     * @return string|array Sanitized string (or each element of array).
     */
    public static function clean($value, $keepTags = true)
    {
        if (blank($value)) {
            $result = null;
        }
        if (is_numeric($value)) {
            $result = $value;
        } else if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $val) {
                $result[self::clean($key)] = self::clean($val);
            }
        } else {
            $stripHtml = is_array($keepTags) || empty($keepTags);
            //Remove magic quotes.
            $string = (ini_get('magic_quotes_gpc')) ? stripslashes($value) : $value;
            //Fix euro symbol.
            $string = str_replace(chr(226) . chr(130) . chr(172), '&euro;', trim($string ?: ''));
            //Remove non-printable characters (like 255 and others, but keep unicode characters intact).
            $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x80-\x9F]/u', '', $string);
            //$string = utf8_decode($string);
            $string = self::from_html_entities($string ?: '', ENT_COMPAT, 'UTF-8');
            //Normalize linebreaks to LINUX format.
            $string = str_replace(["\r\n", "\r"], "\n", $string);
            if ($stripHtml) {
                $allowedTags = is_array($keepTags) ? $keepTags : null;
                $string = strip_tags($string, $allowedTags);
            }
            $result = self::to_html_entities($string, ENT_COMPAT, 'UTF-8');
        }
        return $result;
    }

    /**
     * Remove unwanted characters from a filename, only allowing underscores, slashes and periods, next to letters.
     *
     * @param string $string
     * @return string The cleaned filename.
     */
    public static function fileName($string, $toLower = true)
    {
        $string = ($toLower) ? strtolower(trim($string ?: '')) : trim($string ?: '');
        //Strip unwanted characters.
        $string = preg_replace('/[^A-Za-z0-9\_\/\.]/', '', $string);
        //Remove double slashes.
        $string = preg_replace('/\/+/', '/', $string);
        //Remove leading/trailing dots, slashes. So ../ is removed.
        $string = trim($string, './');
        //Return cleaned string.
        return $string;
    }

    /**
     * Basic HTML stripper, that leaves unclosed tags intact.
     *
     * @param string $string
     * @return string
     */
    public static function stripHtml($string)
    {
        $strippedCss = preg_replace('/<style.*<\/style>/s', '', $string);
        $strippedJs = preg_replace('/<script.*<\/script>/s', '', $strippedCss);
        $removedOneLineTags = preg_replace("/<[^>\n]*>/mu", '', $strippedJs);
        $removedMultiLineTags = preg_replace("/<a [^>]*?>/s", '', $removedOneLineTags);
        return $removedMultiLineTags;
    }

    /**
     * Recursive decoding, to remove any kind of double-encoding that happened for whatever reason.
     */
    public static function from_html_entities($string)
    {
        $newstring = '';
        // Recursive decoding to make sure that any double-encoding is removed.
        while ($string != $newstring) {
            $newstring = html_entity_decode($string);
            $string = $newstring;
        }
        return $newstring;
    }

    /**
     * A much improved version of htmlentities that ALSO encodes emoji and other weird chars.
     *
     * Thank you, random internet person.
     */
    public static function to_html_entities($string)
    {
        $stringBuilder = "";
        $offset = 0;

        if (empty($string)) {
            return "";
        }

        while ($offset >= 0) {
            $decValue = self::ordutf8($string, $offset);
            $char = self::unichr($decValue);

            $htmlEntited = htmlentities($char);
            if ($char != $htmlEntited) {
                $stringBuilder .= $htmlEntited;
            } elseif ($decValue >= 128) {
                $stringBuilder .= "&#" . $decValue . ";";
            } else {
                $stringBuilder .= $char;
            }
        }

        return $stringBuilder;
    }

    /**
     * Get the character value for multi-byte UTF-8 characters.
     *
     * source - http://php.net/manual/en/function.ord.php#109812
     *
     * @param string $string
     * @param int $offset
     * @return void
     */
    private static function ordutf8($string, &$offset)
    {
        $code = ord(substr($string, $offset, 1));
        if ($code >= 128) {        //otherwise 0xxxxxxx
            if ($code < 224) $bytesnumber = 2;                //110xxxxx
            else if ($code < 240) $bytesnumber = 3;        //1110xxxx
            else if ($code < 248) $bytesnumber = 4;    //11110xxx
            $codetemp = $code - 192 - ($bytesnumber > 2 ? 32 : 0) - ($bytesnumber > 3 ? 16 : 0);
            for ($i = 2; $i <= $bytesnumber; $i++) {
                $offset++;
                $code2 = ord(substr($string, $offset, 1)) - 128;        //10xxxxxx
                $codetemp = $codetemp * 64 + $code2;
            }
            $code = $codetemp;
        }
        $offset += 1;
        if ($offset >= strlen($string)) $offset = -1;
        return $code;
    }

    // source - http://php.net/manual/en/function.chr.php#88611
    private static function unichr($u)
    {
        return mb_convert_encoding('&#' . intval($u) . ';', 'UTF-8', 'HTML-ENTITIES');
    }
}
