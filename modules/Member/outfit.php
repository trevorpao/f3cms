<?php

namespace F3CMS;

class oMember extends Outfit
{
	public static function redirect($args)
	{
		$provider = strtolower(trim((string) ($args['provider'] ?? '')));
		if ('' === $provider || !Oauth::instance()->hasHandler($provider)) {
			f3()->error(404);
		}

		$successRedirect = self::normalizeRedirectPath(f3()->get('GET.redirect'));
		$errorRedirect = self::normalizeRedirectPath(f3()->get('GET.error_redirect'));

		f3()->clear('SESSION.oauth_error');

		if (null !== $successRedirect) {
			f3()->set('SESSION.oauth_success_redirect', $successRedirect);
		} else {
			f3()->clear('SESSION.oauth_success_redirect');
		}

		if (null !== $errorRedirect) {
			f3()->set('SESSION.oauth_error_redirect', $errorRedirect);
		} else {
			f3()->clear('SESSION.oauth_error_redirect');
		}

		f3()->reroute('/auth/' . $provider);
	}

	public static function _oauth($payload)
	{
		try {
			if (empty($payload) || !is_array($payload)) {
				throw new \RuntimeException('OAuth callback payload missing.');
			}

			kMember::loginOrRegisterByOauth($payload);

			$redirectUri = f3()->get('SESSION.oauth_success_redirect');
			if (empty($redirectUri)) {
				$redirectUri = '/';
			}

			f3()->clear('SESSION.oauth_success_redirect');
			f3()->clear('SESSION.oauth_error_redirect');

			f3()->reroute($redirectUri);
		} catch (\Throwable $e) {
			f3()->set('SESSION.oauth_error', $e->getMessage());

			$redirectUri = f3()->get('SESSION.oauth_error_redirect');
			if (empty($redirectUri)) {
				$redirectUri = '/login';
			}

			f3()->clear('SESSION.oauth_success_redirect');
			f3()->clear('SESSION.oauth_error_redirect');

			f3()->reroute($redirectUri);
		}
	}

	private static function normalizeRedirectPath($redirectPath)
	{
		$redirectPath = trim((string) $redirectPath);
		if ('' === $redirectPath) {
			return null;
		}

		if ('/' !== substr($redirectPath, 0, 1)) {
			return null;
		}

		if (0 === strpos($redirectPath, '//')) {
			return null;
		}

		return $redirectPath;
	}
}