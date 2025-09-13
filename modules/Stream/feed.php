<?php

namespace F3CMS;

/**
 * data feed
 */
class fStream extends Feed
{
    const MTB = 'stream';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const MULTILANG = 0;

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS   = 'm.id,s.email(staff),s.account,m.content,m.status,m.insert_ts,m.target';
    const PAGELIMIT = 50;

    /**
     * @param $target
     * @param $parent_id
     * @param $content
     */
    public static function insert($target, $parent_id, $content)
    {
        $now   = date('Y-m-d H:i:s');
        $staff = fStaff::_current('id');

        $data = [
            'target'      => $target,
            'parent_id'   => $parent_id,
            'content'     => $content,
            'status'      => self::ST_ON,
            'last_ts'     => $now,
            'last_user'   => $staff,
            'insert_ts'   => $now,
            'insert_user' => $staff,
        ];

        mh()->insert(self::fmTbl(), $data);

        return self::chkErr(mh()->id());
    }

    public static function genJoin()
    {
        return [
            '[>]' . fStaff::fmTbl() . '(s)' => ['m.last_user' => 'id'],
            // '[>]' . fTag::fmTbl('lang') . '(l)' => array('m.tag_id' => 'parent_id', 'l.lang' => '[SV]'. Module::_lang())
        ];
    }
}
