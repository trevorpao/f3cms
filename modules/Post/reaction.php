<?php

namespace F3CMS;

class rPost extends Reaction
{

    public function __construct()
    {
        parent::__construct();
    }

    function do_app_about_us ($f3, $args)
    {
        $cu = fPost::get_row('/app-about-us', 'slug', " AND `status`='". fPost::ST_ON ."' ");

        if (empty($cu)) {
            $f3->error(404);
        }

        $rtn = array(array('content'=>$cu['content']));

        return parent::_return(1, $rtn);
    }
}
