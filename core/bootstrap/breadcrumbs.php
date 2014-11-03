<?php namespace Core\Bootstrap;
/**
 * Easy breadcrumb generator in bootstrap.
 *
 * The returned object can be cast to string.
 */
class Breadcrumbs
{
    /**
     * THe title of the current page.
     * @var string
     */
    private $_title;
    /**
     * Menu array, keys are the links, values the description.
     * @var array
     */
    private $_urls;
    /**
     * The current class.
     * @var string
     */
    private $_barClass;
    /**
     * Current urls from / to the current.
     * @var array
     */
    private $_curUrls;

    /**
     * Construct the breadcrumbs.
     * @param string $title
     * @param array $menuArray
     * @param string $curUrl
     * @param string $barClass
     */
    public function __construct($title, $menuArray, $curUrl, $barClass = '')
    {
        $this->_title = $title;
        $this->_urls = $this->_parseMenuArray($menuArray);
        $this->_curUrls = $this->_parseUrl($curUrl);
        $this->_barClass = $barClass;
    }

    /**
     * Parse the url into each used part.
     * @param string $url
     * @return array
     */
    private function _parseUrl($url)
    {
        $result = array('/');
        if (!empty($url)) {
            $parts = explode('/', $url);
            $cur = '';
            foreach ($parts as $part) {
                $cur .= "/{$part}";
                $result[] = $cur;
            }
        }
        return $result;
    }

    /**
     * Parse recursive array into flat array.
     * @param array $array
     * @return array
     */
    private function _parseMenuArray($array)
    {
        $result = array();
        foreach ($array as $title => $url) {
            if (is_array($url)) {
                $subResult = $this->_parseMenuArray($url);
                $result = array_merge($result, $subResult);
                reset($subResult);
                $firstKey = key($subResult);
                $urlBefore = substr($firstKey, 0, strrpos($firstKey, '/'));
                if (!isset($result[$urlBefore])) {
                    $result[$urlBefore] = $title;
                }
            } else {
                $result[$url] = $title;
            }
        }
        return $result;
    }

    /**
     * Create li items for the url parts.
     * @return array
     */
    private function _createList()
    {
        $urls = $this->_curUrls;
        array_pop($urls);
        $result = array();
        foreach ($urls as $url) {
            if (isset($this->_urls[$url])) {
                $title = $this->_urls[$url];
                $result[] = "<li><a href=\"{$url}\">{$title}</a></li>";
            }
        }
        return implode("\n", $result);
    }

    /**
     * Render breadcrumbs as string as per bootstrap 3.
     * @return type
     */
    public function __toString()
    {
        if (count($this->_curUrls) == 1) {
            return '';
        }
        $items = $this->_createList();
        return "<div class=\"container\"><ol class=\"breadcrumb\">
        {$items}
    <li class=\"active\">{$this->_title}</li>
</ol></div>";
    }
}