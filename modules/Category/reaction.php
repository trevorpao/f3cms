<?php

namespace F3CMS;

/**
 * React any request
 */
class rCategory extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_more($f3, $args)
    {
        $req = parent::_getReq();

        $req['page']  = ($req['page']) ? (intval($req['page']) - 1) : 1;
        $req['limit'] = max(min($req['limit'] * 1, 24), 3);

        if (!empty($req['pid'])) {
            if (is_numeric($req['pid'])) {
                $cate = fCategory::one($req['pid'], 'id', ['status' => fCategory::ST_ON], false);
            } else {
                $cate = fCategory::one(parent::_slugify($req['pid']), 'slug', ['status' => fCategory::ST_ON], false);
            }
        } else {
            $cate = ['id' => 0];
        }

        $filter = [
            'm.cate_id' => $cate['id'],
            'm.status'  => [fPress::ST_PUBLISHED, fPress::ST_CHANGED],
        ];

        if (!empty($args['tag'])) {
            $tag = fTag::one(parent::_slugify($args['tag']), 'slug', ['status' => fTag::ST_ON], false);
            if (empty($tag)) {
                f3()->error(404);
            }

            $ids = fPress::byTag($tag['id']);
            if (!empty($ids)) {
                $filter['m.id'] = $ids;
            } else {
                $filter['m.id'] = '0';
            }
        }

        $rtn           = fPress::limitRows($filter, $req['page'], $req['limit']);
        $rtn['subset'] = \__::map($rtn['subset'], function ($row) {
            return rPress::handleIteratee($row);
        });

        return self::_return(1, $rtn);
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fCategory::lotsTag($row['id']);

        return $row;
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_get_opts($f3, $args)
    {
        // chkAuth(fCategory::PV_R);

        $req       = self::_getReq();
        $condition = [
            'm.status' => fCategory::ST_ON,
        ];

        if (!empty($req['group'])) {
            $condition['m.group'] = $req['group'];
        }

        if (!empty($req['query'])) {
            $condition['l.title[~]'] =  $req['query'];
        }

        $opts = fCategory::limitRows($condition, 0, 20);
        $rtn  = [];

        foreach ($opts['subset'] as &$row) {
            $rtn[] = ['id' => $row['id'], 'title' => $row['title'] . ' / ' . $row['slug']];
        }

        return self::_return(1, $rtn);
    }
}
