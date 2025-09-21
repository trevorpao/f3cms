<?php

namespace F3CMS;

class rOption extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_list($f3, $args)
    {
        chkAuth(fOption::PV_R);

        $req = parent::_getReq();

        if (fStaff::_current('lang')) {
            f3()->set('acceptLang', \__::pluck(fStaff::_current('lang'), 'key'));
        }

        if (empty($req['query'])) {
            $req['query'] = [];
        }

        $req['page'] = (isset($req['page'])) ? ($req['page'] - 1) : 0;

        $rtn    = fOption::limitRows($req['query'], $req['page'], 200);

        $rtn['subset'] = array_reduce($rtn['subset'], function ($carry, $row) {
            // 初始化分组中的 'title'
            if (!isset($carry[$row['group']]['title'])) {
                $carry[$row['group']]['title'] = $row['group'];
            }
            // 添加行到 'rows'
            $carry[$row['group']]['rows'][] = $row;

            return $carry;
        }, []);

        return parent::_return(1, $rtn);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_get_group($f3, $args)
    {
        kStaff::_chkLogin();

        $req = parent::_getReq();

        $opts = fOption::limitRows('m.group~' . $req['group'], 1, 10);
        $rtn  = [];

        foreach ($opts['subset'] as &$row) {
            $rtn[] = ['id' => $row['name'], 'title' => $row['content']];
        }

        return parent::_return(1, $rtn);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_get_counties($f3, $args)
    {
        $req = self::_getReq();

        if (!isset($req['query'])) {
            $req['query'] = '';
        }

        return self::_return(1, fOption::listCounties($req['query']));
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_zipcodes($f3, $args)
    {
        $req = self::_getReq();

        if (!isset($req['county'])) {
            $req['county'] = '';
        }

        return self::_return(1, fOption::loadZipcodes($req['county']));
    }

    public function do_categories($f3, $args)
    {
        $fc  = new FCHelper('categories');
        $rtn = $fc->get('categories', 30); // 1 mins

        if (empty($rtn)) {
            $rtn = fWork::categories();
            $fc->save('categories', jsonEncode($rtn));
        } else {
            $rtn = jsonDecode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
        }

        return self::_return(1, $rtn);
    }
}
