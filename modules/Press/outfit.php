<?php
namespace F3CMS;

/**
* for render page
*/
class oPress extends Outfit
{

    function do_list ($f3, $args)
    {
        $subset = fPress::load_list(Pagination::findCurrentPage(), $args['slug']);

        $cate = array();

        foreach ($subset['subset'] as &$row) {
            $cate[$row->category_id] = rCategory::breadcrumb_categories($row->category_id);

            $f3->set('act_link', str_replace('/', '', $cate[$row->category_id]['slug']));
        }

        $f3->set('pagebrowser', parent::paginate($subset['total'], $subset['limit'], '/presses/'. $args['slug']));

        $f3->set('rows', $subset);
        $f3->set('cate', $cate);

        parent::wrapper('press/list.html', '展覽、文章', '/presses');
    }

    function do_show ($f3, $args)
    {
        $cu = fPress::get_row('/'. $args['slug'], 'slug', " AND `status`='". fPress::ST_ON ."' ");

        if (empty($cu)) {
            f3()->error(404);
        }

        $cate = rCategory::breadcrumb_categories($cu['category_id']);

        f3()->set('cu', $cu);
        f3()->set('cate', $cate);
        f3()->set('next', fPress::load_next($cu, 0, 'online_date'));
        f3()->set('prev', fPress::load_prev($cu, 0, 'online_date'));

        $f3->set('act_link', str_replace('/', '', $cate['slug']));

        parent::wrapper('press/content.html', $cu['title'], '/press'. $cu['slug']);
    }

    function do_preview ($f3, $args)
    {
        rStaff::_chkLogin();

        $cu = fPress::get_row('/'. $args['slug'], 'slug', '', true);

        if (empty($cu)) {
            f3()->error(404);
        }

        f3()->set('cu', $cu);

        parent::wrapper('press/content.html', $cu['title'], '/press'. $cu['slug']);
    }
}
