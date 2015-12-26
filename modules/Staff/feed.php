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

        $result = db()->exec("SELECT * FROM `" . self::fmTbl() . "` ");

        return $result;
    }
    /**
     * get a row
     *
     * @param string $string - condition
     *
     * @return array
     */
    static function get_row($string, $type = 'id', $condition = '')
    {

        switch ($type) {
            case 'account':
                $condition = " WHERE `account`=? ";
                break;

            default:
                $condition = " WHERE `id`=? ";
                break;
        }

        $rows = db()->exec("SELECT * FROM `" . self::fmTbl() . "` " . $condition . " LIMIT 1 ", $string);

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0];
        }
    }

    static function _setPsw($str)
    {
        return md5($str);
    }
}
