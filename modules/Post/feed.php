<?php

namespace F3CMS;

/**
 * data feed
 */
class fPost extends Feed
{
    const MTB    = 'post';
    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    const BE_COLS = 'm.id,l.title,m.status,m.slug,m.cover,m.last_ts';

    public static function suspiciousContentMarkers($pattern, $limit = 50)
    {
        return self::findLangContentMatches((string) $pattern, (int) $limit, false);
    }

    public static function suspiciousXssIndicators($pattern, $limit = 50)
    {
        return self::findLangContentMatches(strtolower((string) $pattern), (int) $limit, true);
    }

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
}
