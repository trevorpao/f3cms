<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Kit boundary for advertisement board loading, cached slot projection, and
 *   content-enriched ad payload shaping around the Adv module.
 *
 * Design Intent
 * - Keep reusable board-loading and ad payload shaping close to the Adv owner module
 *   while preserving the FORKS split: this Kit owns cache-aware board assembly and
 *   content enrichment hooks, while feeds keep advertisement truth and persistence.
 *
 * Strict Constraints
 * - This Kit must not collapse into a generic board helper or absorb content ownership
 *   from Press/Category collaborators.
 * - Cache orchestration and payload shaping may happen here, but canonical ad/resource
 *   truth remains with Adv feeds and related owner modules.
 *
 * Porting Guidelines
 * - Port as the Adv module's owner-side board loading and cache helper layer.
 * - Preserve the split between cache-aware payload assembly here and persisted ad/content
 *   truth in the corresponding feeds.
 */
class kAdv extends Kit
{
    /**
     * Architecture Role
     * - Load and cache advertisement board payloads, enriching slots with owner-side
     *   related content projections when available.
     *
     * Strict Constraints
     * - Board assembly may happen here, but advertisement/resource truth and content
     *   ownership remain with Adv/Press/Category collaborators.
     *
     * Porting Note
     * - Port as the cached board-payload loader for advertisement slots.
     */
    public static function loadAry($key, $ary, $force = 0)
    {
        $fc  = new FCHelper('board');
        $key .= '_' . Module::_lang();
        $cache = $fc->get($key);

        if (1 == $force || empty($cache)) {
            foreach ($ary as $idx => $row) {
                $data = fAdv::getResources($row['pid'], $row['limit'], ' m.position_id, m.`weight` DESC ');

                $data = \__::map($data, function ($cu) {
                    $cu['meta'] = fAdv::lotsMeta($cu['id']);

                    $cu['link'] = '/r/pass?id=' . $cu['id'];
                    unset($cu['uri']);

                    if (!empty($cu['meta']) && !empty($cu['meta']['press_id'])) {
                        $cu['tags']     = fPress::lotsTag($cu['meta']['press_id']);
                        $cu['authors']  = fPress::lotsAuthor($cu['meta']['press_id']);
                        $cu['cate']     = fCategory::onlyColumns(
                            fCategory::one($cu['meta']['cate_id'], 'id',
                                ['status' => fCategory::ST_ON], 0),
                            ['id', 'slug', 'title']
                        );
                    }

                    return $cu;
                });

                $ary[$idx]['data'] = $data;
            }

            $fc->save($key, json_encode($ary));
        } else {
            $ary = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $cache), true);
        }

        return $ary;
    }

    /**
     * Architecture Role
     * - Flush language-scoped board cache entries for a given advertisement key.
     *
     * Strict Constraints
     * - Cache invalidation may happen here, but broader cache policy and ad truth remain
     *   outside this Kit.
     *
     * Porting Note
     * - Port as the advertisement board cache-clear helper.
     */
    public static function clearCache($key)
    {
        $fc         = new FCHelper('board');
        $acceptLang = f3()->get('acceptLang');
        foreach ($acceptLang as $val) {
            $fc->flush($key . '_' . $val);
        }
    }
}
