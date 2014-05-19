<?php
namespace Core\Format;
/**
 * A simple class to assist in pagination. Only shows the correct links (previous, first etc.)
 */
class Paginate
{
    /**
     * The wrapper around the paginator.
     * @var string
     */
    public $wrapper = '<div class="paginate"><div class="left">%s</div><div class="right">%s</div>%s<clear /></div>';
    /**
     * Link/button for the first page.
     * @var string
     */
    public $firstPage = '<a class="first" href="%s">&laquo;</a>';
    /**
     * Link/button for the previous page.
     * @var string
     */
    public $previousPage = '<a class="previous" href="%s">&lsaquo;</a>';
    /**
     * Link/button for the center "title".
     * @var string
     */
    public $center = '<div class="center">Page %s</a>';
    /**
     * Link/button for the next page.
     * @var string
     */
    public $nextPage = '<a class="next" href="%s">&rsaquo;</a>';
    /**
     * Link/button for the last page.
     * @var string
     */
    public $lastPage = '<a class="last" href="%s">&raquo;</a>';
    /**
     * Total for this page.
     * @var int
     */
    private $_total;
    /**
     * The current page.
     * @var int
     */
    private $_page;
    /**
     * Last page.
     * @var int
     */
    private $_lastPage;
    /**
     * The url.
     * @var type
     */
    private $_baseUrl = '';
    /**
     * String to avoid generating twice.
     * @var string
     */
    private $_string = '';

    /**
     *
     * @param string $baseUrl
     * @param int $total
     * @param int $page
     * @param int $perPage
     */
    public function __construct($baseUrl, $total, $page, $perPage)
    {
        $this->_lastPage = floor($total / $perPage) + 1;
        $this->_baseUrl = $baseUrl;
        $this->_total = $total;
        $this->_page = $page < 1 ? 1 : $page > $this->_lastPage ? $this->_lastPage : $page;
    }
    /**
     * If we should show the first link.
     * @return type
     */
    protected function _showFirstLink() {
        return ($this->_page > 2);
    }
    /**
     * If we should show the previous link.
     * @return type
     */
    protected function _showPreviousLink() {
        return ($this->_page > 1);
    }
    /**
     * If we should show the next link.
     * @return type
     */
    protected function _showNextLink() {
        return ($this->_page <  $this->_lastPage);
    }
    /**
     * If we should show the last link.
     * @return type
     */
    protected function _showLastLink() {
        return ($this->_page <  $this->_lastPage - 1);
    }

    private function _createString()
    {
        $left = '';
        if ($this->_showFirstLink()) {
            $left .= $this->_createLink($this->firstPage, 1);
        }
        if ($this->_showPreviousLink()) {
            $left .= $this->_createLink($this->previousPage, $this->_page - 1);
        }
        $right = '';
        if ($this->_showNextLink()) {
            $right .= $this->_createLink($this->nextPage, $this->_page + 1);
        }
        if ($this->_showLastLink()) {
            $right .= $this->_createLink($this->lastPage, $this->_lastPage);
        }
        $center = sprintf($this->center, $this->_page);
        return sprintf($this->wrapper, $left, $right, $center);
    }

    /**
     * ToString function.
     * @return string
     */
    public function __toString()
    {
        if (empty($this->_string)) {
            $this->_string = $this->_createString();
        }
        return $this->_string;
    }

    /**
     * Create link.
     * @param string $string
     * @param int $number
     * @return string
     */
    private function _createLink($string, $number)
    {
        $link = $this->_baseUrl . $number;
        return sprintf($string, $link);
    }
}