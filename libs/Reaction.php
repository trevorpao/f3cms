<?php

namespace F3CMS;

// The Reaction class extends the Module class and handles backend operations.
// It includes methods for saving, uploading, and retrieving data, as well as managing user interactions.

class Reaction extends Module
{
    const RTN_DONE       = 'Done';       // Indicates a successful operation.
    const RTN_MISSCOLS   = 'MissCols';  // Indicates missing columns in the data.
    const RTN_WRONGDATA  = 'WrongData'; // Indicates invalid data.
    const RTN_UNVERIFIED = 'UnVerified'; // Indicates unverified data.

    /**
     * Handles rerouting logic for backend operations.
     *
     * @param object $f3   The Fat-Free Framework instance.
     * @param array  $args The URI parameters.
     */
    public function do_rerouter($f3, $args)
    {
        try {
            $args = parent::_escape($args, false);

            // Create an instance of the module class.
            $class = '\PCMS\r' . ucfirst($args['module']);

            // Check if the action has a corresponding method.
            $method = sprintf('do_%s', $args['method']);
            if (!method_exists($class, $method)) {
                $class = str_replace('PCMS', 'F3CMS', $class);

                if (!method_exists($class, $method)) {
                    return self::_return(1004, ['class' => $class, 'method' => $method]);
                }
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
     * Retrieves a list of records based on the provided query.
     *
     * @param object $f3   The Fat-Free Framework instance.
     * @param array  $args The URI parameters.
     * @return array The list of retrieved records.
     */
    public function do_list($f3, $args)
    {
        $req  = parent::_getReq(); // Retrieve the request data.
        $that = get_called_class(); // Get the current class name.
        $feed = parent::_shift($that, 'feed'); // Shift to the Feed module.

        chkAuth($feed::PV_R); // Check read permissions.

        // Set pagination parameters.
        $req['page']  = (isset($req['page'])) ? ($req['page'] - 1) : 0;
        $maxLimit     = ($feed::PAGELIMIT > 24) ? $feed::PAGELIMIT : 24;
        $req['limit'] = (!empty($req['limit'])) ? max(min($req['limit'] * 1, $maxLimit), 12) : $maxLimit;

        if (!isset($req['query'])) {
            $req['query'] = '';
        }

        // Retrieve the records based on the query.
        $rtn = $feed::limitRows($req['query'], $req['page'], $req['limit']);

        // Process each record using a custom iteratee function.
        $rtn['subset'] = \__::map($rtn['subset'], function ($row) use ($that) {
            return $that::handleIteratee($row);
        });

        return self::_return(1, $rtn); // Return the processed records.
    }

    /**
     * Saves data to the backend.
     *
     * @param object $f3   The Fat-Free Framework instance.
     * @param array  $args The URI parameters.
     * @return array The result of the save operation.
     */
    public function do_save($f3, $args)
    {
        $req  = parent::_getReq(); // Retrieve the request data.
        $feed = parent::_shift(get_called_class(), 'feed'); // Shift to the Feed module.
        $kit  = parent::_shift(get_called_class(), 'kit'); // Shift to the Kit module.

        chkAuth($feed::PV_U); // Check update permissions.

        if (!isset($req['id'])) {
            return self::_return(8004);
        }

        if (parent::_exists($kit)) {
            $chkRule = $kit::rule('save');
            if (!empty($chkRule)) {
                // return self::_return(1, $chkRule);
                Validation::return($req, $chkRule);
            }
        }

        $req = self::beforeSave($req); // Preprocess the data before saving.

        $id = $feed::save($req); // Save the data using the Feed module.

        $feed::handleSave($req); // Perform additional save handling.

        return self::_return(1, ['id' => $id]); // Return the ID of the saved record.
    }

    /**
     * Handles file uploads.
     *
     * @param object $f3   The Fat-Free Framework instance.
     * @param array  $args The URI parameters.
     */
    public function do_upload($f3, $args)
    {
        kStaff::_chkLogin(); // Check if the user is logged in.

        $name = str_replace(['F3CMS\\', '\\'], ['', ''], get_called_class()); // Normalize the class name.

        [$type, $className] = preg_split('/(?<=[rfo])(?=[A-Z])/', $name); // Split the class name into type and name.

        $thumb_str = strtolower($className) . '_thn'; // Generate a thumbnail string.

        $default = f3()->exists($thumb_str) ? f3()->get($thumb_str) : f3()->get('default_thn');

        [$filename, $width, $height] = Upload::savePhoto(
            f3()->get('FILES'), [$default, f3()->get('all_thn')]
        );

        return self::_return(1, ['filename' => $filename]);
    }

    /**
     * save photo
     *
     * @param object $f3   - $f3
     * @param array  $args - uri params
     */
    public function do_upload_file($f3, $args)
    {
        kStaff::_chkLogin();

        $filename = Upload::saveFile(f3()->get('FILES'));

        return self::_return(1, ['filename' => $filename]);
    }

    /**
     * delete one row
     *
     * @param object $f3   - $f3
     * @param array  $args - uri params
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

        if (1 != $feed::HARD_DEL) {
            return self::_return(8008);
        }

        $feed::delRow($req['id']);

        return self::_return(1);
    }

    /**
     * get one row
     *
     * @param object $f3   - $f3
     * @param array  $args - uri params
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
     * Retrieves options for a specific query.
     *
     * @param object $f3   The Fat-Free Framework instance.
     * @param array  $args The URI parameters.
     * @return array The retrieved options.
     */
    public function do_get_opts($f3, $args)
    {
        $that = get_called_class();
        $feed = parent::_shift($that, 'feed');

        chkAuth($feed::PV_R);

        $req = self::_getReq();

        if (!isset($req['query'])) {
            $req['query'] = '';
        }

        $rows = $feed::getOpts($req['query']);

        return self::_return(1, $rows);
    }

    /**
     * @param array $params
     *
     * @return mixed
     */
    public static function beforeSave($params = [])
    {
        return $params;
    }

    /**
     * Processes a single row of data.
     *
     * @param array $row The row of data to process.
     * @return array The processed row.
     */
    public static function handleIteratee($row = [])
    {
        return $row;
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        return $row;
    }

    public static function _parseBackendQuery($query)
    {
        $feed = parent::_shift(get_called_class(), 'feed');

        $query = str_replace(['=', '&'], [':', ','], $query);
        $query = str_replace(',amp;', ',', $query);

        return (!empty($query)) ? $feed::genFilter($query) : [];
    }

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
     * new return mode
     *
     * @param mixed $code - whether sucess or error code
     * @param array $data - the data need to return
     *
     * @return array
     */
    public static function _return($code = 1, $data = [])
    {
        $return = ['code' => (int) $code];

        if (!empty($data)) {
            $return['data'] = $data;
        }

        f3()->set('SESSION.csrf', getCSRF());

        $return['csrf'] = f3()->get('SESSION.csrf');

        // detect jsonp or json
        if (f3()->get('GET.callback')
            && (0 === strpos(f3()->get('GET.callback'), '__jp') || 0 === strpos(f3()->get('GET.callback'), 'ng_jsonp_callback_'))) {
            header('Content-Type: application/javascript; charset=utf-8');
            exit(f3()->get('GET.callback') . ' (' . json_encode($return) . ');');
        } else {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode($return));
        }
    }

    /**
     * _hashKey
     *
     * @param string $action - action name
     * @param array  $args   - hash source
     * @param string $secret - secret
     *
     * @return string
     */
    public static function _hashKey($action, $args, $secret)
    {
        $that      = get_called_class();
        $feed      = parent::_shift($that, 'feed');
        $hash_data = '';
        $keyAry    = $feed::hashGrid($action);

        // Create the string to be hashed.
        foreach ($keyAry as $k) {
            $hash_data .= ($args[$k] ?? '') . '-';
        }

        // Create hash key.
        return hash_hmac('sha256', $hash_data . round(time() / 300), $secret);
    }
}
