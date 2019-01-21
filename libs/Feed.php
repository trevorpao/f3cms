<?php
namespace F3CMS;

class Feed extends Module
{
    const MULTILANG = 1;
    /**
     * save whole form for backend
     * @param array $req
     */
    public static function save($req, $tbl = '')
    {
        $that = get_called_class();
        list($data, $other) = $that::_handleColumn($req);

        $rtn = null;

        if ($req['id'] == 0) {
            $data['insert_ts'] = date('Y-m-d H:i:s');
            $data['insert_user'] = rStaff::_CStaff('id');

            mh()->insert($that::fmTbl($tbl), $data);

            $req['id'] = mh()->id();

            $rtn = self::chkErr($req['id']);
        } else {
            $rtn = mh()->update($that::fmTbl($tbl), $data, array(
                'id' => $req['id']
            ));

            $rtn = self::chkErr($rtn->rowCount());
        }

        $that::saveMeta($req['id'], $other['meta'], true);
        $that::saveMany('tag', $req['id'], $other['tags']);
        $that::saveLang($req['id'], $other['lang']);

        return $rtn;
    }

    /**
     * save whole form for backend
     * @param array $req
     */
    public static function published($req, $tbl = '')
    {
        $that = get_called_class();
        $data = array(
            'status'    => $req['status'],
            'last_ts'   => date('Y-m-d H:i:s'),
            'last_user' => rStaff::_CStaff('id')
        );
        $rtn = null;

        if (isset($req['online_date'])) {
            $data['online_date'] = $req['online_date'];
        }

        $rtn = mh()->update($that::fmTbl($tbl), $data, array(
            'id' => $req['id']
        ));

        $rtn = self::chkErr($rtn->rowCount());

        return $rtn;
    }

