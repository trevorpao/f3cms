<?php

namespace F3CMS;

/**
 * Category
 */
class rCategory extends Reaction
{

    static public function breadcrumb($ary, $li = true)
    {


        $no_show_ary = array();

        if (empty($ary['parentCate'])) {
            if ($li) {
                $str = '<li><a href="'. f3()->get('uri') .'">Home</a></li>';
            }
            else {
                $str = '';
            }
        }
        else {
            $str = self::breadcrumb($ary['parentCate'], $li);
        }

        if (!in_array($ary['id'], $no_show_ary)) {
            if ($li) {
                $str = $str . '<li><a href="'. f3()->get('uri') .'/products' . $ary['slug'] .'">'. $ary['title'] .'</a></li>';
            }
            else {
                $str = $str . ' / '. $ary['title'] .'';
            }
        }
        else {
            if ($li) {
                $str = $str . '<li><a>'. $ary['title'] .'</a></li>';
            }
            else {
                $str = $str . ' / '. $ary['title'] .'';
            }
        }

        return $str;
    }

    /**
     * get categories in option mode
     *
     * @param int $parent_id - parent type id
     * @param int $level     - level number
     * @param int $level_mod - level string mode
     *
     * @return array
     */
    static function sort_categories($parent_id = 0, $level = 0, $level_mod = 'num')
    {


        $categories = f3()->get('categories');

        if (empty($categories)) {
            $categories = fCategory::get_categories();
            f3()->set('categories', $categories);
        }

        $cates = array();

        foreach ($categories AS $row) {
            if ($row['parent_id'] == $parent_id) {
                if ($level_mod == 'num') {
                    $row['prefix'] = '';
                }
                else {
                    $row['prefix'] = str_repeat($level_mod, $level+1);
                }
                $row['level'] = $level;
                $row['title'] = $row['prefix'] . $row['title'];
                $cates[] = $row;
                $subCates = self::sort_categories($row['id'], $level + 1, $level_mod);
                if (!empty($subCates)) {
                    $cates = array_merge($cates, $subCates);
                }
            }
        }

        return $cates;
    }

    /**
     * get categories in breadcrumb mode
     *
     * @param int $parent_id - parent type id
     * @param int $level     - level number
     *
     * @return array
     */
    static function breadcrumb_categories($parent_id = 0, $level = 0)
    {


        $categories = f3()->get('categories');

        if (empty($categories)) {
            $categories = fCategory::get_categories();
            f3()->set('categories', $categories);
        }

        $cates = array();

        foreach ($categories AS $row) {
            if ($row['id'] == $parent_id) {
                $row['parentCate'] = self::breadcrumb_categories($row['parent_id'], $level + 1);
                $cates = $row;
            }
        }

        return $cates;
    }

    static function handleRow($row = array())
    {
        $row['categories'] = self::sort_categories(0, 0 , '~');
        return $row;
    }
}
