<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side content Feed for press entities, covering editorial persistence,
 *   publish-state transitions, relation writeback, and content retrieval surfaces
 *   that stay specific to the Press module.
 *
 * Design Intent
 * - Keep content lifecycle ownership with the Press module even when it reuses the
 *   shared Feed base, so workflow trace logging, related-content mapping, author/tag
 *   relations, and editorial retrieval rules are not flattened into generic helpers.
 *
 * Strict Constraints
 * - This Feed may extend shared persistence behavior, but it must not push
 *   module-owned editorial rules, workflow trace semantics, or relation truth into
 *   unrelated shared libs just for reuse convenience.
 * - Press-specific relation writeback, content scanning, and retrieval shaping must
 *   stay aligned with the Press entity boundary rather than move into Reaction,
 *   transport helpers, or generic search utilities.
 *
 * Porting Guidelines
 * - Port as the Press module's entity-owned persistence/feed boundary.
 * - Preserve the split between shared Feed mechanics and Press-owned content,
 *   workflow-log, relation, and retrieval semantics.
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

    /**
     * Architecture Role
     * - Expose a Press-owned scan surface for suspicious content markers in localized
     *   article bodies.
     *
     * Strict Constraints
     * - This is a diagnostic read helper only; detection policy may evolve, but the
     *   scan must remain scoped to Press content rather than becoming a generic site
     *   moderation service.
     *
     * Porting Note
     * - Port as a module-owned diagnostic query over press_lang content.
     */
    public static function suspiciousContentMarkers($pattern, $limit = 50)
    {
        return self::findLangContentMatches((string) $pattern, (int) $limit, false);
    }

    /**
     * Architecture Role
     * - Provide the Press module's XSS-oriented content scan entry over localized
     *   article bodies.
     *
     * Strict Constraints
     * - Keep this helper focused on module-owned diagnostics; do not treat it as a
     *   replacement for shared request sanitization or session-security layers.
     *
     * Porting Note
     * - Port as a Press-specific suspicious-content scan with lowercase matching.
     */
    public static function suspiciousXssIndicators($pattern, $limit = 50)
    {
        return self::findLangContentMatches(strtolower((string) $pattern), (int) $limit, true);
    }

    /**
     * Architecture Role
     * - Define the canonical backend/default ordering contract for press listings.
     *
     * Strict Constraints
     * - Ordering semantics belong to the Press feed boundary; do not duplicate them in
     *   controllers or generic list helpers.
     *
     * Porting Note
     * - Preserve sorter, online_date, then insert_ts precedence for press reads.
     */
    public static function genOrder()
    {
        return ['m.sorter' => 'ASC', 'm.online_date' => 'DESC', 'm.insert_ts' => 'DESC'];
    }

    /**
     * Architecture Role
     * - Execute the shared query primitive behind Press content-marker scans.
     *
     * Strict Constraints
     * - Keep raw content probing inside the Press feed boundary and avoid turning this
     *   helper into a cross-module text-mining abstraction.
     *
     * Porting Note
     * - Port as a bounded press_lang regexp query with safe fallback on failure.
     */
    private static function findLangContentMatches($pattern, $limit, $lowercase = false)
    {
        $contentExpr = $lowercase ? 'LOWER(`content`)' : '`content`';

        try {
            $rows = mh()->query(
                'SELECT `id`, `parent_id`, LEFT(`content`, 120) AS `snippet` FROM `' . self::fmTbl('lang') . '` WHERE ' . $contentExpr . ' REGEXP :pattern ORDER BY `id` DESC LIMIT ' . (int) $limit,
                [':pattern' => $pattern]
            )->fetchAll(\PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Architecture Role
     * - Declare the join topology for Press reads, including authoring staff and
     *   category-localization projections.
     *
     * Strict Constraints
     * - Join ownership stays with the Press feed because it mirrors module retrieval
     *   shape; do not rebuild this topology ad hoc in request handlers.
     *
     * Porting Note
     * - Port as the default projection graph for press backend/frontend list reads.
     */
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
     * Architecture Role
     * - Read a paginated Press collection constrained to an explicit set of entity IDs.
     *
     * Strict Constraints
     * - This method preserves Press-owned publication filtering and projection shape;
     *   callers must not bypass it with duplicated list logic when the read still
     *   depends on Press entity semantics.
     *
     * Porting Note
     * - Port as an ID-scoped Press listing query that always enforces published/changed
     *   visibility rules.
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
     * Architecture Role
     * - Resolve a single Press entity only when it is already in a public/published
     *   lifecycle state.
     *
     * Strict Constraints
     * - Published-state gating remains a Feed concern here; do not leak this lifecycle
     *   rule into generic callers as ad hoc condition building.
     *
     * Porting Note
     * - Port as a published-only single-entity lookup wrapper over the shared Feed read.
     */
    public static function onePublished($val, $col = 'id', $multilang = 0)
    {
        return self::one($val, $col, [
            'status' => [self::ST_PUBLISHED, self::ST_CHANGED],
        ], $multilang);
    }

    /**
     * Architecture Role
     * - Extract Press IDs from a specific relation table so higher-level relation reads
     *   can reuse one module-owned primitive.
     *
     * Strict Constraints
     * - Keep relation-key translation scoped to Press relation tables; do not widen it
     *   into a generic relation broker that hides ownership.
     *
     * Porting Note
     * - Port as a small relation-row-to-press-id helper local to the Press module.
     */
    private static function pluckPressIds($relationTable, $relationColumn, $id)
    {
        $presses = mh()->select($relationTable . '(r)', ['r.press_id'], ['r.' . $relationColumn => $id]);

        return \__::pluck($presses, 'press_id');
    }

    /**
     * Architecture Role
     * - Compose relation-driven Press listings from a relation table and relation key.
     *
     * Strict Constraints
     * - This helper stays inside the Press feed because it still depends on Press list
     *   ordering, status filtering, and projection rules.
     *
     * Porting Note
     * - Port as a relation-based listing wrapper that reuses Press ID extraction.
     */
    private static function lotsByRelation($relationTable, $relationColumn, $id, $page = 0, $limit = 6, $cols = '')
    {
        return self::lotsByID(self::pluckPressIds(self::fmTbl($relationTable), $relationColumn, $id), $page, $limit, $cols);
    }

    /**
     * Architecture Role
     * - Read Press entities related to a search record through the module-owned search
     *   relation table.
     *
     * Strict Constraints
     * - Keep search-to-press resolution in the Press boundary rather than having search
     *   callers reconstruct Press publication rules themselves.
     *
     * Porting Note
     * - Port as a search relation listing over the Press feed.
     */
    public static function lotsBySearch($id, $page = 0, $limit = 6)
    {
        return self::lotsByID(self::pluckPressIds(fSearch::fmTbl('press'), 'search_id', $id), $page, $limit);
    }

    /**
     * Architecture Role
     * - Read published Press entities linked to an author relation.
     *
     * Strict Constraints
     * - Author relation traversal remains Press-owned retrieval logic, not a generic
     *   author module concern.
     *
     * Porting Note
     * - Port as a Press listing by author relation.
     */
    public static function lotsByAuthor($id, $page = 0, $limit = 6)
    {
        return self::lotsByRelation('author', 'author_id', $id, $page, $limit);
    }

    /**
     * Architecture Role
     * - Read published Press entities linked to a tag relation.
     *
     * Strict Constraints
     * - Tag-to-press listing semantics belong to Press retrieval ownership; do not
     *   relocate them into generic tag helpers.
     *
     * Porting Note
     * - Port as a Press listing by tag relation with the standard Press projection.
     */
    public static function lotsByTag($id, $page = 0, $limit = 6, $cols = '')
    {
        return self::lotsByRelation('tag', 'tag_id', $id, $page, $limit, $cols);
    }

    /**
     * Architecture Role
     * - Commit the Press publish-state transition together with its workflow trace log
     *   inside one owner-side transaction.
     *
     * Strict Constraints
     * - Transaction ownership stays at the Feed boundary. Do not split workflow trace
     *   logging and Press status mutation across separate generic services.
     *
     * Porting Note
     * - Port as a single transactional publish-and-log command owned by the Press feed.
     */
    public static function publishWithWorkflowTrace($req, $trace = null)
    {
        mh()->begin();

        try {
            if (!empty($trace)) {
                mh()->insert(self::fmTbl('log'), [
                    'parent_id' => $trace['parent_id'],
                    'action_code' => $trace['action_code'],
                    'old_state_code' => $trace['old_state_code'],
                    'new_state_code' => $trace['new_state_code'],
                    'insert_user' => $trace['insert_user'],
                ]);
            }

            $published = self::published($req);
            if (empty($published)) {
                throw new \RuntimeException('Press status update failed.');
            }

            mh()->commit();

            return $published;
        } catch (\Throwable $e) {
            mh()->rollback();
            throw $e;
        }
    }

    /**
     * Architecture Role
     * - Resolve the neighboring published Press entity within the same category and
     *   editorial ordering window.
     *
     * Strict Constraints
     * - Neighbor lookup is a Press content-navigation rule, not a reusable generic
     *   paginator. Keep category and ordering semantics with the module.
     *
     * Porting Note
     * - Port as a module-owned next/previous content lookup over published press rows.
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

    /**
     * Architecture Role
     * - Retrieve related Press content by overlapping tag relations and rank it by tag
     *   overlap count.
     *
     * Strict Constraints
     * - This relevance rule is Press-owned content retrieval logic; do not demote it to
     *   a generic relation helper that loses publication and localization semantics.
     *
     * Porting Note
     * - Port as a Press-specific related-content query keyed by tag overlap.
     */
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
     * Architecture Role
     * - Provide the lightweight backend option lookup used for selecting Press entities.
     *
     * Strict Constraints
     * - Keep this legacy option lookup local to Press selection UX; do not treat it as
     *   the canonical public retrieval API.
     *
     * Porting Note
     * - Port as a narrow id-title option query for Press administration flows.
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

    /**
     * Architecture Role
     * - Write AI-generated draft content into the localized Press content row.
     *
     * Strict Constraints
     * - Draft-import ownership stays with Press localized content persistence; do not
     *   push this writeback into generic AI adapters or shared content helpers.
     *
     * Porting Note
     * - Port as a module-owned upsert for AI-originated press_lang fields.
     */
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

    /**
     * Architecture Role
     * - Clear AI-generated localized Press content for a specific language row.
     *
     * Strict Constraints
     * - This reset only applies to Press AI-imported content; keep that ownership local
     *   instead of moving it into generic i18n maintenance utilities.
     *
     * Porting Note
     * - Port as a localized content reset scoped to AI-sourced press_lang rows.
     */
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
     * Architecture Role
     * - Execute the Press-owned save lifecycle for backend editorial forms, including
     *   relation payload decomposition and post-save writeback.
     *
     * Strict Constraints
     * - This is the core Press persistence path. Editorial relation ownership must stay
     *   here and in `_afterSave`, not in request handlers or generic save adapters.
     *
     * Porting Note
     * - Port as the Press form-save pipeline layered on top of the shared Feed base.
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
     * Architecture Role
     * - Normalize incoming Press save payload while preserving module-specific defaults
     *   such as editorial sorter behavior.
     *
     * Strict Constraints
     * - Keep Press-specific column shaping here instead of widening the shared Feed base
     *   with module-owned editorial defaults.
     *
     * Porting Note
     * - Port as a Press-specific payload-normalization extension over shared Feed logic.
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
     * Architecture Role
     * - Persist Press-owned subordinate relations after the main row save completes.
     *
     * Strict Constraints
     * - Author, term, related-content, book, and tag-count writeback are Press entity
     *   responsibilities; do not move them into generic relation dispatchers.
     *
     * Porting Note
     * - Port as the Press post-save relation persistence and count-refresh hook.
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
     * Architecture Role
     * - Read Press entities explicitly related to the current Press row.
     *
     * Strict Constraints
     * - Related-content retrieval is a Press editorial surface; keep relation semantics,
     *   publication filters, and projection shape here.
     *
     * Porting Note
     * - Port as a module-owned related-press listing query.
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
     * Architecture Role
     * - Read author entities attached to a Press row for editorial display surfaces.
     *
     * Strict Constraints
     * - Keep author projection for press content within the Press feed rather than
     *   spreading it across view helpers.
     *
     * Porting Note
     * - Port as a Press-to-author relation listing.
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
     * Architecture Role
     * - Read dictionary/term entities attached to a Press row.
     *
     * Strict Constraints
     * - Term relation reads remain part of Press content ownership and must not be
     *   flattened into generic relation traversal.
     *
     * Porting Note
     * - Port as a Press-to-term relation listing with dictionary visibility rules.
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
     * Architecture Role
     * - Read book entities attached to a Press row for content cross-linking surfaces.
     *
     * Strict Constraints
     * - Book linkage remains a Press-owned relation read, even if the referenced entity
     *   lives in another module.
     *
     * Porting Note
     * - Port as a Press-to-book relation listing with genus projection.
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
     * Architecture Role
     * - Enrich the shared Feed filter map with Press-specific tag and author lookup
     *   semantics before list queries run.
     *
     * Strict Constraints
     * - Filter enrichment remains module-owned because it encodes Press relation truth.
     *   Do not relocate this logic into shared query parsers.
     *
     * Porting Note
     * - Port as a Press-specific filter-normalization extension.
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
     * Architecture Role
     * - Provide the canonical published-only option lookup used by Press selectors.
     *
     * Strict Constraints
     * - Keep published visibility and localized title lookup in the Press feed rather
     *   than duplicating them in callers.
     *
     * Porting Note
     * - Port as a lightweight published press option query.
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

    /**
     * Architecture Role
     * - Rewrite legacy CDN-backed images inside localized Press content through the
     *   Press kit's content migration helper.
     *
     * Strict Constraints
     * - This is a Press content-maintenance path, not a generic media-migration engine.
     *   Keep ownership of logging and content replacement local to the module.
     *
     * Porting Note
     * - Port as a bounded maintenance batch over press_lang HTML/content fields.
     */
    public static function normalizeCdnImages(int $limit = 100)
    {
        $rows = mh()->select(self::fmTbl('lang'), ['id', 'content'], [
            'content[~]' => 'googleusercontent',
            'LIMIT'      => $limit,
        ]);

        if (empty($rows)) {
            return 0;
        }

        $updated = 0;
        $logger  = new \Log('dl_img.log');

        foreach ($rows as $row) {
            $result = kPress::handleCDNImages($row['content']);
            $newContent = $result['content'];

            if (!empty($result['errors'])) {
                $logger->write(json_encode([
                    'lang_id' => $row['id'],
                    'urls'    => $result['errors'],
                ], JSON_UNESCAPED_SLASHES));
            }

            if ($newContent === $row['content']) {
                continue;
            }

            $result = mh()->update(self::fmTbl('lang'), [
                'content' => $newContent,
            ], [
                'id' => $row['id'],
            ]);

            if ($result && method_exists($result, 'rowCount')) {
                $updated += $result->rowCount();
            }
        }

        return $updated;
    }

    /**
     * Architecture Role
     * - Extend the shared protected-column contract with Press-specific excluded fields.
     *
     * Strict Constraints
     * - Only module-owned column exceptions belong here; do not widen this list into a
     *   generic transport policy bucket.
     *
     * Porting Note
     * - Port as Press-specific field-policy metadata.
     */
    public static function filtered_column()
    {
        return ['hh', 'mm'];
    }

    /**
     * Architecture Role
     * - Mark published Press rows for renewal when a linked relation entity changes.
     *
     * Strict Constraints
     * - This batch side effect is Press lifecycle ownership. Do not push relation-driven
     *   renewal policy into generic relation libraries.
     *
     * Porting Note
     * - Port as a relation-triggered status refresh for affected Press rows.
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

    /**
     * Architecture Role
     * - Execute the scheduled publish cron that advances due Press rows and rebuilds
     *   their rendered output.
     *
     * Strict Constraints
     * - This operational batch belongs to the Press module's lifecycle boundary; keep
     *   publish transition and page regeneration coupled here instead of splitting them
     *   across unrelated schedulers.
     *
     * Porting Note
     * - Port as a bounded scheduled-publish worker for Press content.
     */
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
