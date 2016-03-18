<?php

namespace F3CMS;

/**
 * data feed
 */
class fContact extends Feed
{
    const MTB           = "contact";

    static function getAll()
    {

        $result = db()->exec(
            "SELECT `id`, `status`, `name`, `phone`, `email`, `last_ts` FROM `".
            self::fmTbl() ."` ORDER BY insert_ts DESC "
        );

        return $result;
    }

    static function insert($req)
    {

        $now = date('Y-m-d H:i:s');
        $obj = self::map();

        $obj->name = $req['name'];
        $obj->email = $req['email'];
        $obj->type = $req['type'];
        $obj->phone = $req['phone'];
        $obj->message = $req['message'];
        $obj->other = $req['other'];
        $obj->last_ts = $now;
        $obj->insert_ts = $now;
        $obj->save();

        return $obj->id;
    }
}
