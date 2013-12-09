<?php
namespace Core\Model;
/**
 * Class reflection for models.
 *
 * @author Nimja
 */
class Reflect
{
    const DOCCOMMENT_REGEX = '/@([a-zA-Z-]+)(.*)/';
    /**
     * The current class.
     * @var ReflectionClass
     */
    private $_class;
    /**
     * The class we are reflecting.
     * @var string
     */
    private $_className;
    /**
     * The database connection for this class.
     * @var Library_Database
     */
    public $db;
    /**
     * The table name.
     * @var string
     */
    public $table;
    /**
     * The type name.
     * @var string
     */
    public $type;
    /**
     * The listfield.
     * @var type
     */
    public $listField = null;
    /**
     * The fields + types.
     * @var array
     */
    public $fields = array();
    /**
     * The validation array.
     * @var array
     */
    public $validate = array();
    /**
     * Database column information.
     * @var array
     */
    public $columns = array();

    /**
     * @param string $class
     */
    public function __construct($class)
    {
        if (!is_subclass_of($class, '\Core\Model')) {
            throw new \Exception("Reflecting non model class: $class");
        }
        $this->_className = $class;
        $short = $class;
        $this->table = $this->_getTableName($short);
        $this->type = strtolower($short);
        $this->_class = new \ReflectionClass($class);
        $this->_getDb();
        $this->_getSettings();
        unset($this->_class);
    }
    private function _getDb()
    {
        $doc = self::parseDocComment($this->_class->getDocComment());
        $dbName = '';
        if (!empty($doc)) {
            $dbName = getKey($doc, 'db-database', '');
        }
        //$this->db = Library_Database::getDatabase($dbName);

    }

    /**
     * Get the settings information.
     */
    private function _getSettings()
    {
        $properties = $this->_class->getProperties();
        foreach ($properties as $property) {
            $prop = new Reflect\Property($property);
            $prop->fillSettings($this);
        }
        if (empty($this->listField)) {
            $this->listField = array_shift(array_keys($this->fields));
        }
    }

    /**
     * Get the table name.
     * @param string $shortName
     * @return string
     */
    private function _getTableName($shortName)
    {
        $prefix = \Config::system()->get('database', 'table_prefix', '');
        return $prefix . $shortName;
    }

    /**
     * Parse the doc comment into a nice array.
     * @param string $docComment
     * @return array
     */
    public static function parseDocComment($docComment)
    {
        $result = array();
        if (!empty($docComment)) {
            $matches = null;
            if (preg_match_all(self::DOCCOMMENT_REGEX, $docComment, $matches)) {
                $fields = $matches[1];
                $values = $matches[2];
                foreach ($fields as $key => $value) {
                    $var = trim(getKey($values, $key, ''));
                    $result[$value] = $var == '' ? true : $var;
                }
            }
        }
        return $result;
    }

    /**
     * Wrapper for callback.
     * @param string $class
     * @return \self
     */
    public static function get($class)
    {
        return new self($class);
    }
}