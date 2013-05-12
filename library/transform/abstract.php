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

    /**
     * Get the remaining commands.
     * @return array
     */
    public function getCommands()
    {
        return $this->_commands;
    }

    /**
     * Look at the next command, without removing it from the stack.
     * @return mixed
     */
    protected function _peekCommand()
    {
        return reset($this->_commands);
    }

    /**
     * Get the next command, removing it from the stack.
     * @return mixed
     */
    protected function _getCommand()
    {
        return array_shift($this->_commands);
    }
}
