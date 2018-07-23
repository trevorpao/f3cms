<?php
namespace F3CMS;

/**
 * data feed
 */
class fPress extends Feed
{
    const MTB = 'press';
    const CATEGORYTB = 'tag';

    const ST_DRAFT = 'Draft';
    const ST_PUBLISHED = 'Published';
    const ST_SCHEDULED = 'Scheduled';
    const ST_OFFLINED = 'Offlined';

    const PV_R = 'use.cms';
    const PV_U = 'use.cms';
    const PV_D = 'use.cms';

    const PV_SOP = 'see.other.press';

    const BE_COLS = 'm.id,m.title,m.last_ts,m.pic,m.cover,m.status,m.slug,m.online_date,m.label_clr,m.highlight,s.account';

    /**
     * @param $query
     * @param $page
     * @param $limit
     * @return mixed
     */
    public static function limitRows($query = '', $page = 0, $limit = 12)
    {
        $filter = self::genQuery($query);

        // if (!canDo(self::PV_SOP)) {
        //     $filter['m.insert_user'] = rStaff::_CStaff();
        // }

        $filter['ORDER'] = ['m.online_date' => 'DESC', 'm.insert_ts' => 'DESC'];

        $join = ['[>]' . fStaff::fmTbl() . '(s)' => ['m.insert_user' => 'id']];

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);
    }

    public static function lotsByTag($id, $page = 0, $limit = 6)
    {

        if (is_array($id)) {
            // $presses = mh()->select(self::fmTbl('tag') . '(r)',
            //     ['[>]' . self::fmTbl('tag') . '(s)' => ['r.press_id' => 'press_id']],
            //     ['r.press_id'],
            //     ['r.tag_id=' => $id[0], 's.tag_id' => $id[1]]
            // );
            $condi = [];
            foreach ($id as $row) {
                $condi[] = ' SELECT `press_id` FROM `tbl_press_tag` WHERE `tag_id`='. intval($row) .' ';
            }

            $presses = mh()->query('SELECT `press_id`, COUNT(`press_id`) AS `cnt` FROM ('.(implode(' UNION ALL ', $condi)).') u GROUP by `press_id` HAVING `cnt` > '. (sizeof($condi) - 1) .' ')->fetchAll(\PDO::FETCH_ASSOC);
        }
        else {
            $presses = mh()->select(self::fmTbl('tag') . '(r)', ['r.press_id'], ['r.tag_id' => $id]);
        }

        return self::lotsByID(\__::pluck($presses, 'press_id'), $page, $limit);

        // $filter['m.id'] = \__::pluck($presses, 'press_id');

        // $filter['ORDER'] = ['m.online_date' => 'DESC', 'm.insert_ts' => 'DESC'];

        // $join = ['[>]' . fStaff::fmTbl() . '(s)' => ['m.insert_user' => 'id']];

        // return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);

        // $filter = array(
        //     ':tag' => $id,
        //     ':status' => self::ST_PUBLISHED,
        //     ':date' => date('Y-m-d'),
        // );

        // mh()->query(
        //     'SELECT `s`.* FROM `'. self::fmTbl('tag') .'` AS `r` '.
        //     'INNER JOIN `'. self::fmTbl() .'` AS `s` ON `r`.`press_id` = `s`.`id` '.
        //     'WHERE `r`.`tag_id` = :tag AND  s.`status` = :status  AND s.online_date <= :date', $filter
        // )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function lotsBySearch($id, $page = 0, $limit = 6)
    {
        $presses = mh()->select(fSearch::fmTbl('press') . '(r)', ['r.press_id'], ['r.search_id' => $id]);

        return self::lotsByID(\__::pluck($presses, 'press_id'), $page, $limit);
    }

    public static function lotsByAuthor($id, $page = 0, $limit = 6)
    {
        $presses = mh()->select(fPress::fmTbl('author') . '(r)', ['r.press_id'], ['r.author_id' => $id]);

        return self::lotsByID(\__::pluck($presses, 'press_id'), $page, $limit);
    }

    public static function lotsByID($ids, $page = 0, $limit = 6)
    {

        $filter['m.id'] = $ids;

        $filter['m.status'] = self::ST_PUBLISHED;
        $filter['m.site_id'] = f3()->get('site_id');

        $filter['ORDER'] = ['m.online_date' => 'DESC', 'm.insert_ts' => 'DESC'];

        $join = ['[>]' . fStaff::fmTbl() . '(s)' => ['m.insert_user' => 'id']];

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);
    }

    /**
     * @param $page
     * @param $slug
     * @param $type
     * @param $limit
     */
    public static function load_list($page, $slug, $type = 'author', $limit = 9)
    {
        $filter = array(
            ':status' => self::ST_PUBLISHED,
            ':date' => date('Y-m-d'),
        );

        $condition = ' WHERE m.`status` = :status  AND m.online_date <= :date ';

        if (!empty($slug)) {
            if ($type == 'author') {
                $filter[':author'] = $slug;
                $condition .= ' AND m.`author_id` = :author ';
            } else {
                $tag = fTag::get_tag_by_slug($slug);
                if ($tag) {
                    $condition .= " AND m.`rel_tag` LIKE '%\"" . $tag['id'] . "\"%' ";
                }
            }
        }

        $sql = 'SELECT m.`id`, m.`author_id`, m.`status`, m.`slug`, m.`rel_tag`, m.`rel_dict`, m.`online_date`, m.`title`, m.`keyword`, m.`pic`, m.`info`, m.`last_ts`, a.`title` AS `author`, a.`slug` AS `author_slug` FROM `' . self::fmTbl() . '` m '
        . ' LEFT JOIN `' . fAuthor::fmTbl() . '` a ON a.`id` = m.`author_id` '
            . $condition . ' ORDER BY m.`online_date` DESC ';

        // die($sql);

        return self::paginate($sql, $filter, $page - 1, $limit);
    }

    /**
     * get a next press
     *
     * @param  int      $press_id    - current
     * @param  int      $category_id - category
     * @return string
     */
    public static function load_next($cu, $category_id = 0, $col = 'id')
    {
        $condition = " WHERE `status` = '" . self::ST_PUBLISHED . "' AND online_date <= '" . date('Y-m-d') . "' AND `" . $col . "` >= '" . $cu[$col] . "' AND `id` != '" . $cu['id'] . "' ";

        if ($category_id != 0) {
            $condition .= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec('SELECT `id`, `slug`, `title` FROM `' . self::fmTbl() . '` ' . $condition . ' ORDER BY `' . $col . '` ASC, `id` DESC  LIMIT 1 ');

        if (count($rows) != 1) {
            return null;
        } else {
            return array('id' => $rows[0]['id'], 'title' => $rows[0]['title']);
        }
    }

    /**
     * get a prev press
     *
     * @param  int      $cu          - current
     * @param  int      $category_id - category
     * @return string
     */
    public static function load_prev($cu, $category_id = 0, $col = 'id')
    {
        $condition = " WHERE `status` = '" . self::ST_PUBLISHED . "' AND online_date <= '" . date('Y-m-d') . "' AND `" . $col . "` <= '" . $cu[$col] . "' AND `id` != '" . $cu['id'] . "' ";

        if ($category_id != 0) {
            $condition .= " AND `category_id`='" . $category_id . "' ";
        }

        $rows = db()->exec('SELECT `id`, `slug`, `title` FROM `' . self::fmTbl() . '` ' . $condition . ' ORDER BY `' . $col . '` DESC, `id` DESC LIMIT 1 ');

        if (count($rows) != 1) {
            return null;
        } else {
            return array('id' => $rows[0]['id'], 'title' => $rows[0]['title']);
        }
    }

    /**
     * @param $query
     */
    public static function get_opts($query = '', $column = 'title')
    {
        return mh()->query(
            'SELECT `p`.`id`, CONCAT(\' (\', `s`.`title`, \')\', `p`.`title`) AS `title` FROM `' . self::fmTbl() . '` AS `p` ' .
            'LEFT JOIN `' . fSite::fmTbl() . '` AS `s` ON `s`.`id` = `p`.`site_id` ' .
            'WHERE `p`.`title` LIKE :press ORDER BY p.`id` DESC LIMIT 100',
            [
                ":press" => '%' . $query . '%',
            ]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * insert press
     *
     * @return array
     */
    public static function insert($data = [])
    {
        $now = date('Y-m-d H:i:s');
        $obj = self::map();

        foreach ($data as $k => $v) {
            $obj[$k] = $v;
        }

        $obj->last_ts = $now;
        $obj->insert_ts = $now;
        $obj->save();

        return $obj->id;
    }

    /**
     * insert press
     *
     * @return array
     */
    public static function insert_category_rel($data = [])
    {
        $obj = self::map(self::CATEGORYTB);

        foreach ($data as $k => $v) {
            $obj[$k] = $v;
        }

        $obj->save();

        return $obj->press_id;
    }

    /**
     * save whole form for backend
     * @param array $req
     */
    public static function save($req, $tbl = '')
    {
        if (isset($req['pics'])) {
            if (!empty($req['pics'])) {
                $req['pic'] = $req['pics'];
            }
            unset($req['pics']);
        }

        $authors = explode(',', $req['authors']);
        unset($req['authors']);

        $relateds = explode(',', $req['relateds']);
        unset($req['relateds']);

        unset($req['status']); // can't change status from this func
        unset($req['online_date']);

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

        if (isset($other['tags']) && !empty($other['tags'])) {
            self::saveMany('tag', $req['id'], $other['tags']);
        }

        if (isset($other['meta']) && !empty($other['meta'])) {
            self::saveMeta($req['id'], $other['meta'], true);
        }

        if (isset($authors) && !empty($authors)) {
            self::saveMany('author', $req['id'], $authors);
        }

        if (isset($relateds) && !empty($relateds)) {
            self::saveMany('related', $req['id'], $relateds);
        }

        return $rtn;
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsRelated($pid)
    {

        $pk = 'press_id';
        $fk = 'related_id';

        $filter = array(
            'r.' . $pk => $pid,
            't.status' => self::ST_PUBLISHED,
        );

        return mh()->select(self::fmTbl('related') . '(r)', ['[>]' . self::fmTbl() . '(t)' => ['r.related_id' => 'id']], ['t.id', 't.slug', 't.site_id', 't.title'], $filter);
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsAuthor($pid)
    {

        $pk = 'press_id';
        $fk = 'author_id';

        $filter = array(
            'r.' . $pk => $pid,
            't.status' => fAuthor::ST_ON,
        );

        return mh()->select(self::fmTbl('author') . '(r)', ['[>]' . fAuthor::fmTbl() . '(t)' => ['r.author_id' => 'id']], ['t.id', 't.pic', 't.content', 't.slug', 't.title'], $filter);
    }

    public static function filtered_column()
    {
        return ['hh', 'mm'];
    }

    /**
     * @param $queryStr
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);
        $new = array();

        foreach ($query as $key => $value) {
            $new['m.' . $key] = $value;
        }

        return $new;
    }

    public static function batchRenew($type, $ids)
    {
        $rows = mh()->select(self::fmTbl($type) . '(r)', ['r.press_id'], ['r.'. $type .'_id' => $ids]);
        if (!empty($rows)) {
            $rtn = mh()->update(self::fmTbl(), [
                'last_user' => rStaff::_CStaff(),
                'status' => self::ST_SCHEDULED
            ], [
                'id' => \__::pluck($rows, 'press_id'),
                'status' => self::ST_PUBLISHED
            ]);
        }
    }

    public static function cronjob()
    {
        $data = db()->exec("
            SELECT tp.`id`, tp.`status`, tp.`online_date`
            FROM `" . self::fmTbl() . "` AS tp
            WHERE tp.`status` = 'Scheduled'
                AND tp.`online_date` < NOW() LIMIT 200;
        ");

        \__::map($data, function ($p) {
            $p['status'] = 'Published';

            fPress::published($p);

            $fc = new FCHelper('press');
            $fc->ifHistory = 1;

            $fc->save('press_' . $p['id'], oPress::render($p['id']));
            usleep(300000); // 0.3s
        });
    }
}
