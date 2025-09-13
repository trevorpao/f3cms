<?php

namespace F3CMS;

/**
 * Reaction 類別提供了後端資料操作的核心功能，包括資料列的新增、刪除、更新與查詢。
 */
class Reaction extends Module
{
    /**
     * 處理後端表單的路由邏輯。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     */
    public function do_rerouter($f3, $args)
    {
        try {
            $args = parent::_escape($args, false);

            // Create an instance of the module class.
            $class = '\F3CMS\r' . ucfirst($args['module']);

            // Check if the action has a corresponding method.
            $method = sprintf('do_%s', $args['method']);
            if (!method_exists($class, $method)) {
                return self::_return(1004, ['class' => $class, 'method' => $method]);
            }

            // Create a reflection instance of the module, and obtaining the action method.
            $reflectionClass = new \ReflectionClass($class);

            $reflectionInstance = $reflectionClass->newInstance();
            $reflectionMethod   = $reflectionClass->getMethod($method);

            // Invoke module action.
            $reflectionMethod->invokeArgs(
                $reflectionInstance,
                [$f3, $args]
            );
        } catch (Exception $e) {
            return self::_return($e->getCode());
        }
    }

    /**
     * 列出資料。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 資料列表
     */
    public function do_list($f3, $args)
    {
        $req  = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');
        chkAuth($feed::PV_R);

        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 1;

        $rtn = $feed::limitRows($req['query'], $req['page']);

        $rtn['query'] = $query;

        return self::_return(1, $rtn);
    }

    /**
     * 儲存整個表單的資料。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 儲存結果
     */
    public function do_save($f3, $args)
    {
        $req  = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');
        chkAuth($feed::PV_U);

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $id = $feed::save($req);

        $feed::handleSave($req);

        return self::_return(1, ['id' => $id]);
    }

    /**
     * 上傳照片。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 上傳結果
     */
    public function do_upload($f3, $args)
    {
        kStaff::_chkLogin();

        $name = str_replace(['F3CMS\\', '\\'], ['', ''], get_called_class());

        [$type, $className] = preg_split('/(?<=[rfo])(?=[A-Z])/', $name);

        $thumb_str = strtolower($className) . '_thn';

        $default = f3()->exists($thumb_str) ? f3()->get($thumb_str) : f3()->get('default_thn');

        [$filename, $width, $height] = Upload::savePhoto(
            f3()->get('FILES'), [$default, f3()->get('all_thn')]
        );

        return self::_return(1, ['filename' => $filename]);
    }

    /**
     * 上傳文件。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 上傳結果
     */
    public function do_upload_file($f3, $args)
    {
        kStaff::_chkLogin();

        $filename = Upload::saveFile(f3()->get('FILES'));

        return self::_return(1, ['filename' => $filename]);
    }

    /**
     * 儲存單一欄位的資料。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 儲存結果
     */
    public function do_save_col($f3, $args)
    {
        $req  = parent::_getReq();
        $feed = parent::_shift(get_called_class(), 'feed');

        chkAuth($feed::PV_U);

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $id = $feed::save_col($req);

        return self::_return(1, ['id' => $id]);
    }

    /**
     * 刪除單一資料列。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 刪除結果
     */
    public function do_del($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        chkAuth($feed::PV_D);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        $feed::delRow($req['id']);

        return self::_return(1);
    }

    /**
     * 取得單一資料列。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 資料列內容
     */
    public function do_get($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        chkAuth($feed::PV_R);

        $req = parent::_getReq();

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if (0 == $req['id']) {
            // set default array
            $cu = ['id' => 0];
        } else {
            $cu = $feed::one($req['id']);
        }

        if (empty($cu)) {
            return self::_return(8106);
        } else {
            // handleCurrentRow
            $cu = $that::handleRow($cu);

            return self::_return(1, $cu);
        }
    }

    /**
     * 取得選項資料。
     *
     * @param object $f3 框架實例
     * @param array $args URI 參數
     * @return array 選項資料
     */
    public function do_get_opts($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        kStaff::_chkLogin(); // chkAuth($feed::PV_R);

        $req = self::_getReq();

        if (!isset($req['query'])) {
            $req['query'] = '';
        }

        $rows = $feed::getOpts($req['query']);

        return self::_return(1, $rows);
    }

    /**
     * 儲存前的處理邏輯。
     *
     * @param array $params 資料參數
     * @return array 處理後的參數
     */
    public static function beforeSave($params = [])
    {
        return $params;
    }

    /**
     * 處理單一資料列。
     *
     * @param array $row 資料列
     * @return array 處理後的資料列
     */
    public static function handleRow($row = [])
    {
        return $row;
    }

    /**
     * 格式化訊息。
     *
     * @return array 訊息格式
     */
    public static function formatMsgs()
    {
        return [
            'MissCols'   => [
                'code' => '8002',
                'msg'  => '欄位未填寫，請重新確認!',
            ],
            'WrongData'  => [
                'code' => '8204',
                'msg'  => '欄位資料有誤，請重新確認!',
            ],
            'UnVerified' => [
                'code' => '8205',
                'msg'  => 'TOKEN 不符，請重新取得!',
            ],
        ];
    }

    /**
     * 返回資料的標準格式。
     *
     * @param mixed $code 成功或錯誤代碼
     * @param array $data 返回的資料
     * @return array 格式化後的返回資料
     */
    public static function _return($code = 1, $data = [])
    {
        $return = ['code' => (int) $code];

        if (!empty($data)) {
            $return['data'] = $data;
        }

        f3()->set('SESSION.csrf', f3()->get('sess')->csrf());

        $return['csrf'] = f3()->get('SESSION.csrf');

        // detect jsonp or json
        if (f3()->get('GET.callback') &&
            (0 === strpos(f3()->get('GET.callback'), '__jp') || 0 === strpos(f3()->get('GET.callback'), 'ng_jsonp_callback_'))) {
            header('Content-Type: application/javascript; charset=utf-8');
            exit(f3()->get('GET.callback') . ' (' . json_encode($return) . ');');
        } else {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode($return));
        }
    }
}
