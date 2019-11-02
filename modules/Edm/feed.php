<?php
namespace F3CMS;

/**
 * data feed
 */
class fEdm extends Feed
{
    const MTB = 'edm';
    const ST_ON = 'Enabled';
    const ST_OFF = 'Disabled';

    const BE_COLS = 'm.id,l.title,m.status,m.slug,m.cover,m.vol_num,m.last_ts';

    /**
     * @param $query
     */
    public static function get_opts($query)
    {
        $condition = ' WHERE l.`title` like ? ';

        return db()->exec(
            'SELECT a.id, l.`title` FROM `' . self::fmTbl() . '` a
            LEFT JOIN `' . self::fmTbl('lang') . "` l ON l.`parent_id`=a.id AND `lang` = '" . f3()->get('defaultLang') . "' " . $condition . ' ORDER BY `vol_num` LIMIT 30 ',
            '%' . $query . '%'
        );
    }
}
