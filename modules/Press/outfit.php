<?php
namespace F3CMS;

/**
* for render page
*/
class oPress extends Outfit
{

    function do_list ($f3, $args)
    {

        $subset = fPress::load_list(Pagination::findCurrentPage(), '');

        $cate = array('title' => '展覽、文章');

        $f3->set('pagebrowser', parent::paginate($subset['total'], $subset['limit'], '/presses/'. $args['slug']));

        $f3->set('rows', $subset);
        $f3->set('cate', $cate);

        parent::wrapper('press/list.html', '展覽、文章', '/presses/' . $args['slug']);
    }

    function do_tag_list ($f3, $args)
    {

        if (is_numeric($args['slug'])) {
            $tag = fTag::one($args['slug'], 'id');
        }
        else {
            $tag = fTag::one($args['slug'], 'slug');
        }

        if (empty($tag)) {
            f3()->error(404);
        }

        $subset = fPress::load_list(Pagination::findCurrentPage(), $tag['slug'], 'tag');

        $f3->set('pagebrowser', parent::paginate($subset['total'], $subset['limit'], '/tag/'. $tag['slug']));

        $f3->set('rows', $subset);
        $f3->set('cate', $tag);

        parent::wrapper('press/list.html', $tag['title'], '/tag' . $tag['slug']);
    }

    function do_author_list ($f3, $args)
    {

        $author = fAuthor::one($args['slug'], 'slug', ['status' => fAuthor::ST_ON]);

        if (empty($author)) {
            f3()->error(404);
        }

        $subset = fPress::load_list(Pagination::findCurrentPage(), $author['id'], 'author');

        $f3->set('pagebrowser', parent::paginate($subset['total'], $subset['limit'], '/author/'. $author['slug']));

        $seo = array(
            'desc' => $author['title'] .':'. $author['info'],
            'img' => $author['pic']
        );

        f3()->set('page', $seo);

        $f3->set('rows', $subset);
        $f3->set('cate', $author);

        parent::wrapper('press/author.html', $author['title'] . '的所有文章', '/author/' . $author['slug']);
    }

    function do_show ($f3, $args)
    {

        $cu = fPress::one($args['slug'], 'id', ['status' => fPress::ST_ON]);

        if (empty($cu)) {
            f3()->error(404);
        }

        $cate = rCategory::breadcrumb_categories($cu['category_id']);

        $author = fAuthor::one($cu['author_id']);

        $cu['rel_tag'] = json_decode($cu['rel_tag'], true);

        $seo = array(
            'desc' => $cu['info'],
            'img' => $cu['pic'],
            'keyword' => $cu['keyword']
        );

        if (!empty($cu['rel_tag'])) {
            $ary = array();

            foreach ($cu['rel_tag'] as $row) {
                $ary[] = $row['title'];
            }

            $seo['keyword'] = implode(',', $ary) .','. $seo['keyword'];
        }

        $cu['rel_dict'] = json_decode($cu['rel_dict'], true);

        if (!empty($cu['rel_dict'])) {
            $ary = array();

            foreach ($cu['rel_dict'] as $row) {
                $ary[] = $row['title'];
            }

            $seo['keyword'] = implode(',', $ary) .','. $seo['keyword'];

            $cu['rel_dict'] = fDictionary::load_some($cu['rel_dict']);
        }

        f3()->set('page', $seo);

        f3()->set('cu', $cu);
        f3()->set('cate', $cate);
        f3()->set('author', $author);
        f3()->set('next', fPress::load_next($cu, 0, 'online_date'));
        f3()->set('prev', fPress::load_prev($cu, 0, 'online_date'));

        f3()->set('act_link', str_replace('/', '', $cate['slug']));

        parent::wrapper('press/show.html', $cu['title'], '/p/'. $cu['id'] .'/'. $cu['slug']);
    }
}
