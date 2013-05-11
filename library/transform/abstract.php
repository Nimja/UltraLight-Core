<?php

/**
 * Abstract class for view transforms.
 *
 * @author Nimja
 */
abstract class Library_Transform_Abstract {

    /**
     * Array of
     * @var type
     */
    protected $_commands;
    protected $_value;

    /**
     * @param array $command
     * @param string $value
     */
    public function __construct($commands, $value)
    {
        $this->_commands = $commands;
        $this->_value = $value;
    }

    /**
     * Abstract function to parse the string for transformation.
     */
    abstract public function parse();

    public function getCommands()
    {
        return $this->_commands;
    }

}
