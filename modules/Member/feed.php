<?php

namespace F3CMS;

class fMember extends Feed
{
	public const MTB = 'member';
	public const MULTILANG = 0;

	public const ST_ENABLED = 'Enabled';
	public const ST_DISABLED = 'Disabled';
	public const OAUTH_ST_ENABLED = 'Enabled';
	public const OAUTH_ST_DISABLED = 'Disabled';

	public static function oneById($memberId)
	{
		$memberId = (int) $memberId;
		if ($memberId <= 0) {
			return null;
		}

		return mh()->get(self::fmTbl(), '*', [
			'id' => $memberId,
		]);
	}

	public static function oneEnabledById($memberId)
	{
		$memberId = (int) $memberId;
		if ($memberId <= 0) {
			return null;
		}

		return mh()->get(self::fmTbl(), '*', [
			'id' => $memberId,
			'status' => self::ST_ENABLED,
		]);
	}

	public static function oneByEmail($email)
	{
		$email = trim((string) $email);
		if ('' === $email) {
			return null;
		}

		if (!self::hasColumn(self::fmTbl(), 'email')) {
			return null;
		}

		return mh()->get(self::fmTbl(), '*', [
			'email' => $email,
		]);
	}

	public static function createMember($payload, $insertUser = 0)
	{
		$payload = is_array($payload) ? $payload : [];
		$insertUser = (int) $insertUser;

		$data = [
			'status' => isset($payload['status']) ? (string) $payload['status'] : self::ST_ENABLED,
			'display_name' => isset($payload['display_name']) ? trim((string) $payload['display_name']) : '',
			'insert_user' => $insertUser,
			'last_user' => $insertUser,
		];

		if (self::hasColumn(self::fmTbl(), 'email')) {
			$data['email'] = isset($payload['email']) ? trim((string) $payload['email']) : '';
		}

		mh()->insert(self::fmTbl(), $data);

		$memberId = (int) self::chkErr(mh()->id());
		if ($memberId <= 0) {
			return null;
		}

		return self::oneById($memberId);
	}

	public static function oneOauthBinding($provider, $providerUid)
	{
		$provider = strtolower(trim((string) $provider));
		$providerUid = trim((string) $providerUid);

		if ('' === $provider || '' === $providerUid) {
			return null;
		}

		return mh()->get(self::fmTbl('oauth'), '*', [
			'provider' => $provider,
			'provider_uid' => $providerUid,
		]);
	}

	public static function oneEnabledOauthBinding($provider, $providerUid)
	{
		$provider = strtolower(trim((string) $provider));
		$providerUid = trim((string) $providerUid);

		if ('' === $provider || '' === $providerUid) {
			return null;
		}

		return mh()->get(self::fmTbl('oauth'), '*', [
			'provider' => $provider,
			'provider_uid' => $providerUid,
			'bind_status' => self::OAUTH_ST_ENABLED,
		]);
	}

	public static function oauthBindingsByMemberId($memberId)
	{
		$memberId = (int) $memberId;
		if ($memberId <= 0) {
			return [];
		}

		$rows = mh()->select(self::fmTbl('oauth'), '*', [
			'member_id' => $memberId,
			'ORDER' => ['id' => 'ASC'],
		]);

		return is_array($rows) ? $rows : [];
	}

	public static function bindOauthAccount($memberId, $provider, $providerUid, $profile = [], $insertUser = 0)
	{
		$memberId = (int) $memberId;
		$provider = strtolower(trim((string) $provider));
		$providerUid = trim((string) $providerUid);
		$profile = is_array($profile) ? $profile : [];
		$insertUser = (int) $insertUser;

		if ($memberId <= 0 || '' === $provider || '' === $providerUid) {
			return null;
		}

		$existing = self::oneOauthBinding($provider, $providerUid);
		if (!empty($existing)) {
			return $existing;
		}

		mh()->insert(self::fmTbl('oauth'), [
			'member_id' => $memberId,
			'provider' => $provider,
			'provider_uid' => $providerUid,
			'provider_email' => isset($profile['email']) ? trim((string) $profile['email']) : '',
			'provider_name' => isset($profile['name']) ? trim((string) $profile['name']) : '',
			'provider_avatar' => isset($profile['avatar']) ? trim((string) $profile['avatar']) : '',
			'raw_profile' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'bind_status' => self::OAUTH_ST_ENABLED,
			'last_login_ts' => date('Y-m-d H:i:s'),
			'insert_user' => $insertUser,
			'last_user' => $insertUser,
		]);

		$bindingId = (int) self::chkErr(mh()->id());
		if ($bindingId <= 0) {
			return null;
		}

		return mh()->get(self::fmTbl('oauth'), '*', [
			'id' => $bindingId,
		]);
	}

	public static function updateOauthBindingSnapshot($bindingId, $profile = [], $lastUser = 0)
	{
		$bindingId = (int) $bindingId;
		$profile = is_array($profile) ? $profile : [];
		$lastUser = (int) $lastUser;

		if ($bindingId <= 0) {
			return false;
		}

		mh()->update(self::fmTbl('oauth'), [
			'provider_email' => isset($profile['email']) ? trim((string) $profile['email']) : '',
			'provider_name' => isset($profile['name']) ? trim((string) $profile['name']) : '',
			'provider_avatar' => isset($profile['avatar']) ? trim((string) $profile['avatar']) : '',
			'raw_profile' => json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			'last_login_ts' => date('Y-m-d H:i:s'),
			'last_user' => $lastUser,
		], [
			'id' => $bindingId,
		]);

		return true;
	}

