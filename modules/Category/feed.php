<?php
namespace F3CMS;
/**
 * data feed
 */
class fCategory extends Feed
{
    const MTB = "category";

    const ST_NEW = "New";
    const ST_PAID = "Paid";
    const ST_DONE = "Done";
    const ST_INVALID = "Invalid";

    static function getAll()
    {

        $result = db()->exec("SELECT id, parent_id, title, slug, last_ts FROM `" . self::fmTbl() . "` ");

        foreach ($result as & $row) {
            $row['category'] = rCategory::breadcrumb(rCategory::breadcrumb_categories($row['parent_id']) , false);
            $row['category'].= (($row['category'] != ' / ') ? ' / ' : '') . $row['title'];
        }

        return $result;
    }

    /**
     * get a category by category id
     *
     * @param int $cid - type id
     *
     * @return array
     */
    static function get_category($input)
    {
        $condition = '';

        if (is_numeric($input)) {
            $condition .= ' WHERE c.`id`=? ';
        }
        else {
            $input = '/' . $input;
            $condition .= ' WHERE c.`slug`=? ';
        }

        $rows = db()->exec(
            "SELECT c.*, p.title AS parent  FROM `". self::fmTbl() ."` c LEFT JOIN `".
            self::fmTbl() . "` p ON p.id=c.parent_id ". $condition ." LIMIT 1 "
            , $input
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
    static function get_categories($parent_id = - 1)
    {

        $condition = "";

        if ($parent_id != - 1) {
            $condition = " where c.parent_id='" . $parent_id . "' ";
        }

        $rows = db()->exec("SELECT c.id, c.title, c.slug, c.parent_id, p.title AS parent FROM `" . self::fmTbl() . "` c LEFT JOIN `" . self::fmTbl() . "` p ON p.id=c.parent_id " . $condition . " ORDER BY c.sorter, c.id ");

        return $rows;
    }

    static function get_condition($input, $connect = 'AND', $column = '`category_id`')
    {
        $rows = self::get_category($input);

        if ($rows) {
            if (empty($rows['subrows'])) {
                $condition .= " ". $connect ." ". $column ." = '". $rows['id'] ."' ";
            }
            else {
                $cates = array($rows['id']);

                foreach ($rows['subrows'] as $row) {
                    $cates[] = $cow['id'];
                }

                $condition .= " ". $connect ." ". $column ." IN ('". implode("','", $cates) ."') ";
            }
        }

        return $condition;
    }
}
