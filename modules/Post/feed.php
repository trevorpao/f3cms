<?php

namespace F3CMS;

/**
 * data feed
 */
class fPost extends Feed
{
    public const MTB    = 'post';
    public const ST_ON  = 'Enabled';
    public const ST_OFF = 'Disabled';

    public const PV_R = 'use.web.config';
    public const PV_U = 'use.web.config';
    public const PV_D = 'use.web.config';

    public const BE_COLS = 'm.id,l.title,m.status,m.slug,m.cover,m.last_ts';
}
