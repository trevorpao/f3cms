<?php

namespace F3CMS;

/**
 * data feed
 */
class fStats extends Feed
{
    const MTB       = 'stats';
    const MULTILANG = 0;

    /**
     * @param $queryStr
     *
     * @return mixed
     */
    public static function _handleQuery($queryStr = '')
    {
        $query = parent::_handleQuery($queryStr);
        $new   = [];

        foreach ($query as $key => $value) {
            if ('insert_ts' == $key && '28daysAgo' == $value) {
                $new[$key . '[<>]'] = [date('Y-m-d', strtotime('-28 days')), date('Y-m-d')];
            } else {
                $new[$key] = $value;
            }
        }

        return $new;
    }
}
