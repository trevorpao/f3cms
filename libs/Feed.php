<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Feed is the entity-owned persistence base in F3CMS, but it must stay inside the
 *   FORKS boundary of shared persistence runtime. It centralizes main-table, _lang,
 *   _meta, and relation-aware save flows without taking over owner-side request or
 *   business coordination.
 *
 * Design Intent
 * - OntoCMS prioritizes FORKS first, then entity-first semantics inside the correct
 *   owner boundary. Feed exists so modules can reuse stable persistence patterns
 *   without flattening language, metadata, or relation handling into a generic
 *   repository that forgets F3CMS structure or steals module-owned coordination.
 *
 * Strict Constraints
 * - Feed owns entity persistence flow, but it does not own request orchestration,
 *   page rendering, or module-specific duty judgment.
 * - If reuse convenience conflicts with FORKS owner boundary, keep the boundary and
 *   leave owner-side coordination in the module.
 * - Do not move entity-specific coordination into generic helpers only because a
 *   second caller appears; keep ownership with the module when logic depends on
 *   entity truth, writeback policy, or business coordination.
 * - Keep relation, _lang, and _meta handling aligned with the real schema boundary;
 *   do not collapse them into transport-shaped payload storage or convenience JSON.
 *
 * Porting Guidelines
 * - In .NET or Go, map Feed to an entity-owned persistence base or repository layer,
 *   not to a controller/service that also owns request handling.
 * - Preserve the split described in document/guides/sd_conventions.md: schema and
 *   code should mirror each other, and module-owned business coordination should stay
 *   outside shared persistence runtime.
 */

class Feed extends Module
{
    use Belong;

    // Constants defining various configurations for the Feed class.
    const MULTILANG      = 1; // Indicates support for multiple languages.
    const PAGELIMIT      = 12; // Default number of items per page.
    const BE_COLS        = 'm.id'; // Default backend columns.
    const PK_COL         = 'id'; // Primary key column.
    const LANG_ARY_MERGE = 0; // Language array merge mode.
    const LANG_ARY_ALONE = 1; // Language array standalone mode.
    const LANG_ARY_SKIP  = 2; // Language array skip mode.

    const PV_R = 'base.cms'; // Permission value for reading.
    const PV_U = 'base.cms'; // Permission value for updating.
    const PV_D = 'mgr.cms'; // Permission value for deleting.

    const HARD_DEL = 0; // Indicates hard deletion mode.

    /**
     * Architecture Role
     * - Executes the core entity save lifecycle for the owning Feed, including column
     *   normalization, main-table insert/update, and post-save relation/meta/lang hooks.
     *
     * Strict Constraints
     * - This method is a persistence owner path. Request orchestration and business
     *   coordination must stay outside Feed, but the actual row write lifecycle must not
     *   be pulled up into Reaction or generic helpers.
     *
     * Porting Note
     * - Map this to the entity-owned save pipeline in the persistence layer, keeping
     *   post-save relation/meta/lang work in the same ownership boundary.
     */
    public static function save($req, $tbl = '')
    {
        $that               = get_called_class();
        [$data, $other]     = $that::_handleColumn($req);

        $rtn = null;

        if (empty($req['id'])) {
            $data['insert_ts']   = date('Y-m-d H:i:s');
            $data['insert_user'] = fStaff::_current('id');

            mh()->insert($that::fmTbl($tbl), $data);

            $req['id'] = mh()->id();

            $rtn = $that::chkErr($req['id']);
        } else {
            $rtn = mh()->update($that::fmTbl($tbl), $data, [
                'id' => $req['id'],
            ]);

            $rtn = $that::chkErr($rtn->rowCount());
        }

        if (!empty($rtn)) {
            $that::_afterSave($req['id'], $other, $data);

            return $req['id'];
        } else {
            return null;
        }
    }

    /**
     * Architecture Role
     * - Completes Feed-owned subordinate persistence after the main row has been saved,
     *   including `_meta`, tag relations, and `_lang` data.
     *
     * Strict Constraints
     * - This hook keeps subordinate table ownership inside Feed; do not move these
     *   writes into Reaction or replace them with transport-shaped shortcuts.
     *
     * Porting Note
     * - Preserve this as a post-main-row persistence step owned by the entity layer.
     */
    public static function _afterSave($pid, $other, $data = [])
    {
        $that               = get_called_class();
        // self::saveMany('ingredient', $pid, $other['ingredients'], false, true);
        // self::saveMany('related', $pid, $other['relateds'], false, true);

        if (isset($other['meta'])) {
            $that::saveMeta($pid, $other['meta'], true);
        }
        if (isset($other['tags'])) {
            $that::saveMany('tag', $pid, $other['tags'], false, true);
        }
        if (isset($other['lang'])) {
            $that::saveLang($pid, $other['lang']);
        }
    }

    /**
     * Architecture Role
     * - Applies Feed-owned lifecycle status changes to the main entity row.
     *
     * Strict Constraints
     * - This method updates persistence state only; higher-level workflow decisions or
     *   request-side authorization must remain outside this persistence path.
     *
     * Porting Note
     * - Keep publish/status mutation in the entity persistence layer, not in the
     *   request/controller layer.
     */
    public static function published($req, $tbl = '')
    {
        $that = get_called_class();
        $data = [
            'status'    => $req['status'],
            'last_ts'   => date('Y-m-d H:i:s'),
            'last_user' => fStaff::_current('id'),
        ];
        $rtn = null;

        if (isset($req['online_date'])) {
            $data['online_date'] = $req['online_date'];
        }

        $rtn = mh()->update($that::fmTbl($tbl), $data, [
            'id' => $req['id'],
        ]);

        $rtn = self::chkErr($rtn->rowCount());

        return $rtn;
    }

