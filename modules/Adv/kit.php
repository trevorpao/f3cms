<?php

namespace F3CMS;

/**
 * kit lib
 */
class kAdv extends Kit
{
    public static function loadAry($key, $ary, $force = 0)
    {
        $fc  = new FCHelper('board');
        $key .= '_' . Module::_lang();
        $cache = $fc->get($key);

        if (1 == $force || empty($cache)) {
            foreach ($ary as $idx => $row) {
                $data = fAdv::getResources($row['pid'], $row['limit'], ' m.position_id, m.`weight` DESC ');

                $data = \__::map($data, function ($cu) {
                    $cu['meta'] = fAdv::lotsMeta($cu['id']);

                    $cu['link'] = '/r/pass?id=' . $cu['id'];
                    unset($cu['uri']);

                    if (!empty($cu['meta']) && !empty($cu['meta']['press_id'])) {
                        $cu['tags']     = fPress::lotsTag($cu['meta']['press_id']);
                        $cu['authors']  = fPress::lotsAuthor($cu['meta']['press_id']);
                        $cu['cate']     = fCategory::onlyColumns(
                            fCategory::one($cu['meta']['cate_id'], 'id',
                                ['status' => fCategory::ST_ON], 0),
                            ['id', 'slug', 'title']
                        );
                    }

                    return $cu;
                });

                $ary[$idx]['data'] = $data;
            }

            $fc->save($key, json_encode($ary));
        } else {
            $ary = json_decode(preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $cache), true);
        }

        return $ary;
    }

    public static function clearCache($key)
    {
        $fc         = new FCHelper('board');
        $acceptLang = f3()->get('acceptLang');
        foreach ($acceptLang as $val) {
            $fc->flush($key . '_' . $val);
        }
    }
}
