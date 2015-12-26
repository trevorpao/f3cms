<?php

namespace F3CMS;

/**
 * Category
 */
class rCategory extends Backend
{

    const MTB = "categories";

    /**
     * get a category by category id
     *
     * @param int $cid - type id
     *
     * @return array
     */
    static function get_category($cid)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` WHERE `id`=? LIMIT 1 ", $cid
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_categories($cu['id']);
            return $cu;
        }
    }

    /**
     * get a category by slug
     *
     * @param string $slug - slug
     *
     * @return array
     */
    static function get_category_by_slug($slug)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT c.*, p.title AS parent FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id WHERE c.`slug`=? LIMIT 1 ", '/'.$slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_categories($cu['id']);
            return $cu;
        }
    }

    /**
     * get categories by parent id
     *
     * @param int $parent_id - parent type id
     *
     * @return array
     */
    static function get_categories($parent_id)
    {
        $f3 = \Base::instance();

        $f3->set('result',
            $f3->get('DB')->exec("SELECT c.id, c.title, c.slug, c.parent_id, p.title AS parent FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id  where c.parent_id='". $parent_id ."'"));

        return $f3->get('result');
    }

    static public function breadcrumb($ary, $li = true)
    {
        $f3 = \Base::instance();

        $no_show_ary = array();

        if (empty($ary['parentCate'])) {
            if ($li) {
                $str = '<li><a href="'. $f3->get('slug') .'">Home</a></li>';
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
                $str = $str . '<li><a href="'. $f3->get('slug') .'/' . $ary['slug'] .'">'. $ary['title'] .'</a></li>';
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
        $f3 = \Base::instance();

        $categories = $f3->get('categories');

        if (empty($categories)) {
            $categories = $f3->get('DB')->exec("SELECT c.id, c.parent_id, c.title, c.slug, p.title AS parent FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id ORDER BY id");
            $f3->set('categories', $categories);
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
        $f3 = \Base::instance();

        $categories = $f3->get('categories');

        if (empty($categories)) {
            $categories = $f3->get('DB')->exec("SELECT id, parent_id, title, slug FROM `". $f3->get('tpf') . self::MTB ."` ORDER BY id");
            $f3->set('categories', $categories);
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

    /**
     * get all records for backend
     *
     * @param object $f3  - $f3
     * @param array $args - pass by router
     *
     * @return array
     */
    function do_list_all($f3, $args)
    {
        $rows = $this->_db->exec("SELECT id, parent_id, title, slug, last_ts FROM `". $f3->get('tpf') . self::MTB ."` ");

        foreach ($rows as &$row) {
            $row['category'] = rCategory::breadcrumb(Category::breadcrumb_categories($row['parent_id']), false);
            $row['category'] .= (($row['category'] != ' / ')?' / ':'') . $row['title'];
        }

        return parent::_return(1, $rows);
    }
}
