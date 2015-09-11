<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Image extends Base
{
    /**
     * Minimum parameter count.
     * @var int
     */
    protected $_minParameterCount = 1;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $url = $this->_reverseParse($parts[0]);
        $title = isset($parts[1]) ? ' title="'. $parts[1] . '"' : '';
        $class = isset($parts[2]) ? $this->_reverseParse($parts[2]) : false;
        $extra = $class ? " class= \"{$class}\"" : '';
        return "<img src=\"/assets/{$url}\" {$title}{$extra}/>";
    }
}
