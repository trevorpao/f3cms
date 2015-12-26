<?php

namespace F3CMS;

class rAdv extends Backend
{

    const MTB = "advs";
    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    function do_click($f3, $args)
    {

        $obj = new \DB\SQL\Mapper($this->_db, $f3->get('tpf') . self::MTB ."");

        $obj->load(array('id=?', $f3->get('GET.id')));

        if (empty($obj->uri)) {
            $f3->error(404);
        }

        $obj->counter = $obj->counter + 1;

        $obj->save();

        $f3->reroute($obj->uri);
    }

    function do_list_all($f3, $args)
    {
        rUser::_chkLogin();

        $result = $this->_db->exec("SELECT a.id, a.title, a.position_id, a.end_date, a.counter, a.last_ts FROM `".$f3->get('tpf') . self::MTB ."` a ");

        foreach ($result as &$row) {
            $row['position'] = self::getPositions()[$row['position_id']]['title'];
        }

        return parent::_return(1, $result);
    }

    static function getPositions()
    {
        return array(
            '1' => array('id' => '1', 'title' => '首頁/腰帶大圖(wild*282)'),
            '2' => array('id' => '2', 'title' => '首頁/達人堂(455*365)'),
        );
    }

    static function getAdvs($position_id, $limit = 10)
    {
        $f3 = \Base::instance();

        $condition = " WHERE `position_id` = '". $position_id ."' AND `status` = '". self::ST_ON ."' ";
        $condition .= " AND `end_date` > '". date('Y-m-d') ."' ";

        $result = $f3->get('DB')->exec(
            "SELECT `id`, `title`, `status`, `pic`, `uri`, `background`, `summary` FROM `". $f3->get('tpf') . self::MTB .
            "` ". $condition ."  ORDER BY rand() LIMIT ". $limit
        );

        return (1 === $limit && !empty($result)) ? $result[0] : $result;
    }

    static function handleRow($row = array())
    {
        $row['positions'] = array_values(self::getPositions());
        return $row;
    }
}
