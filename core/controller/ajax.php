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
    private $_resultData = array();
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
        $this->_resultData['time'] = time();
        try {
            $this->_resultData['content'] = $this->_run();
        } catch (\Exception $e) {
            $this->_resultData['error'] = $e->getMessage();
        }
        return json_encode($this->_resultData);
    }
    /**
     * Able to set root level result data.
     * @param string $key
     * @param mixed $value
     */
    protected function _setResultData($key, $value)
    {
        $this->_resultData[$key] = $value;
    }
}