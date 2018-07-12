<?php
namespace F3CMS;
/**
 * data feed
 */
class fStaff extends Feed
{
    const MTB = 'staff';
    const ST_NEW = 'New';
    const ST_VERIFIED = 'Verified';
    const ST_FREEZE = 'Freeze';

    const PV_R = 'use.web.config';
    const PV_U = 'use.web.config';
    const PV_D = 'use.web.config';

    const BE_COLS = 'id,account,status';

    /**
     * @param $str
     */
    public static function _setPsw($str)
    {
        return md5($str);
    }
}
