<?php

namespace F3CMS;

/**
 * data feed
 */
class fMedia extends Feed
{
    const MTB           = "media";

    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function getAll()
    {


        $result = f3()->get('DB')->exec(
            "SELECT a.id, a.title, a.pic, a.last_ts FROM `". f3()->get('tpf') . self::MTB ."` a "
        );

        return $result;
    }

    static function insert($req)
    {

        $now = date('Y-m-d H:i:s');

        $obj = new \DB\SQL\Mapper(f3()->get('DB'), f3()->get('tpf') . self::MTB);
        $obj->insert_ts = $now;
        $obj->insert_user = rUser::_CUser('id');
        $obj->last_ts = $now;
        $obj->last_user = rUser::_CUser('id');

        $obj->status = self::ST_ON;
        $obj->title = $title;
        $obj->pic = $filename;
        $obj->slug = '/'. parent::_slugify($title);

        $obj->save();
    }

    /**
     * get a row by slug
     *
     * @param string $slug - slug
     *
     * @return array
     */
    static function get_row_by_slug($slug)
    {


        $rows = f3()->get('DB')->exec(
            "SELECT id, title, content, pic, last_ts, slug FROM `". f3()->get('tpf') . self::MTB ."` WHERE `slug`=? LIMIT 1 ", '/' . $slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0];
        }
    }
}
