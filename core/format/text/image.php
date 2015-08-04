<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Image extends Base
{
    /**
     * Minimum parameter count.
     * @var type
     */
    protected $_minParameterCount = 1;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $url = $parts[0];
        $title = isset($parts[1]) ? ' title="'. $parts[1] . '"' : '';
        $class = getKey($parts, 2);
        $extra = $class ? " class= \"{$class}\"" : '';
        return "<img src=\"/assets/{$url}\" {$title}{$extra}/>";
    }
}
