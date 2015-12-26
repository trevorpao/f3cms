<?php
namespace F3CMS;
/**
 * data feed
 */
class fMedia extends Feed
{
    const MTB = "media";

    const ST_ON = "Enabled";
    const ST_OFF = "Disabled";

    static function getAll()
    {

        $result = db()->exec("SELECT a.id, a.title, a.pic, a.last_ts FROM `" . self::fmTbl() . "` a ");

        return $result;
    }

    static function insert($req)
    {

        $now = date('Y-m-d H:i:s');

        $obj = self::map();
        $obj->insert_ts = $now;
        $obj->insert_user = rUser::_CUser('id');
        $obj->last_ts = $now;
        $obj->last_user = rUser::_CUser('id');

        $obj->status = self::ST_ON;
        $obj->title = $title;
        $obj->pic = $filename;
        $obj->slug = '/' . parent::_slugify($title);

        $obj->save();

        return $obj->id;
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

        $rows = db()->exec("SELECT id, title, content, pic, last_ts, slug FROM `" . self::fmTbl() . "` WHERE `slug`=? LIMIT 1 ", '/' . $slug);

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0];
        }
    }
}
