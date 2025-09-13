<?php

namespace F3CMS;

/**
 * kit lib
 */
class kShorten extends Kit
{
    public static function nextStep($setting, $return = 0)
    {
        if (isset($setting['memberOnly']) && 1 == $setting['memberOnly']
            && !kMember::_isLogin()) {
            f3()->set('SESSION.shortenNextStep', $setting);
            $link = f3()->get('uri') . '/member/login?reason=' . ((!empty($setting['reason'])) ? $setting['reason'] : '試看課程須先登入會員');
        } else {
            $class  = '\F3CMS\\' . $setting['class'];

            if (method_exists($class, $setting['method'])) {
                call_user_func($class . '::' . $setting['method'], $setting);
            }

            $link = $setting['origin'];
        }

        if ($return) {
            return $link;
        } else {
            f3()->reroute($link);
        }
    }

    public static function rules()
    {
        return [];
    }
}
