<?php

namespace F3CMS;

/**
 * data feed
 */
class fPress extends Feed
{
    const MTB        = 'press';
    const CATEGORYTB = 'tag';

    const ST_DRAFT     = 'Draft';
    const ST_PUBLISHED = 'Published';
    const ST_SCHEDULED = 'Scheduled';
    const ST_CHANGED   = 'Changed';
    const ST_OFFLINED  = 'Offlined';

    const BE_COLS = 'm.id,l.title,l.info,m.last_ts,m.cover,m.status,m.slug,m.online_date,s.account,cl.title(category),c.slug(category_slug)';

    public static function genOrder()
    {
        return ['m.sorter' => 'ASC', 'm.online_date' => 'DESC', 'm.insert_ts' => 'DESC'];
    }

    public static function genJoin()
    {
        return [
            '[>]' . fStaff::fmTbl() . '(s)'           => ['m.insert_user' => 'id'],
            '[>]' . self::fmTbl('lang') . '(l)'       => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()],
            '[>]' . fCategory::fmTbl('lang') . '(cl)' => ['m.cate_id' => 'parent_id', 'cl.lang' => '[SV]' . Module::_lang()],
            '[>]' . fCategory::fmTbl() . '(c)'        => ['m.cate_id' => 'id', 'c.status' => '[SV]' . fCategory::ST_ON],
        ];
    }

    /**
     * @param $ids
     * @param $page
     * @param $limit
     * @param $cols
     */
    public static function lotsByID($ids, $page = 0, $limit = 6, $cols = '')
    {
        if (!empty($ids)) {
            $filter['m.id'] = $ids;
        } else {
            $filter['m.id'] = -1;
        }

        $filter['m.status'] = [self::ST_PUBLISHED, self::ST_CHANGED];

        $filter['ORDER'] = self::genOrder();

        return self::paginate(self::fmTbl() . '(m)', $filter, $page, $limit, explode(',', self::BE_COLS . $cols), self::genJoin());
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
     * @param int $cu - current press
     *
     * @return string
     */
    public static function neighbor($cu, $type = 'next')
    {
        $filter = [
            'm.status'  => [self::ST_PUBLISHED, self::ST_CHANGED],
            'm.cate_id' => $cu['cate_id'],
            'm.id[!]'   => $cu['id'],
            'ORDER'     => self::genOrder(),
        ];

        if ('next' == $type) {
            $filter['m.sorter[<=]']      = $cu['sorter'];
            $filter['m.online_date[>=]'] = $cu['online_date'];
            $filter['ORDER']             = array_map(fn ($order) => 'ASC' === strtoupper($order) ? 'DESC' : 'ASC', $filter['ORDER']);
        } else {
            $filter['m.sorter[>=]']      = $cu['sorter'];
            $filter['m.online_date[<=]'] = $cu['online_date'];
        }

        $row = mh()->get(self::fmTbl() . '(m)', self::genJoin(), [
            'm.id', 'm.cover', 'm.slug', 'l.title',
        ], $filter);

        if (empty($row)) {
            return null;
        } else {
            return $row;
        }
    }

    public static function relatedTag($press_id, $tags, $limit = 5)
    {
        return self::exec('SELECT `m`.`id`,`l`.`title`,`l`.`info`,`m`.`slug`,`m`.`cover`,`cl`.`title` AS `category`
            , COUNT(`t`.`press_id`) AS `cnt` FROM `' . self::fmTbl('tag') . '` AS `t`
            INNER JOIN `' . self::fmTbl() . '` AS `m` ON `t`.`press_id` = `m`.`id` AND `t`.`tag_id` IN (' . implode(', ', self::safePKAry($tags)) . ')
                AND `t`.`press_id` != :press_id AND `m`.`status` IN (\'' . self::ST_PUBLISHED . '\', \'' . self::ST_CHANGED . '\')
            INNER JOIN `' . self::fmTbl('lang') . '` AS `l` ON `t`.`press_id` = `l`.`parent_id` AND `l`.`lang` = \'' . Module::_lang() . '\'
            LEFT JOIN `' . fCategory::fmTbl('lang') . '` AS `cl` ON `m`.`cate_id` = `cl`.`parent_id` AND `cl`.`lang` = \'' . Module::_lang() . '\'
            WHERE 1 GROUP BY `t`.`press_id`, `l`.`title`, `l`.`info` ORDER BY `cnt` DESC LIMIT :limit ;', [
            ':press_id' => $press_id,
            ':limit'    => $limit,
        ]);
    }

    /**
     * @param $query
     */
    public static function get_opts($query = '', $column = 'title')
    {
        return mh()->query(
            'SELECT `p`.`id`, CONCAT(\' (\', `s`.`title`, \')\', `p`.`title`) AS `title` FROM `' . self::fmTbl() . '` AS `p` ' .
            'WHERE `p`.`title` LIKE :press ORDER BY p.`id` DESC LIMIT 100',
            [
                ':press' => '%' . $query . '%',
            ]
        )->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function fromDraft($pid, $lang, $data)
    {
        $old = mh()->get(self::fmTbl('lang'), ['id'], [
            'parent_id' => $pid,
            'lang'      => $lang,
        ]);

        if (empty($old)) {
            mh()->insert(self::fmTbl('lang'), [
                'parent_id' => $pid,
                'lang'      => $lang,
            ]);
        }

        $updates = [
            'from_ai' => 'Yes',
        ];

        if (!empty($data['title'])) {
            $updates['title'] = $data['title'];
        }

        if (!empty($data['info'])) {
            $updates['info'] = $data['info'];
        }

        if (!empty($data['content'])) {
            $updates['content'] = $data['content'];
        }

        $data = mh()->update(self::fmTbl('lang'), $updates, [
            'parent_id' => $pid,
            'lang'      => $lang,
        ]);

        return parent::chkErr($data->rowCount());
    }

    public static function emptyI18nContent($pid, $lang)
    {
        $data = mh()->update(self::fmTbl('lang'), [
            'content' => '',
        ], [
            'parent_id' => $pid,
            'lang'      => $lang,
            'from_ai'   => 'Yes',
        ]);

        return parent::chkErr($data->rowCount());
    }

    /**
     * save whole form for backend
     *
     * @param array $req
     */
    public static function save($req, $tbl = '')
    {
        $authors = explode(',', $req['authors']);
        unset($req['authors']);

        unset($req['authors-role']);

        $relateds = explode(',', $req['relateds']);
        unset($req['relateds']);

        $terms = explode(',', $req['terms']);
        unset($req['terms']);

        if (isset($req['books'])) {
            $books = explode(',', $req['books']);
            unset($req['books']);
        } else {
            $books = [];
        }

        unset($req['status']); // can't change status from this func
        unset($req['online_date']);

        [$data, $other] = self::_handleColumn($req);

        $other['relateds'] = $relateds;
        $other['authors']  = $authors;
        $other['terms']    = $terms;
        $other['books']    = $books;

        $rtn = null;

        if (empty($req['id'])) {
            $data['insert_ts']   = date('Y-m-d H:i:s');
            $data['insert_user'] = fStaff::_current('id');

            mh()->insert(self::fmTbl($tbl), $data);

            $req['id'] = mh()->id();

            $rtn = self::chkErr($req['id']);
        } else {
            $rtn = mh()->update(self::fmTbl($tbl), $data, [
                'id' => $req['id'],
            ]);

            $rtn = self::chkErr($rtn->rowCount());
        }

        if (!empty($rtn)) {
            self::_afterSave($req['id'], $other, $data);

            return $req['id'];
        } else {
            return null;
        }
    }

    /**
     * @param $req
     */
    public static function _handleColumn($req)
    {
        [$data, $other] = parent::_handleColumn($req);

        if (0 == $data['sorter']) {
            $data['sorter'] = 99;
        }

        return [$data, $other];
    }

    /**
     * @param $req
     *
     * @return int
     */
    public static function _afterSave($pid, $other, $data = [])
    {
        self::saveMany('author', $pid, $other['authors'], false, true);
        self::saveMany('term', $pid, $other['terms'], false, true);
        self::saveMany('related', $pid, $other['relateds'], false, true);

        if (isset($other['books'])) {
            self::saveMany('book', $pid, $other['books'], false, true);
        }

        if (isset($other['tags'])) {
            foreach ($other['tags'] as $tag) {
                fTag::setPressCnt($tag);
                usleep(200);
            }
        }

        return parent::_afterSave($pid, $other);
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
            'ORDER'    => 'r.sorter',
        ];

        return mh()->select(self::fmTbl('related') . '(r)', [
            '[>]' . self::fmTbl('lang') . '(t)'       => ['r.related_id' => 'parent_id', 't.lang' => '[SV]' . Module::_lang()],
            '[>]' . self::fmTbl() . '(m)'             => ['r.related_id' => 'id'],
            '[>]' . fCategory::fmTbl('lang') . '(cl)' => ['m.cate_id' => 'parent_id', 'cl.lang' => '[SV]' . Module::_lang()],
            '[>]' . fCategory::fmTbl() . '(c)'        => ['m.cate_id' => 'id', 'c.status' => '[SV]' . fCategory::ST_ON],
        ], ['t.parent_id(id)', 't.title', 'm.slug', 'm.cover', 'm.online_date', 'cl.title(category)', 'c.slug(category_slug)'], $filter);
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
            'ORDER'    => 'r.sorter',
        ];

        return mh()->select(
            self::fmTbl('author') . '(r)',
            [
                '[>]' . fAuthor::fmTbl('lang') . '(t)' => ['r.author_id' => 'parent_id', 't.lang' => '[SV]' . Module::_lang()],
                '[>]' . fAuthor::fmTbl() . '(p)'       => ['r.author_id' => 'id'],
            ], ['p.id', 'p.slug', 'p.cover', 't.title', 't.slogan', 't.jobtitle', 't.summary'], $filter
        );
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsTerm($pid)
    {
        $filter = [
            'r.press_id' => $pid,
            'p.status'   => fDictionary::ST_ON,
            'ORDER'      => 'r.sorter',
            'GROUP'      => 'p.id',
        ];

        return mh()->select(
            self::fmTbl('term') . '(r)',
            [
                '[>]' . fDictionary::fmTbl('lang') . '(t)' => ['r.term_id' => 'parent_id', 't.lang' => '[SV]' . Module::_lang()],
                '[>]' . fDictionary::fmTbl() . '(p)'       => ['r.term_id' => 'id'],
            ], ['p.id', 'p.slug', 'p.cover', 't.title', 't.alias', 't.summary'], $filter
        );
    }

    /**
     * @param $pid
     * @param $reverse
     */
    public static function lotsBook($pid)
    {
        $filter = [
            'r.press_id' => $pid,
            'p.status'   => fBook::ST_ON,
            'ORDER'      => 'r.sorter',
            'GROUP'      => 'p.id',
        ];

        return mh()->select(
            self::fmTbl('book') . '(r)',
            [
                '[>]' . fBook::fmTbl('lang') . '(t)'  => ['r.book_id' => 'parent_id', 't.lang' => '[SV]' . Module::_lang()],
                '[>]' . fBook::fmTbl() . '(p)'        => ['r.book_id' => 'id'],
                '[>]' . fGenus::fmTbl() . '(g)'       => ['p.cate_id' => 'id'],
            ], ['p.id', 'p.uri', 'p.cover', 't.title', 't.subtitle', 't.summary', 'g.name(genus)'], $filter
        );
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function adjustFilter($oldFilter = [])
    {
        $oldFilter = parent::adjustFilter($oldFilter);

        if (array_key_exists('tag', $oldFilter)) {
            if (is_string($oldFilter['tag'])) {
                $filter = [
                    'l.title[~]' => $oldFilter['tag'],
                    'm.status'   => fTag::ST_ON,
                ];
            } else {
                $filter = [
                    'm.id'       => $oldFilter['tag'],
                    'm.status'   => fTag::ST_ON,
                ];
            }

            $tag = mh()->get(fTag::fmTbl() . '(m)',
                ['[><]' . fTag::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

            if (!empty($tag)) {
                $presses = mh()->select(self::fmTbl('tag') . '(r)', ['r.' . self::MTB . '_id'], ['r.tag_id' => $tag['id']]);
            }

            if (!empty($presses)) {
                if (!empty($oldFilter['m.id'])) {
                    $oldFilter['m.id'] = array_merge($oldFilter['m.id'], \__::pluck($presses, 'press_id'));
                } else {
                    $oldFilter['m.id'] = \__::pluck($presses, 'press_id');
                }
            } else {
                $oldFilter['m.id'] = -1;
            }

            unset($oldFilter['tag']);
        }

        if (array_key_exists('author', $oldFilter)) {
            if (is_numeric($oldFilter['author'])) {
                $filter = [
                    'm.id'       => $oldFilter['author'],
                    'm.status'   => fAuthor::ST_ON,
                ];
            } else {
                $filter = [
                    'l.title[~]' => $oldFilter['author'],
                    'm.status'   => fAuthor::ST_ON,
                ];
            }

            $author = mh()->get(fAuthor::fmTbl() . '(m)',
                ['[><]' . fAuthor::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

            if (!empty($author)) {
                $presses = mh()->select(self::fmTbl('author') . '(r)', ['r.' . self::MTB . '_id'], ['r.author_id' => $author['id']]);
            }

            if (!empty($presses)) {
                if (!empty($oldFilter['m.id'])) {
                    $oldFilter['m.id'] = array_merge($oldFilter['m.id'], \__::pluck($presses, 'press_id'));
                } else {
                    $oldFilter['m.id'] = \__::pluck($presses, 'press_id');
                }
            } else {
                $oldFilter['m.id'] = -1;
            }

            unset($oldFilter['author']);
        }

        return $oldFilter;
    }

    /**
     * @param $query
     */
    public static function getOpts($query = '', $column = 'title')
    {
        $filter = [
            'm.status' => self::ST_PUBLISHED,
            'LIMIT'    => 100,
        ];

        if ('' != $query) {
            $filter['l.' . $column . '[~]'] = $query;
        }

        return mh()->select(self::fmTbl() . '(m)',
            ['[><]' . self::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]], ['m.id', $column . '(title)'], $filter);
    }

    public static function filtered_column()
    {
        return ['hh', 'mm'];
    }

    /**
     * @param $queryStr
     *
     * @return mixed
     */

    /**
     * @param $type
     * @param $ids
     */
    public static function batchRenew($type, $ids)
    {
        $rows = mh()->select(self::fmTbl($type) . '(r)', ['r.press_id'], ['r.' . $type . '_id' => $ids]);
        if (!empty($rows)) {
            $rtn = mh()->update(self::fmTbl(), [
                'last_user' => fStaff::_current(),
                'status'    => self::ST_SCHEDULED,
            ], [
                'id'     => \__::pluck($rows, 'press_id'),
                'status' => self::ST_PUBLISHED,
            ]);
        }
    }

    public static function cronjob()
    {
        mh(true)->info();

        $data = self::exec('
            SELECT tp.`id`, tp.`status`, tp.`online_date`
            FROM `' . self::fmTbl() . "` AS tp
            WHERE tp.`status` = 'Scheduled'
                AND tp.`online_date` < NOW() LIMIT 200;
        ");

        \__::map($data, function ($p) {
            $p['status'] = 'Published';

            fPress::published($p);

            oPress::buildPage(['slug' => $p['id']]);

            // $fc = new FCHelper('press');
            // $fc->ifHistory = 1;

            // $fc->save('press_' . parent::_lang() . '_' . $p['id'], oPress::_render($p['id']));
            usleep(300000); // 0.3s
        });
    }
}
