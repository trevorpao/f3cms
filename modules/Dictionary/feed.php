<?php

namespace F3CMS;

/**
 * data feed
 */
class fDictionary extends Feed
{
    public const MTB    = 'dictionary';
    public const ST_ON  = 'Enabled';
    public const ST_OFF = 'Disabled';

    /**
     * @return mixed
     */
    public static function getAll()
    {
        $result = self::exec('SELECT a.id, a.title, a.last_ts, a.status, a.slug FROM `' . self::fmTbl() . '` a ');

        return $result;
    }
}
