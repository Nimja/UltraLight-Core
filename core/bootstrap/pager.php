<?php

namespace Core\Bootstrap;

/**
 * A simple class to assist in pagination. Only shows the correct links (previous, first etc.)
 */
class Pager
{
    /**
     * The wrapper around the paginator.
     * @var string
     */
    public $wrapper = '<nav><ul class="pager">%s %s %s</ul></nav>';
    /**
     * Link/button for the first page.
     * @var string
     */
    public $firstPage = '<li class="previous"><a href="%s" class="page_first"><span aria-hidden="true">&laquo;</span></a></li>';
    /**
     * Link/button for the previous page.
     * @var string
     */
    public $previousPage = '<li class="previous"><a href="%s" class="page_previous"><span aria-hidden="true">&lsaquo;</span></a></li>';
    /**
     * Link/button for the center "title".
     * @var string
     */
    public $center = '<li>Page %s</li>';
    /**
     * Link/button for the next page.
     * @var string
     */
    public $nextPage = '<li class="next"><a href="%s" class="page_next"><span aria-hidden="true">&rsaquo;</span></a></li>';
    /**
     * Link/button for the last page.
     * @var string
     */
    public $lastPage = '<li class="next"><a href="%s" class="page_last"><span aria-hidden="true">&raquo;</span></a></li>';
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
     * @var string
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
        if ($page < 1) {
            $this->_page = 1;
        } else if ($page > $this->_lastPage) {
            $this->_page = $this->_lastPage;
        } else {
            $this->_page = $page;
        }
    }
    /**
     * If we should show the first link.
     * @return bool
     */
    protected function _showFirstLink()
    {
        return ($this->_page > 2);
    }
    /**
     * If we should show the previous link.
     * @return bool
     */
    protected function _showPreviousLink()
    {
        return ($this->_page > 1);
    }
    /**
     * If we should show the next link.
     * @return bool
     */
    protected function _showNextLink()
    {
        return ($this->_page <  $this->_lastPage);
    }
    /**
     * If we should show the last link.
     * @return bool
     */
    protected function _showLastLink()
    {
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
        if ($this->_showLastLink()) {
            $right .= $this->_createLink($this->lastPage, $this->_lastPage);
        }
        if ($this->_showNextLink()) {
            $right .= $this->_createLink($this->nextPage, $this->_page + 1);
        }
        $center = sprintf($this->center, $this->_page);
        return sprintf($this->wrapper, $left, $center, $right);
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
