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
        $cu = fMedia::one(parent::_slugify($args['slug']), 'slug', ['status' => fMedia::ST_ON]);

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
        kStaff::_chkLogin();

        $req = parent::_getReq();

        $rtn = mh()->update(fMedia::fmTbl(), [
            'parent_id' => $req['pid'],
        ], [
            'id'     => $req['img'],
            'target' => $req['target'],
        ]);

        return self::_return(1, ['cnt' => $rtn->rowCount()]);
    }

    /**
     * save photo
     *
     * @param object $f3   - $f3
     * @param array  $args - uri params
     */
    public function do_upload($f3, $args)
    {
        kStaff::_chkLogin();

        $req         = parent::_getReq();
        $need2Insert = 1;

        if (empty($req['parent_id']) || empty($req['target'])) {
            $need2Insert = 0;
        }

        $req['target'] = empty($req['target']) ? 'Normal' : $req['target'];

        $files = f3()->get('FILES');
        if (empty($files)) {
            return parent::_return(8004);
        }

        $path_parts    = pathinfo($files['photo']['name']);
        $old_fn        = $path_parts['filename'];
        $thumbnailSize = (f3()->exists($req['target'] . '_thn')) ? f3()->get($req['target'] . '_thn') : f3()->get('default_thn');

        [$filename, $width, $height] = Upload::savePhoto($files, [$thumbnailSize, f3()->get('default_thn'), f3()->get('all_thn')]);

        if ($need2Insert) {
            $pid = fMedia::insert([
                'title'     => $old_fn,
                'target'    => $req['target'],
                'parent_id' => $req['parent_id'],
                'slug'      => fMedia::renderUniqueNo(16),
                'pic'       => $filename,
            ]);
        } else {
            $pid = 0;
        }

        return self::_return(1, ['filename' => $filename, 'id' => $pid]);
    }

    /**
     * save photo
     *
     * @param object $f3   - $f3
     * @param array  $args - uri params
     *
     * @return array - std json
     */
    public function do_editor_upload($f3, $args)
    {
        kStaff::_chkLogin();

        $files = f3()->get('FILES');
        if (empty($files)) {
            return parent::_return(8004);
        }

        [$filename, $width, $height, $title] = Upload::savePhoto(
            $files, [f3()->get('all_thn')]
        );

        fMedia::insert([
            'title'     => $title,
            'target'    => 'Normal',
            'parent_id' => 0,
            'slug'      => fMedia::renderUniqueNo(16),
            'pic'       => $filename,
        ]);

        $response       = new \stdClass();
        $response->link = f3()->get('uri') . $filename;
        echo stripslashes(json_encode($response));
    }
}
