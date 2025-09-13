<?php

namespace F3CMS;

/**
 * for render page
 */
class oAdv extends Outfit
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_pass($f3, $args)
    {
        $row = fAdv::one((int) f3()->get('GET.id'), 'id', ['status' => fAdv::ST_ON]);
        // when task is expired, link still can be reroute!!
        if (null == $row) {
            f3()->error(404);
        }

        fAdv::addCounter($row['id']);
        f3()->reroute($row['uri']);
    }
}
