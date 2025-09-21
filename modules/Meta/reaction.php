<?php

namespace F3CMS;

/**
 * React any request
 */
class rMeta extends Reaction
{
    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fMeta::lotsGenus($row['id']);

        return $row;
    }

    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleIteratee($row = [])
    {
        $row['tags']    = fMeta::lotsGenus($row['id']);

        return $row;
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_all($f3, $args)
    {
        $req = parent::_getReq();
        $req['query'] = (!isset($req['query'])) ? '' : $req['query'];

        $rtn = fMeta::limitRows($req['query'], 0, 100);

        foreach ($rtn['subset'] as $k => $row) {
            $rtn['subset'][$k]['tags'] = '[' . implode('],[', \__::pluck(fMeta::lotsGenus($row['id']), 'id')) . ']';
        }

        return self::_return(1, $rtn['subset']);
    }
}