    /**
     * pre handle column by diff type
     * @param  array   $req request columns
     * @return array
     */
    public static function _handleColumn($req)
    {
        $that = get_called_class();
        $data = array();
        $other = array();

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
                                $other['lang'][] = array($k, $v);
                            }
                        }
                        break;
                    case 'slug':
                        $value = parent::_slugify($value);
                        // $value = str_replace('//', '/', $value);
                        $data[$key] = $value;
                        break;
                    case 'pwd':
                        if (!empty($value)) {
                            $value = $that::_setPsw($value);
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

        $data['last_ts'] = date('Y-m-d H:i:s');
        $data['last_user'] = rStaff::_CStaff('id');

        return array($data, $other);
    }

    /**
     * @param $ta_tbl
     * @param $pid
     * @param $reverse
     */
    public static function lotsSub($subTbl, $pid)
    {
        $that = get_called_class();
        $sub = '\F3CMS\f' . ucfirst($subTbl);

        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';

        $filter = array($pk => $pid);
        $filter['l.lang'] = Module::_lang();

        return mh()->select(
            $that::fmTbl($subTbl) . '(r)',
            array(
                '[>]' . $sub::fmTbl() . '(t)'       => array('r.' . $fk => 'id'),
                '[>]' . $sub::fmTbl('lang') . '(l)' => array('t.id' => 'parent_id')
            ),
            array('t.id', 'l.title'),
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

        $filter = array(
            'r.' . $pk => $pid,
            't.status' => fTag::ST_ON
        );

        $filter['l.lang'] = Module::_lang();

        return mh()->select($that::fmTbl('tag') . '(r)',
            array('[>]' . tpf() . fTag::MTB . '(t)'  => array('r.tag_id' => 'id'),
                '[>]' . fTag::fmTbl('lang') . '(l)' => array('t.id' => 'parent_id')),
            array('t.id', 't.slug', 'l.title', 't.counter'), $filter);
    }

    /**
     * @param  $pid
     * @param  $key
     * @return mixed
     */
    public static function lotsMeta($pid, $key = '')
    {
        $that = get_called_class();

        $filter = array('parent_id' => $pid);

        if ($key != '') {
            $filter['k[~]'] = $key;
        }

        $result = mh()->select($that::fmTbl('meta'), '*', $filter);

        $rows = array();
        foreach ($result as $row) {
            $rows[$row['k']] = $row['v'];
        }
        return $rows;
    }

    /**
     * @param  $pid
     * @return array
     */
    public static function lotsLang($pid, $lang = '')
    {
        $that = get_called_class();
        $filter = array('parent_id' => $pid);
        if ($lang != '') {
            $filter['lang'] = $lang;
        }

        $result = mh()->select($that::fmTbl('lang'), '*', $filter);
        $filter = self::default_filtered_column();
        $filter[] = 'parent_id';

        $rows = array();
        foreach (f3()->get('acceptLang') as $n) {
            $rows[$n] = array();
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

        return ($lang != '') ? $rows[$lang] : $rows;
    }

    /**
     * @param  $subTbl
     * @param  $pid
     * @param  array      $rels
     * @param  $reverse
     * @return int
     */
    public static function saveMany($subTbl, $pid, $rels = array(), $reverse = false, $sortable = false)
    {
        if (empty($rels)) {
            return false;
        }

        $that = get_called_class();
        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';
        $data = array();

        if ($reverse) {
            $fk = $pk;
            $pk = $subTbl . '_id';
        }

        mh()->delete($that::fmTbl($subTbl), array($pk => $pid));

        foreach ($rels as $idx => $value) {
            if (!empty($value)) {
                $data[$idx] = array(
                    $pk => $pid,
                    $fk => $value
                );

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
     * @param  $pid
     * @param  $data
     * @param  $replace
     * @return int
     */
    public static function saveMeta($pid, $data = array(), $replace = false)
    {
        if (empty($data)) {
            return false;
        }

        $that = get_called_class();
        $rows = array();

        if ($replace) {
            mh()->delete($that::fmTbl('meta'), array('parent_id' => $pid, 'k' => array_keys($data)));
        }

        foreach ($data as $k => $v) {
            if (!empty($v)) {
                $rows[] = array(
                    'parent_id' => $pid,
                    'k'         => $k,
                    'v'         => $v
                );
            }
        }

        if (!empty($rows)) {
            mh()->insert($that::fmTbl('meta'), $rows);
        }

        return 1;
    }

    /**
     * @param  $pid
     * @param  $data
     * @param  $replace
     * @return int
     */
    public static function saveLang($pid, $data = array())
    {
        if (empty($data)) {
            return false;
        }

        $that = get_called_class();

        foreach ($data as $v) {
            if (!empty($v[1])) {
                $filter = array(
                    'parent_id' => $pid,
                    'lang'      => $v[0]
                );

                $v[1]['last_ts'] = date('Y-m-d H:i:s');
                $v[1]['last_user'] = rStaff::_CStaff('id');

                if (mh()->has($that::fmTbl('lang'), $filter)) {
                    mh()->update($that::fmTbl('lang'), $v[1], $filter);
                } else {
                    $v[1]['insert_ts'] = date('Y-m-d H:i:s');
                    $v[1]['insert_user'] = rStaff::_CStaff('id');

                    mh()->insert($that::fmTbl('lang'), array_merge($v[1], $filter));
                }
            }
        }

        return 1;
    }

    /**
     * @param $query
     */
    public static function getOpts($query = '', $column = 'title')
    {
        $that = get_called_class();
        $filter = array('LIMIT' => 100);

        $filter['l.lang'] = Module::_lang();

        if ($query != '') {
            $filter['l.title[~]'] = $query;
        }

        return mh()->select($that::fmTbl() . '(m)',
            array('[><]' . $that::fmTbl('lang') . '(l)' => array('m.id' => 'parent_id')), array('m.id', $column . '(title)'), $filter);
    }

    /**
     * delete one row
     * @param int $pid
     */
    public static function delRow($pid, $sub_table = '')
    {
        $that = get_called_class();

        $data = mh()->delete($that::fmTbl($sub_table), array(
            'id' => $pid
        ));

        return $data->rowCount();
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
            $filter['l.lang'] = Module::_lang();
            $join = array('[>]' . $that::fmTbl('lang') . '(l)' => array('m.id' => 'parent_id'));
        } else {
            $join = null;
        }

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
     * @param  mixed   $val - condition
     * @return array
     */
    public static function one($val, $col = 'id', $condition = array(), $multilang = 1)
    {
        $that = get_called_class();

        $data = mh()->get($that::fmTbl(), '*', array_merge(array(
            $col    => $val,
            'ORDER' => array($col => 'DESC')
        ), $condition));

        if (empty($data)) {
            return null;
        } else {
            switch ($multilang) {
                case 1:
                    $data['lang'] = $that::lotsLang($data['id']);
                    break;
                case 0:
                    $lang = $that::lotsLang($data['id'], parent::_lang());
                    if (is_array($lang)) {
                        $data = array_merge($data, $lang);
                    }
                    break;
                default:
                    break;
            }

            return $data;
        }
    }

    /**
     * @param $id
     * @param $page
     * @param $limit
     * @param $cols
     * @return mixed
     */
    public static function lotsByTag($id, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        if (is_array($id)) {
            $condi = array();
            foreach ($id as $row) {
                $condi[] = ' SELECT `' . $that::MTB . '_id` FROM `' . $that::fmTbl('tag') . '` WHERE `tag_id`=' . intval($row) . ' ';
            }

            $presses = mh()->query('SELECT `' . $that::MTB . '_id`, COUNT(`' . $that::MTB . '_id`) AS `cnt` FROM (' . (implode(' UNION ALL ', $condi)) . ') u GROUP by `' . $that::MTB . '_id` HAVING `cnt` > ' . (sizeof($condi) - 1) . ' ')->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $presses = mh()->select($that::fmTbl('tag') . '(r)', array('r.' . $that::MTB . '_id'), array('r.tag_id' => $id));
        }

        return $that::lotsByID(\__::pluck($presses, '' . $that::MTB . '_id'), $page, $limit, $cols);
    }

    /**
     * @param $ids
     * @param $page
     * @param $limit
     * @param $cols
     * @return mixed
     */
    public static function lotsByID($ids, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        $filter['m.id'] = $ids;

        $filter['m.status'] = $that::ST_ON;

        $filter['l.lang'] = Module::_lang();

        $filter['ORDER'] = array('m.insert_ts' => 'DESC');

        $join = array(
            '[>]' . fStaff::fmTbl() . '(s)'      => array('m.insert_user' => 'id'),
            '[>]' . $that::fmTbl('lang') . '(l)' => array('m.id' => 'parent_id')
        );

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
        if ($join == null) {
            $total = mh()->count($tbl, $filter);
        } else {
            $total = mh()->count($tbl, $join, '*', $filter);
        }

        $err = mh()->error();

        if (is_array($err) && $err[0] != '00000') {
            if (f3()->get('DEBUG') === 0) {
                return null;
            } else {
                print_r($err);
                die;
            }
        }

        $count = ceil($total / $limit);
        $page = max(0, min($page, $count - 1));

        $filter['LIMIT'] = array(($page * $limit), $limit);

        if ($join == null) {
            $result = mh()->select($tbl, $cols, $filter);
        } else {
            $result = mh()->select($tbl, $join, $cols, $filter);
        }

        return array(
            'subset' => $result,
            'total'  => $total,
            'limit'  => $limit,
            'count'  => $count,
            'pos'    => (($page < $count) ? $page : 0),
            'filter' => $filter,
            'sql'    => ((f3()->get('DEBUG') === 0) ? '' : mh()->last())
        );
    }

    /**
     * @param $query
     * @param array $map
     * @param $isSole
     * @return mixed
     */
    public static function exec($query, $map = array(), $isSole = false)
    {
        $query = trim($query);
        $res = mh()->query($query, $map);

        if (stripos($query, 'SELECT') === 0) {
            $method = ($isSole) ? 'fetch' : 'fetchAll';

            return $res->{$method}(\PDO::FETCH_ASSOC);
        } else {
            return $res->rowCount();
        }

    }

    /**
     * @param  $queryStr
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $that = get_called_class();
        $arr = explode(',', $queryStr);

        $query = array();

        foreach ($arr as $val) {
            if (!empty($val)) {
                if (strpos($val, '<>') !== false) {
                    list($k, $v) = explode('<>', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k . '[<>]'] = explode('|', $v);
                } else if (strpos($val, '>') !== false) {
                    list($k, $v) = explode('>', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k . '[>]'] = $v;
                } else if (strpos($val, '<') !== false) {
                    list($k, $v) = explode('<', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k . '[<]'] = $v;
                } else if (strpos($val, '!~') !== false) {
                    list($k, $v) = explode('!~', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k . '[!~]'] = $v;
                } else if (strpos($val, '!') !== false) {
                    list($k, $v) = explode('!', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k . '[!]'] = $v;
                } else if (strpos($val, '~') !== false) {
                    list($k, $v) = explode('~', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k . '[~]'] = $v;
                } else if (strpos($val, ':') !== false) {
                    list($k, $v) = explode(':', $val);
                    $k = (empty($k)) ? 'all' : $k;
                    $query[$k] = (strpos($v, '|') !== false) ? explode('|', $v) : (string) $v;
                } else {
                    $query['all'] = $val;
                }
            }
        }

        $new = array();

        foreach ($query as $key => $value) {
            if ($key == 'tag') {
                if (is_array($value)) {
                    $filter = array(
                        'm.status' => fTag::ST_ON
                    );

                    $rows = $that::exec('SELECT t1.`artwork_id` FROM `tbl_artwork_tag` t1
                            INNER JOIN `tbl_artwork_tag` t2 ON t2.artwork_id = t1.`artwork_id` AND t2.`tag_id` = \'' . $value[1] . '\'
                            INNER JOIN `tbl_tag` t ON t.id = t1.`tag_id`
                            WHERE t1.`tag_id` = \'' . $value[0] . '\' ');
                } else {
                    if (is_numeric($value)) {
                        $filter = array(
                            'l.parent_id' => $value,
                            'm.status'    => fTag::ST_ON
                        );
                    } else {
                        $filter = array(
                            'l.title[~]' => $value,
                            'm.status'   => fTag::ST_ON
                        );
                    }

                    $tag = mh()->get(fTag::fmTbl() . '(m)',
                        array('[><]' . fTag::fmTbl('lang') . '(l)' => array('m.id' => 'parent_id')), array('m.id'), $filter);

                    if (!empty($tag)) {
                        $rows = mh()->select($that::fmTbl('tag') . '(r)', array('r.' . $that::MTB . '_id'), array('r.tag_id' => $tag['id']));
                    }
                }

                if (!empty($rows)) {
                    $new['m.id'] = \__::pluck($rows, $that::MTB . '_id');
                } else {
                    $new['m.id'] = -1;
                }
            } else if ($key == 'author') {
                if (is_numeric($value)) {
                    $filter = array(
                        'l.parent_id' => $value,
                        'm.status'    => fAuthor::ST_ON
                    );
                } else {
                    $filter = array(
                        'l.title[~]' => $value,
                        'm.status'   => fAuthor::ST_ON
                    );
                }

                $author = mh()->get(fAuthor::fmTbl() . '(m)',
                    array('[><]' . fAuthor::fmTbl('lang') . '(l)' => array('m.id' => 'parent_id')), array('m.id'), $filter);

                if (!empty($author)) {
                    $rows = mh()->select($that::fmTbl('author') . '(r)', array('r.' . $that::MTB . '_id'), array('r.author_id' => $author['id']));
                }

                if (!empty($rows)) {
                    $new['m.id'] = \__::pluck($rows, $that::MTB . '_id');
                } else {
                    $new['m.id'] = -1;
                }
            } else {
                $new[$key] = $value;
            }
        }

        return $new;
    }

    /**
     * filter some columns we don't want user by themselves
     * @param  string $column - column name
     * @return boolen - filter or not
     */
    public static function filterColumn($column)
    {
        $that = get_called_class();
        return !in_array($column, array_merge(self::default_filtered_column(), $that::filtered_column()));
    }

    public static function filtered_column()
    {
        return array();
    }

    /**
     * save one column
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

        $rtn = mh()->update($that::fmTbl($table), array(
            $req['col'] => $req['val']
        ), array(
            $pk => $req['pid']
        ));

        return $rtn->rowCount();
    }

    /**
     * @param  $req
     * @return int
     */
    public static function handleSave($req)
    {
        return 1;
    }

    public static function default_filtered_column()
    {
        return array('id', 'last_ts', 'last_user', 'insert_ts', 'insert_user');
    }

    /**
     * get class const
     */
    public static function fmTbl($sub_table = '')
    {
        $that = get_called_class();
        return tpf() . $that::MTB . (($sub_table != '') ? '_' . $sub_table : '');
    }

    /**
     * @param $rtn
     * @return mixed
     */
    public static function chkErr($rtn)
    {
        $err = mh()->error();

        if (is_array($err) && $err[0] != '00000') {
            if (f3()->get('DEBUG') === 0) {
                return null;
            } else {
                print_r($err);
                die;
            }
        } else {
            return $rtn;
        }
    }

    /**
     * renderUniqueNo
     * @param  string   $length - serial_no length
     * @param  string   $chars  - available char in serial_no
     * @return string
     */
    public static function renderUniqueNo($length = 6, $chars = '3456789ACDFGHJKLMNPQRSTWXY')
    {
        $sn = '';
        for ($i = 0; $i < $length; $i++) {
            $sn .= substr($chars, rand(0, strlen($chars) - 1), 1);
        }
        return $sn;
    }
}
