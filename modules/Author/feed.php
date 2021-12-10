<?php

namespace F3CMS;

/**
 * data feed
 */
class fAuthor extends Feed
{
    public const MTB    = 'author';
    public const ST_ON  = 'Enabled';
    public const ST_OFF = 'Disabled';

    public const PV_R = 'see.other.press';
    public const PV_U = 'see.other.press';
    public const PV_D = 'see.other.press';

    public const BE_COLS = 'm.id,l.title,m.status,m.slug,m.cover,m.last_ts';
}
