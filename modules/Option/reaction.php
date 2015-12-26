<?php

namespace F3CMS;

class rOption extends Backend
{

    const MTB = "options";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function get_options ()
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT name, content FROM `". $f3->get('tpf') . self::MTB ."` WHERE `status`='". self::ST_ON ."'"
        );

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
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` WHERE `name`=? AND `status`='". self::ST_ON ."' LIMIT 1 ", $name
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0]['content'];
        }
    }

    function do_list_all($f3, $args)
    {
        rUser::_chkLogin();

        $rows = $this->_db->exec("SELECT id, name, content FROM `". $f3->get('tpf') . self::MTB ."` ");

        return parent::_return(1, $rows);
    }
}
