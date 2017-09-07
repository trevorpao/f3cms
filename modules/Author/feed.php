<?php
namespace F3CMS;
/**
 * data feed
 */
class fAuthor extends Feed
{
    const MTB = "author";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function getAll()
    {

        $result = db()->exec("SELECT a.* FROM `" . self::fmTbl() . "` a ");

        return $result;
    }
}
