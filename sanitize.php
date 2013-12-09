<?php
/**
 * Sanitation class for variables.
 */
class Sanitize
{

    /**
     * Sanitation function to run on POST/GET variables.
     *
     * @param mixed $value The value you wish to sanitize.
     * @param booelan|array $keepTags
     * @return string Sanitized string.
     */
    public static function clean($value, $keepTags = true)
    {
        if (blank($value)) {
            $result = null;
        } if (is_numeric($value)) {
            $result = $value;
        } else if (is_array($value)) {
            $result = array();
            foreach ($value as $key => $val) {
                $result[self::clean($key)] = self::clean($val);
            }
        } else {
            $stripHtml = is_array($keepTags) || empty($keepTags);
            //Remove magic quotes.
            $string = (ini_get('magic_quotes_gpc')) ? stripslashes($value) : $value;
            //fix euro symbol.
            $string = str_replace(chr(226) . chr(130) . chr(172), '&euro;', trim($string));
            $string = utf8_decode($string);
            $string = html_entity_decode($string, ENT_COMPAT, 'ISO-8859-15');
            //Normalize linebreaks to LINUX format.
            $string = strtr($string, array("\r\n" => "\n", "\r" => "\n"));
            if ($stripHtml) {
                $allowedTags = is_array($keepTags) ? $keepTags : null;
                $string = strip_tags($string, $allowedTags);
            }
            $result = htmlentities($string, ENT_COMPAT, 'ISO-8859-15');
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
        $string = ($toLower) ? strtolower(trim($string)) : trim($string);
        //Strip unwanted characters.
        $string = preg_replace('/[^A-Za-z0-9\_\/\.]/', '', $string);
        //Remove double slashes.
        $string = preg_replace('/\/+/', '/', $string);
        //Remove leading/trailing dots, slashes. So ../ is removed.
        $string = trim($string, './');
        //Return cleaned string.
        return $string;
    }
}