<?php
namespace F3CMS;

class Reaction extends Module
{

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
                return self::_return(1004, array('class'=>$class, 'method'=>$method));
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
            return self::_return($e->getCode());
        }
    }

    function do_list_all($f3, $args)
    {
        rStaff::_chkLogin();

        $feed = parent::_shift(get_called_class(), 'feed');

        $result = $feed::getAll();

        return self::_return(1, $result);
    }

    /**
     * save whole form for backend
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_save($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        rStaff::_chkLogin();

        $req = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $id = $feed::save($req);

        $feed::handleSave($req);

        return self::_return(1, array('id' => $id));
    }

    /**
     * draft whole form for backend
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_draft($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');

        $feed::draft($req);

        return self::_return(1, array('id' => $req['id']));
    }

    /**
     * save photo
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_upload($f3, $args)
    {
        rStaff::_chkLogin();

        $name = str_replace(array('F3CMS\\', '\\'), array('', ''), get_called_class());

        list($type, $className) = preg_split("/(?<=[rfo])(?=[A-Z])/", $name);

        $thumb_str = strtolower($className) . '_thn';

        list($filename, $width, $height) = Upload::savePhoto(
            f3()->get('FILES'), array(f3()->get($thumb_str), f3()->get('all_thn'))
        );

        return self::_return(1, array('filename' => $filename));
    }

    /**
     * save photo
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_upload_file($f3, $args)
    {
        rStaff::_chkLogin();

         $filename = Upload::saveFile(f3()->get('FILES'));

        return self::_return(1, array('filename' => $filename));
    }

    /**
     * save one column
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     */
    function do_save_col($f3, $args)
    {

        rStaff::_chkLogin();

        $req = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $id = $feed::save_col($req);

        return self::_return(1, array('id' => $id));
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

        rStaff::_chkLogin();

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $feed::del_row($req['id']);

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
        $feed = parent::_shift($that, 'feed');

        rStaff::_chkLogin();

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if ($req['id'] == 0) {
            // set default array
            $cu = array('id'=>0, 'title'=>'新增中…', 'status'=>'New');
        }
        else {
            $cu = $feed::get_row($req['id']);
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

    function do_get_opts($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        rStaff::_chkLogin();

        $req = self::_getReq();

        if (!isset($req['query'])) {
            return self::_return(8004);
        }

        $rows = $feed::get_opts($req['query']);

        return self::_return(1, $rows);
    }

    static function beforeSave($params = array()){
        return $params;
    }

    static function handleRow($row = array()){
        return $row;
    }

    /**
     * new return mode
     * @param mixed $code - whether sucess or error code
     * @param array $data - the data need to return
     * @return array
     */
    static function _return($code = 1, $data = array())
    {
        $return = array('code' => (string)$code);

        if (!empty($data)) {
            $return['data'] = $data;
        }

        f3()->set('SESSION.csrf', f3()->get('sess')->csrf());

        $return['csrf'] = f3()->get('SESSION.csrf');

        header('Content-Type: application/json');
        die(json_encode($return));
    }
}
