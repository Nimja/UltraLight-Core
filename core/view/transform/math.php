<?php namespace Core\View\Transform;
/**
 * Class to do basic math operations with view placeholders.
 */
class Math extends \Core\View\Transform
{

    public function parse()
    {
        $operation = $this->_getCommand();
        $value = is_numeric($this->_peekCommand()) ? intval($this->_getCommand()) : 1;
        $result = intval($this->_value);
        switch ($operation) {
            case 'times':
                $result *= $value;
                break;
            case 'divided':
                $result /= $value;
                break;
            case 'plus':
                $result += $value;
                break;
            case 'minus':
                $result -= $value;
                break;
        }
        return $result;
    }
}