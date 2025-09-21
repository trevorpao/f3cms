<?php

namespace F3CMS;

use Medoo\Medoo;

/**
 * MHelper class extends Medoo to provide additional database-related utilities
 * and custom SQL handling for the Petite CMS.
 */
class MHelper extends Medoo
{
    /**
     * Singleton instance of the MHelper class.
     *
     * @var mixed
     */
    private static $_instance = false;

    /**
     * Constructor initializes the database connection using configuration
     * values from the framework (f3).
     */
    public function __construct()
    {
        $port = 3306;
        if (f3()->exists('db_port')) {
            $port = f3()->get('db_port');
        }

        parent::__construct([
            'database_type' => 'mysql',
            'database_name' => f3()->get('db_name'),
            'server'        => f3()->get('db_host'),
            'username'      => f3()->get('db_account'),
            'password'      => f3()->get('db_password'),
            'port'          => $port,
            'charset'       => 'utf8mb4',
        ]);
    }

    /**
     * Initializes or retrieves the singleton instance of the MHelper class.
     *
     * @param bool $force Whether to force reinitialization of the instance.
     * @return MHelper The singleton instance.
     */
    public static function init($force = false)
    {
        if (!self::$_instance || $force) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Constructs a SQL SELECT query with support for joins, aliases, and custom
     * column functions.
     *
     * @param string $table The table name or alias.
     * @param array $map Reference to the map for query parameters.
     * @param mixed $join Join conditions or columns.
     * @param mixed|null $columns Columns to select.
     * @param mixed|null $where WHERE conditions.
     * @param mixed|null $columnFn Custom column function (e.g., COUNT, SUM).
     * @return string The constructed SQL SELECT query.
     */
    protected function selectContext(string $table, array &$map, $join, &$columns = null, $where = null, $columnFn = null): string
    {
        // Parse table and alias
        preg_match('/(?<table>[a-zA-Z0-9_]+)\s*\((?<alias>[a-zA-Z0-9_]+)\)/i', $table, $table_match);
        if (isset($table_match['table'], $table_match['alias'])) {
            $table       = $this->tableQuote($table_match['table']);
            $table_query = $table . ' AS ' . $this->tableQuote($table_match['alias']);
        } else {
            $table       = $this->tableQuote($table);
            $table_query = $table;
        }

        // Handle joins
        $is_join  = false;
        $join_key = is_array($join) ? array_keys($join) : null;
        if (isset($join_key[0]) && 0 === strpos($join_key[0], '[')) {
            $is_join    = true;
            $table_join = [];
            $join_array = [
                '>'  => 'LEFT',
                '<'  => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER',
            ];
            foreach ($join as $sub_table => $relation) {
                preg_match('/(\[(?<join><\>?|>\<?)\])?(?<table>[a-zA-Z0-9_]+)\s?(\((?<alias>[a-zA-Z0-9_]+)\))?/', $sub_table, $match);
                if ('' !== $match['join'] && '' !== $match['table']) {
                    if (is_string($relation)) {
                        $relation = 'USING ("' . $relation . '")';
                    }
                    if (is_array($relation)) {
                        if (isset($relation[0])) {
                            $relation = 'USING ("' . implode('", "', $relation) . '")';
                        } else {
                            $joins = [];
                            foreach ($relation as $key => $value) {
                                $joins[] = (
                                    strpos($key, '.') > 0 ?
                                    $this->columnQuote($key) :
                                    $table . '."' . $key . '"'
                                ) .
                                ' = ' . (('[SV]' == substr($value, 0, 4)) ? $this->pdo->quote(substr($value, 4)) :
                                $this->tableQuote($match['alias'] ?? $match['table']) . '."' . $value . '"');
                            }
                            $relation = 'ON ' . implode(' AND ', $joins);
                        }
                    }
                    $table_name = $this->tableQuote($match['table']) . ' ';
                    if (isset($match['alias'])) {
                        $table_name .= 'AS ' . $this->tableQuote($match['alias']) . ' ';
                    }
                    $table_join[] = $join_array[$match['join']] . ' JOIN ' . $table_name . $relation;
                }
            }
            $table_query .= ' ' . implode(' ', $table_join);
        } else {
            if (is_null($columns)) {
                if (!is_null($where) || (is_array($join) && isset($columnFn))) {
                    $where   = $join;
                    $columns = null;
                } else {
                    $where   = null;
                    $columns = $join;
                }
            } else {
                $where   = $columns;
                $columns = $join;
            }
        }

        // Handle column functions
        if (isset($columnFn)) {
            if (1 === $columnFn) {
                $column = '1';
                if (is_null($where)) {
                    $where = $columns;
                }
            } elseif ($raw = $this->buildRaw($columnFn, $map)) {
                $column = $raw;
            } else {
                if (empty($columns) || $this->isRaw($columns)) {
                    $columns = '*';
                    $where   = $join;
                }
                $column = $columnFn . '(' . $this->columnPush($columns, $map, true) . ')';
            }
        } else {
            $column = $this->columnPush($columns, $map, true, $is_join);
        }

        return 'SELECT ' . $column . ' FROM ' . $table_query . $this->whereClause($where, $map);
    }

    /**
     * Begins an SQL transaction.
     *
     * @return bool True if the transaction begins successfully.
     */
    public function begin()
    {
        $out = $this->pdo->begintransaction();

        return $out;
    }

    /**
     * Rolls back the current SQL transaction.
     *
     * @return bool True if the rollback is successful.
     */
    public function rollback()
    {
        $out = $this->pdo->rollback();

        return $out;
    }

    /**
     * Commits the current SQL transaction.
     *
     * @return bool True if the commit is successful.
     */
    public function commit()
    {
        $out = $this->pdo->commit();

        return $out;
    }

    /**
     * Retrieves the last error information from the database connection.
     *
     * @return array|null Error information or null if no error exists.
     */
    public function error()
    {
        if ($this->error) {
            return $this->errorInfo;
        } else {
            return null;
        }
    }
}
