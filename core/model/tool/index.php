<?php

namespace Core\Model\Tool;

/**
 * Helper entity for page model, but can be used more broad.
 *
 * @author Nimja
 */
class Index {

    /**
     * The class we use.
     * @var string
     */
    protected $_entityClass;

    /**
     * The edit link.
     * @var string
     */
    protected $_editLink;

    /**
     * Get request values.
     * @var array
     */
    protected $_values;

    /**
     * Basic constructor.
     * @param string $class
     * @param string $editLink
     * @param array $values
     */
    public function __construct($class, $editLink, $values = null)
    {
        $this->_entityClass = $class;
        $this->_editLink = $editLink;
        $this->_values = !empty($values) ? $values : \Request::getValues();
    }

    /**
     * Get entity, returns entity OR redirects if we are successfully editing.
     * @return \Core\Model
     */
    public function getEntityList()
    {
        $class = $this->_entityClass;
        return $class::getEntityList(0, true, getKey($this->_values, 'parentId'));
    }

    /**
     * Get output string.
     * @return string
     */
    public function __toString()
    {
        try {
            $entities = $this->getEntityList();
            $result = [
                '<div class="list-group">',
                $this->_makeLink(),
            ];
            if (!empty($this->_values)) {
                $result[] = $this->_makeLink(-1);
            }
            foreach ($entities as $id => $title) {
                if (is_numeric($id) && $id < 1) {
                    continue;
                }
                $result[] = $this->_makeLink($id, $title);
            }
            $result[] = '</div>';
        } catch (\Exception $ex) {
            $result = [\Show::output($ex, 'Exception!', \Show::COLOR_ERROR)];
        }
        return implode(PHP_EOL, $result);
    }

    /**
     * Make simple link.
     * @param type $id
     * @param string $title
     * @return type
     */
    private function _makeLink($id = 0, $title = null)
    {
        $active = '';
        $link = $this->_editLink;
        if (!$id) {
            $active = ' active';
            $title = '+ New';
            if (!empty($this->_values)) {
                $link .= '?' . http_build_query($this->_values);
            }
        } else if ($id == -1) {
            $active = ' active';
            $title = '&lArr; Root';
            $link = '?';
        } else if (is_numeric($id)) {
            $link = $this->_editLink . '/' . $id;
        } else if (substr($id, 0, 1) == '?') {
            $link = $id;
        }
        return "<a class=\"list-group-item{$active}\" href=\"{$link}\">{$title}</a>";
    }

}
