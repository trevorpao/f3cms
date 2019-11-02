<?php

namespace F3CMS;

class rMedia extends Reaction
{
    /**
     * @param $f3
     * @param $args
     */
    public function do_show($f3, $args)
    {
        $cu = fMedia::one($args['slug'], 'slug', ['status' => fMedia::ST_ON]);

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->reroute($cu['pic']);
    }

    /**
     * @param $f3
     * @param $args
     */
    public function do_renewParent($f3, $args)
    {
        rStaff::_chkLogin();

        $req = parent::_getReq();

        $rtn = mh()->update(fMedia::fmTbl(), [
            'parent_id' => $req['pid']
        ], [
            'id'     => $req['img'],
            'target' => $req['target']
        ]);

        return self::_return(1, ['cnt' => $rtn->rowCount()]);
    }

    /**
     * save photo
     * @param  object $f3   - $f3
     * @param  array  $args - uri params
     * @return array  - std json
     */
    public function do_editor_upload($f3, $args)
    {
        rStaff::_chkLogin();

        list($filename, $width, $height, $title) = Upload::savePhoto(
            f3()->get('FILES'), [f3()->get('all_thn')]
        );

        $response = new \StdClass;
        $response->link = f3()->get('uri') . $filename;
        echo stripslashes(json_encode($response));
    }
}
