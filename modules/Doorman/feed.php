<?php

namespace F3CMS;

/**
 * data feed
 */
class fDoorman extends Feed
{
    const MTB       = 'doorman';
    const MULTILANG = 0;

    const T_MEMBER = 'Member';
    const T_STAFF  = 'Staff';
    const T_ADMIN  = 'Admin';

    const ST_NEW     = 'New';
    const ST_INVALID = 'Invalid';

    /**
     * @param $req
     */
    public static function insert($type, $id, $pwd)
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'type'      => $type,
            'user_id'   => $id,
            'pwd'       => $pwd,
            'status'    => self::ST_NEW,
            'insert_ts' => $now,
        ];

        mh()->insert(self::fmTbl(), $data);

        return self::chkErr(mh()->id());
    }

    /**
     * @param $req
     */
    public static function insertFootmark($type, $id, $pwd)
    {
        $now  = date('Y-m-d H:i:s');
        $user = 0;
        $user = kMember::_isLogin() ? fMember::_current('id') : $user;
        $user = kStaff::_isLogin() ? fStaff::_current('id') : $user; // if user is a staff, staff id first

        $data = [
            'parent_id'   => $id,
            'pwd'         => $pwd,
            // 'status'      => self::ST_NEW,
            'insert_ts'   => $now,
            'insert_user' => $user,
        ];

        mh()->insert(tpf() . strtolower($type) . '_footmark', $data);

        return self::chkErr(mh()->id());
    }

    /**
     * @param $type
     * @param $id
     */
    public static function count($type, $id)
    {
        $cnt = mh()->get(self::fmTbl() . '(m)', ['cnt' => MHelper::raw('COUNT(m.<id>)')], [
            'm.user_id'      => $id,
            'm.type'         => $type,
            'm.status'       => self::ST_NEW,
            'm.insert_ts[>]' => date('Y-m-d H:i:s', time() - 3600),
        ]);

        return ($cnt) ? $cnt['cnt'] * 1 : 0;
    }

    /**
     * @param $type
     * @param $id
     */
    public static function zero($type, $id)
    {
        $data = mh()->update(self::fmTbl(), [
            'status' => self::ST_INVALID,
        ], [
            'user_id' => $id,
            'type'    => $type,
            'status'  => self::ST_NEW,
        ]);

        return parent::chkErr($data->rowCount());
    }

    /**
     * @param $type
     * @param $id
     *
     * @return mixed
     */
    public static function lastFootmark($type, $id)
    {
        $rows = self::exec('SELECT `insert_ts` FROM `' . tpf() . strtolower($type) . '_footmark` WHERE `parent_id`=:parent_id ORDER BY `insert_ts` DESC ' . self::limit(0, 1), [
            ':parent_id' => $id,
        ]);

        if (1 != count($rows)) {
            return 0;
        } else {
            $lastFootmark = $rows[0]['insert_ts'];

            $rtn = 0;

            if ($lastFootmark) {
                $birth = new \DateTime($lastFootmark); // three days difference!
                $today = new \DateTime();
                $diff  = $birth->diff($today);

                $rtn = $diff->days;
            }

            return $rtn;
        }
    }

    /**
     * @param string $type     module name
     * @param int    $parentID find those imgs from this type
     * @param int    $limit
     * @param string $col      pluck data
     *
     * @return mixed
     */
    public static function lotsFootmark($type, $parentID, $limit = 3, $col = 'pwd')
    {
        $limit = (int) min(100, max(2, $limit));

        $rows = self::exec('SELECT `pwd`, `insert_ts` FROM `' . tpf() . strtolower($type) . '_footmark` WHERE `parent_id` = :parent_id ORDER BY `id` ' . self::limit(0, $limit), [
            ':parent_id' => $parentID,
        ]);

        if (!empty($rows)) {
            if ('' != $col) {
                $rows = \__::pluck($rows, $col);
            }

            return $rows;
        } else {
            return [];
        }
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $query = [];
        $re    = '/:([\w_]+):([\w_]+)/m';

        preg_match_all($re, $queryStr, $matches, PREG_SET_ORDER, 0);

        foreach ($matches as $match) {
            $query[':' . $match[1]] = (string) $match[2];
        }

        return $query;
    }
}
