<?php

namespace F3CMS;

class rRole extends Reaction
{
    public function do_list($f3, $args)
    {
        kStaff::_chkLogin(); // chkAuth(fRole::PV_R);

        $req   = parent::_getReq();
        $auths = fRole::getAuth();

        $req['page']  = (isset($req['page'])) ? ($req['page'] - 1) : 0;
        $req['limit'] = (!empty($req['limit'])) ? max(min($req['limit'] * 1, 24), 3) : 12;

        $req['query'] = (!isset($req['query'])) ? '' : $req['query'];

        $rtn = fRole::limitRows($req['query'], $req['page'], $req['limit']);

        $rtn['subset'] = \__::map($rtn['subset'], function ($row) use ($auths) {
            $chkPriv = [];
            $priv    = fRole::parseAuth(fRole::parseAuthIdx(fRole::reverseAuth($row['priv'])));

            foreach ($auths as $idx => $val) {
                if (fRole::hasAuth($priv, $idx)) {
                    $chkPriv[] = $val['title'];
                }
            }

            $row['priv'] = implode('<br/>', $chkPriv);

            return $row;
        });

        return self::_return(1, $rtn);
    }

    public static function handleRow($row = [])
    {
        $row['auth'] = implode(',', fRole::parseAuthVal(fRole::reverseAuth($row['priv'])));

        return $row;
    }
}
