<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Owner-side Kit boundary for member identity/session helpers, OAuth binding and merge
 *   flow, registration bridge, and EventRule player-context preload around the Member
 *   module.
 *
 * Design Intent
 * - Keep reusable member identity rules and OAuth/session bridge logic close to the
 *   Member owner module while preserving the FORKS split: this Kit owns owner-side
 *   normalization, merge judgment, transactional bind/register helpers, and reusable
 *   session/context builders; Feed/Reaction collaborators keep persistence truth and
 *   request orchestration.
 *
 * Strict Constraints
 * - This Kit must not collapse into a generic social-login service or shared auth runtime.
 * - Member truth, binding persistence, and request/response transport remain owned by the
 *   Member collaborators and surrounding module layers.
 *
 * Porting Guidelines
 * - Port as the Member module's owner-side OAuth/session/business bridge helper layer.
 * - Preserve the split between reusable identity helpers here and persistence or request
 *   orchestration in Member feeds/reactions.
 */
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

    /**
     * Architecture Role
     * - Report whether the current member session is authenticated.
     *
     * Strict Constraints
     * - This helper reads owner-side session projection only; shared session runtime and
     *   persistence remain outside this Kit.
     *
     * Porting Note
     * - Port as the lightweight frontend/member login-state probe.
     */
    public static function _isLogin()
    {
        return (1 === (int) fMember::_current('has_login')) ? 1 : 0;
    }

    /**
     * Architecture Role
     * - Enforce member login gating, including the development mobile-token bridge.
     *
     * Strict Constraints
     * - Gate handling may happen here, but request transport and shared auth runtime must
     *   not collapse into this Kit.
     *
     * Porting Note
     * - Port as the reusable member login-guard helper with development token support.
     */
    public static function _chkLogin()
    {
        if (self::_isLogin()) {
            return 1;
        }

        $token = isset($_SERVER['HTTP_MOBILE_TOKEN']) ? trim((string) $_SERVER['HTTP_MOBILE_TOKEN']) : '';
        if ('' !== $token && 'develop' === (string) f3()->get('APP_ENV')) {
            $devToken = trim((string) f3()->get('DEV_TOKEN'));
            $devMemberId = (int) f3()->get('DEV_MEMBER_ID');

            if ('' !== $devToken && hash_equals($devToken, $token) && $devMemberId > 0) {
                $member = fMember::oneEnabledById($devMemberId);
                if (!empty($member)) {
                    self::createMemberSession($member);

                    return 1;
                }
            }
        }

        return Reaction::_return(8201, ['msg' => 'Member login required.']);
    }

    /**
     * Architecture Role
     * - Preload the owner-side EventRule player context for a member.
     *
     * Strict Constraints
     * - Context shaping may happen here, but member/account truth remains owned by Member
     *   and related feeds.
     *
     * Porting Note
     * - Port as the canonical member EventRule context preloader.
     */
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

    /**
     * Architecture Role
     * - Build the EventRule player-context object from preloaded member context data.
     *
     * Strict Constraints
     * - This helper assembles runtime context only; EventRule execution and member truth
     *   stay outside the Kit.
     *
     * Porting Note
     * - Port as the member-to-EventRule context object bridge.
     */
    public static function createEventRuleContext($memberId, $overrides = [])
    {
        if (!class_exists(EventRulePlayerContext::class)) {
            class_exists(EventRuleEngine::class);
        }

        return new EventRulePlayerContext(self::preloadEventRuleContext($memberId, $overrides));
    }

    /**
     * Architecture Role
     * - Normalize third-party OAuth profile payloads into the Member module's canonical
     *   owner-side shape.
     *
     * Strict Constraints
     * - Provider payload normalization may happen here, but provider transport contracts
     *   and persistence truth remain outside this Kit.
     *
     * Porting Note
     * - Port as the canonical OAuth profile normalizer for Member flows.
     */
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

    /**
     * Architecture Role
     * - Resolve OAuth login into bind, merge, or registration against the Member owner
     *   boundary.
     *
     * Strict Constraints
     * - Flow branching may happen here, but member persistence and request transport
     *   remain owned by surrounding collaborators.
     *
     * Porting Note
     * - Port as the main OAuth login-or-register decision helper for Member.
     */
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

    /**
     * Architecture Role
     * - Register a new member from OAuth data and trigger owner-side post-register work.
     *
     * Strict Constraints
     * - Transaction coordination may happen here, but member/binding persistence truth
     *   remains with Member collaborators.
     *
     * Porting Note
     * - Port as the OAuth-based member registration bridge.
     */
    public static function registerByOauth($oauthProfile)
    {
        $profile = self::normalizeOauthProfile($oauthProfile);
        $member = null;
        $createdTasks = [];

        mh()->begin();

        try {
            $member = fMember::createMember(self::buildMemberPayload($profile));
            if (empty($member) || empty($member['id'])) {
                throw new \RuntimeException('Create member failed.');
            }

            self::bindOauthToMember((int) $member['id'], $profile, (int) $member['id']);
            $createdTasks = kDuty::createTasksForTrigger('Member::Register', (int) $member['id'], (int) $member['id']);

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
            'created_tasks' => $createdTasks,
        ];
    }

    /**
     * Architecture Role
     * - Merge an OAuth identity into an existing member when owner-side auto-merge rules
     *   permit it.
     *
     * Strict Constraints
     * - Merge judgment and bind transaction may happen here, but canonical member truth
     *   remains Member-owned.
     *
     * Porting Note
     * - Port as the owner-side OAuth merge bridge for existing members.
     */
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

    /**
     * Architecture Role
     * - Bind a normalized OAuth identity to a member account.
     *
     * Strict Constraints
     * - Binding coordination may happen here, but persistence truth and conflict rules
     *   remain owned by Member collaborators.
     *
     * Porting Note
     * - Port as the owner-side OAuth account binding helper.
     */
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

    /**
     * Architecture Role
     * - Establish the current member session snapshot from a member payload.
     *
     * Strict Constraints
     * - Session projection may be set here, but shared session runtime and persisted
     *   member truth remain outside this Kit.
     *
     * Porting Note
     * - Port as the member-session snapshot helper after successful auth/bind flows.
     */
    public static function createMemberSession($member)
    {
        $member = is_array($member) ? $member : [];
        if (empty($member) || empty($member['id'])) {
            throw new \InvalidArgumentException('createMemberSession requires member payload.');
        }

        fMember::_setCurrent($member);

        return $member;
    }

    /**
     * Architecture Role
     * - Decide whether an existing member may be auto-merged with an OAuth profile.
     *
     * Strict Constraints
     * - This helper enforces owner-side merge policy only; it must not become a generic
     *   identity-linking rule engine.
     *
     * Porting Note
     * - Port as the Member-specific OAuth auto-merge gate.
     */
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

    /**
     * Architecture Role
     * - Extract a verified email from a normalized OAuth profile.
     *
     * Strict Constraints
     * - This helper shapes trusted email projection only; provider verification truth
     *   remains in the incoming profile data.
     *
     * Porting Note
     * - Port as the Member-specific verified-email extractor for OAuth flows.
     */
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

    /**
     * Architecture Role
     * - Filter override payloads down to the allowed member EventRule context fields.
     *
     * Strict Constraints
     * - This helper constrains owner-side context shaping only; arbitrary runtime payloads
     *   must not leak through this Kit.
     *
     * Porting Note
     * - Port as the whitelist gate for member context override fields.
     */
    private static function filterOverrides($overrides)
    {
        if (!is_array($overrides) || empty($overrides)) {
            return [];
        }

        return array_intersect_key($overrides, array_flip(self::CONTEXT_FIELDS));
    }

    /**
     * Architecture Role
     * - Normalize owner-side string-list payloads used in member context shaping.
     *
     * Strict Constraints
     * - This helper exists for local context normalization only; it must not become a
     *   generic string-array utility surface.
     *
     * Porting Note
     * - Port as the member-context string-list normalizer.
     */
    private static function normalizeStringList($values)
    {
        if (!is_array($values) || empty($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, $values))));
    }

    /**
     * Architecture Role
     * - Normalize the member seen-target map used in EventRule/player context payloads.
     *
     * Strict Constraints
     * - This helper shapes owner-side context data only; canonical seen-target truth stays
     *   in Member collaborators.
     *
     * Porting Note
     * - Port as the member seen-target map normalizer.
     */
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

    /**
     * Architecture Role
     * - Build the canonical member-create payload from a normalized OAuth profile.
     *
     * Strict Constraints
     * - Payload shaping may happen here, but persisted member truth remains owned by the
     *   Member feed.
     *
     * Porting Note
     * - Port as the member registration payload builder for OAuth onboarding.
     */
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

    /**
     * Architecture Role
     * - Normalize loosely typed truthy/falsey values into booleans for OAuth payloads.
     *
     * Strict Constraints
     * - This helper exists for local Member payload normalization only; it must not grow
     *   into a generic boolean utility surface.
     *
     * Porting Note
     * - Port as the OAuth-profile boolean normalizer used by Member.
     */
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