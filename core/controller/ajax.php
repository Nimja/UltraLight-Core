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
    private $_resultData = [];
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
        return $this->_jsonEncode($this->_resultData);
    }
    /**
     * Proper json encoding with exception if something goes wrong.
     * @param mixed $data
     * @return array
     * @throws \Exception
     */
    protected function _jsonEncode($data)
    {
        $result = json_encode($data);
        $error = json_last_error();
        if ($error) {
            $errors = [
                JSON_ERROR_NONE => null,
                JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
                JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
                JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
                JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
                JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded'
            ];
            $message = getKey($errors, $error, "Unknown error: $error");
            throw new \Exception($message);
        }
        return $result;
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