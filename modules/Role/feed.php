<?php

namespace F3CMS;

/**
 * data feed
 */
class fRole extends Feed
{
    const MTB       = 'role';
    const MULTILANG = 0;

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    // 完全管理權限 mgr.site
    // 二進位值 0b0001 代表僅有第一個權限位元被啟用
    // 用途範例：用於判斷是否擁有全部管理權限
    const P_ALL = 0b0001;
    // basic.product
    // 二進位值 0b00000010 代表第二個權限位元被啟用
    const P_BASIC_PRODUCT = 0b00000010; // 2
    // mgr.product
    // 二進位值 0b00000100 代表第三個權限位元被啟用
    const P_MGR_PRODUCT = 0b00000100; // 4
    // basic.marketing
    // 二進位值 0b00001000 代表第四個權限位元被啟用
    const P_BASIC_MARKETING = 0b00001000; // 8
    // mgr.marketing
    // 二進位值 0b00010000 代表第五個權限位元被啟用
    const P_MGR_MARKETING = 0b00010000; // 16
    // basic.customer
    // 二進位值 0b00100000 代表第六個權限位元被啟用
    const P_BASIC_CUSTOMER = 0b00100000; // 32
    // mgr.customer
    // 二進位值 0b01000000 代表第七個權限位元被啟用
    const P_MGR_CUSTOMER = 0b01000000; // 64
    // basic.sales
    // 二進位值 0b10000000 代表第八個權限位元被啟用
    const P_BASIC_SALES = 0b10000000; // 128
    // mgr.sales
    // 二進位值 0b0001000000 代表第九個權限位元被啟用
    const P_MGR_SALES = 0b0001000000; // 256
    // basic.finance
    // 二進位值 0b01000000000 代表第十個權限位元被啟用
    const P_BASIC_FINANCE = 0b01000000000; // 512
    // mgr.finance
    // 二進位值 0b10000000000 代表第十一個權限位元被啟用
    const P_MGR_FINANCE = 0b10000000000; // 1024
    // basic.admin
    // 二進位值 0b100000000000 代表第十二個權限位元被啟用
    const P_BASIC_ADMIN = 0b100000000000; // 2048
    // mgr.admin
    // 二進位值 0b1000000000000 代表第十三個權限位元被啟用
    const P_MGR_ADMIN = 0b1000000000000; // 4096 


    const BE_COLS = 'id,status,priv,title,info';

    /**
     * Generates a query based on the provided query string.
     * Handles special cases for filtering by title.
     *
     * @param string $queryStr The query string.
     * @return mixed The generated query.
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);

        if (array_key_exists('all', $query)) {
            $query['OR']['title[~]'] = $query['all'];
            unset($query['all']);
        }

        if (array_key_exists('all[!]', $query)) {
            $query['AND']['title[!]'] = $query['all[!]'];
            unset($query['all[!]']);
        }

        if (array_key_exists('all[!~]', $query)) {
            $query['AND']['title[!~]'] = $query['all[!~]'];
            unset($query['all[!~]']);
        }

        return $query;
    }

    /**
     * Retrieves the list of authorization options.
     * If not cached, it initializes the list and caches it.
     *
     * @return array The list of authorization options.
     */
    public static function getAuth()
    {
        $load = [];

        if (!f3()->exists('AUTH_LIST')) {
            $auth_list = [
                ['idx' => 1, 'name' => 'mgr.site', 'title' => '完全管理 - 進階'],                     // 1
                ['idx' => 2, 'name' => 'basic.product', 'title' => '產品管理 - 一般'],                 // 3
                ['idx' => 3, 'name' => 'mgr.product', 'title' => '產品管理 - 進階'],                   // 7
                ['idx' => 4, 'name' => 'basic.marketing', 'title' => '行銷管理 - 一般'],               // 15
                ['idx' => 5, 'name' => 'mgr.marketing', 'title' => '行銷管理 - 進階'],                 // 31
                ['idx' => 6, 'name' => 'basic.customer', 'title' => '客服管理 - 一般'],                // 63
                ['idx' => 7, 'name' => 'mgr.customer', 'title' => '客服管理 - 進階'],                  // 127
                ['idx' => 8, 'name' => 'basic.sales', 'title' => '銷售管理 - 一般'],                   // 255
                ['idx' => 9, 'name' => 'mgr.sales', 'title' => '銷售管理 - 進階'],                     // 511
                ['idx' => 10, 'name' => 'basic.finance', 'title' => '財務管理 - 一般'],                 // 1023
                ['idx' => 11, 'name' => 'mgr.finance', 'title' => '財務管理 - 進階'],                  // 2047
                ['idx' => 12, 'name' => 'basic.admin', 'title' => '行政管理 - 一般'],                  // 4095
                ['idx' => 13, 'name' => 'mgr.admin', 'title' => '行政管理 - 進階'],                    // 8191
            ];

            foreach ($auth_list as $auth) {
                $load[$auth['name']] = [
                    'idx'   => $auth['idx'],
                    'val'   => (1 << ($auth['idx'] - 1)),
                    'title' => $auth['title'],
                ];
            }

            f3()->set('AUTH_LIST', $load, 36000);
        } else {
            $load = f3()->get('AUTH_LIST');
        }

        return $load;
    }

