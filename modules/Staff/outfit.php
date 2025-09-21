<?php

namespace F3CMS;

/**
 * for render page
 */
class oStaff extends Outfit
{
    /**
     * @param $args
     */
    public static function verify($args)
    {
        if (empty($args['code'])) {
            f3()->error(404);
        }

        $cu = fStaff::one($args['code'], 'verify_code');

        if (empty($cu)) {
            f3()->error(404);
        }

        fStaff::changeStatus($cu['id'], fStaff::ST_VERIFIED);

        $role = fRole::one($cu['role_id'], 'id');

        if (empty($role)) {
            f3()->error(404);
        }

        fStaff::_setCurrent($cu['account'], $cu['id'], $cu['email'], '', $role['priv'], $role['menu_id']);

        f3()->reroute(f3()->get('uri') . '/backend/board/stats/simple/mine/' . $cu['id']);
    }
}
