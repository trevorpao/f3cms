<?php

namespace F3CMS;

/**
 * React any request
 */
class rAdv extends Reaction
{
    /**
     * @return mixed
     */
    public function do_load()
    {
        $req = parent::_getReq();

        $limit = max(min($req['limit'] * 1, 12), 1);

        $rtn = [];

        if (0 != f3()->get('cache.adv')) {
            $fc  = new FCHelper('board');
            $rtn = $fc->get('board_' . $req['pid'] . 'x' . $limit, f3()->get('cache.adv')); // 1 mins

            if (empty($rtn)) {
                $rtn = self::_render($req['pid'], $limit, $req['meta']);
            } else {
                $rtn = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
            }
        } else {
            $rtn = self::_render($req['pid'], $limit, $req['meta']);
        }

        fAdv::addExposure(\__::pluck($rtn, 'id'));

        return self::_return(1, $rtn);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_pass($f3, $args)
    {
        $row = fAdv::one((int) f3()->get('GET.id'), 'id', ['status' => fAdv::ST_ON]);

        // when adv is expired, link still can be reroute!!

        if (null == $row) {
            f3()->error(404);
        }

        fAdv::addCounter($row['id']);

        f3()->reroute($row['uri']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_clearCache($f3, $args)
    {
        chkAuth(fAdv::PV_D);

        $req = parent::_getReq();

        kAdv::clearCache($req['pid']); // TODO: use f3 config

        return self::_return(1);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_list($f3, $args)
    {
        chkAuth(fAdv::PV_R);
        $req = parent::_getReq();

        if (fStaff::_current('lang')) {
            f3()->set('acceptLang', \__::pluck(fStaff::_current('lang'), 'key'));
        }

        if (empty($req['query'])) {
            $req['query'] = [];
        }

        $req['page'] = (isset($req['page'])) ? ($req['page'] - 1) : 0;

        $rtn    = fAdv::limitRows($req['query'], $req['page'], 200, ',m.uri');
        $groups = [];

        $origAry = fAdv::getPositions();
        $origAry = array_merge([
            [
                'id'    => '0',
                'title' => '未選擇',
            ],
        ], $origAry);

        $idArray   = array_column($origAry, 'id');
        $positions = array_combine($idArray, $origAry);

        $rtn['subset'] = array_reduce($rtn['subset'], function ($carry, $row) use ($positions) {
            // 初始化分组中的 'title'
            if (!isset($carry[$row['position_id']]['title'])) {
                $carry[$row['position_id']]['title'] = $positions[$row['position_id']]['title'];
            }
            // 添加行到 'rows'
            $carry[$row['position_id']]['rows'][] = $row;

            return $carry;
        }, []);

        return parent::_return(1, $rtn);
    }

    public function do_push($f3, $args)
    {
        chkAuth(fAdv::PV_U);
        $req = parent::_getReq();

        $press = fPress::one((int) $req['pid']);

        if (empty($press) || empty($press['lang'][f3()->get('defaultLang')]['content'])) {
            return self::_return(8106, ['msg' => '原文無內容']);
        }

        $adv = [
            'position_id' => 0,
            'weight'      => 1,
            'cover'       => $press['cover'],
            'uri'         => '/p/' . $press['id'] . '/' . $press['slug'],
            'start_date'  => date('Y-m-d H:i:s'),
            'end_date'    => date('Y-m-d H:i:s', strtotime('+ 30 days')),
            'meta'        => [
                'press_id' => $press['id'],
                'cate_id'  => $press['cate_id'],
            ],
        ];

        foreach ($press['lang'] as $idx => $row) {
            if (isset($press['lang'][$idx]['title'])) {
                $adv['lang'][$idx] = [
                    'title'    => $press['lang'][$idx]['title'],
                    'subtitle' => $press['online_date'],
                    'content'  => (!empty($press['lang'][$idx]['exposure'])) ? $press['lang'][$idx]['exposure'] : $press['lang'][$idx]['info'],
                ];
            }
        }

        $pid = fAdv::save($adv);

        if ($pid) {
            return self::_return(1, ['msg' => '已新增廣告，請至廣告管理進行上架。']);
        } else {
            return self::_return(8106, ['msg' => '未完成新增!!']);
        }
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        // $row['positions'] = array_values(fAdv::getPositions());
        $row['meta'] = fAdv::lotsMeta($row['id']);

        return $row;
    }

    /**
     * @param $id
     * @param $limit
     *
     * @return mixed
     */
    private static function _render($id = 1, $limit = 4, $meta = 0)
    {
        $rtn = fAdv::getResources($id, $limit, ' m.`weight` DESC ');

        if (empty($rtn)) {
            return [];
        }

        $rtn = \__::map($rtn, function ($cu) use ($meta) {
            if (1 == $meta) {
                $cu['meta'] = fAdv::lotsMeta($cu['id']);
            }

            $cu['link'] = '/r/pass?id=' . $cu['id'];
            unset($cu['uri']);

            return $cu;
        });

        $fc = new FCHelper('board');
        $fc->save('board_' . $id . 'x' . $limit, json_encode($rtn));

        return $rtn;
    }
}
