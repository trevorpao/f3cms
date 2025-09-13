<?php

namespace F3CMS;

/**
 * Feed 類別提供後端資料處理功能，
 * 包括儲存、發佈、欄位處理與批量查詢等操作。
 */
class Feed extends Module
{
    use Belong;

    const MULTILANG      = 1;
    const PAGELIMIT      = 12;
    const BE_COLS        = 'm.id';
    const PK_COL         = 'id';
    const LANG_ARY_MERGE = 0;
    const LANG_ARY_ALONE = 1;
    const LANG_ARY_SKIP  = 2;

    const PV_R = 'base.cms';
    const PV_U = 'base.cms';
    const PV_D = 'mgr.cms';

    const HARD_DEL = 0;

    /**
     * 儲存後端表單資料。
     *
     * @param array $req 請求資料
     * @param string $tbl 資料表名稱
     * @return int|null 儲存的資料 ID 或 null
     */
    public static function save($req, $tbl = '')
    {
        $that               = get_called_class();
        [$data, $other]     = $that::_handleColumn($req);

        $rtn = null;

        if (empty($req['id'])) {
            $data['insert_ts']   = date('Y-m-d H:i:s');
            $data['insert_user'] = fStaff::_current('id');

            mh()->insert($that::fmTbl($tbl), $data);

            $req['id'] = mh()->id();

            $rtn = $that::chkErr($req['id']);
        } else {
            $rtn = mh()->update($that::fmTbl($tbl), $data, [
                'id' => $req['id'],
            ]);

            $rtn = $that::chkErr($rtn->rowCount());
        }

        if (!empty($rtn)) {
            $that::_afterSave($req['id'], $other, $data);

            return $req['id'];
        } else {
            return null;
        }
    }

    /**
     * 儲存後的處理邏輯。
     *
     * @param int $pid 資料 ID
     * @param array $other 其他資料
     * @param array $data 主資料
     * @return int
     */
    public static function _afterSave($pid, $other, $data = [])
    {
        $that               = get_called_class();
        // self::saveMany('ingredient', $pid, $other['ingredients'], false, true);
        // self::saveMany('related', $pid, $other['relateds'], false, true);

        if (isset($other['meta'])) {
            $that::saveMeta($pid, $other['meta'], true);
        }
        if (isset($other['tags'])) {
            $that::saveMany('tag', $pid, $other['tags'], false, true);
        }
        if (isset($other['lang'])) {
            $that::saveLang($pid, $other['lang']);
        }
    }

    /**
     * 發佈後端表單資料。
     *
     * @param array $req 請求資料
     * @param string $tbl 資料表名稱
     * @return int|null 發佈結果
     */
    public static function published($req, $tbl = '')
    {
        $that = get_called_class();
        $data = [
            'status'    => $req['status'],
            'last_ts'   => date('Y-m-d H:i:s'),
            'last_user' => fStaff::_current('id'),
        ];
        $rtn = null;

        if (isset($req['online_date'])) {
            $data['online_date'] = $req['online_date'];
        }

        $rtn = mh()->update($that::fmTbl($tbl), $data, [
            'id' => $req['id'],
        ]);

        $rtn = self::chkErr($rtn->rowCount());

        return $rtn;
    }

