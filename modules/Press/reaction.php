<?php

namespace F3CMS;

class rPress extends Reaction
{
    /**
     * @param array $row
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fPress::lotsTag($row['id']);
        $row['authors'] = fPress::lotsAuthor($row['id']);
        $row['relateds'] = fPress::lotsRelated($row['id']);
        $row['meta'] = fPress::lotsMeta($row['id']);

        $ts = strtotime($row['online_date']);
        $ts = $ts - $ts % 300; // deduct the seconds that is not a multiple of 5 minutes

        $row['hh'] = date('H', $ts);
        $row['mm'] = date('i', $ts);
        $row['online_date'] = date('Y-m-d', $ts);

        // read history file
        // $fc = new FCHelper('press');
        $row['history'] = []; // $fc->getLog('press_' . $row['id']);
        $row['status_publish'] = $row['status'];

        return $row;
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_list($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();

        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 1;

        $rtn = fPress::limitRows($req['query'], $req['page']);

        foreach ($rtn['subset'] as $k => $row) {
            $rtn['subset'][$k]['tags'] = fPress::lotsTag($row['id']);
            $rtn['subset'][$k]['authors'] = fPress::lotsAuthor($row['id']);
        }

        return self::_return(1, $rtn);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_published($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();

        $cu = fPress::one($req['id']);

        if (empty($cu)) {
            return parent::_return(8106);
        }

        switch ($req['status']) {
            case fPress::ST_PUBLISHED:
                if ($cu['online_date'] > date('Y-m-d H:i:s')) {
                    $req['online_date'] = date('Y-m-d H:i') . ':00';
                } else {
                    unset($req['online_date']);
                }

                if (f3()->get('cache.press') === 0) {
                    oPress::force(['slug' => $cu['id']]);
                }
                break;
            case fPress::ST_OFFLINED:
                // TODO: remove file when offlined
                break;
            default:
                break;
        }

        fPress::published($req);

        return self::_return(1, ['id' => $req['id']]);
    }
}