    /**
     * Converts the authorization list into a format suitable for options.
     *
     * @return array The formatted authorization options.
     */
    public static function getAuthOpts()
    {
        $auth_list = fRole::getAuth();
        $load      = [];
        foreach ($auth_list as $auth) {
            $load[] = [
                'id'    => $auth['val'],
                'title' => $auth['title'],
            ];
        }

        return $load;
    }

    /**
     * Checks if a user has a specific authorization.
     *
     * @param int $user_priv The user's privilege value.
     * @param string $authority The authority to check.
     * @return bool True if the user has the authority, false otherwise.
     */
    public static function hasAuth($user_priv = 0, $authority = '')
    {
        $auth_list = self::getAuth();

        return !empty($auth_list[$authority]) && (($user_priv & $auth_list[$authority]['val']) == $auth_list[$authority]['val']);
    }

    /**
     * Parses an array of authorization indices into a single privilege value.
     *
     * @param array $ary The array of authorization indices.
     * @return int The calculated privilege value.
     */
    public static function parseAuth($ary = [])
    {
        $sum = 0;
        foreach ($ary as $val) {
            $sum += (1 << (intval($val) - 1));
        }

        return $sum;
    }

    /**
     * Parses an array of authorization values into their corresponding powers of 2.
     *
     * @param array $ary The array of authorization values.
     * @return array The calculated powers of 2.
     */
    public static function parseAuthVal($ary = [])
    {
        $sum = [];
        foreach ($ary as $idx => $val) {
            if ($val) {
                $sum[] = pow(2, $idx);
            }
        }

        return $sum;
    }

    /**
     * Parses an array of authorization values into their corresponding indices.
     *
     * @param array $ary The array of authorization values.
     * @return array The calculated indices.
     */
    public static function parseAuthIdx($ary = [])
    {
        $sum = [];
        foreach ($ary as $idx => $val) {
            if ($val) {
                $sum[] = $idx + 1;
            }
        }

        return $sum;
    }

    /**
     * Reverses the authorization value into a binary string representation.
     *
     * @param int $val The authorization value.
     * @return array The reversed binary representation.
     */
    public static function reverseAuth($val = 0)
    {
        return str_split(strrev(sprintf('%07s', base_convert($val, 10, 2) . '')));
    }

    /**
     * Handles column data for requests.
     * Converts authorization data into a privilege value.
     *
     * @param array $req The request data.
     * @return mixed The processed column data.
     */
    public static function _handleColumn($req)
    {
        $req['priv'] = array_sum($req['auth']);
        unset($req['auth']);

        return parent::_handleColumn($req);
    }
}
