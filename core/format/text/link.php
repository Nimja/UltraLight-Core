<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Link extends Base
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
        $title = getKey($parts, 1, $url);
        $class = getKey($parts, 2);
        $extra = $class ? "class= \"{$class}\"" : '';
        return "<a href=\"{$url}\" {$extra}>{$title}</a>";
    }
}