    /**
     * Architecture Role
     * - Splits incoming save payload into main-row columns versus Feed-owned subordinate
     *   structures such as meta, tags, and language rows.
     *
     * Strict Constraints
     * - Column decomposition belongs to Feed because it mirrors schema ownership; do not
     *   flatten these distinctions into a generic request normalizer outside Feed.
     *
     * Porting Note
     * - Preserve this as a schema-aware payload decomposition step close to the entity
     *   persistence layer.
     */
    public static function _handleColumn($req)
    {
        $that  = get_called_class();
        $data  = [];
        $other = [];

        foreach ($req as $key => $value) {
            if ($that::filterColumn($key)) {
                switch ($key) {
                    case 'meta':
                        if (is_array($value)) {
                            foreach ($value as $k => $v) {
                                $other['meta'][$k] = $v;
                            }
                        }
                        break;
                    case 'tags':
                        if (is_array($value)) {
                            foreach ($value as $v) {
                                $other['tags'][] = $v['v'];
                            }
                        } else {
                            $other['tags'] = explode(',', $value);
                        }
                        break;
                    case 'lang':
                        if (is_array($value)) {
                            foreach ($value as $k => $v) {
                                $other['lang'][] = [$k, $v];
                            }
                        }
                        break;
                    case 'slug':
                        $value = (empty($value)) ? $that::renderUniqueNo(16) : $value;
                        // $value = str_replace('//', '/', $value);
                        $data[$key] = parent::_slugify($value);
                        break;
                    case 'pwd':
                        if (!empty($value)) {
                            $value      = $that::_setPsw($value);
                            $data[$key] = $value;
                        }
                        break;
                    case 'online_date':
                        $data[$key] = date('Y-m-d H:i:s', strtotime($value)); // + 7 * 3600);
                        break;
                    case 'id':
                        break;
                    default:
                        $data[$key] = ((is_array($value)) ? json_encode($value) : ((null === $value) ? null : trim($value)));
                        break;
                }
            }
        }

        $data['last_ts']   = date('Y-m-d H:i:s');
        $data['last_user'] = fStaff::_current('id');

        return [$data, $other];
    }

    /**
     * Architecture Role
     * - Performs Feed-owned bulk row retrieval against the main table or a declared
     *   join path while keeping the entity query boundary inside the persistence layer.
     *
     * Strict Constraints
     * - Query execution belongs here; callers may supply filter intent, but they should
     *   not re-implement row retrieval outside Feed.
     *
     * Porting Note
     * - Preserve this as an entity-owned collection read helper over the persistence
     *   adapter or repository.
     */
    public static function lots($condition, $cols = '*', $join = null, $limit = 500, $table = '')
    {
        $that               = get_called_class();
        $condition['LIMIT'] = (empty($condition['LIMIT'])) ? $limit : $condition['LIMIT'];

        if (null == $join) {
            $result = mh()->select($that::fmTbl($table), $cols, $condition);
        } else {
            $result = mh()->select($that::fmTbl($table) . '(m)', $join, $cols, $condition);
        }

        $result = self::chkErr($result);

        if (empty($result)) {
            return [];
        } else {
            return $result;
        }
    }

