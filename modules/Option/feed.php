<?php
namespace F3CMS;
/**
 * data feed
 */
class fOption extends Feed
{
    const MTB = "option";
    const COUNTYTB = "county";
    const ZIPCODETB = "zipcode";

    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function get_options()
    {

        $rows = db()->exec("SELECT name, content FROM `" . self::fmTbl() . "` WHERE `status`='" . self::ST_ON . "'");

        $options = array();

        foreach ($rows as $row) {
            $options[$row['name']] = $row['content'];
        }

        return $options;
    }
    /**
     * get one row by name
     *
     * @param int $name - option name
     *
     * @return array
     */
    static function get_option($name)
    {

        $rows = db()->exec("SELECT * FROM `" . self::fmTbl() . "` WHERE `name`=? AND `status`='" . self::ST_ON . "' LIMIT 1 ", $name);

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['content'];
        }
    }

    static function get_counties()
    {

        $rows = db()->exec("SELECT * FROM `" . tpf() . self::COUNTYTB . "` ORDER BY `id`");

        return $rows;
    }

    static function get_zipcodes($county)
    {

        $rows = db()->exec("SELECT `zipcode`, `town`, CONCAT(`county`, `town`) AS `full_name` FROM `" . tpf() . self::ZIPCODETB . "` WHERE `county`= ? ORDER BY `zipcode`", $county);

        return $rows;
    }

    static function getAll()
    {

        $result = db()->exec("SELECT id, name, content FROM `" . self::fmTbl() . "` ");

        return $result;
    }

    static function split($str, $cols = array())
    {
        $rtn = array();
        if (!empty($str)) {
            $ary = explode("\n", $str);
            foreach ($ary as $idx => $value) {
                $tmp = explode(":", $value);
                foreach ($tmp as $k => $v) {
                    if (isset($cols[$k])) {
                        $rtn[$idx][$cols[$k]] = $v;
                    }
                    else {
                        $rtn[$idx][$k] = $v;
                    }
                }
            }
        }
        return $rtn;
    }
}
