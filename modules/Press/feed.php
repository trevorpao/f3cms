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
    const ST_CHANGED = 'Changed';
    const ST_OFFLINED = 'Offlined';

    const PV_R = 'use.cms';
    const PV_U = 'use.cms';
    const PV_D = 'use.cms';

    const PV_SOP = 'see.other.press';

    const BE_COLS = 'm.id,l.title,l.info,m.last_ts,m.cover,m.status,m.slug,m.online_date,s.account';

    /**
     * @param  $query
     * @param  $page
     * @param  $limit
     * @param  $cols
     * @return mixed
     */
    public static function limitRows($query = '', $page = 0, $limit = 12, $cols = '')
    {
        $filter = self::genQuery($query);

        // if (!canDo(self::PV_SOP)) {
        //     $filter['m.insert_user'] = rStaff::_CStaff();
        // }

        $filter['ORDER'] = ['m.online_date' => 'DESC', 'm.insert_ts' => 'DESC'];

        $join = [
            '[>]' . fStaff::fmTbl() . '(s)'     => ['m.insert_user' => 'id'],
            '[>]' . self::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]
        ];

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS), $join);
    }

    /**
     * @param $ids
     * @param $page
     * @param $limit
     * @param $cols
     */
    public static function lotsByID($ids, $page = 0, $limit = 6, $cols = '')
    {
        $filter['m.id'] = $ids;

        $filter['m.status'] = self::ST_PUBLISHED;

        $filter['ORDER'] = ['m.insert_ts' => 'DESC'];

        $join = [
            '[>]' . fStaff::fmTbl() . '(s)'     => ['m.insert_user' => 'id'],
            '[>]' . self::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]
        ];

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS . $cols), $join);
    }

    /**
     * @param $id
     * @param $page
     * @param $limit
     */
    public static function lotsBySearch($id, $page = 0, $limit = 6)
    {
        $presses = mh()->select(fSearch::fmTbl('press') . '(r)', ['r.press_id'], ['r.search_id' => $id]);

        return self::lotsByID(\__::pluck($presses, 'press_id'), $page, $limit);
    }

    /**
     * @param $id
     * @param $page
     * @param $limit
     */
    public static function lotsByAuthor($id, $page = 0, $limit = 6)
    {
        $presses = mh()->select(self::fmTbl('author') . '(r)', ['r.press_id'], ['r.author_id' => $id]);

        return self::lotsByID(\__::pluck($presses, 'press_id'), $page, $limit);
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
        $condition = " WHERE m.`status` = '" . self::ST_PUBLISHED . "' AND online_date <= '" . date('Y-m-d') . "' AND m.`" . $col . "` >= '" . $cu[$col] . "' AND m.`id` != '" . $cu['id'] . "' ";

        $join = ' LEFT JOIN `' . self::fmTbl('lang') . "` l ON l.`parent_id`=m.`id` AND l.`lang` = '" . Module::_lang() . "' ";

        $rows = mh()->query('SELECT m.`id`, m.`slug`, l.`title` FROM `' . self::fmTbl() . '` m ' . $join . ' ' . $condition . ' ORDER BY m.`' . $col . '` ASC, m.`id` DESC  LIMIT 1 ')->fetch(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        } else {
            return ['id' => $rows[0]['id'], 'title' => $rows[0]['title']];
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
        $condition = " WHERE m.`status` = '" . self::ST_PUBLISHED . "' AND online_date <= '" . date('Y-m-d') . "' AND m.`" . $col . "` <= '" . $cu[$col] . "' AND m.`id` != '" . $cu['id'] . "' ";

        $join = ' LEFT JOIN `' . self::fmTbl('lang') . "` l ON l.`parent_id`=m.`id` AND l.`lang` = '" . Module::_lang() . "' ";

        $rows = mh()->query('SELECT m.`id`, m.`slug`, l.`title` FROM `' . self::fmTbl() . '` m ' . $join . ' ' . $condition . ' ORDER BY m.`' . $col . '` DESC, m.`id` DESC LIMIT 1 ')->fetch(\PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return null;
        } else {
            return ['id' => $rows[0]['id'], 'title' => $rows[0]['title']];
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
                ':press' => '%' . $query . '%'
            ]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * save whole form for backend
     * @param array $req
     */
    public static function save($req, $tbl = '')
    {
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
            $rtn = mh()->update(self::fmTbl($tbl), $data, [
                'id' => $req['id']
            ]);

            $rtn = self::chkErr($rtn->rowCount());
        }

        self::saveMeta($req['id'], $other['meta'], true);
        self::saveMany('tag', $req['id'], $other['tags']);
        self::saveLang($req['id'], $other['lang']);
        self::saveMany('author', $req['id'], $authors, false, true);
        self::saveMany('related', $req['id'], $relateds, false, true);

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

        $filter = [
            'r.' . $pk => $pid,
            'm.status' => self::ST_PUBLISHED,
            'ORDER'    => 'r.sorter'
        ];

        return mh()->select(self::fmTbl('related') . '(r)', [
            '[>]' . self::fmTbl('lang') . '(t)' => ['r.related_id' => 'parent_id', 't.lang' => '[SV]' . Module::_lang()],
            '[>]' . self::fmTbl() . '(m)'       => ['r.related_id' => 'id']
        ], ['t.parent_id(id)', 't.title'], $filter);
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsAuthor($pid)
    {
        $pk = 'press_id';
        $fk = 'author_id';

        $filter = [
            'r.' . $pk => $pid,
            'p.status' => fAuthor::ST_ON,
            'ORDER'    => 'r.sorter'
        ];

        return mh()->select(
            self::fmTbl('author') . '(r)',
            [
                '[>]' . fAuthor::fmTbl('lang') . '(t)' => ['r.author_id' => 'parent_id', 't.lang' => '[SV]' . Module::_lang()],
                '[>]' . fAuthor::fmTbl() . '(p)'       => ['r.author_id' => 'id']
            ], ['p.id', 'p.cover', 't.title'], $filter
        );
    }

    public static function filtered_column()
    {
        return ['hh', 'mm'];
    }

    /**
     * @param  $queryStr
     * @return mixed
     */

    /**
     * @param  $queryStr
     * @return mixed
     */
    public static function genQuery($queryStr = '')
    {
        $query = parent::genQuery($queryStr);
        $new = [];

        foreach ($query as $key => $value) {
            if ($key == 'tag') {
                $filter = [
                    'l.title[~]' => $value,
                    'm.status'   => fTag::ST_ON
                ];

                $tag = mh()->get(fTag::fmTbl() . '(m)',
                    ['[><]' . fTag::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                if (!empty($tag)) {
                    $presses = mh()->select(self::fmTbl('tag') . '(r)', ['r.' . self::MTB . '_id'], ['r.tag_id' => $tag['id']]);
                }

                if (!empty($presses)) {
                    $new['m.id'] = \__::pluck($presses, 'press_id');
                } else {
                    $new['m.id'] = -1;
                }
            } else {
                $new[$key] = $value;
            }
        }

        return $new;
    }

    /**
     * @param $type
     * @param $ids
     */
    public static function batchRenew($type, $ids)
    {
        $rows = mh()->select(self::fmTbl($type) . '(r)', ['r.press_id'], ['r.' . $type . '_id' => $ids]);
        if (!empty($rows)) {
            $rtn = mh()->update(self::fmTbl(), [
                'last_user' => rStaff::_CStaff(),
                'status'    => self::ST_SCHEDULED
            ], [
                'id'     => \__::pluck($rows, 'press_id'),
                'status' => self::ST_PUBLISHED
            ]);
        }
    }

    public static function cronjob()
    {
        $data = self::exec('
            SELECT tp.`id`, tp.`status`, tp.`online_date`
            FROM `' . self::fmTbl() . "` AS tp
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
