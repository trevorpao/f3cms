<?php
namespace F3CMS;

/**
 * data feed
 */
class fDictionary extends Feed
{
    const MTB = 'dictionary';
    const ST_ON = 'Enabled';
    const ST_OFF = 'Disabled';

    /**
     * @return mixed
     */
    public static function getAll()
    {
        $result = self::exec('SELECT a.id, a.title, a.last_ts, a.status, a.slug FROM `' . self::fmTbl() . '` a ');

        return $result;
    }
}
