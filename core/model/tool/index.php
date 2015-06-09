<?php namespace Core\Model\Tool;
/**
 * Helper entity for page model, but can be used more broad.
 *
 * @author Nimja
 */
class Index
{
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
     * Basic constructor.
     * @param string $class
     * @param string $editLink
     */
    public function __construct($class, $editLink)
    {
        $this->_entityClass = $class;
        $this->_editLink = $editLink;
    }

    /**
     * Get entity, returns entity OR redirects if we are successfully editing.
     * @return \Core\Model
     */
    public function getEntityList()
    {
        $class = $this->_entityClass;
        return $class::getEntityList();
    }

    /**
     * Get output string.
     * @return string
     */
    public function __toString()
    {
        $entities = $this->getEntityList();
        $result = array(
            '<div class="list-group">',
            $this->_makeLink(),
        );
        foreach ($entities as $id => $title) {
            if ($id < 1) {
                continue;
            }
            $result[] = $this->_makeLink($id, $title);
        }
        $result[] = '</div>';
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
        if (!$id) {
            $active = ' active';
            $link = $this->_editLink;
            $title = '+ New';
        } else {
            $link = $this->_editLink . '/' . $id;
        }
        return "<a class=\"list-group-item{$active}\" href=\"{$link}\">{$title}</a>";
    }
}
