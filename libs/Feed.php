<?php
namespace F3CMS;

class Feed extends Module
{

    /**
     * save whole form for backend
     * @param  array  $req
     */
    static function save($req)
    {
        $that = get_called_class();
        $obj = $that::map();

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
        $obj = $that::map('meta');

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
        $obj = $that::map();

        $obj->load(array('id=?', $pid));

        $obj->last_ts = date('Y-m-d H:i:s');
        $obj->status = $status;

        $obj->save();

        return $obj->id;
    }

    static function get_opts($query)
    {

        $condition = " WHERE `title` like ? ";

        return db()->exec("SELECT id, title FROM `". self::fmTbl() ."` " . $condition . " LIMIT 30 ", '%'. $query .'%');
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

        db()->exec(
            "DELETE FROM `". self::fmTbl() ."` WHERE `id`=? LIMIT 1 ", $pid
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

        $rows = db()->exec(
            "SELECT * FROM `". self::fmTbl() ."` ". $condition ." LIMIT 1 ", $string
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
    static function fmTbl($sub_table = '')
    {
        $that = get_called_class();
        return tpf() . $that::MTB . (($sub_table != '') ? '_'.$sub_table : '');
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

    static function map($sub_table = '') {
        $that = get_called_class();
        $row = new \DB\SQL\Mapper(
            db(),
            $that::fmTbl()
        );

        return $row;
    }
}
