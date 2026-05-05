<?php

namespace F3CMS;

class kDoorman extends Kit
{
    protected static array $blacklistCache = [];

    protected static function normalizePassword(string $password): string
    {
        return strtolower(trim($password));
    }

    protected static function loadBlacklist(string $normalizedPassword): bool
    {
        if (array_key_exists($normalizedPassword, self::$blacklistCache)) {
            return self::$blacklistCache[$normalizedPassword];
        }

        self::$blacklistCache[$normalizedPassword] = fDoorman::hasBlacklistedPassword($normalizedPassword);

        return self::$blacklistCache[$normalizedPassword];
    }

    public static function isBlacklistedPassword(string $password, array $context = []): bool
    {
        $normalizedPassword = self::normalizePassword($password);

        if ('' === $normalizedPassword) {
            return false;
        }

        if (self::loadBlacklist($normalizedPassword)) {
            return true;
        }

        $account = self::normalizePassword((string) ($context['account'] ?? ''));
        $email = self::normalizePassword((string) ($context['email'] ?? ''));
        $emailPrefix = '';

        if ('' !== $email && false !== strpos($email, '@')) {
            $emailPrefix = strstr($email, '@', true);
        }

        foreach ([$account, $emailPrefix] as $identifier) {
            if ('' === $identifier || strlen($identifier) < 3) {
                continue;
            }

            if (false !== strpos($normalizedPassword, $identifier)) {
                return true;
            }
        }

        return false;
    }
}