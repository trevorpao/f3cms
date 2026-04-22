<?php

namespace F3CMS;

class rMember extends Reaction
{
	const RTN_DONE = 'Done';
	const RTN_LOGIN_FAILED = 'LoginFailed';

	public function do_oauthLogin($f3, $args)
	{
		$req = parent::_getReq();

		if (empty($req['provider']) || empty($req['credential'])) {
			return parent::_return(8004, [
				'msg' => 'provider and credential are required',
				'code' => self::RTN_LOGIN_FAILED,
			]);
		}

		try {
			$oauthResult = Oauth::byToken($req['provider'], $req['credential']);
			if (isset($oauthResult['error'])) {
				throw new \RuntimeException($oauthResult['error_description'] ?? 'OAuth token validation failed.');
			}

			$result = kMember::loginOrRegisterByOauth($oauthResult);

			return parent::_return(1, [
				'msg' => 'login success',
				'code' => self::RTN_DONE,
				'member_id' => isset($result['member']['id']) ? (int) $result['member']['id'] : 0,
				'is_new' => !empty($result['is_new']),
			]);
		} catch (\Throwable $e) {
			return parent::_return(8004, [
				'msg' => $e->getMessage(),
				'code' => self::RTN_LOGIN_FAILED,
			]);
		}
	}
}