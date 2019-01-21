<?php
namespace F3CMS;
/**
 * data feed
 */
class fProject extends Feed
{
    const MTB = "project";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    const PV_R = 'see.other.press';
    const PV_U = 'see.other.press';
    const PV_D = 'see.other.press';

    const BE_COLS = 'm.id,l.title,m.status,m.slug,m.cover,m.last_ts';

    /**
     * save whole form for backend
     * @param array $req
     */
    public static function save($req, $tbl = '')
    {

        $relateds = explode(',', $req['relateds']);
        unset($req['relateds']);


        list($data, $other) = self::_handleColumn($req);

        $rtn = null;

        if ($req['id'] == 0) {
            $data['insert_ts'] = date('Y-m-d H:i:s');
            $data['insert_user'] = rStaff::_CStaff('id');

            mh()->insert(self::fmTbl($tbl), $data);

            $req['id'] = mh()->id();

            $rtn = self::chkErr($req['id']);
        } else {
            $rtn = mh()->update(self::fmTbl($tbl), $data, array(
                'id' => $req['id'],
            ));

            $rtn = self::chkErr($rtn->rowCount());
        }

        self::saveLang($req['id'], $other['lang']);
        self::saveMany('related', $req['id'], $relateds);

        return $rtn;
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsRelated($pid)
    {

        $pk = 'project_id';
        $fk = 'related_id';

        $filter = array(
            'r.' . $pk => $pid,
            't.status' => self::ST_ON,
            'l.lang' => Module::_lang(),
        );

        return mh()->select(self::fmTbl('related') . '(r)', [
            '[>]' . self::fmTbl() . '(t)' => ['r.related_id' => 'id'],
            '[>]' . self::fmTbl('lang') . '(l)' => ['r.related_id' => 'parent_id']
        ], [
            't.id', 't.slug', 't.cover', 'l.subtitle', 'l.title'], $filter);
    }

    /**
     * get a next press
     *
     * @param  int      $press_id    - current
     * @return string
     */
    public static function load_next($cu, $col = 'id')
    {
        $condition = " WHERE m.`status` = '" . self::ST_ON . "' AND m.`" . $col . "` > '" . $cu[$col] . "' ";

        $join = " LEFT JOIN `". self::fmTbl('lang') ."` l ON l.`parent_id`=m.`id` AND l.`lang` = '". Module::_lang() ."' ";

        $rows = mh()->query('SELECT m.`id`, m.`slug`, l.`title` FROM `'. self::fmTbl() .'` m '. $join .' '. $condition .' ORDER BY m.`' . $col . '` ASC, m.`id` ASC  LIMIT 1 ')->fetchAll();

        if (count($rows) != 1) {
            return null;
        } else {
            return array('id' => $rows[0]['id'], 'slug' => $rows[0]['slug'], 'title' => $rows[0]['title']);
        }
    }

    /**
     * get a prev press
     *
     * @param  int      $cu          - current
     * @return string
     */
    public static function load_prev($cu, $col = 'id')
    {
        $condition = " WHERE m.`status` = '" . self::ST_ON . "' AND m.`" . $col . "` < '" . $cu[$col] . "' ";

        $join = " LEFT JOIN `". self::fmTbl('lang') ."` l ON l.`parent_id`=m.`id` AND l.`lang` = '". Module::_lang() ."' ";

        $rows = mh()->query('SELECT m.`id`, m.`slug`, l.`title` FROM `'. self::fmTbl() .'` m '. $join .' '. $condition .' ORDER BY m.`' . $col . '` DESC, m.`id` DESC LIMIT 1 ')->fetchAll();

        if (count($rows) != 1) {
            return null;
        } else {
            return array('id' => $rows[0]['id'], 'slug' => $rows[0]['slug'], 'title' => $rows[0]['title']);
        }
    }

}
