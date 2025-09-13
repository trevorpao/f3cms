<?php

namespace F3CMS;

/**
 * data feed
 */
class fSearch extends Feed
{
    const MTB       = 'search';
    const MULTILANG = 0;

    const BE_COLS = 'm.id,m.insert_date,m.status,m.type,m.key,m.cnt,m.insert_ts';

    public static function genOrder()
    {
        return ['m.key' => 'ASC', 'm.insert_date' => 'DESC'];
    }

    public static function get($key, $type = 'Press')
    {
        $row = mh()->get(self::fmTbl(strtolower($type)), ['raw'], [
            'sha1' => sha1($key),
        ]);

        if (!empty($row)) {
            $data        = jsonDecode($row['raw']);
            $data['sql'] = 'from cache';

            return $data;
        } else {
            return [];
        }
    }

    public static function keep($key, $content, $type = 'Press')
    {
        if (is_array($content)) {
            $content = jsonEncode($content);
        }

        self::exec('REPLACE INTO `' . self::fmTbl(strtolower($type)) . '` (`sha1`, `raw`) VALUES (:sha1, :raw) ', [
            ':sha1' => sha1($key),
            ':raw'  => $content,
        ]);
    }

    public static function memberLog($search_id, $member_id = 0)
    {
        if (empty($member_id)) {
            $member_id = fMember::_CMember();
        }

        if (!empty($member_id)) {
            $row = mh()->get(self::fmTbl('member'), '*', [
                'member_id' => $member_id,
                'search_id' => $search_id,
            ]);

            if (empty($row)) {
                mh()->insert(self::fmTbl('member'), [
                    'member_id' => $member_id,
                    'search_id' => $search_id,
                ]);
            }
        }

        mh()->update(self::fmTbl(), [
            'cnt[+]' => 1,
        ], [
            'id' => $search_id,
        ]);
    }

    public static function insert($key, $type = 'Press')
    {
        mh()->insert(self::fmTbl(), [
            'type'        => $type,
            'key'         => $key,
            'cnt'         => 0,
            'insert_date' => date('Y-m-d'),
        ]);

        self::memberLog(mh()->id());

        return self::chkErr();
    }

    public static function chk($key, $type = 'Press')
    {
        $row = mh()->get(self::fmTbl() . '(m)', [
            'm.id', 'm.key',
        ], [
            'm.key'       => $key,
            'type'        => $type,
            'insert_date' => date('Y-m-d'),
        ]);

        return (!empty($row)) ? $row['id'] : 0;
    }
}
