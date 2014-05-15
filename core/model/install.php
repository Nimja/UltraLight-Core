<?php
namespace Core\Model;
/**
 * Install functionality for models.
 *
 * @author Nimja
 */
class Install
{
    protected $_installed = array();

    /**
     * Contructor.
     * @param array $classes
     * @param boolean $force
     */
    public function __construct($classes, $force)
    {
        \Core::clearCache();
        foreach ($classes as $class) {
            $this->install($class, $force);
        }
    }

    /**
     * Install this model, ie. create the table if it is required.
     *
     * @param string $class
     * @param boolean $force
     * @return void
     */
    public function install($class, $force = false)
    {
        $re = $class::re();
        $fields = $re->columns;
        if (empty($fields)) {
            return false;
        }
        $table = $re->db()->table($re->table);
        $result = $table->applyStructure($fields, $force);
        switch ($result) {
            case \Core\Database\Table::STRUCTURE_UPDATED:
                \Show::info("$class", 'Table updated.');
                break;
            case \Core\Database\Table::STRUCTURE_CREATED:
                $this->_installed["$class"] = true;
                \Show::info("$class", 'Table created.');
                break;
            default:
                \Show::info("$class", 'No actions.');
        }
    }

    /**
     * Create a basic user.
     * @param string $class
     * @param string $name
     * @param string $password
     * @param int $role
     * @return \Core\Model New user.
     */
    public function addSimpleUser($class, $name, $password, $role = 0)
    {
        $user = null;
        if (!empty($this->_installed["$class"])) {
            $user = new $class(array('name' => $name, 'role' => $role));
            $user->password = $class::encryptPassword($name, $password);
            $user->save();
            $user->logout();
            \Show::info($class, "Added {$name}");
        }
        return $user;
    }
}