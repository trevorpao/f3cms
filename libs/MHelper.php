<?php
namespace F3CMS;

use \Medoo\Medoo as Medoo;

class MHelper extends Medoo
{
    /**
     * @var mixed
     */
    private static $_instance = false;

    public function __construct()
    {
        // $this->pdo = f3()->get('DB')->pdo();
        parent::__construct(array(
            'database_type' => 'mysql',
            'database_name' => f3()->get('db_name'),
            'server'        => f3()->get('db_host'),
            'username'      => f3()->get('db_account'),
            'password'      => f3()->get('db_password'),
            'charset'       => 'utf8'
        ));
    }

    public static function init()
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @param $table
     * @param $map
     * @param $join
     * @param $columns
     * @param null $where
     * @param null $column_fn
     */
    protected function selectContext($table, &$map, $join, &$columns = null, $where = null, $column_fn = null)
    {
        preg_match('/(?<table>[a-zA-Z0-9_]+)\s*\((?<alias>[a-zA-Z0-9_]+)\)/i', $table, $table_match);
        if (isset($table_match['table'], $table_match['alias'])) {
            $table = $this->tableQuote($table_match['table']);
            $table_query = $table . ' AS ' . $this->tableQuote($table_match['alias']);
        } else {
            $table = $this->tableQuote($table);
            $table_query = $table;
        }
        $is_join = false;
        $join_key = is_array($join) ? array_keys($join) : null;
        if (
            isset($join_key[0]) &&
            strpos($join_key[0], '[') === 0
        ) {
            $is_join = true;
            $table_join = array();
            $join_array = array(
                '>'  => 'LEFT',
                '<'  => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER'
            );
            foreach ($join as $sub_table => $relation) {
                preg_match('/(\[(?<join>\<\>?|\>\<?)\])?(?<table>[a-zA-Z0-9_]+)\s?(\((?<alias>[a-zA-Z0-9_]+)\))?/', $sub_table, $match);
                if ($match['join'] !== '' && $match['table'] !== '') {
                    if (is_string($relation)) {
                        $relation = 'USING ("' . $relation . '")';
                    }
                    if (is_array($relation)) {
                        // For ['column1', 'column2']
                        if (isset($relation[0])) {
                            $relation = 'USING ("' . implode($relation, '", "') . '")';
                        } else {
                            $joins = array();
                            foreach ($relation as $key => $value) {
                                $joins[] = (
                                    strpos($key, '.') > 0 ?
                                    // For ['tableB.column' => 'column']
                                    $this->columnQuote($key) :
                                    // For ['column1' => 'column2']
                                    $table . '."' . $key . '"'
                                ) .
                                ' = ' . ((substr($value, 0, 4) == '[SV]') ? $this->pdo->quote(substr($value, 4)) :
                                $this->tableQuote(isset($match['alias']) ? $match['alias'] : $match['table']) . '."' . $value . '"');
                            }
                            $relation = 'ON ' . implode($joins, ' AND ');
                        }
                    }
                    $table_name = $this->tableQuote($match['table']) . ' ';
                    if (isset($match['alias'])) {
                        $table_name .= 'AS ' . $this->tableQuote($match['alias']) . ' ';
                    }
                    $table_join[] = $join_array[$match['join']] . ' JOIN ' . $table_name . $relation;
                }
            }
            $table_query .= ' ' . implode($table_join, ' ');
        } else {
            if (is_null($columns)) {
                if (
                    !is_null($where) ||
                    (is_array($join) && isset($column_fn))
                ) {
                    $where = $join;
                    $columns = null;
                } else {
                    $where = null;
                    $columns = $join;
                }
            } else {
                $where = $columns;
                $columns = $join;
            }
        }
        if (isset($column_fn)) {
            if ($column_fn === 1) {
                $column = '1';
                if (is_null($where)) {
                    $where = $columns;
                }
            } else if ($raw = $this->buildRaw($column_fn, $map)) {
                $column = $raw;
            } else {
                if (empty($columns) || $this->isRaw($columns)) {
                    $columns = '*';
                    $where = $join;
                }
                $column = $column_fn . '(' . $this->columnPush($columns, $map, true) . ')';
            }
        } else {
            $column = $this->columnPush($columns, $map, true, $is_join);
        }
        return 'SELECT ' . $column . ' FROM ' . $table_query . $this->whereClause($where, $map);
    }

    /**
     *   Begin SQL transaction
     * @return bool
     */
    public function begin()
    {
        $out = $this->pdo->begintransaction();
        return $out;
    }

    /**
     *   Rollback SQL transaction
     * @return bool
     */
    public function rollback()
    {
        $out = $this->pdo->rollback();
        return $out;
    }

    /**
     *   Commit SQL transaction
     * @return bool
     */
    public function commit()
    {
        $out = $this->pdo->commit();
        return $out;
    }
}
