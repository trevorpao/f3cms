<?php

namespace F3CMS;

/**
 * Belong trait 提供多種與資料關聯相關的功能，
 * 包括綁定資料、計算數量、查詢與儲存多對多關係等操作。
 */
trait Belong
{
    /**
     * 綁定子資料表與目標資料。
     *
     * @param string $subTbl 子資料表名稱
     * @param int $target_id 目標資料 ID
     * @param int $member_id 成員 ID（預設為 0）
     */
    public static function bind($subTbl, $target_id, $member_id = 0)
    {
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        if (0 == $member_id) {
            $member_id = fMember::_CMember();
        }

        $related = mh()->get($that::fmTbl($subTbl) . '(r)', [
            '[>]' . $that::fmTbl() . '(t)'       => ['r.' . $pk => 'id'],
        ], ['t.id', 'r.status'], [
            'r.member_id' => $member_id,
            'r.' . $pk    => $target_id,
        ]);

        if ($related) {
            if ('claps' == $subTbl) {
                mh()->update($that::fmTbl($subTbl), [
                    'cnt[+]' => 1,
                ], [
                    'member_id' => $member_id,
                    $pk         => $target_id,
                    'cnt[<]'    => 100, // TODO: use fOption
                ]);
            } elseif ('hits' == $subTbl) {
                mh()->update($that::fmTbl($subTbl), [
                    'cnt[+]' => 1,
                ], [
                    'member_id' => $member_id,
                    $pk         => $target_id,
                ]);
            } else {
                if ('Enabled' == $related['status'] && 'seen' != $subTbl) {
                    mh()->update($that::fmTbl($subTbl), [
                        'status' => 'Disabled',
                    ], [
                        'member_id' => $member_id,
                        $pk         => $target_id,
                    ]);
                }
            }
        } else {
            mh()->insert($that::fmTbl($subTbl), [
                'member_id' => $member_id,
                $pk         => $target_id,
                'status'    => 'Enabled',
                'insert_ts' => date('Y-m-d H:i:s'),
            ]);
        }

        if (in_array($subTbl, ['seen', 'favo'])) {
            $that::setCnt($subTbl, $target_id);
        }

        if ('claps' == $subTbl) {
            $that::setClapCnt($target_id);
        }
    }

    /**
     * 設定點擊數量。
     *
     * @param int $pid 主資料 ID
     * @return int 更新結果
     */
    public static function setClapCnt($pid)
    {
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), 'member');

        $cnt = mh()->get($that::fmTbl('claps'), ['cnt' => MHelper::raw('SUM(<cnt>)')], [$pk => $pid]);
        $cnt = ($cnt) ? $cnt['cnt'] * 1 : 0;

        $rtn = mh()->update($that::fmTbl(), [
            'claps' => $cnt,
        ], [
            'id' => $pid,
        ]);

        return $that::chkErr($rtn->rowCount());
    }

    /**
     * 設定子資料表的數量。
     *
     * @param int $pid 主資料 ID
     * @param string $subTbl 子資料表名稱
     * @param array $filter 過濾條件
     * @param int $type 計算類型（預設為 0）
     * @return int 更新結果
     */
    public static function setCnt($pid, $subTbl = 'video', $filter = [], $type = 0)
    {
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        switch ($type) {
            case 1:
                $cnt = $sub::total(
                    array_merge([$pk => $pid], $filter),
                    null, '', 'COUNT(<' . $pk . '>)');
                break;
            default:
                $cnt = $that::total(
                    array_merge([$pk => $pid], $filter),
                    null, $subTbl, 'COUNT(<' . $pk . '>)');
                break;
        }

        $rtn = mh()->update($that::fmTbl(), [
            $subTbl . '_cnt' => $cnt,
        ], [
            'id' => $pid,
        ]);

        return $that::chkErr($rtn->rowCount());
    }

    /**
     * 查詢子資料表的多筆資料。
     *
     * @param string $subTbl 子資料表名稱
     * @param int $pid 主資料 ID
     * @param array $columns 查詢欄位（預設為 ['t.id', 'title']）
     * @return array 查詢結果
     */
    public static function lotsSub($subTbl, $pid, $columns = ['t.id', 'title'])
    {
        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        $filter = [$pk => $pid];

        $join = $sub::genJoin();

        if (!$join) {
            $join = [];
        }
        $join['[>]' . $sub::fmTbl() . '(t)'] = ['r.' . $fk => 'id'];

        return mh()->select(
            $that::fmTbl($subTbl) . '(r)',
            $join,
            $columns,
            $filter
        );
    }

    /**
     * 根據標籤查詢多筆資料。
     *
     * @param int|array $id 標籤 ID 或 ID 陣列
     * @param int $page 分頁頁碼（預設為 0）
     * @param int $limit 每頁限制數量（預設為 6）
     * @param string $cols 查詢欄位
     * @return mixed 查詢結果
     */
    public static function lotsByTag($id, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        return $that::lotsByID($that::byTag($id), $page, $limit, $cols);
    }

    /**
     * @param $id
     *
     * @return array
     */
    public static function byTag($id)
    {
        $that = get_called_class();

        if (is_array($id)) {
            $condi = [];
            foreach ($id as $row) {
                $condi[] = ' SELECT `' . $that::MTB . '_id` FROM `' . $that::fmTbl('tag') . '` WHERE `tag_id`=' . intval($row) . ' ';
            }

            $rows = mh()->query('SELECT `' . $that::MTB . '_id`, COUNT(`' . $that::MTB . '_id`) AS `cnt` FROM (' . implode(' UNION ALL ', $condi) . ') u GROUP by `' . $that::MTB . '_id` HAVING `cnt` > ' . (count($condi) - 1) . ' ')->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $rows = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $id]);
        }

        return \__::pluck($rows, '' . $that::MTB . '_id');
    }

    /**
     * 儲存多對多關係的資料。
     *
     * @param string $subTbl 子資料表名稱
     * @param int $pid 主資料 ID
     * @param array $rels 關聯資料
     * @param bool $reverse 是否反轉主外鍵（預設為 false）
     * @param bool $sortable 是否可排序（預設為 false）
     * @return int 儲存結果
     */
    public static function saveMany($subTbl, $pid, $rels = [], $reverse = false, $sortable = false)
    {
        if (empty($rels)) {
            return false;
        }

        [$that, $sub, $pk, $fk] = self::_init(get_called_class(), $subTbl);

        $data = [];

        if ($reverse) {
            [$fk, $pk] = [$pk, $fk];
        }

        mh()->delete($that::fmTbl($subTbl), [$pk => $pid]);

        foreach ($rels as $idx => $value) {
            if (!empty($value)) {
                $data[$idx] = [
                    $pk => $pid,
                    $fk => $value,
                ];

                if ($sortable) {
                    $data[$idx]['sorter'] = $idx;
                }
            }
        }

        if (!empty($data)) {
            mh()->insert($that::fmTbl($subTbl), $data);
        }

        return 1;
    }

    /**
     * 初始化資料表與欄位名稱。
     *
     * @param string $that 主資料表類別名稱
     * @param string $subTbl 子資料表名稱
     * @return array 初始化結果，包括主資料表、子資料表、主鍵與外鍵名稱
     */
    private static function _init($that, $subTbl)
    {
        // $that = get_called_class();
        $sub  = '\F3CMS\f' . ucfirst($subTbl);

        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';

        return [$that, $sub, $pk, $fk];
    }
}
