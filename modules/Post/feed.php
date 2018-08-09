<?php
namespace F3CMS;

/**
 * data feed
 */
class fPost extends Feed
{
    const MTB = 'post';
    const ST_ON = 'Enabled';
    const ST_OFF = 'Disabled';

    const PV_R = 'use.web.config';
    const PV_U = 'use.web.config';
    const PV_D = 'use.web.config';

    const BE_COLS = 'm.id,l.title,m.status,m.slug,m.cover,m.last_ts';
}
