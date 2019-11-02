<?php
namespace F3CMS;

/**
 * data feed
 */
class fDictionary extends Feed
{
    const MTB = 'dictionary';
    const ST_ON = 'Enabled';
    const ST_OFF = 'Disabled';

    /**
     * @return mixed
     */
    public static function getAll()
    {
        $result = db()->exec('SELECT a.id, a.title, a.last_ts, a.status, a.slug FROM `' . self::fmTbl() . '` a ');

        return $result;
    }

    /**
     * @param $ids
     * @return mixed
     */
    public static function load_some($ids)
    {
        $limit = 9;

        $filter = [
            ':status' => self::ST_ON
        ];

        $condition = ' WHERE m.`status` = :status ';

        if (is_array($ids)) {
            $ary = [];

            foreach ($ids as $row) {
                $ary[] = intval($row['id']);
            }

            $condition .= " AND m.`id` IN ('" . implode("','", $ary) . "') ";
        } else {
            $filter[':id'] = $ids;
            $condition .= ' AND m.`id` = :id ';
        }

        $sql = 'SELECT m.* FROM `' . self::fmTbl() . '` m '
            . $condition . ' ORDER BY m.`id` DESC LIMIT 5 ';

        // die($sql);

        $stmt = db()->prepare($sql);
        $stmt->execute($filter);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $result;
    }
}
