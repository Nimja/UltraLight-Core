<?php namespace Core\Format\Text;
/**
 * Basic String to HTML formatting class.
 *
 */
class Tooltip extends Base
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
        $text = $parts[0];
        $detail = htmlentities($this->_reverseParse($parts[1]));
        $class = isset($parts[2]) ? $this->_reverseParse($parts[2]) : '';
        return "<span class=\"hasTooltip {$class}\" data-toggle=\"tooltip\" title=\"{$detail}\">{$text}</span>";
    }
}
