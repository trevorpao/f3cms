<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Shared module-owned rule base for validation groups and lightweight rule helpers that are reused by module Kit classes.
 *
 * Design Intent
 * - Keep reusable rule-shaping and validation defaults close to the Kit boundary without promoting owner-side business judgment into generic libs.
 *
 * Strict Constraints
 * - This class must not become a generic service layer for entity truth, persistence coordination, or module-specific duty judgment.
 * - If a rule depends on one module's business ownership, keep that specialization in the owning module Kit instead of widening this shared base.
 *
 * Porting Guidelines
 * - Port as a shared validation-contract base or rule helper layer.
 * - Preserve the split between reusable rule composition here and owner-specific validation semantics in module Kits.
 */
class Kit extends Module
{
    /**
     * Architecture Purpose
     * - Resolve the effective validation rule group for the current Kit class.
     *
     * Design Intent
     * - Allow module Kits to override or extend shared rule groups while keeping rule lookup centralized at the Kit boundary.
     *
     * Strict Constraints
     * - This helper chooses rule ownership; it must not fetch runtime context, infer business state, or coordinate persistence.
     *
     * Porting Guidelines
     * - Port as a rule-merge helper where child Kits can overlay shared defaults without changing owner boundaries.
     */
    public static function rule($group = 'default')
    {
        // Get the current class and its rules.
        $that   = get_called_class();
        $rules  = $that::rules();

        // Get the parent class rules.
        $parent = self::rules();

        // Default to the 'default' group rules.
        $rtn = $parent['default'];

        // Check if the group exists in the parent rules.
        if (array_key_exists($group, $parent)) {
            $rtn = $parent[$group];
        }

        // Check if the group exists in the current class rules.
        if (array_key_exists($group, $rules)) {
            $rtn = $rules[$group];
        }

        return $rtn;
    }

    /**
        * Architecture Purpose
        * - Provide a shared display-width helper for validation or UI-adjacent rule checks.
        *
        * Design Intent
        * - Keep multibyte-aware width calculation in one shared helper so module Kits do not duplicate low-level string-width logic.
        *
        * Strict Constraints
        * - This helper is formatting-oriented only; it must not absorb locale policy or module-specific field rules.
        *
        * Porting Guidelines
        * - Port to the platform's multibyte-safe width calculation primitive, but keep higher-level validation policy in owner Kits.
     */
    public static function strWidth($str = '')
    {
        // Calculate the length of the string in multibyte and single-byte terms.
        $mblen = mb_strlen($str);
        $len   = strlen($str);

        // Calculate the display width by accounting for multibyte characters.
        $clen = ($len - $mblen) / 2;

        return $clen + $mblen;
    }

    /**
        * Architecture Purpose
        * - Define the baseline shared rule groups that module Kits may inherit.
        *
        * Design Intent
        * - Keep only cross-module validation defaults here so module Kits start from a stable contract but remain free to own their specific rule semantics.
        *
        * Strict Constraints
        * - Do not turn this shared rule map into a dumping ground for entity-specific policy or caller-specific workflow assumptions.
        *
        * Porting Guidelines
        * - Port as static baseline validation metadata or DTO rules, then layer owner-specific rules in derived module Kits.
     */
    public static function rules()
    {
        return [
            'default'    => [
                // Default rules can be defined here.
            ],
            'del'        => [
                'pid' => 'required|integer', // Rule for deleting: 'pid' must be an integer and is required.
            ],
            'upload'     => [
                'photo' => 'required|uploaded_file|max:5M|mimes:jpeg,png', // Rule for uploading photos.
            ],
            'uploadFile' => [
                'file' => 'required|uploaded_file|max:10M|mimes:pdf,zip,xls,xlsx', // Rule for uploading files.
            ],
        ];
    }
}
