<?php

namespace F3CMS;

/**
 * data feed
 */
class fTag extends Feed
{
    const MTB    = 'tag';
    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PAGELIMIT = 1000;

    const BE_COLS = 'm.id,l.title,m.slug,m.cate_id,m.counter';

    /**
     * @param $req
     *
     * @return int
     */
    public static function handleSave($req)
    {
        if (0 != $req['id']) {
            // fPress::batchRenew('tag', $req['id']);
        }

        return 1;
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsRelated($pid)
    {
        $pk = 'tag_id';
        $fk = 'related_id';

        $filter = [
            'r.' . $pk => $pid,
            't.status' => self::ST_ON,
        ];

        return mh()->select(self::fmTbl('related') . '(r)', ['[>]' . self::fmTbl() . '(t)' => ['r.related_id' => 'id']], ['t.id', 't.slug', 't.title'], $filter);
    }

    /**
     * @param $query
     */
    public static function get_opts($query = '', $column = 'title')
    {
        $filter = ['LIMIT' => 100];

        if ('' != $query) {
            $filter['OR']['title[~]'] = $query;
            $filter['OR']['slug[~]']  = $query;
        }

        return mh()->select(self::fmTbl(), ['id', $column], $filter);
    }

    /**
     * get detail by tag id
     *
     * @param int $pid - parent id
     *
     * @return array
     */
    public static function detail($pid, $type = 'all')
    {
        if ('all' == $type) {
            $cols = '*';
        } else {
            $cols = '`cover`, `banner`, `info`';
        }

        $rows = self::exec('SELECT ' . $cols . ' FROM `' . self::fmTbl('detail') . '` WHERE `parent_id`=:pid LIMIT 1 ', [':pid' => $pid]);

        if (1 != count($rows)) {
            return null;
        } else {
            return $rows[0];
        }
    }

    public static function setPressCnt($pid)
    {
        $cnt = self::exec('SELECT COUNT(r.`press_id`) AS `cnt` FROM `' . fPress::fmTbl('tag') . '` AS `r` ' .
            ' INNER JOIN `' . fPress::fmTbl() . '` AS `m` ON `r`.`press_id` = `m`.`id` AND `m`.`status` IN (\'' . fPress::ST_PUBLISHED . '\', \'' . fPress::ST_CHANGED . '\') ' .
            'WHERE `r`.`tag_id` = :pid LIMIT 1', [':pid' => $pid], true);
        $cnt = ($cnt) ? $cnt['cnt'] * 1 : 0;

        $rtn = mh()->update(self::fmTbl(), [
            'counter' => $cnt,
        ], [
            'id' => $pid,
        ]);

        return self::chkErr($rtn->rowCount());
    }
}
