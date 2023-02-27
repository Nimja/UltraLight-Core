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
            $string = html_entity_decode($string ?: '', ENT_COMPAT, 'UTF-8');
            //Normalize linebreaks to LINUX format.
            $string = str_replace(["\r\n", "\r"], "\n", $string);
            if ($stripHtml) {
                $allowedTags = is_array($keepTags) ? $keepTags : null;
                $string = strip_tags($string, $allowedTags);
            }
            $result = htmlentities($string, ENT_COMPAT, 'UTF-8');
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
}
