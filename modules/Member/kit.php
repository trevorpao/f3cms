<?php

namespace F3CMS;

class kMember extends Kit
{
    private const CONTEXT_FIELDS = [
        'member_id',
        'watched_video_codes',
        'exam_scores',
        'heraldry_codes',
        'member_seen_targets',
        'account_balance',
        'account_status',
    ];

    public static function preloadEventRuleContext($memberId, $overrides = [])
    {
        $memberId = (int) $memberId;
        if ($memberId <= 0) {
            throw new \RuntimeException('Member context requires a valid member_id.');
        }

        $member = fMember::oneEnabledById($memberId);
        if (empty($member)) {
            throw new \RuntimeException('Member context member not found or disabled for member_id: ' . $memberId);
        }

        $account = fManaccount::oneByMemberId($memberId);

        $payload = [
            'member_id' => $memberId,
            'watched_video_codes' => [],
            'exam_scores' => [],
            'heraldry_codes' => fMember::heraldryCodesByMemberId($memberId),
            'member_seen_targets' => fMember::seenTargetMapByMemberId($memberId),
            'account_balance' => null,
            'account_status' => null,
        ];

        if (!empty($account)) {
            $payload['account_balance'] = isset($account['balance']) ? (int) $account['balance'] : null;
            $payload['account_status'] = isset($account['status']) ? (string) $account['status'] : null;
        }

        foreach (self::filterOverrides($overrides) as $field => $value) {
            $payload[$field] = $value;
        }

        $payload['member_id'] = $memberId;
        $payload['watched_video_codes'] = self::normalizeStringList($payload['watched_video_codes']);
        $payload['heraldry_codes'] = self::normalizeStringList($payload['heraldry_codes']);
        $payload['exam_scores'] = is_array($payload['exam_scores']) ? $payload['exam_scores'] : [];
        $payload['member_seen_targets'] = self::normalizeSeenTargetMap($payload['member_seen_targets']);
        $payload['account_balance'] = null === $payload['account_balance'] ? null : (int) $payload['account_balance'];
        $payload['account_status'] = null === $payload['account_status'] ? null : (string) $payload['account_status'];

        return $payload;
    }

    public static function createEventRuleContext($memberId, $overrides = [])
    {
        if (!class_exists(EventRulePlayerContext::class)) {
            class_exists(EventRuleEngine::class);
        }

        return new EventRulePlayerContext(self::preloadEventRuleContext($memberId, $overrides));
    }

    public static function normalizeOauthProfile($oauthProfile)
    {
        $oauthProfile = is_array($oauthProfile) ? $oauthProfile : [];

        if (isset($oauthProfile['auth']) && is_array($oauthProfile['auth'])) {
            $oauthProfile = $oauthProfile['auth'];
        }

        $info = isset($oauthProfile['info']) && is_array($oauthProfile['info']) ? $oauthProfile['info'] : [];
        $provider = isset($oauthProfile['provider']) ? strtolower(trim((string) $oauthProfile['provider'])) : '';

        if ('' === $provider && isset($oauthProfile['strategy'])) {
            $provider = strtolower(trim((string) $oauthProfile['strategy']));
        }

        $email = isset($info['email']) ? trim((string) $info['email']) : '';
        if ('' === $email && isset($oauthProfile['email'])) {
            $email = trim((string) $oauthProfile['email']);
        }

        $name = isset($info['name']) ? trim((string) $info['name']) : '';
        if ('' === $name && isset($oauthProfile['name'])) {
            $name = trim((string) $oauthProfile['name']);
        }

        $avatar = isset($info['image']) ? trim((string) $info['image']) : '';
        if ('' === $avatar && isset($info['picture'])) {
            $avatar = trim((string) $info['picture']);
        }
        if ('' === $avatar && isset($oauthProfile['avatar'])) {
            $avatar = trim((string) $oauthProfile['avatar']);
        }

        return [
            'provider' => $provider,
            'uid' => isset($oauthProfile['uid']) ? trim((string) $oauthProfile['uid']) : '',
            'email' => $email,
            'name' => $name,
            'avatar' => $avatar,
            'email_verified' => self::toBool(
                $oauthProfile['email_verified']
                ?? $oauthProfile['verified']
                ?? $info['email_verified']
                ?? null
            ),
            'raw_profile' => $oauthProfile,
        ];
    }

    public static function loginOrRegisterByOauth($oauthProfile)
    {
        $profile = self::normalizeOauthProfile($oauthProfile);
        if ('' === $profile['provider'] || '' === $profile['uid']) {
            throw new \InvalidArgumentException('OAuth profile requires provider and uid.');
        }

        $boundMember = fMember::oneEnabledMemberByOauth($profile['provider'], $profile['uid']);
        if (!empty($boundMember)) {
            fMember::updateOauthBindingSnapshot($boundMember['_binding_id'], $profile, (int) $boundMember['id']);
            self::createMemberSession($boundMember);

            return [
                'member' => $boundMember,
                'is_new' => false,
                'is_bound' => true,
            ];
        }

        $existingMember = null;
        $verifiedEmail = self::extractVerifiedEmail($profile);
        if ('' !== $verifiedEmail) {
            $existingMember = fMember::oneByEmail($verifiedEmail);
        }

        if (empty($existingMember)) {
            return self::registerByOauth($profile);
        }

        return self::mergeExistingMemberByOauth($existingMember, $profile);
    }

