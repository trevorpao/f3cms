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

        $limit = (int) $req['limit'];

        $rtn = [];

        $fc  = new FCHelper('board');
        $rtn = $fc->get('board_' . $req['pid'] . 'x' . $limit, 1); // 1 mins

        if (empty($rtn)) {
            $rtn = fAdv::getResources($req['pid'], $limit, ' m.`weight` ');

            if (1 == $req['meta']) {
                $rtn = \__::map($rtn, function ($cu) {
                    $cu['meta'] = fAdv::lotsMeta($cu['id']);

                    return $cu;
                });
            }

            $fc->save('board_' . $req['pid'] . 'x' . $limit, json_encode($rtn));
        } else {
            $rtn = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
        }

        fAdv::addExposure(\__::pluck($rtn, 'id'));

        return self::_return(1, ['cu' => [], 'data' => $rtn]);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_pass($f3, $args)
    {
        $row = fAdv::one((int) f3()->get('GET.id'), 'id', ['status' => fAdv::ST_ON]);

        if (null == $row) {
            f3()->error(404);
        }

        fAdv::addCounter($row['id']);

        f3()->reroute($row['uri']);
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        // $row['positions'] = array_values(fAdv::getPositions());
        $row['meta'] = fPress::lotsMeta($row['id']);

        return $row;
    }
}
