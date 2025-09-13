<?php

namespace F3CMS;

use Medoo\Medoo;

/**
 * MHelper 類別擴展了 Medoo 資料庫框架，
 * 提供額外的資料庫操作功能，例如自訂查詢上下文與交易管理。
 */
class MHelper extends Medoo
{
    /**
     * @var MHelper 實例，用於管理資料庫操作。
     */
    private static $_instance = false;

    /**
     * 初始化 MHelper 實例，並設置資料庫連線配置。
     */
    public function __construct()
    {
        // $this->pdo = f3()->get('DB')->pdo();
        parent::__construct([
            'database_type' => 'mysql',
            'database_name' => f3()->get('db_name'),
            'server'        => f3()->get('db_host'),
            'username'      => f3()->get('db_account'),
            'password'      => f3()->get('db_password'),
            'charset'       => 'utf8mb4',
        ]);
    }

    /**
     * 獲取或重新初始化 MHelper 實例。
     *
     * @param bool $force 是否強制重新初始化
     * @return MHelper 實例
     */
    public static function init($force = false)
    {
        if (!self::$_instance || $force) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * 建立 SELECT 查詢的上下文。
     *
     * @param string $table 資料表名稱
     * @param array $map 映射參數
     * @param mixed $join JOIN 條件
     * @param mixed $columns 查詢欄位
     * @param mixed $where 查詢條件
     * @param mixed $columnFn 欄位函式
     * @return string 組合的 SQL 查詢字串
     */
    protected function selectContext(string $table, array &$map, $join, &$columns = NULL, $where = NULL, $columnFn = NULL): string
    {
        preg_match('/(?<table>[a-zA-Z0-9_]+)\s*\((?<alias>[a-zA-Z0-9_]+)\)/i', $table, $table_match);
        if (isset($table_match['table'], $table_match['alias'])) {
            $table       = $this->tableQuote($table_match['table']);
            $table_query = $table . ' AS ' . $this->tableQuote($table_match['alias']);
        } else {
            $table       = $this->tableQuote($table);
            $table_query = $table;
        }
        $is_join  = false;
        $join_key = is_array($join) ? array_keys($join) : null;
        if (
            isset($join_key[0]) &&
            0 === strpos($join_key[0], '[')
        ) {
            $is_join    = true;
            $table_join = [];
            $join_array = [
                '>'  => 'LEFT',
                '<'  => 'RIGHT',
                '<>' => 'FULL',
                '><' => 'INNER',
            ];
            foreach ($join as $sub_table => $relation) {
                preg_match('/(\[(?<join>\<\>?|\>\<?)\])?(?<table>[a-zA-Z0-9_]+)\s?(\((?<alias>[a-zA-Z0-9_]+)\))?/', $sub_table, $match);
                if ('' !== $match['join'] && '' !== $match['table']) {
                    if (is_string($relation)) {
                        $relation = 'USING ("' . $relation . '")';
                    }
                    if (is_array($relation)) {
                        // For ['column1', 'column2']
                        if (isset($relation[0])) {
                            $relation = 'USING ("' . implode('", "', $relation) . '")';
                        } else {
                            $joins = [];
                            foreach ($relation as $key => $value) {
                                $joins[] = (
                                    strpos($key, '.') > 0 ?
                                    // For ['tableB.column' => 'column']
                                    $this->columnQuote($key) :
                                    // For ['column1' => 'column2']
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
                if (
                    !is_null($where) ||
                    (is_array($join) && isset($column_fn))
                ) {
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
        if (isset($column_fn)) {
            if (1 === $column_fn) {
                $column = '1';
                if (is_null($where)) {
                    $where = $columns;
                }
            } elseif ($raw = $this->buildRaw($column_fn, $map)) {
                $column = $raw;
            } else {
                if (empty($columns) || $this->isRaw($columns)) {
                    $columns = '*';
                    $where   = $join;
                }
                $column = $column_fn . '(' . $this->columnPush($columns, $map, true) . ')';
            }
        } else {
            $column = $this->columnPush($columns, $map, true, $is_join);
        }

        return 'SELECT ' . $column . ' FROM ' . $table_query . $this->whereClause($where, $map);
    }

    /**
     * 開始 SQL 交易。
     *
     * @return bool 是否成功開始交易
     */
    public function begin()
    {
        $out = $this->pdo->begintransaction();

        return $out;
    }

    /**
     * 回滾 SQL 交易。
     *
     * @return bool 是否成功回滾交易
     */
    public function rollback()
    {
        $out = $this->pdo->rollback();

        return $out;
    }

    /**
     * 提交 SQL 交易。
     *
     * @return bool 是否成功提交交易
     */
    public function commit()
    {
        $out = $this->pdo->commit();

        return $out;
    }

    /**
     * 獲取最近的錯誤資訊。
     *
     * @return array|null 錯誤資訊或 null
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
