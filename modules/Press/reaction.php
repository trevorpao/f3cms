<?php

namespace F3CMS;

class rPress extends Reaction
{
    public static function handleRow($row = array())
    {
        $row['tags'] = fPress::lotsTag($row['id']);
        $row['authors'] = fPress::lotsSub('author', $row['id']);
        $row['relateds'] = fPress::lotsRelated($row['id']);
        $row['meta'] = []; // fPress::lotsMeta($row['id']);

        $ts = strtotime($row['online_date']);
        $ts = $ts - $ts % 300; // deduct the seconds that is not a multiple of 5 minutes

        $row['hh'] = date('H', $ts);
        $row['mm'] = date('i', $ts);
        $row['online_date'] = date('Y-m-d', $ts);

        // read history file
        // $fc = new FCHelper('press');
        $row['history'] = []; // $fc->getLog('press_' . $row['id']);
        $row['status_publish'] = $row['status'];

        return $row;
    }
}
