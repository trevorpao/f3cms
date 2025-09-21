<?php

namespace F3CMS;

/**
 * React any request
 */
class rGenus extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_get_opts($f3, $args)
    {
        kStaff::_chkLogin();

        $req   = self::_getReq();
        $query = '';

        if (!empty($req['group'])) {
            $condition = 'm.group';
            $query     = $req['group'];
        }

        if (!empty($req['query'])) {
            $condition = 'm.name';
            $query     = $req['query'];
        }

        $opts = fGenus::getOpts($query, $condition);

        return parent::_return(1, $opts);
    }
}
