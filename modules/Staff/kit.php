<?php

namespace F3CMS;

/**
 * kit lib
 */
class kStaff extends Kit
{
    /**
     * @param $email
     * @param $verify_code
     */
    public static function sendInvite($email, $verify_code)
    {
        if ('qa' == f3()->get('APP_ENV')) {
            $verify_code .= '?qa=' . f3()->get('feVersion');
        }

        f3()->set('verify_code', $verify_code);
        f3()->set('service_mail', fOption::get('service_mail'));
        f3()->set('email', $email);

        Sender::sendmail(f3()->get('site_title') . '-後台帳號開通通知信', Sender::renderBody('invite'), $email);
    }

    public static function _notExistAccount($account)
    {
        $currnt = (int) f3()->exists('POST.id') ? f3()->get('POST.id') : 0;
        $staff  = fStaff::one($account, 'account');
        if (empty($staff)) {
            return true;
        } else {
            if (0 == $currnt) {
                return '已有此管理員帳號，無法新增資料!!';
            } else {
                return ($staff['id'] == $currnt) ? true : '已有此管理員帳號，無法修改資料!!';
            }
        }
    }

    public static function _accountRule()
    {
        return [
            'required',
            'min:6',
            function ($value) {
                return self::_notExistAccount($value);
            },
        ];
    }

    public static function rules()
    {
        return [
            'save'        => [
                'account' => self::_accountRule(),
                'pwd'     => 'regex:/^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{8,32}/', // 建議長度 8-16 字元，含英數字
            ],
            'login'        => [
                'account' => 'required|min:3|max:200', // 建議長度 8-16 字元，含英數字
                // SELECT `id`, `account`, LENGTH(`account`) AS LengthOfString FROM `tbl_member`
                //     WHERE 1 HAVING LengthOfString > 16 OR  LengthOfString < 4;
                // 526, 1062, 61, 415

                'pwd'  => 'required|min:4|max:32', // 建議長度 8-16 字元，含英數字
            ],
            'update'       => [
                'pwd'    => 'regex:/^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{8,32}/', // 建議長度 8-16 字元，含英數字
                're_pwd' => 'regex:/^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{8,32}/', // 建議長度 8-16 字元，含英數字

                'realname'  => 'required|min:2|max:100',
                'nickname'  => 'required|min:2|max:100',
                'email'     => 'required|email',
                // 'phone'     => 'required|numeric|min:6'
            ],
            'reset' => [
                'pwd'    => 'regex:/^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{8,32}/', // 建議長度 8-16 字元，含英數字
                're_pwd' => 'regex:/^(?=.*\d)(?=.*[a-zA-Z]){2,}(?=.*[a-zA-Z])(?!.*\s).{8,32}/', // 建議長度 8-16 字元，含英數字
            ],
            'tokenVerify'  => [
                'token' => 'required',
                'way'   => 'required|in:google,facebook',
            ],
        ];
    }

    /**
     * @return int
     */
    public static function _isLogin()
    {
        $status = fStaff::_current('has_login');

        return (1 == $status) ? 1 : 0;
    }

    public static function _chkLogin()
    {
        if (isAjax()) {
            if (!self::_isLogin()) {
                Reaction::_return(8201);
            }
        } else {
            if (!self::_isLogin()) {
                f3()->set('SESSION.redirect', $_SERVER['REQUEST_URI']);
                f3()->reroute(f3()->get('uri') . '/login');
            }
        }
    }
}