    /**
     * 處理請求欄位資料。
     *
     * @param array $req 請求欄位資料
     * @return array 處理後的主資料與其他資料
     */
    public static function _handleColumn($req)
    {
        $that  = get_called_class();
        $data  = [];
        $other = [];

        foreach ($req as $key => $value) {
            if ($that::filterColumn($key)) {
                switch ($key) {
                    case 'meta':
                        if (is_array($value)) {
                            foreach ($value as $k => $v) {
                                $other['meta'][$k] = $v;
                            }
                        }
                        break;
                    case 'tags':
                        if (is_array($value)) {
                            foreach ($value as $v) {
                                $other['tags'][] = $v['v'];
                            }
                        } else {
                            $other['tags'] = explode(',', $value);
                        }
                        break;
                    case 'lang':
                        if (is_array($value)) {
                            foreach ($value as $k => $v) {
                                $other['lang'][] = [$k, $v];
                            }
                        }
                        break;
                    case 'slug':
                        $value = (empty($value)) ? $that::renderUniqueNo(16) : $value;
                        // $value = str_replace('//', '/', $value);
                        $data[$key] = parent::_slugify($value);
                        break;
                    case 'pwd':
                        if (!empty($value)) {
                            $value      = $that::_setPsw($value);
                            $data[$key] = $value;
                        }
                        break;
                    case 'online_date':
                        $data[$key] = date('Y-m-d H:i:s', strtotime($value)); // + 7 * 3600);
                        break;
                    case 'id':
                        break;
                    default:
                        $data[$key] = ((is_array($value)) ? json_encode($value) : ((null === $value) ? null : trim($value)));
                        break;
                }
            }
        }

        $data['last_ts']   = date('Y-m-d H:i:s');
        $data['last_user'] = fStaff::_current('id');

        return [$data, $other];
    }

    /**
     * 批量查詢資料。
     *
     * @param array $condition 查詢條件
     * @param string|array $cols 查詢欄位
     * @param mixed $join JOIN 條件
     * @param int $limit 查詢限制
     * @param string $table 資料表名稱
     * @return array 查詢結果
     */
    public static function lots($condition, $cols = '*', $join = null, $limit = 500, $table = '')
    {
        $that               = get_called_class();
        $condition['LIMIT'] = (empty($condition['LIMIT'])) ? $limit : $condition['LIMIT'];

        if (null == $join) {
            $result = mh()->select($that::fmTbl($table), $cols, $condition);
        } else {
            $result = mh()->select($that::fmTbl($table) . '(m)', $join, $cols, $condition);
        }

        $result = self::chkErr($result);

        if (empty($result)) {
            return [];
        } else {
            return $result;
        }
    }