	public static function touchOauthLogin($bindingId, $lastUser = 0)
	{
		$bindingId = (int) $bindingId;
		$lastUser = (int) $lastUser;

		if ($bindingId <= 0) {
			return false;
		}

		mh()->update(self::fmTbl('oauth'), [
			'last_login_ts' => date('Y-m-d H:i:s'),
			'last_user' => $lastUser,
		], [
			'id' => $bindingId,
		]);

		return true;
	}

	public static function oneEnabledMemberByOauth($provider, $providerUid)
	{
		$provider = strtolower(trim((string) $provider));
		$providerUid = trim((string) $providerUid);

		if ('' === $provider || '' === $providerUid) {
			return null;
		}

		$row = mh()->get(self::fmTbl('oauth') . '(o)', [
			'[>]' . self::fmTbl() . '(m)' => ['o.member_id' => 'id'],
		], [
			'm.*',
			'o.id(_binding_id)',
		], [
			'o.provider' => $provider,
			'o.provider_uid' => $providerUid,
			'o.bind_status' => self::OAUTH_ST_ENABLED,
			'm.status' => self::ST_ENABLED,
		]);

		return is_array($row) && !empty($row) ? $row : null;
	}

	public static function _setCurrent($member)
	{
		$member = is_array($member) ? $member : [];
		if (empty($member)) {
			return;
		}

		f3()->set('SESSION.cu_member', [
			'id' => isset($member['id']) ? (int) $member['id'] : 0,
			'email' => isset($member['email']) ? (string) $member['email'] : '',
			'name' => isset($member['display_name']) ? (string) $member['display_name'] : '',
			'avatar' => isset($member['avatar']) ? (string) $member['avatar'] : '',
			'has_login' => 1,
		]);
	}

	public static function _clearCurrent()
	{
		f3()->clear('SESSION.cu_member');
	}

	public static function _current($column = 'id')
	{
		$cu = f3()->get('SESSION.cu_member');
		$str = '';

		if (isset($cu) && '*' != $column && isset($cu[$column])) {
			$str = $cu[$column];
		}

		return ('*' == $column) ? $cu : $str;
	}

	public static function heraldryCodesByMemberId($memberId)
	{
		$memberId = (int) $memberId;
		if ($memberId <= 0) {
			return [];
		}

		$rows = mh()->select(self::fmTbl('heraldry') . '(r)', [
			'[>]' . tpf() . 'heraldry(h)' => ['r.heraldry_id' => 'id'],
		], [
			'h.slug',
		], [
			'r.member_id' => $memberId,
			'h.status' => self::ST_ENABLED,
		]);

		if (!is_array($rows) || empty($rows)) {
			return [];
		}

		return array_values(array_unique(array_filter(array_map(function ($row) {
			return isset($row['slug']) ? trim((string) $row['slug']) : '';
		}, $rows))));
	}

	public static function oneSeenTarget($memberId, $target, $rowId)
	{
		$memberId = (int) $memberId;
		$rowId = (int) $rowId;
		$target = trim((string) $target);

		if ($memberId <= 0 || $rowId <= 0 || '' === $target) {
			return null;
		}

		return mh()->get(self::fmTbl('seen'), '*', [
			'member_id' => $memberId,
			'target' => $target,
			'row_id' => $rowId,
		]);
	}

	public static function seenTargetMapByMemberId($memberId)
	{
		$memberId = (int) $memberId;
		if ($memberId <= 0) {
			return [];
		}

		$rows = mh()->select(self::fmTbl('seen'), [
			'target',
			'row_id',
		], [
			'member_id' => $memberId,
		]);

		if (!is_array($rows) || empty($rows)) {
			return [];
		}

		$map = [];

		foreach ($rows as $row) {
			$target = isset($row['target']) ? trim((string) $row['target']) : '';
			$rowId = isset($row['row_id']) ? (int) $row['row_id'] : 0;

			if ('' === $target || $rowId <= 0) {
				continue;
			}

			if (!isset($map[$target])) {
				$map[$target] = [];
			}

			$map[$target][] = $rowId;
		}

		foreach ($map as $target => $rowIds) {
			$map[$target] = array_values(array_unique(array_map('intval', $rowIds)));
		}

		return $map;
	}

	public static function createSeenTarget($memberId, $target, $rowId, $source, $insertUser = 0)
	{
		$memberId = (int) $memberId;
		$rowId = (int) $rowId;
		$target = trim((string) $target);
		$source = trim((string) $source);
		$insertUser = (int) $insertUser;

		if ($memberId <= 0 || $rowId <= 0 || '' === $target || '' === $source) {
			return null;
		}

		$existing = self::oneSeenTarget($memberId, $target, $rowId);
		if (!empty($existing)) {
			$existing['_created'] = false;

			return $existing;
		}

		mh()->insert(self::fmTbl('seen'), [
			'member_id' => $memberId,
			'target' => $target,
			'row_id' => $rowId,
			'source' => $source,
			'insert_ts' => date('Y-m-d H:i:s'),
			'insert_user' => $insertUser,
		]);

		$seenId = (int) self::chkErr(mh()->id());
		if ($seenId <= 0) {
			return null;
		}

		$created = mh()->get(self::fmTbl('seen'), '*', [
			'id' => $seenId,
		]);

		if (empty($created) || !is_array($created)) {
			return null;
		}

		$created['_created'] = true;

		return $created;
	}

	private static function hasColumn($tableName, $columnName)
	{
		$tableName = trim((string) $tableName);
		$columnName = trim((string) $columnName);

		if ('' === $tableName || '' === $columnName) {
			return false;
		}

		$columns = mh()->query('SHOW COLUMNS FROM `' . $tableName . '` LIKE :column_name', [
			':column_name' => $columnName,
		])->fetchAll();

		return is_array($columns) && !empty($columns);
	}
}