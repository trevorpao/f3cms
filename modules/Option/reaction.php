<?php

namespace F3CMS;

class rOption extends Reaction
{

    function do_list ($f3, $args)
    {
        $req = parent::_getReq();

        $req['page'] = ($req['page']) ? ($req['page'] -1) : 1;

        $req['query'] = explode(',', $req['query']);

        $query = array();

        // TODO: handle ALL, start from !,~

        foreach ($req['query'] as $val) {
            if (!empty($val)) {
                if (strpos($val, '<>')) {
                    list($k, $v) = explode('<>', $val);
                    $query[$k .'[<>]'] = explode('|', $v);
                } else if (strpos($val, '>')) {
                    list($k, $v) = explode('>', $val);
                    $query[$k .'[>]'] = $v;
                } else if (strpos($val, '<')) {
                    list($k, $v) = explode('<', $val);
                    $query[$k .'[<]'] = $v;
                } else if (strpos($val, '!')) {
                    list($k, $v) = explode('!', $val);
                    $query[$k .'[!]'] = $v;
                } else if (strpos($val, '~')) {
                    list($k, $v) = explode('~', $val);
                    $query[$k .'[~]'] = $v;
                } else {
                    list($k, $v) = explode(':', $val);
                    $query[$k] = $v;
                }
            }
        }

        $rtn = fOption::limitRows($query, $req['page'], 10);

        foreach ($rtn['subset'] as &$row) {
        }

        return parent::_return(1, $rtn);
    }

    function do_get_zipcodes($f3, $args)
    {

        $zipcodes = fOption::load_zipcodes(f3()->get('POST')['query']);

        $rtn = array();
        $rtn['html'] = '<option value="">請選擇</option>';
        foreach ($zipcodes as $value) {
            $rtn['html'] .= '<option value="'. $value['town'] .' '. $value['zipcode'] .'">'. $value['town'] .' '. $value['zipcode'] .'</option>';
        }

        return parent::_return(1, $rtn);
    }

}
