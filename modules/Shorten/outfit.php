<?php

namespace F3CMS;

class oShorten extends Outfit
{
    /**
     * @param $f3
     * @param $args
     *
     * /s/qhD6XWrJR5NFyt3a
     */
    public function do_pass($f3, $args)
    {
        $row = fShorten::one($args['token'], 'token', ['status' => fShorten::ST_ON]);

        if (null == $row) {
            f3()->error(404);
        }

        fShorten::addCounter($row['id']);

        if (!empty($row['note'])) {
            $setting = jsonDecode(self::safeRaw($row['note']));
            if (is_array($setting) && !empty($setting)) {
                $setting['origin'] = $row['origin'];
                kShorten::nextStep($setting); // reroute to somewhere
            }
        }

        f3()->reroute($row['origin']);
    }
}
