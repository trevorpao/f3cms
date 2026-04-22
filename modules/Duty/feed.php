<?php

namespace F3CMS;

class fDuty extends Feed
{
    public const MTB = 'duty';
    public const MULTILANG = 0;

    public const ST_ENABLED = 'Enabled';
    public const ST_DISABLED = 'Disabled';

    public static function oneBySlug($slug)
    {
        if ('' === trim((string) $slug)) {
            return null;
        }

        return mh()->get(self::fmTbl(), '*', [
            'slug' => trim((string) $slug),
        ]);
    }

    public static function enabledClaimRows()
    {
        $rows = mh()->select(self::fmTbl(), [
            'id',
            'slug',
            'claim',
        ], [
            'status' => self::ST_ENABLED,
        ]);

        return is_array($rows) ? $rows : [];
    }

    public static function loadRulePayload($dutyId, $column = 'claim')
    {
        if (!in_array($column, ['claim', 'factor', 'next'], true)) {
            throw new \InvalidArgumentException('Unsupported duty payload column: ' . $column);
        }

        $dutyId = (int) $dutyId;
        if ($dutyId <= 0) {
            return null;
        }

        return mh()->get(self::fmTbl(), [$column], [
            'id' => $dutyId,
        ]);
    }
}