<?php
namespace F3CMS;

class Feed extends Module
{
    const MULTILANG      = 1;
    const BE_COLS        = 'm.id';
    const LANG_ARY_MERGE = 0;
    const LANG_ARY_ALONE = 1;
    const LANG_ARY_SKIP  = 2;

    const PV_R = 'base.cms';
    const PV_U = 'base.cms';
    const PV_D = 'mgr.cms';

    /**
     * save whole form for backend
     * @param array $req
     */
    public static function save($req, $tbl = '')
    {
        $that               = get_called_class();
        list($data, $other) = $that::_handleColumn($req);

        $rtn = null;

        if ($req['id'] == 0) {
            $data['insert_ts']   = date('Y-m-d H:i:s');
            $data['insert_user'] = fStaff::_current('id');

            mh()->insert($that::fmTbl($tbl), $data);

            $req['id'] = mh()->id();

            $rtn = self::chkErr($req['id']);
        } else {
            $rtn = mh()->update($that::fmTbl($tbl), $data, [
                'id' => $req['id'],
            ]);

            $rtn = self::chkErr($rtn->rowCount());
        }

        $that::saveMeta($req['id'], $other['meta'], true);
        $that::saveMany('tag', $req['id'], $other['tags']);
        $that::saveLang($req['id'], $other['lang']);

        return $rtn;
    }

