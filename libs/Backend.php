<?php
namespace F3CMS;

class Backend extends BaseModule
{

    protected $default_filtered_column = array('id', 'last_ts', 'last_user', 'insert_ts', 'insert_user');

    var $filtered_column = array();

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * save whole form for backend
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    public function do_rerouter($f3, $args)
    {
        try {
            // Create an instance of the module class.
            $class = ucfirst($args['module']);

            // Check if the action has a corresponding method.
            $method = sprintf('do_%s', $args['method']);
            if (!method_exists($class, $method)) {
                return self::_return(1004);
            }

            // Create a reflection instance of the module, and obtaining the action method.
            $reflectionClass    = new ReflectionClass($class);

            $reflectionInstance = $reflectionClass->newInstance();
            $reflectionMethod   = $reflectionClass->getMethod($method);

            // Invoke module action.
            $reflectionMethod->invokeArgs(
                $reflectionInstance,
                array($f3, $args)
            );
        }
        catch (Exception $e) {
            return self::_return($e->getCode());
        }
    }

    /**
     * save whole form for backend
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_save($f3, $args)
    {
        User::_chkLogin();

        $req = self::_getReq();
        $that = get_called_class();

        if (!isset($req['pid'])) {
            return self::_return(8004);
        }

        $obj = new \DB\SQL\Mapper($this->_db, $f3->get('tpf') . self::_getMainTbl() ."");

        if ($req['pid'] == 0) {
            $obj->insert_ts = date('Y-m-d H:i:s');
            $obj->insert_user = User::_CUser('id');
            $obj->save();
        }
        else {
            $obj->load(array('id=?', $req['pid']));
        }

        foreach ($req['data'] as $key => $value) {
            if ($this->filterColumn($key)) {
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

        $obj->last_user = User::_CUser('id');

        $obj->save();

        return self::_return(1, array('pid' => $obj->id));
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

    function do_get_opts($f3, $args)
    {
        User::_chkLogin();

        $req = self::_getReq();

        if (!isset($req['query'])) {
            return self::_return(8004);
        }

        $condition = " WHERE `title` like '%". parent::_escape($req['query'], false) ."%' ";

        $rows = $this->_db->exec("SELECT id, title FROM `". $f3->get('tpf') . self::_getMainTbl() ."` " . $condition . " LIMIT 30 ");

        return parent::_return(1, $rows);
    }

    /**
     * save photo
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_upload($f3, $args)
    {
        User::_chkLogin();

        $thumb_str = strtolower(get_called_class()) . '_thn';

        list($filename, $width, $height) = Upload::savePhoto(
            $f3->get('FILES'), array($f3->get($thumb_str), $f3->get('all_thn'))
        );

        return self::_return(1, array('filename' => $filename));
    }

    /**
     * save one column
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_save_col($f3, $args)
    {

        User::_chkLogin();

        $req = self::_getReq();
        $that = get_called_class();

        if (!isset($req['pid'])) {
            return self::_return(8004);
        }

        $obj = new \DB\SQL\Mapper($this->_db, $f3->get('tpf') . self::_getMainTbl() ."");

        if ($req['pid'] == 0) {
            $obj->insert_ts = date('Y-m-d H:i:s');
            $obj->insert_user = User::_CUser('id');
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

        $obj->last_user = User::_CUser('id');

        $obj->save();

        return self::_return(1, array('pid' => $obj->id));
    }

    /**
     * delete one row
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_del_row($f3, $args)
    {

        User::_chkLogin();

        $req = self::_getReq();

        $this->_db->exec(
            "DELETE FROM `". $f3->get('tpf') . self::_getMainTbl() ."` WHERE `id`='".
            intval($req['pid']) ."'"
        );

        return self::_return(1);
    }

    /**
     * get one row
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_get_one($f3, $args)
    {
        $that = get_called_class();

        User::_chkLogin();

        $req = self::_getReq();

        if (!isset($req['pid'])) {
            return self::_return(8004);
        }

        if ($req['pid'] == 0) {
            // set default array
            $cu = array('id'=>0, 'title'=>'新增中…', 'status'=>'New');
        }
        else {
            $cu = $that::get_row($req['pid']);
        }

        if (empty($cu)) {
            return self::_return(8106);
        }
        else {
            // handleCurrentRow
            $cu = $that::handleRow($cu);
            return self::_return(1, $cu);
        }
    }

    /**
     * get one row by id
     *
     * @param int $pid - type id
     *
     * @return array
     */
    static function get_row($pid)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::_getMainTbl() ."` WHERE `id`=? LIMIT 1 ", $pid
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
    function filterColumn($column)
    {
        return !in_array($column, array_merge($this->default_filtered_column, $this->filtered_column));
    }

    static function beforeSave($params = array()){
        return $params;
    }

    static function handleRow($row = array()){
        return $row;
    }

}
