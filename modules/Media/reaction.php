<?php

namespace F3CMS;

class rMedia extends Backend
{

    const MTB = "medias";
    const STATUS_ON = "Enabled";
    const STATUS_OFF = "Disabled";

    function do_list_all($f3, $args)
    {
        rUser::_chkLogin();

        $rows = $this->_db->exec("SELECT a.id, a.title, a.pic, a.last_ts FROM `". $f3->get('tpf') . self::MTB ."` a ");

        return parent::_return(1, $rows);
    }

    /**
     * save photo
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     * @return array        - std json
     */
    function do_editor_upload($f3, $args)
    {
        rUser::_chkLogin();

        list($filename, $width, $height, $title) = Upload::savePhoto(
            $f3->get('FILES'), array($f3->get('all_thn'))
        );

        $obj = new \DB\SQL\Mapper($this->_db, $f3->get('tpf') . parent::_getMainTbl() ."");
        $obj->insert_ts = date('Y-m-d H:i:s');
        $obj->insert_user = rUser::_CUser('id');
        $obj->last_ts = date('Y-m-d H:i:s');
        $obj->last_user = rUser::_CUser('id');

        $obj->status = self::STATUS_ON;
        $obj->title = $title;
        $obj->pic = $filename;
        $obj->slug = '/'. parent::_slugify($title);

        $obj->save();

        $response = new StdClass;
        $response->link = $f3->get('uri') . $filename;
        echo stripslashes(json_encode($response));
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
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT id, title, content, pic, last_ts, slug FROM `". $f3->get('tpf') . self::MTB ."` WHERE `slug`=? LIMIT 1 ", '/' . $slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            return $rows[0];
        }
    }
}
