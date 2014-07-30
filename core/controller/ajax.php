<?php
namespace Core\Controller;
/**
 * Ajax controller class, nicely running and returning JSON encoded results.
 */
abstract class Ajax extends \Core\Controller
{
    /**
     * Extra data allowed on rootlevel.
     * @var array
     */
    protected $_rootData = array();
    /**
     * JSON mime-type as we send it back always.
     *
     * @var string
     */
    protected $_contentType = 'application/json';
    /**
     * Slightly different execution; capturing errors to send them back.
     * @return type
     */
    protected function _executeRun()
    {
        $result = array('time' => time());
        try {
            $result['content'] = $this->_run();
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }
        return json_encode(array_merge($this->_rootData, $result));
    }
}