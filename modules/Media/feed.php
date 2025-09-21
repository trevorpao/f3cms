<?php

namespace F3CMS;

/**
 * data feed
 */
class fMedia extends Feed
{
    const MTB = 'media';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const BE_COLS = 'm.id,l.title,m.target,m.slug,m.status,m.pic,l.info,m.last_ts';

    /**
     * @param $req
     */
    public static function insert($req)
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'title'     => $req['title'],
            'slug'      => $req['slug'],
            'parent_id' => $req['parent_id'],
            'target'    => ucfirst($req['target']),
            'pic'       => $req['pic'],
            'status'    => self::ST_ON,
            'last_ts'   => $now,
            'insert_ts' => $now,
        ];

        mh()->insert(self::fmTbl(), $data);

        $pid = self::chkErr(mh()->id());

        if ($pid) {
            mh()->insert(self::fmTbl('lang'), [
                'parent_id' => $pid,
                'from_ai'   => 'No',
                'lang'      => Module::_lang(),
                'title'     => $req['title'],
                'last_ts'   => $now,
                'insert_ts' => $now,
            ]);
        }

        return $pid;
    }

    public static function genOrder()
    {
        return ['m.sorter' => 'ASC', 'm.insert_ts' => 'DESC'];
    }
}
