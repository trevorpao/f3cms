<?php

namespace F3CMS;

/**
 * React any request
 */
class rAuthor extends Reaction
{
    /**
     * @param array $row
     *
     * @return mixed
     */
    public static function handleRow($row = [])
    {
        $row['tags'] = fAuthor::lotsTag($row['id']);

        // echo mh()->last();
        return $row;
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_more($f3, $args)
    {
        $req = parent::_getReq();

        $req['page'] = intval($req['page']);
        $req['page'] = ($req['page']) ? ($req['page'] - 1) : 1;

        $req['limit'] = max(min($req['limit'] * 1, 24), 3);

        $author = null;

        if (!empty($req['pid'])) {
            if (is_numeric($req['pid'])) {
                $author = fAuthor::one($req['pid'], 'id', ['status' => fAuthor::ST_ON], false);
            } else {
                $author = fAuthor::one(parent::_slugify($req['pid']), 'slug', ['status' => fAuthor::ST_ON], false);
            }
        }

        if (empty($author)) {
            return parent::_return(8002);
        }

        $rtn = fPress::lotsByAuthor($author['id'], $req['page'], $req['limit']);

        $rtn['subset'] = \__::map($rtn['subset'], function ($row) {
            return rPress::handleIteratee($row);
        });

        return self::_return(1, $rtn);
    }
}
