<?php
namespace F3CMS;

class Feed extends BaseModule
{

    protected $_db;

    public function __construct()
    {
        parent::__construct();
        $this->_db = \Base::instance()->get('DB');
    }

    /**
     * set a no [0] array
     * @param  array $ary - target array
     * @return array      - fixed array
     */
    static function _fixAry(array $ary)
    {
        array_unshift($ary, "");
        unset($ary[0]);
        return $ary;
    }

    /**
     * renderUniqueNo
     * @param string $length - serial_no length
     * @param string $chars  - available char in serial_no
     * @return string
     */
    static function renderUniqueNo($length = 6, $chars = '3456789ACDFGHJKLMNPQRSTWXY')
    {
        $sn = '';
        for ($i = 0; $i < $length; $i++) {
            $sn.= substr($chars, rand(0, strlen($chars) - 1) , 1);
        }
        return $sn;
    }

    /**
     * save whole form for backend
     * @param  array  $req
     */
    static function save($req)
    {
        $that = get_called_class();
        $f3 = \Base::instance();

        $obj = new \DB\SQL\Mapper($f3->get('DB'), $f3->get('tpf') . self::_getMainTbl() ."");

        if ($req['pid'] == 0) {
            $obj->insert_ts = date('Y-m-d H:i:s');
            $obj->insert_user = rUser::_CUser('id');
            $obj->save();
        }
        else {
            $obj->load(array('id=?', $req['pid']));
        }

        foreach ($req['data'] as $key => $value) {
            if ($that::filterColumn($key)) {
                switch ($key) {
                    case 'slug':
                        $value = '/'. parent::_slugify($value);
                        $obj->{$key} = $value;
                        break;
                    case 'pwd':
                        if (!empty($value)) {
                            $value = $that::_setPsw($value);
                            $obj->{$key} = $value;
                        }
                        break;
                    default:
                        $obj->{$key} = (is_array($value)) ? json_encode($value) : $value;
                        break;
                }
            }
        }

        $obj->last_ts = date('Y-m-d H:i:s');

        $obj->last_user = rUser::_CUser('id');

        $obj->save();

        return $obj->id;
    }

    static function save_meta($pid, $k, $v, $replace = false)
    {
        $f3 = \Base::instance();

        $obj = new \DB\SQL\Mapper($f3->get('DB'), $f3->get('tpf') . self::_getMainTbl() ."_meta");

        if ($replace == false) {
            $obj->parent_id = $pid;
            $obj->save();
        }
        else {
            $obj->load(array(' parent_id=? AND k=? ', $pid, $k));
        }

        $obj->last_ts = date('Y-m-d H:i:s');
        $obj->k = $k;
        $obj->v = (is_array($v)) ? json_encode($v) : $v;

        $obj->save();

        return $obj->id;
    }

    static function change_status($pid, $status)
    {
        $f3 = \Base::instance();

        $obj = new \DB\SQL\Mapper($f3->get('DB'), $f3->get('tpf') . self::_getMainTbl() ."");

        $obj->load(array('id=?', $pid));

        $obj->last_ts = date('Y-m-d H:i:s');
        $obj->status = $status;

        $obj->save();

        return 1;
    }

    static function get_opts($query)
    {
        $f3 = \Base::instance();

        $condition = " WHERE `title` like ? ";

        return $f3->get('DB')->exec("SELECT id, title FROM `". $f3->get('tpf') . self::_getMainTbl() ."` " . $condition . " LIMIT 30 ", '%'. $query .'%');
    }

    /**
     * save one column
     * @param  array  $req
     */
    static function save_col($req)
    {
        $f3 = \Base::instance();
        $that = get_called_class();

        $obj = new \DB\SQL\Mapper($f3->get('DB'), $f3->get('tpf') . self::_getMainTbl() ."");

        if ($req['pid'] == 0) {
            $obj->insert_ts = date('Y-m-d H:i:s');
            $obj->insert_user = rUser::_CUser('id');
            $obj->save();
        }
        else {
            $obj->load(array('id=?', $req['pid']));
        }

        $obj->{$req['col_name']} = $req['val'];

        if ($req['col_name'] == 'pwd') {
            $obj->{$req['col_name']} = $that::_setPsw($req['val']);
        }

        $obj->last_ts = date('Y-m-d H:i:s');

        $obj->last_user = rUser::_CUser('id');

        $obj->save();

        return $obj->id;
    }

    /**
     * delete one row
     * @param  int $pid
     */
    static function del_row($pid)
    {
        $f3 = \Base::instance();

        $f3->get('DB')->exec(
            "DELETE FROM `". $f3->get('tpf') . self::_getMainTbl() ."` WHERE `id`=? LIMIT 1 ", $pid
        );

        return 1;
    }

    /**
     * get a row
     *
     * @param string $string - condition
     *
     * @return array
     */
    static function get_row($string, $type='id', $condition='')
    {
        $f3 = \Base::instance();

        switch ($type) {
            case 'account':
                $condition = " WHERE `account`=? ". $condition;
                break;
            case 'slug':
                $condition = " WHERE `slug`=? ". $condition;
                break;
            default:
                $condition = " WHERE `id`=? ". $condition;
                break;
        }

        $rows = $f3->get('DB')->exec(
            "SELECT * FROM `". $f3->get('tpf') .
            self::_getMainTbl() ."` ". $condition ." LIMIT 1 ", $string
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0];
        }
    }

    /**
     * filter some columns we don't want user by themselves
     * @param  string $column - column name
     * @return boolen         - filter or not
     */
    static function filterColumn($column)
    {
        $that = get_called_class();
        return !in_array($column, array_merge(self::default_filtered_column(), $that::filtered_column()));
    }

    static function filtered_column()
    {
        return array();
    }

    static function default_filtered_column()
    {
        return array('id', 'last_ts', 'last_user', 'insert_ts', 'insert_user');
    }

    /** get class const */
    static function _getMainTbl()
    {
        $that = get_called_class();
        return $that::MTB;
    }

}
