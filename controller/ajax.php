<?php
/**
 * Ajax controller class, nicely running and returning JSON encoded results.
 */
abstract class Controller_Ajax extends Controller_Abstract
{
    protected function _executeRun()
    {
        $error = null;
        $result = array('time' => time());
        try {
            $result['content'] = $this->_run();
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        return json_encode($result);
    }
}