    /**
     * @param $pid
     * @param $sorter
     */
    public static function lotsTag($pid, $sorter = false)
    {
        $that = get_called_class();

        $pk = $that::MTB . '_id';
        $fk = 'tag_id';

        $filter = [
            'r.' . $pk => $pid,
            't.status' => fTag::ST_ON,
        ];

        if ($sorter) {
            $filter['ORDER'] = 'r.sorter';
        }

        return mh()->select($that::fmTbl('tag') . '(r)',
            ['[>]' . fTag::fmTbl() . '(t)'          => ['r.tag_id' => 'id'],
                '[>]' . fTag::fmTbl('lang') . '(l)' => ['t.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]],
            ['t.id', 't.slug', 'l.title', 't.counter'], $filter);
    }

    /**
     * @param $pid
     * @param $key
     *
     * @return mixed
     */
    public static function lotsMeta($pid, $key = '')
    {
        $that = get_called_class();

        $filter = ['parent_id' => $pid];

        if ('' != $key) {
            $filter['k[~]'] = $key;
        }

        $result = mh()->select($that::fmTbl('meta'), '*', $filter);

        $rows = [];
        if ($result) {
            foreach ($result as $row) {
                $rows[$row['k']] = $row['v'];
            }
        }

        return $rows;
    }

    /**
     * @param $pid
     * @param $lang
     *
     * @return array
     */
    public static function lotsLang($pid, $lang = '')
    {
        $that   = get_called_class();
        $filter = ['parent_id' => $pid];
        if ('' != $lang) {
            $filter['lang'] = $lang;
        }

        $result   = mh()->select($that::fmTbl('lang'), '*', $filter);
        $filter   = self::default_filtered_column();
        $filter[] = 'parent_id';

        $rows = [];
        foreach (f3()->get('acceptLang') as $n) {
            $rows[$n] = [];
        }

        if (count($result) > 0) {
            if (count($rows) > 1) {
                foreach ($result as $row) {
                    $rows[$row['lang']] = array_filter(
                        $row,
                        function ($key) use ($filter) {
                            return !in_array($key, $filter);
                        },
                        ARRAY_FILTER_USE_KEY
                    );
                }
            } else {
                $rows = array_filter(
                    $result[0],
                    function ($key) use ($filter) {
                        return !in_array($key, $filter);
                    },
                    ARRAY_FILTER_USE_KEY
                );
            }
        }

        return ('' != $lang) ? $rows[$lang] : $rows;
    }

    /**
     * @param $pid
     * @param $data
     * @param $replace
     *
     * @return int
     */
    public static function saveMeta($pid, $data = [], $replace = false)
    {
        if (empty($data)) {
            return false;
        }

        $that = get_called_class();
        $rows = [];

        if ($replace) {
            mh()->delete($that::fmTbl('meta'), ['parent_id' => $pid, 'k' => array_keys($data)]);
        }

        foreach ($data as $k => $v) {
            if (!empty($v)) {
                $rows[] = [
                    'parent_id' => $pid,
                    'k'         => $k,
                    'v'         => $v,
                ];
            }
        }

        if (!empty($rows)) {
            mh()->insert($that::fmTbl('meta'), $rows);
        }

        return $that::chkErr(1);
    }

    /**
     * @param $pid
     * @param $data
     * @param $replace
     *
     * @return int
     */
    public static function saveLang($pid, $data = [])
    {
        if (empty($data)) {
            return false;
        }

        $that = get_called_class();

        foreach ($data as $v) {
            if (!empty($v[1])) {
                $filter = [
                    'parent_id' => $pid,
                    'lang'      => $v[0],
                ];

                $v[1]['last_ts']   = date('Y-m-d H:i:s');
                $v[1]['last_user'] = fStaff::_current('id');

                if (mh()->get($that::fmTbl('lang'), ['parent_id'], $filter)) {
                    mh()->update($that::fmTbl('lang'), $v[1], $filter);
                } else {
                    $v[1]['insert_ts']   = date('Y-m-d H:i:s');
                    $v[1]['insert_user'] = fStaff::_current('id');

                    mh()->insert($that::fmTbl('lang'), array_merge($v[1], $filter));
                }
            }
        }

        return self::chkErr(1);
    }

    /**
     * @param $query
     */
    public static function getOpts($query = '', $column = 'title')
    {
        $that   = get_called_class();
        $filter = [
            'm.status' => $that::ST_ON,
            'LIMIT'    => 100,
        ];

        if ($that::MULTILANG) {
            if ('' != $query) {
                $filter['l.' . $column . '[~]'] = $query;
            }

            return mh()->select($that::fmTbl() . '(m)',
                ['[><]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]], ['m.id', $column . '(title)'], $filter);
        } else {
            if ('' != $query) {
                $filter['m.' . $column . '[~]'] = $query;
            }

            return mh()->select($that::fmTbl() . '(m)', ['m.id', $column . '(title)'], $filter);
        }
    }

    /**
     * @return mixed
     */
    public static function oneOpt($pid)
    {
        $that   = get_called_class();
        $filter = [
            'm.id'  => $pid,
            'ORDER' => ['id' => 'DESC'],
        ];

        if ($that::MULTILANG) {
            $join = [
                '[>]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()],
            ];

            $cu = mh()->get($that::fmTbl() . '(m)', $join, ['m.id', 'l.title'], $filter);
        } else {
            $cu = mh()->get($that::fmTbl() . '(m)', ['m.id', 'm.title'], $filter);
        }

        return (empty($cu)) ? [] : $cu;
    }

    /**
     * delete one row
     *
     * @param int $pid
     */
    public static function delRow($pid, $sub_table = '')
    {
        $that = get_called_class();

        $data = mh()->delete($that::fmTbl($sub_table), [
            'id' => $pid,
        ]);

        return $data->rowCount();
    }

    /**
     * @param $pid
     * @param $status
     *
     * @return null
     */
    public static function changeStatus($pid, $status)
    {
        $that = get_called_class();
        mh()->update($that::fmTbl(), [
            'status' => $status,
        ], [
            'id' => $pid,
        ]);
    }

    /**
     * @param $pid
     * @param $value
     */
    public static function update_sorter($pid, $sorter)
    {
        $that = get_called_class();
        mh()->query(
            'UPDATE `' . $that::fmTbl() . '` SET `sorter`=:sorter WHERE `id`=:id',
            [
                ':sorter' => $sorter,
                ':id'     => $pid,
            ]
        );
    }

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @param $cols
     */
    public static function limitRows($query = '', $page = 0, $limit = 0, $cols = '')
    {
        $that = get_called_class();

        $filter = $that::genFilter($query);

        return $that::paginate(
            $that::fmTbl() . '(m)',
            $filter,
            $page,
            $limit,
            explode(',', $that::BE_COLS . $cols),
            $that::genJoin()
        );
    }

    /**
     * get a row
     *
     * @param mixed $val - condition
     *
     * @return array
     */
    public static function one($val, $col = 'id', $condition = [], $multilang = 1)
    {
        $that = get_called_class();

        $data = mh()->get($that::fmTbl(), '*', array_merge([
            $col    => $val,
            'ORDER' => [$col => 'DESC'],
        ], $condition));

        if (empty($data)) {
            return null;
        } else {
            if ($that::MULTILANG) {
                switch ($multilang) {
                    case $that::LANG_ARY_ALONE:
                        $data['lang'] = $that::lotsLang($data['id']);
                        break;
                    case $that::LANG_ARY_MERGE:
                        $lang = $that::lotsLang($data['id'], parent::_lang());
                        if (is_array($lang)) {
                            $data = array_merge($data, $lang);
                        }
                        break;
                    default:
                        break;
                }
            }

            return $data;
        }
    }

    /**
     * @param array $ids
     *
     * @return mixed
     */
    public static function notExists($ids = [])
    {
        $that = get_called_class();
        $data = mh()->get($that::fmTbl(), ['ids' => MHelper::raw('GROUP_CONCAT(`id`)')], ['id' => $ids]);

        if (empty($data['ids'])) {
            return $ids;
        } else {
            return array_diff(explode(',', $data['ids']), $ids);
        }
    }

    /**
     * @param $ids
     * @param $page
     * @param $limit
     * @param $cols
     *
     * @return mixed
     */
    public static function lotsByID($ids, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        $filter['m.id'] = (empty($ids)) ? '' : $ids;

        $filter['m.status'] = $that::ST_ON;

        $filter['ORDER'] = $that::genOrder();

        return $that::paginate($that::fmTbl() . '(m)', $filter, $page, $limit, explode(',', $that::BE_COLS . $cols), $that::genJoin());
    }

    /**
     * @param $tbl
     * @param $filter
     * @param $page
     * @param $limit
     * @param $cols
     * @param $join
     */
    public static function paginate($tbl, $filter, $page = 0, $limit = 0, $cols = '*', $join = null)
    {
        $that = get_called_class();

        if (isset($filter['ORDER'])) {
            $order = $filter['ORDER'];
            unset($filter['ORDER']);
        }

        if (0 == $limit) {
            $limit = $that::PAGELIMIT;
        }

        $total = $that::_total($tbl, $filter, $join);
        if (0 == $total) {
            $result = [];
            $count  = 0;
            $page   = 0;
        } else {
            if (!empty($order)) {
                $filter['ORDER'] = $order;
            }

            $count = ceil((int) $total / (int) $limit);
            $page  = max(0, min($page, $count - 1));

            $filter['LIMIT'] = [$page * $limit, $limit];

            if (null == $join) {
                $result = mh()->select($tbl, $cols, $filter);
            } else {
                $result = mh()->select($tbl, $join, $cols, $filter);
            }
        }

        return [
            'subset' => $result,
            'total'  => $total,
            'limit'  => $limit,
            'count'  => $count,
            'pos'    => (($page < $count) ? $page : 0),
            'filter' => $filter,
            'sql'    => ((0 === f3()->get('DEBUG')) ? '' : mh()->last()),
        ];
    }

    /**
     * @param $tbl
     * @param $filter
     * @param $join
     */
    public static function _total($tbl, $filter, $join = null, $col = null)
    {
        $that = get_called_class();

        if (null == $col) {
            $col = (null == $join) ? 'COUNT(<' . $that::PK_COL . '>)' : 'COUNT(m.<' . $that::PK_COL . '>)';
        }

        if (null == $join) {
            $total = mh()->get($tbl, ['cnt' => MHelper::raw($col)], $filter);
        } else {
            $total = mh()->get($tbl, $join, ['cnt' => MHelper::raw($col)], $filter);
        }

        $err = mh()->error();

        if (is_array($err) && '00000' != $err[0]) {
            if (0 === f3()->get('DEBUG')) {
                return null;
            } else {
                print_r($err);
                exit;
            }
        }

        return ($total) ? $total['cnt'] * 1 : 0;
    }

    /**
     * @param $tbl
     * @param $filter
     * @param $join
     */
    public static function total($filter, $join = null, $subTbl = '', $col = null)
    {
        $that = get_called_class();
        $tbl  = $that::fmTbl($subTbl) . ((null == $join) ? '' : '(m)');

        return $that::_total($tbl, $filter, $join, $col);
    }

    /**
     * @param       $query
     * @param array $map
     * @param       $isSole
     *
     * @return mixed
     */
    public static function exec($query, $map = [], $isSole = false)
    {
        $query = trim($query);
        $res   = self::chkErr(mh()->query($query, $map));

        if ($res) {
            if (0 === stripos($query, 'SELECT')) {
                $method = ($isSole) ? 'fetch' : 'fetchAll';

                return $res->{$method}(\PDO::FETCH_ASSOC);
            } else {
                return $res->rowCount();
            }
        } else {
            return $res;
        }
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $that = get_called_class();
        $arr  = explode(',', $queryStr);

        $query = [];

        foreach ($arr as $val) {
            if (!empty($val)) {
                if (!preg_match('/(ORDER|LIMIT)/', $val)) {
                    if (false !== strpos($val, '<>')) {
                        [$k, $v]            = explode('<>', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<>]'] = explode('|', $v);
                    } elseif (false !== strpos($val, '>')) {
                        [$k, $v]           = explode('>', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[>]'] = $v;
                    } elseif (false !== strpos($val, '<')) {
                        [$k, $v]           = explode('<', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<]'] = $v;
                    } elseif (false !== strpos($val, '!~')) {
                        [$k, $v]            = explode('!~', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!~]'] = $v;
                    } elseif (false !== strpos($val, '!')) {
                        [$k, $v]           = explode('!', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!]'] = $v;
                    } elseif (false !== strpos($val, '~')) {
                        [$k, $v]           = explode('~', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[~]'] = $v;
                    } elseif (false !== strpos($val, ':')) {
                        [$k, $v]     = explode(':', $val);
                        $k           = (empty($k)) ? 'all' : $k;
                        $query[$k]   = (false !== strpos($v, '|')) ? explode('|', $v) : (string) $v;
                    } else {
                        $query['all'] = $val;
                    }
                } else {
                    [$k, $v]     = explode(':', $val);
                    $query[$k]   = $v;
                }
            }
        }

        $new = [];

        foreach ($query as $key => $value) {
            switch ($key) {
                case 'tag':
                    if (is_array($value)) {
                        $filter = [
                            'm.status' => fTag::ST_ON,
                        ];

                        $rows = $that::exec('SELECT t1.`artwork_id` FROM `tbl_artwork_tag` t1
                                INNER JOIN `tbl_artwork_tag` t2 ON t2.artwork_id = t1.`artwork_id` AND t2.`tag_id` = \'' . $value[1] . '\'
                                INNER JOIN `tbl_tag` t ON t.id = t1.`tag_id`
                                WHERE t1.`tag_id` = \'' . $value[0] . '\' ');
                    } else {
                        if (is_numeric($value)) {
                            $filter = [
                                'l.parent_id' => $value,
                                'm.status'    => fTag::ST_ON,
                            ];
                        } else {
                            $filter = [
                                'l.title[~]' => $value,
                                'm.status'   => fTag::ST_ON,
                            ];
                        }

                        $tag = mh()->get(fTag::fmTbl() . '(m)',
                            ['[><]' . fTag::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                        if (!empty($tag)) {
                            $rows = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $tag['id']]);
                        }
                    }

                    if (!empty($rows)) {
                        $new['m.id'] = \__::pluck($rows, $that::MTB . '_id');
                    } else {
                        $new['m.id'] = -1;
                    }
                    break;
                case 'author':
                    if (is_numeric($value)) {
                        $filter = [
                            'l.parent_id' => $value,
                            'm.status'    => fAuthor::ST_ON,
                        ];
                    } else {
                        $filter = [
                            'l.title[~]' => $value,
                            'm.status'   => fAuthor::ST_ON,
                        ];
                    }

                    $author = mh()->get(fAuthor::fmTbl() . '(m)',
                        ['[><]' . fAuthor::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                    if (!empty($author)) {
                        $rows = mh()->select($that::fmTbl('author') . '(r)', ['r.' . $that::MTB . '_id'], ['r.author_id' => $author['id']]);
                    }

                    if (!empty($rows)) {
                        $new['m.id'] = \__::pluck($rows, $that::MTB . '_id');
                    } else {
                        $new['m.id'] = -1;
                    }
                    break;
                case 'ORDER':
                    if (false !== strpos($value, '!')) {
                        $value = str_replace('!', '', $value);
                        $tmp   = [$value => 'DESC'];
                    } else {
                        $tmp = [$value => 'ASC'];
                    }
                    $new[$key] = $tmp;
                    break;
                default:
                    $new[$key] = $value;
                    break;
            }
        }

        return $new;
    }

    public static function genOrder()
    {
        return ['m.insert_ts' => 'DESC'];
    }

    public static function genJoin()
    {
        $that = get_called_class();
        $join = null;

        if ($that::MULTILANG) {
            $join = ['[>]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]];
        }

        return $join;
    }

    /**
     * @param $query
     *
     * @return mixed
     */
    public static function genFilter($query = '')
    {
        $that = get_called_class();

        if (is_string($query)) {
            $filter = $that::adjustFilter($that::_handleQuery($query));
        } elseif (is_array($query)) {
            $filter = $that::adjustFilter($query);
        } else {
            $filter = [];
        }

        return $filter;
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function _handleQuery($queryStr = '')
    {
        $that = get_called_class();
        $ary  = explode(',', $queryStr);

        $query = [];

        foreach ($ary as $val) {
            if (!empty($val)) {
                if (!preg_match('/(ORDER|LIMIT)/', $val)) {
                    if (false !== strpos($val, '&lt;&gt;')) {
                        [$k, $v]            = explode('&lt;&gt;', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<>]'] = explode('|', $v);
                    } elseif (false !== strpos($val, '&gt;')) {
                        [$k, $v]           = explode('&gt;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[>]'] = $v;
                    } elseif (false !== strpos($val, '&lt;')) {
                        [$k, $v]           = explode('&lt;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<]'] = $v;
                    } elseif (false !== strpos($val, '!~')) {
                        [$k, $v]            = explode('!~', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!~]'] = $v;
                    } elseif (false !== strpos($val, '!')) {
                        [$k, $v]           = explode('!', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!]'] = $v;
                    } elseif (false !== strpos($val, '~')) {
                        [$k, $v]           = explode('~', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[~]'] = $v;
                    } elseif (false !== strpos($val, ':')) {
                        [$k, $v]     = explode(':', $val);
                        $k           = (empty($k)) ? 'all' : $k;
                        $query[$k]   = (false !== strpos($v, '|')) ? explode('|', $v) : (string) $v;
                    } elseif (false !== strpos($val, '&#60;&#62;')) {
                        [$k, $v]            = explode('&#60;&#62;', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<>]'] = explode('|', $v);
                    } elseif (false !== strpos($val, '&#62;')) {
                        [$k, $v]           = explode('&#62;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[>]'] = $v;
                    } elseif (false !== strpos($val, '&#60;')) {
                        [$k, $v]           = explode('&#60;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<]'] = $v;
                    } else {
                        $query['all'] = $val;
                    }
                } else {
                    [$k, $v]     = explode(':', $val);
                    $query[$k]   = $v;
                }
            }
        }

        return $query;
    }

    public static function adjustFilter($oldFilter = [])
    {
        $that = get_called_class();

        if (array_key_exists('ORDER', $oldFilter)) {
            if (is_string($oldFilter['ORDER'])) {
                $ary = explode('|', $oldFilter['ORDER']);
                $tmp = [];
                foreach ($ary as $val) {
                    if (false !== strpos($val, '!')) {
                        $val   = str_replace('!', '', $val);
                        $tmp   = [$val => 'DESC'];
                    } else {
                        $tmp = [$val => 'ASC'];
                    }
                }
                $oldFilter['ORDER'] = $tmp;
            }
        } else {
            $oldFilter['ORDER'] = $that::genOrder();
        }

        if (array_key_exists('tag', $oldFilter)) {
            $value = $oldFilter['tag'];
            unset($oldFilter['tag']);

            if (is_array($value)) {
                $filter = [
                    'm.status' => fTag::ST_ON,
                ];

                $rows = $that::exec('SELECT t1.`' . $that::MTB . '_id` FROM `tbl_' . $that::MTB . '_tag` t1
                        INNER JOIN `tbl_' . $that::MTB . '_tag` t2
                            ON t2.`' . $that::MTB . '_id` = t1.`' . $that::MTB . '_id`
                            AND t2.`tag_id` = \'' . $value[1] . '\'
                        INNER JOIN `tbl_tag` t ON t.id = t1.`tag_id`
                        WHERE t1.`tag_id` = \'' . $value[0] . '\' ');
            } else {
                if (is_numeric($value)) {
                    $filter = [
                        'l.parent_id' => $value,
                        'm.status'    => fTag::ST_ON,
                    ];
                } else {
                    $filter = [
                        'l.title[~]' => $value,
                        'm.status'   => fTag::ST_ON,
                    ];
                }

                $tag = mh()->get(fTag::fmTbl() . '(m)',
                    ['[><]' . fTag::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                if (!empty($tag)) {
                    $rows = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $tag['id']]);
                }
            }

            if (!empty($rows)) {
                $oldFilter['m.id'] = \__::pluck($rows, $that::MTB . '_id');
            } else {
                $oldFilter['m.id'] = -1;
            }
        }

        return $oldFilter;
    }

    /**
     * save one column
     *
     * @param array $req
     */
    public static function saveCol($req, $table = '', $pk = 'id')
    {
        $that = get_called_class();

        $security_check = (
            in_array($req['col'], array_merge(self::default_filtered_column(), $that::filtered_column()))
            || $req['col'] == $pk
        );

        if ($security_check) {
            return false;
        }

        $rtn = mh()->update($that::fmTbl($table), [
            $req['col'] => $req['val'],
        ], [
            $pk => $req['pid'],
        ]);

        return $rtn->rowCount();
    }

    /**
     * @param $row
     * @param $filter
     */
    public static function onlyColumns($row, $filter)
    {
        return array_filter(
            $row,
            function ($key) use ($filter) {
                return in_array($key, $filter);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * filter some columns we don't want user by themselves
     *
     * @param string $column - column name
     *
     * @return boolen - filter or not
     */
    public static function filterColumn($column)
    {
        $that = get_called_class();

        return !in_array($column, array_merge(self::default_filtered_column(), $that::filtered_column()));
    }

    public static function filtered_column()
    {
        return [];
    }

    /**
     * @param $sql
     */
    public static function format($sql)
    {
        return ('sqlsrv' == f3()->get('db_type')) ? preg_replace('/`([\w]+)`/m', '[$1]', $sql) : $sql;
    }

    /**
     * @param $offset
     * @param $limit
     */
    public static function limit($offset = 0, $limit = 10)
    {
        return ('sqlsrv' == f3()->get('db_type')) ? ' OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $limit . ' ROWS ONLY ' : ' LIMIT ' . $offset . ', ' . $limit;
    }

    /**
     * @param $req
     *
     * @return int
     */
    public static function handleSave($req)
    {
        return 1;
    }

    public static function default_filtered_column()
    {
        return ['id', 'last_ts', 'last_user', 'insert_ts', 'insert_user'];
    }

    /**
     * get class const
     */
    public static function fmTbl($sub_table = '')
    {
        $that = get_called_class();

        return tpf() . $that::MTB . (('' != $sub_table) ? '_' . $sub_table : '');
    }

    /**
     * @param $rtn
     *
     * @return mixed
     */
    final public static function chkErr($rtn = 1)
    {
        $err = mh()->error();

        // array (
        //     ODBC 3.x SQLSTATE
        //     Driver-specific error code.
        //     Driver-specific error message.
        // )

        if (is_array($err) && (!empty($err[1]) || !empty($err[2]))) {
            $logger = new \Log('sql_error.log');
            $logger->write($err[2]);
            $logger->write(mh()->last());

            if (0 === f3()->get('DEBUG')) {
                return null;
            } else {
                $err['query'] = mh()->last();
                print_r($err);
                exit;
            }
        } else {
            return $rtn;
        }
    }

    public static function safePKAry($pid)
    {
        if (is_array($pid)) {
            $pid = array_filter($pid, function ($v) {
                return is_numeric($v);
            });
        } else {
            $pid = [intval($pid)];
        }

        return $pid;
    }

    public static function safeSlugAry($slugs)
    {
        if (is_array($slugs)) {
            $slugs = array_filter($slugs, function ($v) {
                return parent::_slugify($v);
            });
        } else {
            $slugs = [parent::_slugify($slugs)];
        }

        return $slugs;
    }

    /**
     * renderUniqueNo
     *
     * @param string $length - serial_no length
     * @param string $chars  - available char in serial_no
     *
     * @return string
     */
    final public static function renderUniqueNo($length = 6, $chars = '3456789ACDFGHJKLMNPQRSTWXY')
    {
        return secure_random_string($length, $chars);
    }

    /**
     * @param $str
     */
    final public static function _setPsw($str)
    {
        return password_hash($str, PASSWORD_BCRYPT, [
            'cost' => 10,
        ]);
    }

    /**
     * @param $str
     * @param $hash
     * @param $memberID
     */
    final public static function _chkPsw($str, $hash, $memberID = 0)
    {
        if (0 != $memberID && strlen($hash) < 40) {
            if ($hash == md5($str)) {
                $that = get_called_class();

                $that::saveCol([
                    'col' => 'pwd',
                    'val' => $that::_setPsw($str),
                    'pid' => $memberID,
                ]);

                return true;
            } else {
                return false;
            }
        }

        return password_verify($str, $hash);
    }

    /**
     * @param  no       input
     *
     * @return [string] [tokenString]
     */
    final public static function _genToken()
    {
        return secure_random_string(64);
    }
}
