<?php

namespace F3CMS;

class rOption extends Reaction
{

    function do_list ($f3, $args)
    {
        $req = parent::_getReq();

        $req['page'] = ($req['page']) ? $req['page'] : 1;

        $rtn = fOption::limitRows('', ($req['page'] -1), 2);

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
