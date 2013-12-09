<?php
namespace Core\Database;
class Export
{

    /**
     * Make HTML from result.
     * @param \mysqli_result $res
     * @return string html
     */
    public static function html($res)
    {
        self::_checkResult($res);
        $result = array('<table>');
        $header = false;
        while ($row = $res->fetch_assoc()) {
            if (!$header) {
                $result[] = self::_createRow(array_keys($row), '</th><th>', '<tr><th>', '</th></tr>');
                $header = true;
            }
            $result[] = self::_createRow($row, '</td><td>', '<tr><td>', '</td></tr>');
        }
        $res->free();
        $result[] = '</table>';
        return implode("\n", $result);
    }

    /**
     * Make HTML from result.
     * @param \mysqli_result $res
     * @return string html
     */
    public static function csv($res)
    {
        self::_checkResult($res);
        $result = array();
        $header = false;
        while ($row = $res->fetch_assoc()) {
            if (!$header) {
                $result[] = self::_createRow(array_keys($row), ';');
                $header = true;
            }
            $result[] = self::_createRow($row, ';');
        }
        $res->free();
        return implode("\r\n", $result);
    }

    /**
     * Export resultset as SQL insert statements.
     * @param \Core\Database $db
     * @param \mysqli_result $res
     * @return type
     */
    public static function sql($db, $table, $res)
    {
        self::_checkResult($res);
        $result = array();
        $count = 0;
        $valueBuffer = array();
        while ($row = $res->fetch_assoc()) {
            if ($count == 0) {
                $names = implode('`,`', array_keys($row));
                $result [] = "INSERT INTO {$table} (`{$names}`) VALUES";
            }
            $valueBuffer[] = "\t{$db->escape($row)}";
            $count++;
            if ($count > 100) {
                $result [] = implode(",\n", $valueBuffer) . ';';
                $valueBuffer = array();
                $result [] = "\n";
                $count = 0;
            }
        }
        $result [] = implode(",\n", $valueBuffer) . ';';
        $result [] = "\n";
        $res->free();
        return implode("\n", $result);
    }

    /**
     * Validate resultset for export.
     * @param \mysqli_result $res
     * @throws Exception
     * @throws \Exception
     */
    private static function _checkResult($res)
    {
        if (!($res instanceof \mysqli_result)) {
            throw new Exception("Can only make csv of query result set.");
        }
        if ($res->num_rows == 0) {
            throw new \Exception("Cannot generate for empty result set.");
        }
    }

    /**
     * Compile a string.
     * @param array $data
     * @param string $div
     * @param string $pre
     * @param string $post
     * @return string
     */
    private static function _createRow($data, $div, $pre = '', $post = '')
    {
        return $pre . implode($div, $data) . $post;
    }
}