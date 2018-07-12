<?php
namespace F3CMS;

class Feed extends Module
{
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

        if (isset($other['meta']) && !empty($other['meta'])) {
            self::saveMeta($req['id'], $other['meta'], true);
        }

        if (isset($other['tags']) && !empty($other['tags'])) {
            $that::saveMany('tag', $req['id'], $other['tags']);
        }

        if (isset($other['lang'])) {
            // TODO: save lang
        }

        return $rtn;
    }
    /**
     * save whole form for backend
     * @param array $req
     */
    public static function published($req, $tbl = '')
    {
        $that                = get_called_class();
        $data                = [];
        $rtn                 = null;

        $data['status']      = $req['status'];

        if (isset($req['online_date'])) {
            $data['online_date'] = $req['online_date'];
        }

        $data['last_ts']     = date('Y-m-d H:i:s');
        $data['last_user']   = rStaff::_CStaff('id');

        $rtn = mh()->update($that::fmTbl($tbl), $data, array(
            'id' => $req['id']
        ));

        $rtn = self::chkErr($rtn->rowCount());

        return $rtn;
    }

    /**
     * pre handle column by diff type
     * @param  array $req request columns
     * @return array
     */
    public static function _handleColumn($req)
    {
        $that = get_called_class();
        $data = [];
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
                        $value = parent::_slugify($value);
                        $value = str_replace('//', '/', $value);
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
        $sub = '\F3CMS\f'.ucfirst($subTbl);

        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';

        $filter = array($pk => $pid);

        return mh()->select($that::fmTbl($subTbl) . '(r)', ['[>]'.tpf().$sub::MTB.'(t)' => ['r.'. $fk => 'id']], ['t.id', 't.title'], $filter);
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
            'r.'.$pk => $pid,
            't.status' => fTag::ST_ON
        );

        return mh()->select($that::fmTbl('tag') . '(r)', ['[>]'.tpf().fTag::MTB.'(t)' => ['r.tag_id' => 'id']], ['t.id', 't.slug', 't.title', 't.counter'] , $filter);
    }

    /**
     * @param $pid
     * @param $lang
     * @param $draft
     * @return mixed
     */
    public static function load_lang($pid, $lang = '', $draft = false)
    {
        $that = get_called_class();
        $rows = array();

        if ($draft == ture) {
            $type = 'Draft';
        } else {
            $type = 'Ancestor';
        }

        $condition = " WHERE `parent_id` = '" . $pid . "' AND `type` = '" . $type . "' ";
        $order = ' ORDER BY `id` DESC ';

        if ($lang !== '') {
            $condition .= " AND `lang` = '" . $lang . "' ";
            $order .= ' LIMIT 1';
        }

        $rows = db()->exec('SELECT * FROM `' . $that::fmTbl('lang') . '` ' . $condition . ' ' . $order);

        if (count($rows) != 1) {
            $langs = array();
            foreach ($rows as $row) {
                $langs[$row['lang']] = $row;
            }

            return $langs;
        } else {
            return $rows[0];
        }
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
     * @param  $subTbl
     * @param  $pid
     * @param  array      $rels
     * @param  $reverse
     * @return int
     */
    public static function saveMany($subTbl, $pid, $rels = array(), $reverse = false)
    {
        $that = get_called_class();
        $pk = $that::MTB . '_id';
        $fk = $subTbl . '_id';
        $data = [];

        if ($reverse) {
            $fk = $pk;
            $pk = $subTbl . '_id';
        }

        mh()->delete($that::fmTbl($subTbl), [$pk => $pid]);

        foreach ($rels as $value) {
            if (!empty($value)) {
                $data[] = [
                    $pk => $pid,
                    $fk => $value
                ];
            }
        }

        if (!empty($data)) {
            mh()->insert($that::fmTbl($subTbl), $data);
        }

        return 1;
    }

    /**
     * @param $pid
     * @param $lang
     * @param $row
     * @param $replace
     * @param false $draft
     * @return mixed
     */
    public static function save_lang($pid, $lang, $row, $replace = false, $draft = false)
    {
        $that = get_called_class();
        $obj = $that::map('lang');

        if ($draft == ture) {
            $type = 'Draft';
        } else {
            $type = 'Ancestor';
        }

        if ($replace == false) {
            $obj->insert_ts = date('Y-m-d H:i:s');
            $obj->insert_user = rStaff::_CStaff('id');
            $obj->parent_id = $pid;
            $obj->lang = $lang;
            $obj->type = $type;
            $obj->save();
        } else {
            $obj->load(array(' `parent_id` = ? AND `type` = ? AND `lang` = ? ', $pid, $type, $lang));

            if ($obj->lang != $lang) {
                $obj->insert_ts = date('Y-m-d H:i:s');
                $obj->insert_user = rStaff::_CStaff('id');
                $obj->parent_id = $pid;
                $obj->lang = $lang;
                $obj->type = $type;
                $obj->save();
            }
        }

        $obj->last_ts = date('Y-m-d H:i:s');
        $obj->last_user = rStaff::_CStaff('id');

        foreach ($row as $key => $value) {
            if ($that::filterColumn($key) && !in_array($key, array('type', 'lang'))) {
                $obj->{$key} = $value;
            }
        }

        $obj->save();

        return $obj->id;
    }

    /**
     * @param  $pid
     * @param  $data
     * @param  $replace
     * @return int
     */
    public static function saveMeta($pid, $data, $replace = false)
    {
        $that = get_called_class();
        $obj = $that::map('meta');
        $rows = [];

        if ($replace) {
            mh()->delete($that::fmTbl('meta'), ['parent_id' => $pid, 'k' => array_keys($data)]);
        }

        foreach ($data as $k => $v) {
            if (!empty($v)) {
                $rows[] = [
                    'parent_id' => $pid,
                    'k' => $k,
                    'v' => $v
                ];
            }
        }

        if (!empty($rows)) {
            mh()->insert($that::fmTbl('meta'), $rows);
        }

        return 1;
    }

    /**
     * @param  $pid
     * @param  $status
     * @return mixed
     */
    public static function change_status($pid, $status)
    {
        $that = get_called_class();
        $obj = $that::map();

        $obj->load(array('id=?', $pid));

        $obj->last_ts = date('Y-m-d H:i:s');
        $obj->status = $status;

        $obj->save();

        return $obj->id;
    }

    /**
     * @param $query
     */
    public static function get_opts($query = '', $column = 'title')
    {
        $that = get_called_class();
        $filter = array('LIMIT' => 100);

        if ($query != '') {
            $filter['title[~]'] = $query;
        }

        return mh()->select($that::fmTbl(), ['id', $column], $filter);
    }

    /**
     * delete one row
     * @param int $pid
     */
    public static function del_row($pid, $sub_table = '')
    {
        $that = get_called_class();

        $data = mh()->delete($that::fmTbl($sub_table), array(
            'id' => $pid
        ));

        return $data->rowCount();
    }

    /**
     * save one column
     * @param  array  $req
     */
    static function save_col($req)
    {
        $that = get_called_class();
        $obj = $that::map();

        if ($req['pid'] == 0) {
            $obj->insert_ts = date('Y-m-d H:i:s');
            $obj->insert_user = rStaff::_CStaff('id') || rMember::_CMember();
            $obj->save();
        }
        else {
            $obj->load(array('id=?', $req['pid']));
        }

        $obj->{$req['col_name']} = $req['val'];

        // if ($req['col_name'] == 'pwd') {
        //     $obj->{$req['col_name']} = $that::_setPsw($req['val']);
        // }

        $obj->last_ts = date('Y-m-d H:i:s');

        $obj->last_user = rStaff::_CStaff('id') || rMember::_CMember();

        $obj->save();

        return $obj->id;
    }

    static function limitRows($query = '', $page = 0, $limit = 10)
    {
        $that = get_called_class();

        $lang = Module::_lang();

        $filter = $that::genQuery($query);

        return self::paginate($that::fmTbl(), $filter, $page, $limit, explode(',', $that::BE_COLS));
    }

    /**
     * get a row
     *
     * @param  mixed   $val - condition
     * @return array
     */
    public static function one($val, $col = 'id', $condition = array(), $draft = false)
    {
        $that = get_called_class();

        $data = mh()->get($that::fmTbl((($draft) ? 'draft' : '')), '*', array_merge(array(
            $col    => $val,
            'ORDER' => array($col => 'DESC')
        ), $condition));

        if (empty($data)) {
            return null;
        } else {
            return $data;
        }
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
        $total = mh()->count($tbl, $filter);

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
        }
        else {
            $result = mh()->select($tbl, $join, $cols, $filter);
        }

        return array(
            'subset' => $result,
            'total'  => $total,
            'limit'  => $limit,
            'count'  => $count,
            'pos'    => (($page < $count) ? $page : 0),
            'filter' => $filter,
            'sql'    => mh()->last()
        );
    }

    /**
     * @param  $queryStr
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
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
                    $query[$k] = $v;
                } else {
                    $query['all'] = $val;
                }
            }
        }

        return $query;
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
     * set a no [0] array
     * @param  array $ary - target array
     * @return array - fixed array
     */
    public static function _fixAry(array $ary)
    {
        array_unshift($ary, '');
        unset($ary[0]);
        return $ary;
    }

    public static function chkErr($rtn)
    {
        $err = mh()->error();

        if (is_array($err) && $err[0] != '00000') {
            if (f3()->get('DEBUG') === 0) {
                return null;
            }
            else {
                print_r($err);
                die;
            }
        }
        else {
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

    /**
     * @param  $sub_table
     * @return mixed
     */
    public static function map($sub_table = '')
    {
        $that = get_called_class();
        $row = new \DB\SQL\Mapper(
            db(),
            $that::fmTbl($sub_table)
        );

        return $row;
    }
}
