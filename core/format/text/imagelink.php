<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class ImageLink extends Base
{
    /**
     * Minimum parameter count.
     * @var int
     */
    protected $_minParameterCount = 2;

    /**
     * Parse string into the required data.
     * @param array $parts
     * @return string
     */
    protected function _parse($parts)
    {
        $url = '/assets/' . $this->_reverseParse($parts[0]);
        $thumburl = '/assets/' . $this->_reverseParse($parts[1]);
        $title = isset($parts[2]) ? ' title="'. $parts[2] . '"' : '';
        $class = isset($parts[3]) ? $this->_reverseParse($parts[3]) : false;
        $extra = $class ? " class= \"{$class}\"" : '';
        return "<a href=\"{$url}\"{$extra}><img src=\"{$thumburl}\" {$title}/></a>";
    }
}