    /**
     * Architecture Role
     * - Loads tag relations for the current entity through the Feed-owned relation table
     *   path, including language-aware tag display data.
     *
     * Strict Constraints
     * - Tag relation retrieval must stay schema-aware and relation-table based; do not
     *   replace it with denormalized transport shortcuts outside Feed.
     *
     * Porting Note
     * - Keep this as a relation-aware read helper tied to the entity persistence layer.
     */
    public static function lotsTag($pid, $sorter = false)
    {
        $that = get_called_class();

        $pk = $that::MTB . '_id';
        $fk = 'tag_id';

        $filter = [
            'r.' . $pk => $pid,
            't.status' => fTag::ST_ON,
        ];

        if ($sorter) {
            $filter['ORDER'] = 'r.sorter';
        }

        return mh()->select($that::fmTbl('tag') . '(r)',
            ['[>]' . fTag::fmTbl() . '(t)'          => ['r.tag_id' => 'id'],
                '[>]' . fTag::fmTbl('lang') . '(l)' => ['t.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]],
            ['t.id', 't.slug', 'l.title', 't.counter'], $filter);
    }

    /**
     * Architecture Role
     * - Reads `_meta` rows for a specific entity and reshapes them into a key/value map
     *   for Feed-owned subordinate metadata access.
     *
     * Strict Constraints
     * - Metadata retrieval stays subordinate to the entity boundary; do not move `_meta`
     *   ownership into request handlers or unrelated generic helpers.
     *
     * Porting Note
     * - Preserve this as a subordinate-table read helper for optional entity metadata.
     */
    public static function lotsMeta($pid, $key = '')
    {
        $that = get_called_class();

        $filter = ['parent_id' => $pid];

        if ('' != $key) {
            $filter['k[~]'] = $key;
        }

        $result = mh()->select($that::fmTbl('meta'), '*', $filter);

        $rows = [];
        if ($result) {
            foreach ($result as $row) {
                $rows[$row['k']] = $row['v'];
            }
        }

        return $rows;
    }

    /**
     * Architecture Role
     * - Reads `_lang` rows for an entity and normalizes them into the language-shaped
     *   structure expected by Feed callers.
     *
     * Strict Constraints
     * - Language decomposition belongs to Feed because it mirrors schema ownership; do
     *   not flatten multi-language data into the main row boundary for convenience.
     *
     * Porting Note
     * - Preserve this as a language-table read helper that stays aligned with `_lang`
     *   decomposition.
     */
    public static function lotsLang($pid, $lang = '')
    {
        $that   = get_called_class();
        $filter = ['parent_id' => $pid];
        if ('' != $lang) {
            $filter['lang'] = $lang;
        }

        $result   = mh()->select($that::fmTbl('lang'), '*', $filter);
        $filter   = self::default_filtered_column();
        $filter[] = 'parent_id';

        $rows = [];
        foreach (f3()->get('acceptLang') as $n) {
            $rows[$n] = [];
        }

        if (count($result) > 0) {
            if (count($rows) > 1) {
                foreach ($result as $row) {
                    $rows[$row['lang']] = array_filter(
                        $row,
                        function ($key) use ($filter) {
                            return !in_array($key, $filter);
                        },
                        ARRAY_FILTER_USE_KEY
                    );
                }
            } else {
                $rows = array_filter(
                    $result[0],
                    function ($key) use ($filter) {
                        return !in_array($key, $filter);
                    },
                    ARRAY_FILTER_USE_KEY
                );
            }
        }

        return ('' != $lang) ? $rows[$lang] : $rows;
    }

    /**
     * Architecture Role
     * - Persists `_meta` subordinate rows for an entity within the Feed ownership
     *   boundary, optionally replacing selected existing keys.
     *
     * Strict Constraints
     * - `_meta` writes stay part of entity persistence ownership; they must not be
     *   rewritten as ad hoc request-side updates outside Feed.
     *
     * Porting Note
     * - Keep this as a subordinate-table persistence helper for optional entity data.
     */
    public static function saveMeta($pid, $data = [], $replace = false)
    {
        if (empty($data)) {
            return false;
        }

        $that = get_called_class();
        $rows = [];

        if ($replace) {
            mh()->delete($that::fmTbl('meta'), ['parent_id' => $pid, 'k' => array_keys($data)]);
        }

        foreach ($data as $k => $v) {
            if (!empty($v)) {
                $rows[] = [
                    'parent_id' => $pid,
                    'k'         => $k,
                    'v'         => $v,
                ];
            }
        }

        if (!empty($rows)) {
            mh()->insert($that::fmTbl('meta'), $rows);
        }

        return $that::chkErr(1);
    }

    /**
     * Architecture Role
     * - Persists `_lang` subordinate rows for the entity, handling insert/update flow
     *   per accepted language row.
     *
     * Strict Constraints
     * - Multi-language row ownership stays inside Feed; do not hide `_lang` persistence
     *   behind transport-level payload shortcuts or move it into request orchestration.
     *
     * Porting Note
     * - Preserve this as a language-table persistence helper coupled to the entity
     *   persistence layer.
     */
    public static function saveLang($pid, $data = [])
    {
        if (empty($data)) {
            return false;
        }

        $that = get_called_class();

        foreach ($data as $v) {
            if (!empty($v[1])) {
                $filter = [
                    'parent_id' => $pid,
                    'lang'      => $v[0],
                ];

                $v[1]['last_ts']   = date('Y-m-d H:i:s');
                $v[1]['last_user'] = fStaff::_current('id');

                if (mh()->get($that::fmTbl('lang'), ['parent_id'], $filter)) {
                    mh()->update($that::fmTbl('lang'), $v[1], $filter);
                } else {
                    $v[1]['insert_ts']   = date('Y-m-d H:i:s');
                    $v[1]['insert_user'] = fStaff::_current('id');

                    mh()->insert($that::fmTbl('lang'), array_merge($v[1], $filter));
                }
            }
        }

        return self::chkErr(1);
    }

    /**
     * Architecture Role
     * - Returns option-list rows for backend selectors through the Feed-owned entity
     *   read path, including multilingual title lookup when needed.
     *
     * Strict Constraints
     * - Option lookup remains an entity read concern; do not duplicate this as ad hoc
     *   selector SQL in Reaction or other request-level code.
     *
     * Porting Note
     * - Preserve this as a lightweight read helper for option surfaces over the entity
     *   repository/persistence layer.
     */
    public static function getOpts($query = '', $column = 'title')
    {
        $that   = get_called_class();
        $filter = [
            'm.status' => $that::ST_ON,
            'LIMIT'    => 100,
        ];

        if ($that::MULTILANG) {
            if ('' != $query) {
                $filter['l.' . $column . '[~]'] = $query;
            }

            return mh()->select($that::fmTbl() . '(m)',
                ['[><]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]], ['m.id', $column . '(title)'], $filter);
        } else {
            if ('' != $query) {
                $filter['m.' . $column . '[~]'] = $query;
            }

            return mh()->select($that::fmTbl() . '(m)', ['m.id', $column . '(title)'], $filter);
        }
    }

    /**
     * Architecture Role
     * - Loads a single option row for the entity, shaping it for selector-style reads.
     *
     * Strict Constraints
     * - This remains a Feed-owned read helper; do not fork separate single-option read
     *   behavior into the request layer.
     *
     * Porting Note
     * - Keep this as a compact entity read helper over the persistence layer.
     */
    public static function oneOpt($pid)
    {
        $that   = get_called_class();
        $filter = [
            'm.id'  => $pid,
            'ORDER' => ['id' => 'DESC'],
        ];

        if ($that::MULTILANG) {
            $join = [
                '[>]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()],
            ];

            $cu = mh()->get($that::fmTbl() . '(m)', $join, ['m.id', 'l.title'], $filter);
        } else {
            $cu = mh()->get($that::fmTbl() . '(m)', ['m.id', 'm.title'], $filter);
        }

        return (empty($cu)) ? [] : $cu;
    }

    /**
     * Architecture Role
     * - Executes Feed-owned row deletion for the main table or a declared sub-table.
     *
     * Strict Constraints
     * - Row deletion semantics belong to Feed; callers may decide whether deletion is
     *   allowed, but they should not own the delete query itself.
     *
     * Porting Note
     * - Preserve this as an entity persistence delete primitive.
     */
    public static function delRow($pid, $sub_table = '')
    {
        $that = get_called_class();

        $data = mh()->delete($that::fmTbl($sub_table), [
            'id' => $pid,
        ]);

        return $data->rowCount();
    }

    /**
     * Architecture Role
     * - Performs a direct Feed-owned status mutation on the entity row.
     *
     * Strict Constraints
     * - Status writeback belongs to the persistence owner; higher-level workflow or
     *   request authorization decisions must stay outside this method.
     *
     * Porting Note
     * - Keep this as a narrow entity state update helper in the persistence layer.
     */
    public static function changeStatus($pid, $status)
    {
        $that = get_called_class();
        mh()->update($that::fmTbl(), [
            'status' => $status,
        ], [
            'id' => $pid,
        ]);
    }

    /**
     * Architecture Role
     * - Updates the entity row's ordering field through the Feed-owned persistence path.
     *
     * Strict Constraints
     * - Sort order persistence stays in Feed; do not move this write path into request
     *   orchestration just because it is operationally simple.
     *
     * Porting Note
     * - Preserve this as a narrow persistence helper for ordering updates.
     */
    public static function update_sorter($pid, $sorter)
    {
        $that = get_called_class();
        mh()->query(
            'UPDATE `' . $that::fmTbl() . '` SET `sorter`=:sorter WHERE `id`=:id',
            [
                ':sorter' => $sorter,
                ':id'     => $pid,
            ]
        );
    }

    /**
     * Architecture Role
     * - Exposes the canonical paginated backend list read for the entity by combining
     *   Feed-owned filter generation, join definition, and pagination.
     *
     * Strict Constraints
     * - This method defines the standard entity list surface; callers should not rebuild
     *   pagination/filter/join behavior outside Feed.
     *
     * Porting Note
     * - Keep this as the persistence-side list endpoint over query/filter helpers.
     */
    public static function limitRows($query = '', $page = 0, $limit = 0, $cols = '')
    {
        $that = get_called_class();

        $filter = $that::genFilter($query);

        return $that::paginate(
            $that::fmTbl() . '(m)',
            $filter,
            $page,
            $limit,
            explode(',', $that::BE_COLS . $cols),
            $that::genJoin()
        );
    }

    /**
     * Architecture Role
     * - Loads a single entity row and, when configured, expands multilingual data using
     *   the Feed-owned `_lang` read path.
     *
     * Strict Constraints
     * - Single-row truth remains with Feed; request handlers may shape the response, but
     *   they should not re-implement entity lookup or language merge behavior.
     *
     * Porting Note
     * - Preserve this as the canonical single-entity read helper in the persistence
     *   layer, with optional `_lang` expansion rules kept nearby.
     */
    public static function one($val, $col = 'id', $condition = [], $multilang = 1)
    {
        $that = get_called_class();

        $data = mh()->get($that::fmTbl(), '*', array_merge([
            $col    => $val,
            'ORDER' => [$col => 'DESC'],
        ], $condition));

        if (empty($data)) {
            return null;
        } else {
            if ($that::MULTILANG) {
                switch ($multilang) {
                    case $that::LANG_ARY_ALONE:
                        $data['lang'] = $that::lotsLang($data['id']);
                        break;
                    case $that::LANG_ARY_MERGE:
                        $lang = $that::lotsLang($data['id'], parent::_lang());
                        if (is_array($lang)) {
                            $data = array_merge($data, $lang);
                        }
                        break;
                    default:
                        break;
                }
            }

            return $data;
        }
    }

    /**
     * Architecture Role
     * - Computes which requested primary keys are missing from the entity table through
     *   a Feed-owned existence check.
     *
     * Strict Constraints
     * - Missing-ID resolution remains a persistence concern; callers may use the result,
     *   but should not re-implement existence checks outside Feed.
     *
     * Porting Note
     * - Preserve this as a small repository/persistence helper for existence validation.
     */
    public static function notExists($ids = [])
    {
        $that = get_called_class();
        $data = mh()->get($that::fmTbl(), ['ids' => MHelper::raw('GROUP_CONCAT(`id`)')], ['id' => $ids]);

        if (empty($data['ids'])) {
            return $ids;
        } else {
            return array_diff(explode(',', $data['ids']), $ids);
        }
    }

    /**
     * Architecture Role
     * - Loads a paginated entity collection scoped to a known set of IDs using the
     *   standard Feed-owned list surface.
     *
     * Strict Constraints
     * - ID-scoped list retrieval should still reuse Feed pagination/query helpers rather
     *   than spawning special-case collection logic in request handlers.
     *
     * Porting Note
     * - Keep this as an entity collection read helper layered over paginate/genJoin.
     */
    public static function lotsByID($ids, $page = 0, $limit = 6, $cols = '')
    {
        $that = get_called_class();

        $filter['m.id'] = (empty($ids)) ? '' : $ids;

        $filter['m.status'] = $that::ST_ON;

        $filter['ORDER'] = $that::genOrder();

        return $that::paginate($that::fmTbl() . '(m)', $filter, $page, $limit, explode(',', $that::BE_COLS . $cols), $that::genJoin());
    }

    /**
     * Architecture Role
     * - Provides the canonical Feed-owned pagination algorithm over a table/filter/join
     *   combination and returns the normalized subset/total/count envelope.
     *
     * Strict Constraints
     * - Central pagination behavior belongs here; do not fork page counting, limit, or
     *   subset envelope logic across callers.
     *
     * Porting Note
     * - Preserve this as a reusable persistence-layer pagination primitive.
     */
    public static function paginate($tbl, $filter, $page = 0, $limit = 0, $cols = '*', $join = null)
    {
        $that = get_called_class();

        if (isset($filter['ORDER'])) {
            $order = $filter['ORDER'];
            unset($filter['ORDER']);
        }

        if (0 == $limit) {
            $limit = $that::PAGELIMIT;
        }

        $total = $that::_total($tbl, $filter, $join);
        if (0 == $total) {
            $result = [];
            $count  = 0;
            $page   = 0;
        } else {
            if (!empty($order)) {
                $filter['ORDER'] = $order;
            }

            $count = ceil((int) $total / (int) $limit);
            $page  = max(0, min($page, $count - 1));

            $filter['LIMIT'] = [$page * $limit, $limit];

            if (null == $join) {
                $result = mh()->select($tbl, $cols, $filter);
            } else {
                $result = mh()->select($tbl, $join, $cols, $filter);
            }
        }

        return [
            'subset' => $result,
            'total'  => $total,
            'limit'  => $limit,
            'count'  => $count,
            'pos'    => (($page < $count) ? $page : 0),
            'filter' => $filter,
            'sql'    => ((0 === f3()->get('DEBUG')) ? '' : mh()->last()),
        ];
    }

    /**
     * Architecture Role
     * - Calculates the total row count for a Feed-owned query shape, optionally using a
     *   join-aware count expression.
     *
     * Strict Constraints
     * - Count semantics stay with Feed query ownership; callers should not invent their
     *   own incompatible total logic outside this persistence boundary.
     *
     * Porting Note
     * - Keep this as the counting primitive paired with paginate/total.
     */
    public static function _total($tbl, $filter, $join = null, $col = null)
    {
        $that = get_called_class();

        if (null == $col) {
            $col = (null == $join) ? 'COUNT(<' . $that::PK_COL . '>)' : 'COUNT(m.<' . $that::PK_COL . '>)';
        }

        if (null == $join) {
            $total = mh()->get($tbl, ['cnt' => MHelper::raw($col)], $filter);
        } else {
            $total = mh()->get($tbl, $join, ['cnt' => MHelper::raw($col)], $filter);
        }

        $err = mh()->error();

        if (is_array($err) && '00000' != $err[0]) {
            if (0 === f3()->get('DEBUG')) {
                return null;
            } else {
                print_r($err);
                exit;
            }
        }

        return ($total) ? $total['cnt'] * 1 : 0;
    }

    /**
     * Architecture Role
     * - Exposes a simpler entity-oriented total-count helper over `_total()` for the
     *   current Feed table or sub-table.
     *
     * Strict Constraints
     * - Keep total counting aligned with Feed table ownership instead of duplicating raw
     *   count SQL at call sites.
     *
     * Porting Note
     * - Preserve this as the convenience count wrapper for entity totals.
     */
    public static function total($filter, $join = null, $subTbl = '', $col = null)
    {
        $that = get_called_class();
        $tbl  = $that::fmTbl($subTbl) . ((null == $join) ? '' : '(m)');

        return $that::_total($tbl, $filter, $join, $col);
    }

    /**
     * Architecture Role
     * - Executes a raw SQL statement through the Feed boundary when a caller needs a
     *   persistence-side escape hatch.
     *
     * Strict Constraints
     * - This helper is still a Feed-owned persistence path; using it must not justify
     *   moving query ownership out of Feed or bypassing owner boundaries elsewhere.
     *
     * Porting Note
     * - If recreated in another runtime, keep it behind the persistence abstraction and
     *   do not expose raw SQL execution as a request-layer concern.
     */
    public static function exec($query, $map = [], $isSole = false)
    {
        $query = trim($query);
        $res   = self::chkErr(mh()->query($query, $map));

        if (is_object($res)) {
            if (0 === stripos($query, 'SELECT')) {
                $method = ($isSole) ? 'fetch' : 'fetchAll';

                return $res->{$method}(\PDO::FETCH_ASSOC);
            } else {
                return $res->rowCount();
            }
        } else {
            return $res;
        }
    }

    /**
     * Architecture Role
     * - Parses the compact query-string syntax used by Feed into an internal query map
     *   that later filter builders can consume.
     *
     * Strict Constraints
     * - Query grammar parsing belongs near Feed query ownership; do not scatter this
     *   transport/query conversion logic across request handlers.
     *
     * Porting Note
     * - Preserve this as the syntax-to-query-map adapter for the Feed query model.
     */
    public static function genQuery($queryStr = '')
    {
        $that = get_called_class();
        $arr  = explode(',', $queryStr);

        $query = [];

        foreach ($arr as $val) {
            if (!empty($val)) {
                if (!preg_match('/(ORDER|LIMIT)/', $val)) {
                    if (false !== strpos($val, '<>')) {
                        [$k, $v]            = explode('<>', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<>]'] = explode('|', $v);
                    } elseif (false !== strpos($val, '>')) {
                        [$k, $v]           = explode('>', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[>]'] = $v;
                    } elseif (false !== strpos($val, '<')) {
                        [$k, $v]           = explode('<', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<]'] = $v;
                    } elseif (false !== strpos($val, '!~')) {
                        [$k, $v]            = explode('!~', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!~]'] = $v;
                    } elseif (false !== strpos($val, '!')) {
                        [$k, $v]           = explode('!', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!]'] = $v;
                    } elseif (false !== strpos($val, '~')) {
                        [$k, $v]           = explode('~', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[~]'] = $v;
                    } elseif (false !== strpos($val, ':')) {
                        [$k, $v]     = explode(':', $val);
                        $k           = (empty($k)) ? 'all' : $k;
                        $query[$k]   = (false !== strpos($v, '|')) ? explode('|', $v) : (string) $v;
                    } else {
                        $query['all'] = $val;
                    }
                } else {
                    [$k, $v]     = explode(':', $val);
                    $query[$k]   = $v;
                }
            }
        }

        $new = [];

        foreach ($query as $key => $value) {
            switch ($key) {
                case 'tag':
                    if (is_array($value)) {
                        $filter = [
                            'm.status' => fTag::ST_ON,
                        ];

                        $rows = $that::exec('SELECT t1.`artwork_id` FROM `tbl_artwork_tag` t1
                                INNER JOIN `tbl_artwork_tag` t2 ON t2.artwork_id = t1.`artwork_id` AND t2.`tag_id` = \'' . $value[1] . '\'
                                INNER JOIN `tbl_tag` t ON t.id = t1.`tag_id`
                                WHERE t1.`tag_id` = \'' . $value[0] . '\' ');
                    } else {
                        if (is_numeric($value)) {
                            $filter = [
                                'l.parent_id' => $value,
                                'm.status'    => fTag::ST_ON,
                            ];
                        } else {
                            $filter = [
                                'l.title[~]' => $value,
                                'm.status'   => fTag::ST_ON,
                            ];
                        }

                        $tag = mh()->get(fTag::fmTbl() . '(m)',
                            ['[><]' . fTag::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                        if (!empty($tag)) {
                            $rows = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $tag['id']]);
                        }
                    }

                    if (!empty($rows)) {
                        $new['m.id'] = \__::pluck($rows, $that::MTB . '_id');
                    } else {
                        $new['m.id'] = -1;
                    }
                    break;
                case 'author':
                    if (is_numeric($value)) {
                        $filter = [
                            'l.parent_id' => $value,
                            'm.status'    => fAuthor::ST_ON,
                        ];
                    } else {
                        $filter = [
                            'l.title[~]' => $value,
                            'm.status'   => fAuthor::ST_ON,
                        ];
                    }

                    $author = mh()->get(fAuthor::fmTbl() . '(m)',
                        ['[><]' . fAuthor::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                    if (!empty($author)) {
                        $rows = mh()->select($that::fmTbl('author') . '(r)', ['r.' . $that::MTB . '_id'], ['r.author_id' => $author['id']]);
                    }

                    if (!empty($rows)) {
                        $new['m.id'] = \__::pluck($rows, $that::MTB . '_id');
                    } else {
                        $new['m.id'] = -1;
                    }
                    break;
                case 'ORDER':
                    if (false !== strpos($value, '!')) {
                        $value = str_replace('!', '', $value);
                        $tmp   = [$value => 'DESC'];
                    } else {
                        $tmp = [$value => 'ASC'];
                    }
                    $new[$key] = $tmp;
                    break;
                default:
                    $new[$key] = $value;
                    break;
            }
        }

        return $new;
    }

    /**
     * Architecture Role
     * - Supplies the default ordering contract for Feed-owned collection queries when the
     *   caller does not override ordering explicitly.
     *
     * Strict Constraints
     * - Default list ordering stays inside Feed query ownership so callers do not fork
     *   incompatible sort defaults across request or module layers.
     *
     * Porting Note
     * - Preserve this as the owner-side default sort policy for collection reads.
     */
    public static function genOrder()
    {
        return ['m.insert_ts' => 'DESC'];
    }

    /**
     * Architecture Role
     * - Builds the standard Feed join graph required for entity reads, especially the
     *   language-aware join used by multilingual tables.
     *
     * Strict Constraints
     * - Join ownership remains with Feed; callers should request data, not assemble the
     *   entity join topology themselves.
     *
     * Porting Note
     * - Keep this as the canonical entity-read join builder for the Feed model.
     */
    public static function genJoin()
    {
        $that = get_called_class();
        $join = null;

        if ($that::MULTILANG) {
            $join = ['[>]' . $that::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id', 'l.lang' => '[SV]' . Module::_lang()]];
        }

        return $join;
    }

    /**
     * Architecture Role
     * - Normalizes external query input into the adjusted filter map consumed by Feed's
     *   list and total query paths.
     *
     * Strict Constraints
     * - Query normalization belongs at the Feed boundary; request handlers should not
     *   duplicate this translation into persistence filters.
     *
     * Porting Note
     * - Preserve this as the entry point that bridges transport query input into Feed's
     *   internal filter model.
     */
    public static function genFilter($query = '')
    {
        $that = get_called_class();

        if (is_string($query)) {
            $filter = $that::adjustFilter($that::_handleQuery($query));
        } elseif (is_array($query)) {
            $filter = $that::adjustFilter($query);
        } else {
            $filter = [];
        }

        return $filter;
    }

    /**
     * Architecture Role
     * - Parses HTML-escaped query-string fragments into the raw query-map format that
     *   `adjustFilter()` can refine.
     *
     * Strict Constraints
     * - Escaped transport parsing stays near Feed query ownership instead of leaking into
     *   controller/request code paths.
     *
     * Porting Note
     * - Keep this as the escaped-query adapter when legacy transport syntax must still be
     *   supported in a new runtime.
     */
    public static function _handleQuery($queryStr = '')
    {
        $that = get_called_class();
        $ary  = explode(',', $queryStr);

        $query = [];

        foreach ($ary as $val) {
            if (!empty($val)) {
                if (!preg_match('/(ORDER|LIMIT)/', $val)) {
                    if (false !== strpos($val, '&lt;&gt;')) {
                        [$k, $v]            = explode('&lt;&gt;', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<>]'] = explode('|', $v);
                    } elseif (false !== strpos($val, '&gt;')) {
                        [$k, $v]           = explode('&gt;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[>]'] = $v;
                    } elseif (false !== strpos($val, '&lt;')) {
                        [$k, $v]           = explode('&lt;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<]'] = $v;
                    } elseif (false !== strpos($val, '!~')) {
                        [$k, $v]            = explode('!~', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!~]'] = $v;
                    } elseif (false !== strpos($val, '!')) {
                        [$k, $v]           = explode('!', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[!]'] = $v;
                    } elseif (false !== strpos($val, '~')) {
                        [$k, $v]           = explode('~', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[~]'] = $v;
                    } elseif (false !== strpos($val, ':')) {
                        [$k, $v]     = explode(':', $val);
                        $k           = (empty($k)) ? 'all' : $k;
                        $query[$k]   = (false !== strpos($v, '|')) ? explode('|', $v) : (string) $v;
                    } elseif (false !== strpos($val, '&#60;&#62;')) {
                        [$k, $v]            = explode('&#60;&#62;', $val);
                        $k                  = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<>]'] = explode('|', $v);
                    } elseif (false !== strpos($val, '&#62;')) {
                        [$k, $v]           = explode('&#62;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[>]'] = $v;
                    } elseif (false !== strpos($val, '&#60;')) {
                        [$k, $v]           = explode('&#60;', $val);
                        $k                 = (empty($k)) ? 'all' : $k;
                        $query[$k . '[<]'] = $v;
                    } else {
                        $query['all'] = $val;
                    }
                } else {
                    [$k, $v]     = explode(':', $val);
                    $query[$k]   = $v;
                }
            }
        }

        return $query;
    }

    /**
     * Architecture Role
     * - Refines a raw Feed filter map into the canonical query filter, including default
     *   ordering and tag-to-entity resolution.
     *
     * Strict Constraints
     * - Filter enrichment and relation-based expansion are Feed-owned persistence rules;
     *   do not spread these mutations across request or module call sites.
     *
     * Porting Note
     * - Preserve this as the final filter-normalization step before executing Feed reads.
     */
    public static function adjustFilter($oldFilter = [])
    {
        $that = get_called_class();

        if (array_key_exists('ORDER', $oldFilter)) {
            if (is_string($oldFilter['ORDER'])) {
                $ary = explode('|', $oldFilter['ORDER']);
                $tmp = [];
                foreach ($ary as $val) {
                    if (false !== strpos($val, '!')) {
                        $val   = str_replace('!', '', $val);
                        $tmp   = [$val => 'DESC'];
                    } else {
                        $tmp = [$val => 'ASC'];
                    }
                }
                $oldFilter['ORDER'] = $tmp;
            }
        } else {
            $oldFilter['ORDER'] = $that::genOrder();
        }

        if (array_key_exists('tag', $oldFilter)) {
            $value = $oldFilter['tag'];
            unset($oldFilter['tag']);

            if (is_array($value)) {
                $filter = [
                    'm.status' => fTag::ST_ON,
                ];

                $rows = $that::exec('SELECT t1.`' . $that::MTB . '_id` FROM `tbl_' . $that::MTB . '_tag` t1
                        INNER JOIN `tbl_' . $that::MTB . '_tag` t2
                            ON t2.`' . $that::MTB . '_id` = t1.`' . $that::MTB . '_id`
                            AND t2.`tag_id` = \'' . $value[1] . '\'
                        INNER JOIN `tbl_tag` t ON t.id = t1.`tag_id`
                        WHERE t1.`tag_id` = \'' . $value[0] . '\' ');
            } else {
                if (is_numeric($value)) {
                    $filter = [
                        'l.parent_id' => $value,
                        'm.status'    => fTag::ST_ON,
                    ];
                } else {
                    $filter = [
                        'l.title[~]' => $value,
                        'm.status'   => fTag::ST_ON,
                    ];
                }

                $tag = mh()->get(fTag::fmTbl() . '(m)',
                    ['[><]' . fTag::fmTbl('lang') . '(l)' => ['m.id' => 'parent_id']], ['m.id'], $filter);

                if (!empty($tag)) {
                    $rows = mh()->select($that::fmTbl('tag') . '(r)', ['r.' . $that::MTB . '_id'], ['r.tag_id' => $tag['id']]);
                }
            }

            if (!empty($rows)) {
                $oldFilter['m.id'] = \__::pluck($rows, $that::MTB . '_id');
            } else {
                $oldFilter['m.id'] = -1;
            }
        }

        return $oldFilter;
    }

    /**
     * Architecture Role
     * - Applies a constrained single-column update through the Feed boundary while still
     *   enforcing the class's filtered-column red lines.
     *
     * Strict Constraints
     * - Even narrow field writes stay under Feed ownership; callers must not bypass
     *   protected columns by issuing ad hoc updates outside this guard.
     *
     * Porting Note
     * - Keep this as a guarded field-update helper rather than exposing raw partial-write
     *   access directly to request handlers.
     */
    public static function saveCol($req, $table = '', $pk = 'id')
    {
        $that = get_called_class();

        $security_check = (
            in_array($req['col'], array_merge(self::default_filtered_column(), $that::filtered_column()))
            || $req['col'] == $pk
        );

        if ($security_check) {
            return false;
        }

        $rtn = mh()->update($that::fmTbl($table), [
            $req['col'] => $req['val'],
        ], [
            $pk => $req['pid'],
        ]);

        return $rtn->rowCount();
    }

    /**
     * Architecture Role
     * - Reduces a row payload to a caller-approved column subset without changing the
     *   underlying Feed ownership of the row data.
     *
     * Strict Constraints
     * - Column slicing is a formatting/helper concern inside Feed; it should not turn
     *   into a second source of truth for field-visibility rules.
     *
     * Porting Note
     * - Preserve this as a simple row-shaping helper for owner-approved field subsets.
     */
    public static function onlyColumns($row, $filter)
    {
        return array_filter(
            $row,
            function ($key) use ($filter) {
                return in_array($key, $filter);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Architecture Role
     * - Decides whether a field is writable/readable through the public Feed surface by
     *   combining default and entity-specific filtered-column rules.
     *
     * Strict Constraints
     * - Field exposure rules remain Feed-owned policy; callers should consult this guard
     *   instead of hardcoding their own allow/deny lists.
     *
     * Porting Note
     * - Keep this as the shared field-policy gate for public Feed column operations.
     */
    public static function filterColumn($column)
    {
        $that = get_called_class();

        return !in_array($column, array_merge(self::default_filtered_column(), $that::filtered_column()));
    }

    /**
     * Architecture Role
     * - Provides the entity-specific extension point for additional filtered columns on
     *   top of the Feed baseline protected-field set.
     *
     * Strict Constraints
     * - Per-entity field restrictions should extend this owner-side hook rather than
     *   being scattered through generic helpers or request code.
     *
     * Porting Note
     * - Preserve this as an override seam for entity-owned protected-field policy.
     */
    public static function filtered_column()
    {
        return [];
    }

    /**
     * Architecture Role
     * - Normalizes SQL syntax differences for the current database driver while keeping
     *   query-shape ownership inside Feed.
     *
     * Strict Constraints
     * - Driver-specific SQL formatting remains a persistence helper; callers should not
     *   fork backend-specific SQL quoting rules outside Feed.
     *
     * Porting Note
     * - Preserve this as the thin SQL-dialect adapter used by Feed-generated queries.
     */
    public static function format($sql)
    {
        return ('sqlsrv' == f3()->get('db_type')) ? preg_replace('/`([\w]+)`/m', '[$1]', $sql) : $sql;
    }

    /**
     * Architecture Role
     * - Produces the database-specific limit/offset clause used by Feed collection reads.
     *
     * Strict Constraints
     * - Pagination SQL differences should stay inside Feed helpers rather than leaking
     *   into request or module code paths.
     *
     * Porting Note
     * - Keep this as the persistence-side pagination clause adapter for target runtimes.
     */
    public static function limit($offset = 0, $limit = 10)
    {
        return ('sqlsrv' == f3()->get('db_type')) ? ' OFFSET ' . $offset . ' ROWS FETCH NEXT ' . $limit . ' ROWS ONLY ' : ' LIMIT ' . $offset . ', ' . $limit;
    }

    /**
     * Architecture Role
     * - Acts as the entity-owned save-policy extension hook that subclasses can override
     *   before or during Feed save handling.
     *
     * Strict Constraints
     * - Save policy customization belongs to the owning entity class through Feed
     *   inheritance, not by pushing ad hoc save decisions into generic request layers.
     *
     * Porting Note
     * - Preserve this as an override seam for entity-specific save policy.
     */
    public static function handleSave($req)
    {
        return 1;
    }

    /**
     * Architecture Role
     * - Defines the baseline protected columns that every Feed entity should keep out of
     *   generic public field operations.
     *
     * Strict Constraints
     * - Baseline field-protection policy stays centralized here so all entities inherit
     *   the same core red lines.
     *
     * Porting Note
     * - Keep this as the shared default protected-field list for Feed descendants.
     */
    public static function default_filtered_column()
    {
        return ['id', 'last_ts', 'last_user', 'insert_ts', 'insert_user'];
    }

    /**
     * Architecture Role
     * - Resolves the canonical table name for the owning Feed entity and its subordinate
     *   tables.
     *
     * Strict Constraints
     * - Table naming rules remain Feed-owned metadata; callers should not rebuild these
     *   names manually across the codebase.
     *
     * Porting Note
     * - Preserve this as the central table-name resolver for entity persistence mapping.
     */
    public static function fmTbl($sub_table = '')
    {
        $that = get_called_class();

        return tpf() . $that::MTB . (('' != $sub_table) ? '_' . $sub_table : '');
    }

    /**
     * Architecture Role
     * - Normalizes database error handling for Feed persistence operations and gates how
     *   SQL errors surface in debug versus production modes.
     *
     * Strict Constraints
     * - SQL error inspection and logging remain inside the persistence boundary; do not
     *   duplicate ad hoc DB error branching in callers.
     *
     * Porting Note
     * - Keep this as the common DB-error adapter for Feed-level persistence execution.
     */
    final public static function chkErr($rtn = 1)
    {
        $err = mh()->error();

        // array (
        //     ODBC 3.x SQLSTATE
        //     Driver-specific error code.
        //     Driver-specific error message.
        // )

        if (is_array($err) && (!empty($err[1]) || !empty($err[2]))) {
            $logger = new \Log('sql_error.log');
            $logger->write($err[2]);
            $logger->write(mh()->last());

            if (0 === f3()->get('DEBUG')) {
                return null;
            } else {
                $err['query'] = mh()->last();
                print_r($err);
                exit;
            }
        } else {
            return $rtn;
        }
    }

    /**
     * Architecture Role
     * - Sanitizes incoming primary-key input into a numeric array before Feed uses it in
     *   persistence operations.
     *
     * Strict Constraints
     * - Identifier sanitization for Feed-owned persistence paths belongs here rather than
     *   being inconsistently reimplemented in request handlers.
     *
     * Porting Note
     * - Preserve this as the primary-key normalization helper for Feed calls.
     */
    public static function safePKAry($pid)
    {
        if (is_array($pid)) {
            $pid = array_filter($pid, function ($v) {
                return is_numeric($v);
            });
        } else {
            $pid = [intval($pid)];
        }

        return $pid;
    }

    /**
     * Architecture Role
     * - Sanitizes incoming slug input into the canonical slug array consumed by Feed
     *   lookup and persistence helpers.
     *
     * Strict Constraints
     * - Slug normalization remains an owner-side persistence helper; callers should not
     *   diverge from Feed's canonical slug sanitation rules.
     *
     * Porting Note
     * - Keep this as the slug normalization helper for Feed-backed entities.
     */
    public static function safeSlugAry($slugs)
    {
        if (is_array($slugs)) {
            $slugs = array_filter($slugs, function ($v) {
                return parent::_slugify($v);
            });
        } else {
            $slugs = [parent::_slugify($slugs)];
        }

        return $slugs;
    }

    /**
     * Architecture Role
     * - Generates a Feed-compatible unique token fragment for entity-owned serial or code
     *   allocation flows.
     *
     * Strict Constraints
     * - Random identifier generation stays as a utility under Feed ownership when the
     *   resulting value is part of entity persistence policy.
     *
     * Porting Note
     * - Preserve this as the shared secure-random helper for Feed-level unique strings.
     */
    final public static function renderUniqueNo($length = 6, $chars = '3456789ACDFGHJKLMNPQRSTWXY')
    {
        return secure_random_string($length, $chars);
    }

    /**
     * Architecture Role
     * - Encapsulates Feed's password-hashing policy for entities that store credentials.
     *
     * Strict Constraints
     * - Credential hashing policy should stay centralized instead of letting individual
     *   callers choose divergent hashing behavior.
     *
     * Porting Note
     * - Keep this as the password-hash adapter when credential-bearing entities are
     *   ported to another runtime.
     */
    final public static function _setPsw($str)
    {
        return password_hash($str, PASSWORD_BCRYPT, [
            'cost' => 10,
        ]);
    }

    /**
     * Architecture Role
     * - Verifies a credential against stored hash state and performs the legacy-hash
     *   upgrade path when the owning entity still carries older password formats.
     *
     * Strict Constraints
     * - Hash verification and upgrade policy remain centralized here so credential
     *   migration does not drift across callers.
     *
     * Porting Note
     * - Preserve both verification and legacy-upgrade semantics if credential migration
     *   still matters in the target runtime.
     */
    final public static function _chkPsw($str, $hash, $memberID = 0)
    {
        if (0 != $memberID && strlen($hash) < 40) {
            if ($hash == md5($str)) {
                $that = get_called_class();

                $that::saveCol([
                    'col' => 'pwd',
                    'val' => $that::_setPsw($str),
                    'pid' => $memberID,
                ]);

                return true;
            } else {
                return false;
            }
        }

        return password_verify($str, $hash);
    }

    /**
     * Architecture Role
     * - Generates a secure opaque token for Feed-owned credential, reset, or API-token
     *   style flows.
     *
     * Strict Constraints
     * - Token generation policy should stay centralized rather than letting callers
     *   invent weaker or inconsistent token semantics.
     *
     * Porting Note
     * - Keep this as the shared secure token generator for Feed descendants.
     */
    final public static function _genToken()
    {
        return secure_random_string(64);
    }
}
