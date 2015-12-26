<?php

namespace F3CMS;

/**
 * React any request
 */
class rAdv extends Reaction
{
    function do_click($f3, $args)
    {
        $row = fAdv::get_row(f3()->get('GET.id'));

        if ($row == null) {
            f3()->error(404);
        }

        fAdv::save_col(array(
            'pid' => $row['id'],
            'col_name' => 'counter',
            'val' => $row['counter'] + 1,
        ));

        f3()->reroute($row['uri']);
    }

    static function handleRow($row = array())
    {
        $row['positions'] = array_values(fAdv::getPositions());
        return $row;
    }
}