    public static function registerByOauth($oauthProfile)
    {
        $profile = self::normalizeOauthProfile($oauthProfile);
        $member = null;

        mh()->begin();

        try {
            $member = fMember::createMember(self::buildMemberPayload($profile));
            if (empty($member) || empty($member['id'])) {
                throw new \RuntimeException('Create member failed.');
            }

            self::bindOauthToMember((int) $member['id'], $profile, (int) $member['id']);

            mh()->commit();
        } catch (\Throwable $e) {
            mh()->rollback();

            throw $e;
        }

        $member = fMember::oneEnabledById((int) $member['id']);
        if (empty($member)) {
            throw new \RuntimeException('Created member cannot be loaded.');
        }

        self::createMemberSession($member);

        return [
            'member' => $member,
            'is_new' => true,
            'is_bound' => true,
        ];
    }

    public static function mergeExistingMemberByOauth($member, $oauthProfile)
    {
        $member = is_array($member) ? $member : [];
        $profile = self::normalizeOauthProfile($oauthProfile);

        if (empty($member) || empty($member['id'])) {
            throw new \InvalidArgumentException('Existing member is required for merge.');
        }

        if (!self::canAutoMergeByOauth($member, $profile)) {
            throw new \RuntimeException('OAuth account cannot be auto-merged with existing member.');
        }

        mh()->begin();

        try {
            self::bindOauthToMember((int) $member['id'], $profile, (int) $member['id']);
            mh()->commit();
        } catch (\Throwable $e) {
            mh()->rollback();

            throw $e;
        }

        $member = fMember::oneEnabledById((int) $member['id']);
        if (empty($member)) {
            throw new \RuntimeException('Merged member cannot be loaded.');
        }

        self::createMemberSession($member);

        return [
            'member' => $member,
            'is_new' => false,
            'is_bound' => true,
        ];
    }

    public static function bindOauthToMember($memberId, $oauthProfile, $operatorId = 0)
    {
        $memberId = (int) $memberId;
        $operatorId = (int) $operatorId;
        $profile = self::normalizeOauthProfile($oauthProfile);

        if ($memberId <= 0) {
            throw new \InvalidArgumentException('bindOauthToMember requires a valid member id.');
        }

        $existingBinding = fMember::oneOauthBinding($profile['provider'], $profile['uid']);
        if (!empty($existingBinding)) {
            if ((int) $existingBinding['member_id'] !== $memberId) {
                throw new \RuntimeException('OAuth account is already bound to another member.');
            }

            fMember::updateOauthBindingSnapshot((int) $existingBinding['id'], $profile, $operatorId);

            return $existingBinding;
        }

        $binding = fMember::bindOauthAccount($memberId, $profile['provider'], $profile['uid'], $profile, $operatorId);
        if (empty($binding) || empty($binding['id'])) {
            throw new \RuntimeException('Bind OAuth account failed.');
        }

        return $binding;
    }

    public static function createMemberSession($member)
    {
        $member = is_array($member) ? $member : [];
        if (empty($member) || empty($member['id'])) {
            throw new \InvalidArgumentException('createMemberSession requires member payload.');
        }

        fMember::_setCurrent($member);

        return $member;
    }

    public static function canAutoMergeByOauth($member, $oauthProfile)
    {
        $member = is_array($member) ? $member : [];
        $profile = self::normalizeOauthProfile($oauthProfile);

        if (empty($member) || empty($member['id'])) {
            return false;
        }

		if ('google' !== $profile['provider']) {
			return false;
		}

        if (isset($member['status']) && fMember::ST_ENABLED !== (string) $member['status']) {
            return false;
        }

        $verifiedEmail = self::extractVerifiedEmail($profile);
        if ('' === $verifiedEmail) {
            return false;
        }

        return !empty($member['email']) && 0 === strcasecmp((string) $member['email'], $verifiedEmail);
    }

    public static function extractVerifiedEmail($oauthProfile)
    {
        $profile = self::normalizeOauthProfile($oauthProfile);

        if (empty($profile['email'])) {
            return '';
        }

        if (true !== $profile['email_verified']) {
            return '';
        }

        return strtolower(trim((string) $profile['email']));
    }

    private static function filterOverrides($overrides)
    {
        if (!is_array($overrides) || empty($overrides)) {
            return [];
        }

        return array_intersect_key($overrides, array_flip(self::CONTEXT_FIELDS));
    }

    private static function normalizeStringList($values)
    {
        if (!is_array($values) || empty($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $values))));
    }

    private static function normalizeSeenTargetMap($values)
    {
        if (!is_array($values) || empty($values)) {
            return [];
        }

        $map = [];

        foreach ($values as $target => $rowIds) {
            $target = trim((string) $target);
            if ('' === $target || !is_array($rowIds)) {
                continue;
            }

            $map[$target] = array_values(array_unique(array_filter(array_map('intval', $rowIds), function ($rowId) {
                return $rowId > 0;
            })));
        }

        return $map;
    }

    private static function buildMemberPayload($profile)
    {
        $profile = self::normalizeOauthProfile($profile);
        $displayName = $profile['name'];

        if ('' === $displayName && '' !== $profile['email']) {
            $displayName = strstr($profile['email'], '@', true);
        }

        if ('' === $displayName) {
            $displayName = ucfirst($profile['provider']) . ' User';
        }

        return [
            'status' => fMember::ST_ENABLED,
            'display_name' => $displayName,
            'email' => $profile['email'],
        ];
    }

    private static function toBool($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if (null === $value) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}