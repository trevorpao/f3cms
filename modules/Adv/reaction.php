<?php

namespace F3CMS;

/**
 * React any request
 */
class rAdv extends Reaction
{

    public function do_load()
    {
        $req = parent::_getReq();

        $limit = (int)$req['limit'];

        $rtn = [];

        $fc = new FCHelper('board');
        $rtn = $fc->get('board_'. $req['pid'] .'x'. $limit, 1); // 1 mins

        if (empty($rtn)) {
            $rtn = fAdv::getResources($req['pid'], $limit, ' m.`weight` ');

            if ($req['meta'] == 1) {
                $rtn = \__::map($rtn, function ($cu) {
                    $cu['meta'] = fAdv::lotsMeta($cu['id']);

                    return $cu;
                });
            }

            $fc->save('board_'. $req['pid'] .'x'. $limit, json_encode($rtn));
        }
        else {
            $rtn = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $rtn), true);
        }

        fAdv::addExposure(\__::pluck($rtn, 'id'));


        return self::_return(1, ['cu' => [], 'data' => $rtn]);
    }

    public function do_pass($f3, $args)
    {
        $row = fAdv::one((int)f3()->get('GET.id'), 'id', ['status' => fAdv::ST_ON]);

        if ($row == null) {
            f3()->error(404);
        }

        fAdv::addCounter($row['id']);

        f3()->reroute($row['uri']);
    }

    static function handleRow($row = array())
    {
        // $row['positions'] = array_values(fAdv::getPositions());
        $row['meta'] = fPress::lotsMeta($row['id']);
        return $row;
    }
}
