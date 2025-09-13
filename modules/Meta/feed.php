<?php

namespace F3CMS;

/**
 * data feed
 */
class fMeta extends Feed
{
    const MTB        = 'meta';
    const MULTILANG  = 0;
    const BRANCHMODE = 1;

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const BE_COLS = 'm.id,m.sorter,m.fence,m.status,m.ps,m.label,m.type,m.input,s.account';

    /**
     * @param $tag_id
     *
     * @return mixed
     */
    public static function load($tag_id = '')
    {
        $filter = [
            'status' => self::ST_ON,
        ];

        if ('' != $tag_id) {
            $filter['tag_id'] = $tag_id;
        }

        $filter = self::branchSite($filter);

        $rows = mh()->select(self::fmTbl(), '*', $filter);

        $metas = [];

        foreach ($rows as $row) {
            if ('' != $tag_id) {
                $metas[$row['fence']] = $row;
            } else {
                $metas[$row['tag_id']][$row['fence']] = $row;
            }
        }

        return $metas;
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsGenus($pid)
    {
        $pk = self::MTB . '_id';
        $fk = 'tag_id';

        $filter = [
            'r.' . $pk => $pid,
            't.status' => fGenus::ST_ON,
        ];

        return mh()->select(self::fmTbl('tag') . '(r)',
            ['[>]' . tpf() . fGenus::MTB . '(t)'  => ['r.tag_id' => 'id']],
            // '[>]' . fTag::fmTbl('lang') . '(l)' => ['t.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]],
            ['t.id', 't.name(title)'], $filter);
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);

        if (array_key_exists('all', $query)) {
            $query['OR']['fence[~]'] = $query['all'];
            $query['OR']['label[~]'] = $query['all'];
            unset($query['all']);
        }

        if (array_key_exists('all[!]', $query)) {
            $query['AND']['fence[!]'] = $query['all[!]'];
            $query['AND']['label[!]'] = $query['all[!]'];
            unset($query['all[!]']);
        }

        if (array_key_exists('all[!~]', $query)) {
            $query['AND']['fence[!~]'] = $query['all[!~]'];
            $query['AND']['label[!~]'] = $query['all[!~]'];
            unset($query['all[!~]']);
        }

        return $query;
    }

    public static function genJoin()
    {
        return [
            '[>]' . fStaff::fmTbl() . '(s)' => ['m.last_user' => 'id'],
            // '[>]' . fTag::fmTbl('lang') . '(l)' => array('m.tag_id' => 'parent_id', 'l.lang' => '[SV]'. Module::_lang())
        ];
    }

    public static function genOrder()
    {
        return ['m.sorter' => 'ASC', 'm.fence' => 'ASC'];
    }
}
