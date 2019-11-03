<?php
namespace F3CMS;

/**
 * data feed
 */
class fTag extends Feed
{
    const MTB = 'tag';
    const ST_ON = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'see.other.press';
    const PV_U = 'see.other.press';
    const PV_D = 'see.other.press';

    const BE_COLS = 'm.id,l.title,m.slug,m.counter';

    /**
     * @param  $query
     * @param  $page
     * @param  $limit
     * @param  $cols
     * @return mixed
     */
    public static function limitRows($query = '', $page = 0, $limit = 1000, $cols = '')
    {
        $rtn = parent::limitRows($query, $page, $limit);

        // TODO: add the title of parent

        return $rtn;
    }

    /**
     * @param  $req
     * @return int
     */
    public static function handleSave($req)
    {
        if ($req['id'] != 0) {
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
            't.status' => self::ST_ON
        ];

        return mh()->select(self::fmTbl('related') . '(r)', ['[>]' . self::fmTbl() . '(t)' => ['r.related_id' => 'id']], ['t.id', 't.slug', 't.title'], $filter);
    }

    /**
     * @param $query
     */
    public static function get_opts($query = '', $column = 'title')
    {
        $filter = ['LIMIT' => 100];

        if ($query != '') {
            $filter['OR']['title[~]'] = $query;
            $filter['OR']['slug[~]'] = $query;
        }

        return mh()->select(self::fmTbl(), ['id', $column], $filter);
    }

    /**
     * get detail by tag id
     *
     * @param  int     $pid - parent id
     * @return array
     */
    public static function detail($pid)
    {
        $rows = self::exec('SELECT * FROM `' . self::fmTbl('detail') . '` WHERE `parent_id`=? LIMIT 1 ', $pid);

        if (count($rows) != 1) {
            return null;
        } else {
            return $rows[0];
        }
    }

    /**
     * get a tag by tag id
     *
     * @param  int     $cid - type id
     * @return array
     */
    public static function get_tag($cid)
    {
        $rows = self::exec('SELECT * FROM `' . self::fmTbl() . '` WHERE `id`=? LIMIT 1 ', $cid);

        if (count($rows) != 1) {
            return null;
        } else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_tags($cu['id']);
            return $cu;
        }
    }

    /**
     * get a tag by slug
     *
     * @param  string  $slug - slug
     * @return array
     */
    public static function get_tag_by_slug($slug)
    {
        $rows = self::exec('SELECT * FROM `' . self::fmTbl() . '` WHERE `slug`=? LIMIT 1 ', $slug);

        if (count($rows) != 1) {
            return null;
        } else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_tags($cu['id']);
            return $cu;
        }
    }

    /**
     * get tags by parent id
     *
     * @param  int     $parent_id - parent type id
     * @return array
     */
    public static function get_tags($parent_id = -1)
    {
        $condition = '';

        if ($parent_id != -1) {
            $condition = " where c.parent_id='" . $parent_id . "' ";
        }

        return self::exec('SELECT c.`id`, c.title, c.`slug` FROM `' . self::fmTbl() . '` c ' . $condition);
    }
}
