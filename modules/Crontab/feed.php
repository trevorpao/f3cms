<?php

namespace F3CMS;

class fCrontab extends Feed
{
    const MTB    = 'crontab';
    const ST_ON  = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'mgr.site';
    const PV_U = 'mgr.site';
    const PV_D = 'mgr.site';

    public static function many($freq, $tally)
    {
        return mh()->select(self::fmTbl() . '(m)', '*', [
            'm.status' => self::ST_ON,
            'm.freq'   => $freq,
            'm.tally'  => $tally,
            'ORDER'    => ['id' => 'ASC'],
        ]);
    }
}