    /**
     * save whole form for backend
     *
     * @param array $req
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
     * pre handle column by diff type
     *
     * @param array $req request columns
     *
     * @return array
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
                        $value = (empty($value)) ? $that::renderUniqueNo(16) : parent::_slugify($value);
                        // $value = str_replace('//', '/', $value);
                        $data[$key] = $value;
                        break;
                    case 'pwd':
                        if (!empty($value)) {
                            $value      = $that::_setPsw($value);
                            $data[$key] = $value;
                        }
                        break;
                    case 'online_date':
                        $data[$key] = date('Y-m-d', strtotime($value)); // + 7 * 3600);
                        break;
                    case 'id':
                        break;
                    default:
                        $data[$key] = (is_array($value)) ? json_encode($value) : $value;
                        break;
                }
            }
        }

        $data['last_ts']   = date('Y-m-d H:i:s');
        $data['last_user'] = fStaff::_current('id');

        return [$data, $other];
    }

    /**
     * @param $ta_tbl
     * @param $pid
     * @param $reverse
     */
    public static function lotsSub($subTbl, $pid)
    {
        $that = get_called_class();
        $sub  = '\F3CMS\f' . ucfirst($subTbl);

        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';

        $filter = [$pk => $pid];

        return mh()->select(
            $that::fmTbl($subTbl) . '(r)',
            [
                '[>]' . $sub::fmTbl() . '(t)'       => ['r.' . $fk => 'id'],
                '[>]' . $sub::fmTbl('lang') . '(l)' => ['t.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()],
            ],
            ['t.id', 'l.title'],
            $filter
        );
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsTag($pid)
    {
        $that = get_called_class();

        $pk = $that::MTB . '_id';
        $fk = 'tag_id';

        $filter = [
            'r.' . $pk => $pid,
            't.status' => fTag::ST_ON,
        ];

        return mh()->select($that::fmTbl('tag') . '(r)',
            ['[>]' . tpf() . fTag::MTB . '(t)'          => ['r.tag_id' => 'id'],
                '[>]' . fTag::fmTbl('lang') . '(l)'     => ['t.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()], ],
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
     * @param       $subTbl
     * @param       $pid
     * @param array $rels
     * @param       $reverse
     *
     * @return int
     */
    public static function saveMany($subTbl, $pid, $rels = [], $reverse = false, $sortable = false)
    {
        if (empty($rels)) {
            return false;
        }

        $that = get_called_class();
        $pk   = $that::MTB . '_id';
        $fk   = $subTbl . '_id';
        $data = [];

        if ($reverse) {
            $fk = $pk;
            $pk = $subTbl . '_id';
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

        return self::chkErr(1);
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

        return self::chkErr(1);
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

                if (mh()->has($that::fmTbl('lang'), $filter)) {
                    mh()->update($that::fmTbl('lang'), $v[1], $filter);
                } else {
                    $v[1]['insert_ts']   = date('Y-m-d H:i:s');
                    $v[1]['insert_user'] = fStaff::_current('id');

                    mh()->insert($that::fmTbl('lang'), array_merge($v[1], $filter));
                }
                rtTrace();
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

        return self::chkErr($data->rowCount());
    }

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @param $cols
     */
    public static function limitRows($query = '', $page = 0, $limit = 12, $cols = '')
    {
        $that = get_called_class();

        $filter = $that::genQuery($query);

        if ($that::MULTILANG) {
            $join = ['[>]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]];
        } else {
            $join = null;
        }

        $filter['ORDER'] = (isset($filter['ORDER'])) ? $filter['ORDER'] : ['m.insert_ts' => 'DESC'];

        return self::paginate(
            $that::fmTbl() . '(m)',
            $filter,
            $page,
            $limit,
            explode(',', $that::BE_COLS . $cols),
            $join
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

            $data = json_decode(json_encode($data, JSON_NUMERIC_CHECK), true);

            return $data;
        }
    }

    /**
     * @param $id
     * @param $page
     * @param $limit
     * @param $cols
     *
     * @return mixed
     */
    public static function lotsByTag($id, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        if (is_array($id)) {
            $condi = [];
            foreach ($id as $row) {
                $condi[] = ' SELECT `' . $that::MTB . '_id` FROM `' . $that::fmTbl('tag') . '` WHERE `tag_id`=' . intval($row) . ' ';
            }

            $presses = mh()->query('SELECT `' . $that::MTB . '_id`, COUNT(`' . $that::MTB . '_id`) AS `cnt` FROM (' . (implode(' UNION ALL ', $condi)) . ') u GROUP by `' . $that::MTB . '_id` HAVING `cnt` > ' . (count($condi) - 1) . ' ')->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $presses = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $id]);
        }

        return $that::lotsByID(\__::pluck($presses, '' . $that::MTB . '_id'), $page, $limit, $cols);
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

        $filter['m.id'] = $ids;

        $filter['m.status'] = $that::ST_ON;

        $filter['ORDER'] = ['m.insert_ts' => 'DESC'];

        $join = [
            '[>]' . fStaff::fmTbl() . '(s)'      => ['m.insert_user' => 'id'],
            '[>]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()],
        ];

        return $that::paginate($that::fmTbl() . '(m)', $filter, $page, $limit, explode(',', $that::BE_COLS . $cols), $join);
    }

    /**
     * @param $tbl
     * @param $filter
     * @param $page
     * @param $limit
     * @param $cols
     * @param $join
     */
    public static function paginate($tbl, $filter, $page = 0, $limit = 10, $cols = '*', $join = null)
    {
        if (null == $join) {
            $total = mh()->count($tbl, $filter);
        } else {
            $total = mh()->count($tbl, $join, '*', $filter);
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

        $count = ceil($total / $limit);
        $page  = max(0, min($page, $count - 1));

        $filter['LIMIT'] = [($page * $limit), $limit];

        if (null == $join) {
            $result = mh()->select($tbl, $cols, $filter);
        } else {
            $result = mh()->select($tbl, $join, $cols, $filter);
        }

        return [
            'subset' => $result,
            'total'  => $total,
            'limit'  => $limit,
            'count'  => $count,
            'pos'    => (($page < $count) ? $page : 0),
            'filter' => ((0 === f3()->get('DEBUG')) ? '' : $filter),
            'sql'    => ((0 === f3()->get('DEBUG')) ? '' : mh()->last()),
        ];
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
        $res   = mh()->query($query, $map);

        if (0 === stripos($query, 'SELECT')) {
            $method = ($isSole) ? 'fetch' : 'fetchAll';

            return $res->{$method}(\PDO::FETCH_ASSOC);
        } else {
            return self::chkErr($res->rowCount());
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
                if (!preg_match('/[A-Z]./', $val)) {
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
     * save one column
     *
     * @param array $req
     */
    public static function saveCol($req, $table = '', $pk = 'id')
    {
        $that = get_called_class();

        $security_check = (
            in_array($req['col'], array_merge(self::default_filtered_column(), $that::filtered_column())) ||
            $req['col'] == $pk
        );

        if ($security_check) {
            return false;
        }

        $rtn = mh()->update($that::fmTbl($table), [
            $req['col'] => $req['val'],
        ], [
            $pk => $req['pid'],
        ]);

        return self::chkErr($rtn->rowCount());
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
    public static function chkErr($rtn)
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

    /**
     * renderUniqueNo
     *
     * @param string $length - serial_no length
     * @param string $chars  - available char in serial_no
     *
     * @return string
     */
    public static function renderUniqueNo($length = 6, $chars = '3456789ACDFGHJKLMNPQRSTWXY')
    {
        $sn = '';
        for ($i = 0; $i < $length; ++$i) {
            $sn .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }

        return $sn;
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
        $rand1       = Encryption::salt(32);
        $rand2       = Encryption::salt(32);
        $tokenString = $rand1 . $rand2;

        return $tokenString;
    }
}
