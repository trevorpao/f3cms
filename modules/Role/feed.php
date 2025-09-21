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

    const BE_COLS = 'id,status,priv,title,info';

    /**
     * @param $queryStr
     *
     * @return mixed
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

    public static function getAuth()
    {
        $load = [];

        if (!f3()->exists('AUTH_LIST')) {
            $auth_list = [
                ['idx' => 1, 'name' => 'base.cms', 'title' => '基本管理'],                // 1
                ['idx' => 2, 'name' => 'mgr.cms', 'title' => '進階內容管理'],              // 2
                ['idx' => 3, 'name' => 'base.member', 'title' => '基本客戶管理'],          // 4
                ['idx' => 4, 'name' => 'mgr.member', 'title' => '進階客戶管理'],           // 8
                ['idx' => 5, 'name' => 'mgr.site', 'title' => '完整網站管理'],             // 16
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
     * http://sqlfiddle.com/#!9/6932da6/1
     */
    public static function hasAuth($user_priv = 0, $authority = '')
    {
        $auth_list = self::getAuth();

        return !empty($auth_list[$authority]) && (($user_priv & $auth_list[$authority]['val']) == $auth_list[$authority]['val']);
    }

    public static function parseAuth($ary = [])
    {
        $sum = 0;
        foreach ($ary as $val) {
            $sum += (1 << (intval($val) - 1));
        }

        return $sum;
    }

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

    public static function reverseAuth($val = 0)
    {
        return str_split(strrev(sprintf('%07s', base_convert($val, 10, 2) . '')));
    }

    public static function _handleColumn($req)
    {
        $req['priv'] = array_sum($req['auth']);
        unset($req['auth']);

        return parent::_handleColumn($req);
    }
}
