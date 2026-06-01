<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Kit boundary for backend staff account rules, invite delivery, login gate,
 *   and validation policy around the Staff module's identity surface.
 *
 * Design Intent
 * - Keep backend staff validation, invite-mail coordination, and login guard behavior
 *   close to the Staff owner module while preserving the FORKS split: this Kit owns
 *   reusable owner-side rules and gating helpers, while Feed/Reaction collaborators keep
 *   persisted staff truth and request orchestration.
 *
 * Strict Constraints
 * - This Kit must not absorb shared auth runtime responsibilities or turn into a generic
 *   form-validation bin for unrelated modules.
 * - Account uniqueness policy, invite semantics, and backend login gating may be exposed
 *   here, but staff persistence ownership remains with the Staff feed.
 *
 * Porting Guidelines
 * - Port as the Staff module's owner-side validation and login-gate helper layer.
 * - Preserve the split between reusable backend identity rules here and request/session
 *   orchestration in Staff reactions or shared runtime surfaces.
 */
class kStaff extends Kit
{
    /**
     * Architecture Role
     * - Deliver backend invite mail for staff account activation.
     *
     * Strict Constraints
     * - Mail-body preparation may happen here, but account truth and invite lifecycle
     *   ownership remain with Staff collaborators.
     *
     * Porting Note
     * - Port as the owner-side backend invite delivery helper.
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

    /**
     * Architecture Role
     * - Enforce staff-account uniqueness against the persisted backend identity set.
     *
     * Strict Constraints
     * - This helper evaluates owner-side uniqueness policy only; it must not replace
     *   canonical persistence ownership in the Staff feed.
     *
     * Porting Note
     * - Port as the backend staff account uniqueness rule helper.
     */
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

    /**
     * Architecture Role
     * - Expose the canonical staff account validation rule set for backend save flows.
     *
     * Strict Constraints
     * - Rule composition may happen here, but downstream validation orchestration remains
     *   outside this Kit.
     *
     * Porting Note
     * - Port as the reusable account-rule builder for Staff forms.
     */
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

    /**
     * Architecture Role
     * - Publish the backend staff validation map for save, login, update, reset, and
     *   token-verify flows.
     *
     * Strict Constraints
     * - This method defines owner-side validation policy only; request parsing and error
     *   response handling remain the responsibility of callers.
     *
     * Porting Note
     * - Port as the canonical Staff validation-rules registry.
     */
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
     * Architecture Role
     * - Report whether the current backend staff session is authenticated.
     *
     * Strict Constraints
     * - This helper reads owner-side session projection only; shared session runtime and
     *   login state persistence remain outside this Kit.
     *
     * Porting Note
     * - Port as the lightweight backend staff login-state probe.
     */
    public static function _isLogin()
    {
        $status = fStaff::_current('has_login');

        return (1 == $status) ? 1 : 0;
    }

    /**
     * Architecture Role
     * - Enforce backend login gating for ajax and page routes.
     *
     * Strict Constraints
     * - Gate handling may happen here, but request orchestration and shared auth runtime
     *   must not collapse into this Kit.
     *
     * Porting Note
     * - Port as the reusable backend login-guard helper for Staff-protected routes.
     */
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
