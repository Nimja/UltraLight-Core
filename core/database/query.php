<?php

namespace Core\Database;

/**
 * Basic table class for table creation and checking.
 */
class Query {
    const SEARCH_PREG = '/(.+)(\|[l\:\=\<\>\!\[\]])(.+)/';
    const SEARCH_FIELD = 'field';
    const SEARCH_VALUE = 'value';
    const SEARCH_OPERATION = 'operation';

    /**
     * Database (for escaping).
     *
     * @var \Core\Database
     */
    private $db;

    /**
     * The where SQL/string.
     *
     * @var string
     */
    private $sql;

    public function __construct(\Core\Database $db, $search, $explicit=false)
    {
        $this->db = $db;
        if ($explicit) {
            $this->sql = $search;
        } else {
            $this->sql = $this->searchToSql($search);
        }
    }

    /**
     * Get the where string.
     *
     * @return string
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Do a search that can be used through JS, using operators and values.
     *
     * |= -> =<br />
     * |: -> LIKE<br />
     * |> -> > <br />
     * |< -> < <br />
     * |! -> <> <br />
     * |n -> IN <br />
     *
     * Combine searches with ;
     *
     * @param string $search
     * @return string
     */
    protected function searchToSql($search)
    {
        $where = [];
        if ($search instanceof self) {
            return $search->getSql();
        } else if (empty($search)) {
            $where = null;
        } else if (is_int($search)) {
            $where[] = "id = $search";
        } else {
            $searches = is_array($search) ? $search : $this->parseSearch($search);
            $where = $this->parseSearches($searches);
        }
        return empty($where) ? '1' : implode(' AND ', $where);
    }

    /**
     * Parse search string into nice array.
     *
     * @param type $search
     * @return array
     */
    protected function parseSearch($search)
    {
        $result = [];
        $search = html_entity_decode($search);
        $searches = explode(';', $search);
        foreach ($searches as $search) {
            $matches = null;
            $found = preg_match(self::SEARCH_PREG, $search, $matches);
            if (!$found || count($matches) != 4) {
                continue;
            }
            $result[] = [
                self::SEARCH_FIELD => trim($matches[1]),
                self::SEARCH_VALUE => trim($matches[3]),
                self::SEARCH_OPERATION => $matches[2],
            ];
        }
        return $result;
    }

    /**
     * Parse search array into where array.
     * @param array $searches
     * @return array
     */
    private function parseSearches($searches)
    {
        $where = [];
        foreach ($searches as $field => $details) {
            if (!is_array($details)) {
                $details = ['field' => $field, 'value' => $details];
            }
            $field = $this->db->escape(getKey($details, self::SEARCH_FIELD, $field), true);
            $originalValue = getKey($details, self::SEARCH_VALUE);
            $value = $this->db->escape($originalValue);
            $operation = getKey($details, self::SEARCH_OPERATION, '|=');
            if ($originalValue === 'null') {
                $condition = ($operation != '|!') ? "{$field} IS NULL" : "{$field} IS NOT NULL";
            } else if ($operation == '|[') {
                $condition = "FIND_IN_SET({$value}, {$field}) > 0";
            } else if ($operation == '|]') {
                $condition = "FIND_IN_SET({$value}, {$field}) = 0";
            } else {
                $operand = '=';
                switch ($operation) {
                    case '|l': $field = "LENGTH($field)";
                        $operand = '>';
                        break;
                    case '|!': $operand = '!=';
                        break;
                    case '|<': $operand = '<';
                        break;
                    case '|>': $operand = '>';
                        break;
                    case '|:':
                        $operand = 'LIKE';
                        trim($value, "'");
                        $value = "'%{$value}%'";
                        break;
                    case '|n':
                        $operand = 'IN';
                        $stringValue = is_array($value) ? implode(', ', $value) : $value;
                        $value = "({$stringValue})";
                        break;
                }
                $condition = "{$field} {$operand} {$value}";
            }
            $where[] = $condition;
        }
        return $where;
    }
}
