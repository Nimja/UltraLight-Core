<?php
namespace Core\View\Transform;
/**
 * Limit output to X characters. Requires you to add the character number at the end, default 30.
 *
 * @author Nimja
 */
class Limit extends Base {

    const DEFAULT_LIMIT = 30;
    protected function _parse()
    {
        $limit = is_numeric($this->_peekCommand()) ? intval($this->_getCommand()) : self::DEFAULT_LIMIT;
        if (strlen($this->_value) <= $limit) {
            $result = $this->_value;
        } else {
            $result = substr($this->_value, 0, $limit) . '...';
        }
        return $result;
    }

}