<?php

namespace F3CMS;

/**
 * Tag
 */
class rTag extends Backend
{
    const MTB = "tags";

    /**
     * get a tag by tag id
     *
     * @param int $cid - type id
     *
     * @return array
     */
    static function get_tag($cid)
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
            $cu['subrows'] = self::get_tags($cu['id']);
            return $cu;
        }
    }

    /**
     * get a tag by slug
     *
     * @param string $slug - slug
     *
     * @return array
     */
    static function get_tag_by_slug($slug)
    {
        $f3 = \Base::instance();

        $rows = $f3->get('DB')->exec(
            "SELECT * FROM `". $f3->get('tpf') . self::MTB ."` WHERE `slug`=? LIMIT 1 ", '/'.$slug
        );

        if (count($rows) != 1) {
            return null;
        }
        else {
            $cu = $rows[0];
            $cu['subrows'] = self::get_tags($cu['id']);
            return $cu;
        }
    }

    /**
     * get tags by parent id
     *
     * @param int $parent_id - parent type id
     *
     * @return array
     */
    static function get_tags($parent_id)
    {
        $f3 = \Base::instance();

        $f3->set('result',
            $f3->get('DB')->exec("SELECT c.`id`, c.`title` FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id  where c.parent_id='". $parent_id ."'"));

        return $f3->get('result');
    }

    static public function breadcrumb($ary, $li = true) {
        $f3 = \Base::instance();

        $no_show_ary = array(3, 6, 7, 8, 9);

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
                $str = $str . '<li><a href="'. $f3->get('slug') .'/'. $lang . $ary['slug'] .'">'. $ary['title'] .'</a></li>';
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
     * get tags in option mode
     *
     * @param int $parent_id - parent type id
     * @param int $level     - level number
     * @param int $level_mod - level string mode
     *
     * @return array
     */
    static function sort_tags($parent_id = 0, $level = 0, $level_mod = 'num')
    {
        $f3 = \Base::instance();

        $tags = $f3->get('tags');

        if (empty($tags)) {
            $tags = $f3->get('DB')->exec("SELECT c.id, c.parent_id, c.`title`, c.slug, p.`title` AS parent FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id ORDER BY id");
            $f3->set('tags', $tags);
        }

        $cates = array();

        foreach ($tags AS $row) {
            if ($row['parent_id'] == $parent_id) {
                // $row['title'] = str_repeat("~", $level) . $row['title'];
                if ($level_mod == 'num') {
                    $row['prefix'] = '';
                }
                else {
                    $row['prefix'] = str_repeat($level_mod, $level);
                }
                $row['level'] = $level;
                $cates[] = $row;
                $subCates = self::sort_tags($row['id'], $level + 1, $level_mod);
                if (!empty($subCates)) {
                    $cates = array_merge($cates, $subCates);
                }
            }
        }

        return $cates;
    }

    /**
     * get tags in breadcrumb mode
     *
     * @param int $parent_id - parent type id
     * @param int $level     - level number
     *
     * @return array
     */
    static function breadcrumb_tags($parent_id = 0, $level = 0)
    {
        $f3 = \Base::instance();

        $tags = $f3->get('tags');

        if (empty($tags)) {
            $tags = $f3->get('DB')->exec("SELECT id, parent_id, `title`, slug FROM `". $f3->get('tpf') . self::MTB ."` ORDER BY id");
            $f3->set('tags', $tags);
        }

        $cates = array();

        foreach ($tags AS $row) {
            if ($row['id'] == $parent_id) {
                $row['title'] = $row['title'];
                $row['parentCate'] = self::breadcrumb_tags($row['parent_id'], $level + 1);
                $cates = $row;
            }
        }

        return $cates;
    }

    /**
     * get all records for backend
     *
     * @param object $f3  - $f3
     * @param array $args - pass by router
     *
     * @return array
     */
    function do_get_tags($f3, $args)
    {
        $req = parent::_escape($f3->get('GET'));

        $condition = " WHERE 1 ";

        if (!empty($req['query'])) {
            $condition .= " AND `title` < ". $req['query'] ." ";
        }

        $f3->set('result',
            $this->_db->exec("SELECT `id`, `title` FROM `". $f3->get('tpf') . self::MTB ."` ". $condition ." ORDER BY `title` LIMIT 20 "));

        return parent::_return(1, $f3->get('result'));
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
        $f3->set('result',
            $this->_db->exec("SELECT c.*, p.`title` AS parent, c.`title` AS `title`, c.`slug` AS `slug`, c.`counter` AS `counter` FROM `". $f3->get('tpf') . self::MTB ."` c LEFT JOIN `". $f3->get('tpf') . self::MTB ."` p ON p.id=c.parent_id "));

        return parent::_return(1, $f3->get('result'));
    }

    static function handleRow($row = array())
    {
        $row['tags'] = self::sort_tags();
        return $row;
    }
}
