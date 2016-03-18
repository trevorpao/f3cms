<?php
namespace F3CMS;
/**
 * data feed
 */
class fStaff extends Feed
{
    const MTB = "staff";
    const ST_NEW = "New";
    const ST_VERIFIED = "Verified";
    const ST_FREEZE = "Freeze";

    static function getAll()
    {

        $result = db()->exec("SELECT `id`, `status`, `account` FROM `" . self::fmTbl() . "` ");

        return $result;
    }

    static function _setPsw($str)
    {
        return md5($str);
    }
}
