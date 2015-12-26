<?php
namespace F3CMS;

class Reaction extends BaseModule
{

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
            $class = '\F3CMS\r'. ucfirst($args['module']);

            // Check if the action has a corresponding method.
            $method = sprintf('do_%s', $args['method']);
            if (!method_exists($class, $method)) {
                return parent::_return(1004, array('class'=>$class, 'method'=>$method));
            }

            // Create a reflection instance of the module, and obtaining the action method.
            $reflectionClass    = new \ReflectionClass($class);

            $reflectionInstance = $reflectionClass->newInstance();
            $reflectionMethod   = $reflectionClass->getMethod($method);

            // Invoke module action.
            $reflectionMethod->invokeArgs(
                $reflectionInstance,
                array($f3, $args)
            );
        }
        catch (Exception $e) {
            return parent::_return($e->getCode());
        }
    }

    function do_list_all($f3, $args)
    {
        rUser::_chkLogin();

        $feed = parent::_shift(get_called_class(), 'feed');

        $result = $feed::getAll();

        return parent::_return(1, $result);
    }

    /**
     * save whole form for backend
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_save($f3, $args)
    {
        rUser::_chkLogin();

        $req = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');

        if (!isset($req['pid'])) {
            return parent::_return(8004);
        }

        $pid = $feed::save($req);

        return parent::_return(1, array('pid' => $pid));
    }

    /**
     * save photo
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_upload($f3, $args)
    {
        rUser::_chkLogin();

        $name = str_replace(array('F3CMS\\', '\\'), array('', ''), get_called_class());

        list($type, $className) = preg_split("/(?<=[rfo])(?=[A-Z])/", $name);

        $thumb_str = strtolower($className) . '_thn';

        list($filename, $width, $height) = Upload::savePhoto(
            $f3->get('FILES'), array($f3->get($thumb_str), $f3->get('all_thn'))
        );

        return parent::_return(1, array('filename' => $filename));
    }

    /**
     * save one column
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_save_col($f3, $args)
    {

        rUser::_chkLogin();

        $req = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');

        if (!isset($req['pid'])) {
            return parent::_return(8004);
        }

        $pid = $feed::save_col($req);

        return parent::_return(1, array('pid' => $pid));
    }

    /**
     * delete one row
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_del_row($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        rUser::_chkLogin();

        $req = parent::_getReq();

        if (!isset($req['pid'])) {
            return parent::_return(8004);
        }

        $feed::del_row($req['pid']);

        return parent::_return(1);
    }

    /**
     * get one row
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_get_one($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        rUser::_chkLogin();

        $req = parent::_getReq();

        if (!isset($req['pid'])) {
            return parent::_return(8004);
        }

        if ($req['pid'] == 0) {
            // set default array
            $cu = array('id'=>0, 'title'=>'新增中…', 'status'=>'New');
        }
        else {
            $cu = $feed::get_row($req['pid']);
        }

        if (empty($cu)) {
            return parent::_return(8106);
        }
        else {
            // handleCurrentRow
            $cu = $that::handleRow($cu);
            return parent::_return(1, $cu);
        }
    }

    function do_get_opts($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        rUser::_chkLogin();

        $req = self::_getReq();

        if (!isset($req['query'])) {
            return self::_return(8004);
        }

        $rows = $feed::get_opts($req['query']);

        return parent::_return(1, $rows);
    }

    static function beforeSave($params = array()){
        return $params;
    }

    static function handleRow($row = array()){
        return $row;
    }

}
