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


        $result = f3()->get('DB')->exec(
            "SELECT `id`, `status`, `name`, `phone`, `email`, `last_ts` FROM `".
            f3()->get('tpf') . self::MTB ."` ORDER BY insert_ts DESC "
        );

        return $result;
    }

    static function insert($req)
    {


        $now = date('Y-m-d H:i:s');
        $obj = new \DB\SQL\Mapper(f3()->get('DB'), f3()->get('tpf') . self::MTB);

        $obj->name = $req['cname'];
        $obj->email = $req['cemail'];
        $obj->message = $req['cmessage'];
        $obj->last_ts = $now;
        $obj->insert_ts = $now;
        $obj->save();
    }
}
