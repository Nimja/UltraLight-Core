<?php
/**
 * Abstract class for view transforms.
 *
 * @author Nimja
 */
abstract class Library_Transform_Abstract
{
    /**
     * Maintain a list of instances.
     *
     * @var array
     */
    private static $_instances = array();

    /**
     * @return \Library_Transform_Abstract
     */
    public static function instance()
    {
        $class = get_called_class();
        if (!isset(self::$_instances[$class])) {
            self::$_instances[$class] = new $class();
        }
        return self::$_instances[$class];
    }

    /**
     * Abstract function to parse the string for transformation.
     */
    abstract public function parse($command, $string);

    /**
     * Get the array of the remaining commands, the first two are always varname and transform.
     * @param string $command
     * @return array
     */
    protected function _getExtra($command)
    {
        $parts = explode(Library_View::TRANSFORM, $command);
        return array_splice($parts, 2);
    }
}
