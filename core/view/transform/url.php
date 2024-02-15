<?php

namespace Core\View\Transform;

/**
 * Easily escape values for a URL.
 */
class Url extends Base
{

    protected function _parse()
    {
        $string = \Sanitize::from_html_entities($this->_value);
        return urlencode($string);
    }
}
