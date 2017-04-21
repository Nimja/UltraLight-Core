<?php namespace Core\Bootstrap;
/**
 * Basic listgroup.
 *
 * http://getbootstrap.com/components/#list-group
 */
class Listgroup
{
    /**
     * Currently active link.
     * @var string
     */
    private $_active;
    /**
     * List aray, keys are the links, values the name.
     * @var array
     */
    private $_urls;

    /**
     * @param array $urls
     * @param string $active
     */
    public function __construct(array $urls, $active = '')
    {
        $this->_urls = $urls;
        $this->_active = $active;
    }

    /**
     * Render listgroup.
     * @return type
     */
    public function __toString()
    {
        $result = ['<div class="list-group">'];
        foreach ($this->_urls as $url => $name) {
            $active = $url == $this->_active ? ' active' : '';
            $result[] = "<a href=\"{$url}\" class=\"list-group-item{$active}\">{$name}</a>";
        }
        $result[] = '</div>';
        return implode(PHP_EOL, $result);
    }
}