<?php

/**
 * Architecture Purpose
 * - Shared global runtime surface for low-level F3CMS entry helpers, transport/environment helpers, and cross-cutting encoding/security utilities.
 *
 * Design Intent
 * - Keep legacy global runtime entry points such as `f3()`, `mh()`, and `rc()` explicit so ports can preserve their boundary while gradually narrowing dependence on global helpers.
 *
 * Strict Constraints
 * - This file must not become a generic dumping ground for owner-side business logic, entity truth, or module coordination.
 * - Global helper convenience does not override FORKS boundaries; persistence ownership, auth policy, and module-specific workflows stay in their owning classes.
 *
 * Porting Guidelines
 * - Port as a file-level runtime helper surface or compatibility layer.
 * - Preserve the meaning of each helper, but prefer replacing global access with explicit injected/runtime-owned services where the target platform supports it.
 */

/**
 * Architecture Purpose
 * - Expose the shared Fat-Free base runtime instance through one compatibility helper.
 *
 * Design Intent
 * - Keep legacy global runtime access stable while making the entry point explicit for future ports.
 *
 * Strict Constraints
 * - This helper returns the framework runtime only; it must not absorb feature-specific bootstrap.
 *
 * Porting Guidelines
 * - Port as the compatibility accessor for the main application/runtime container.
 */
function f3()
{
    return Base::instance();
}

/**
 * Architecture Purpose
 * - Expose the shared database helper runtime instance.
 *
 * Design Intent
 * - Centralize lazy MHelper initialization behind one legacy compatibility entry.
 *
 * Strict Constraints
 * - This helper exposes shared persistence infrastructure only; it must not absorb entity-specific query or writeback policy.
 *
 * Porting Guidelines
 * - Port as a runtime-owned DB helper accessor or compatibility shim, not as a business repository.
 */
function mh($force = false)
{
    if (!f3()->exists('MH') || $force) {
        $mh = F3CMS\MHelper::init($force);
        f3()->set('MH', $mh);
    }

    return f3()->get('MH');
}

/**
 * Architecture Purpose
 * - Expose the shared Redis helper runtime instance.
 *
 * Design Intent
 * - Keep lazy Redis adapter access behind one explicit runtime entry.
 *
 * Strict Constraints
 * - This helper provides infrastructure access only; module-specific cache/session policy stays elsewhere.
 *
 * Porting Guidelines
 * - Port as a runtime-owned cache/Redis accessor or compatibility shim.
 */
function rc()
{
    if (!f3()->exists('RH')) {
        $rc = \F3CMS\RHelper::getInstance();
        f3()->set('RH', $rc);
    }

    return f3()->get('RH');
}

/**
 * Architecture Purpose
 * - Expose the shared web/HTTP utility instance.
 *
 * Design Intent
 * - Keep legacy access to framework web helpers explicit and centralized.
 *
 * Strict Constraints
 * - This helper remains transport-oriented only.
 *
 * Porting Guidelines
 * - Port as a compatibility accessor for the platform's HTTP/web helper surface.
 */
function cs()
{
    return Web::instance();
}

/**
 * Architecture Purpose
 * - Expose the current template-prefix runtime setting.
 *
 * Design Intent
 * - Keep lightweight template context access centralized in the shared runtime surface.
 *
 * Strict Constraints
 * - This helper returns runtime configuration only.
 *
 * Porting Guidelines
 * - Port as a small config/runtime accessor.
 */
function tpf()
{
    return f3()->get('tpf');
}

/**
 * Architecture Purpose
 * - Store a debugging or shared runtime value into the framework hive under the `_dzv` namespace.
 *
 * Design Intent
 * - Keep this legacy global side-channel explicit instead of scattering ad hoc hive writes.
 *
 * Strict Constraints
 * - This helper is a runtime stash only; it must not become an owner-side state container.
 *
 * Porting Guidelines
 * - Port only if a similar shared runtime stash is still needed; otherwise replace with explicit logging/debug facilities.
 */
function _dzv($key, $object)
{
    f3()->set('_dzv.' . $key, $object);
}

/**
 * Architecture Purpose
 * - Select between default and alternate text based on the current runtime language.
 *
 * Design Intent
 * - Keep a small legacy language-toggle helper near the global runtime surface.
 *
 * Strict Constraints
 * - This helper performs transport/presentation selection only; localization ownership remains elsewhere.
 *
 * Porting Guidelines
 * - Port as a thin language-branch helper if still needed, or replace with the target platform's i18n layer.
 */
function langTPS($default, $other)
{
    return (F3CMS\Module::_lang() == f3()->get('defaultLang')) ? $default : $other;
}

/**
 * Architecture Purpose
 * - Determine whether the current request should be treated as HTTPS.
 *
 * Design Intent
 * - Centralize proxy/header-aware HTTPS detection for shared runtime callers.
 *
 * Strict Constraints
 * - This helper only inspects transport headers/server vars; security policy remains elsewhere.
 *
 * Porting Guidelines
 * - Port as an environment helper aware of proxy-forwarded HTTPS indicators.
 */
function is_https()
{
    if (isset($_SERVER['HTTPS']) && 'on' === strtolower($_SERVER['HTTPS'])) {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && 'https' === $_SERVER['HTTP_X_FORWARDED_PROTO']) {
        return true;
    } elseif (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && 'on' === $_SERVER['HTTP_FRONT_END_HTTPS']) {
        return true;
    }

    return false;
}

/**
 * Architecture Purpose
 * - Check whether one IP matches a whitelist with optional wildcard entries.
 *
 * Design Intent
 * - Keep low-level whitelist matching in a shared runtime helper instead of scattering string-pattern checks.
 *
 * Strict Constraints
 * - This helper performs raw IP-pattern matching only; access-control ownership stays elsewhere.
 *
 * Porting Guidelines
 * - Port as a simple whitelist matcher with equivalent wildcard behavior.
 */
function isAllowedIP($ip, array $whitelist)
{
    $ip        = (string) $ip;
    $whitelist = array_map('trim', $whitelist);

    if (in_array($ip, $whitelist)) {
        // the given ip is found directly on the whitelist --allowed
        return true;
    }
    // go through all whitelisted ips
    foreach ($whitelist as $whitelistedIp) {
        $whitelistedIp = (string) $whitelistedIp;
        // find the wild card * in whitelisted ip (f.e. find position in "127.0.*" or "127*")
        $wildcardPosition = strpos($whitelistedIp, '*');
        if (false === $wildcardPosition) {
            // no wild card in whitelisted ip --continue searching
            continue;
        }
        // cut ip at the position where we got the wild card on the whitelisted ip
        // and add the wold card to get the same pattern
        if (substr($ip, 0, $wildcardPosition) . '*' === $whitelistedIp) {
            // f.e. we got
            //  ip "127.0.0.1"
            //  whitelisted ip "127.0.*"
            // then we compared "127.0.*" with "127.0.*"
            // return success
            return true;
        }
    }

    // return false on default
    return false;
}

/**
 * Architecture Purpose
 * - Infer a default runtime language from the browser's Accept-Language header.
 *
 * Design Intent
 * - Keep fallback browser-language detection in one shared helper for bootstrap flows.
 *
 * Strict Constraints
 * - This helper infers a coarse locale only; full localization policy remains elsewhere.
 *
 * Porting Guidelines
 * - Port as a small Accept-Language parser with the same coarse language mapping.
 */
function detectBrowserLang($default = 'en')
{
    $rtn = $default;

    switch (strtolower(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2))) {
        case 'zh':
        case 'cn':
        case 'tw':
        case 'hk':
            $rtn = 'tw';
            break;
    }

    return $rtn;
}

/**
 * Architecture Purpose
 * - Populate shared runtime CORS state for cross-origin requests.
 *
 * Design Intent
 * - Keep legacy CORS header/config shaping centralized instead of reproducing request-origin checks in multiple callers.
 *
 * Strict Constraints
 * - This helper only prepares transport-level CORS state; endpoint authorization stays elsewhere.
 *
 * Porting Guidelines
 * - Port as a request pipeline helper that applies the same allowed-origin semantics.
 */
function setCORS($allowedOrigins = [])
{
    $allowedOrigins = array_merge($allowedOrigins, ['http://127.0.0.1', 'http://localhost']);

    if (isset($_SERVER['HTTP_ORIGIN'])) {
        f3()->set('isCORS', 1);
        if (
            in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)  // for localhost testing
             || 'demo.sense-info.co' == $_SERVER['SERVER_NAME'] // for demosite testing
        ) {
            f3()->copy('HEADERS.Origin', 'CORS.origin');
            // f3()->set('CORS.origin', $_SERVER['HTTP_ORIGIN']);
            f3()->set('CORS.credentials', true);
            f3()->set('CORS.headers', 'X-Requested-With, X-Requested-Token, Mobile-Token, Content-Type, Content-Range, Content-Disposition, Origin, Accept');
            f3()->set('CORS.expose', true);
            f3()->set('CORS.ttl', 86400);
        }
    } else {
        f3()->set('isCORS', 0);
    }
}

/**
 * Architecture Purpose
 * - Exit immediately with the last SQL trace from the shared DB helper.
 *
 * Design Intent
 * - Preserve a small legacy debug escape hatch for inspecting the last query.
 *
 * Strict Constraints
 * - This helper is debug-only and must not be repurposed for business responses.
 *
 * Porting Guidelines
 * - Port only as a diagnostics helper if similar emergency SQL inspection is still needed.
 */
function fQuery()
{
    exit(mh()->last());
}

/**
 * Architecture Purpose
 * - Emit SQL/runtime trace diagnostics when the configured debug level permits it.
 *
 * Design Intent
 * - Keep low-level runtime tracing close to the global helper surface so modules do not invent parallel debug paths.
 *
 * Strict Constraints
 * - Tracing here is observational only; it must not change behavior.
 *
 * Porting Guidelines
 * - Port as optional runtime debug tracing tied to the platform's logging facilities.
 */
function rtTrace($flag = '', $debug = 0)
{
    if (f3()->get('DEBUG') >= $debug) {
        $logger = new Log('sql_trace.log');
        if ('' != $flag) {
            $logger->write($flag);
        }
        $logger->write(mh()->last());
    }
}

/**
 * Architecture Purpose
 * - Decode escaped Unicode sequences into UTF-8 text.
 *
 * Design Intent
 * - Keep one shared text-decoding helper for legacy payloads that still carry `\uXXXX` sequences.
 *
 * Strict Constraints
 * - This helper performs text transformation only.
 *
 * Porting Guidelines
 * - Port as a Unicode-escape decoder using the target platform's encoding utilities.
 */
function decodeUnicode($str)
{
    // Use an anonymous function to replace Unicode escape sequences with their UTF-8 equivalents.
    return preg_replace_callback('/\\u([0-9a-f]{4})/i', function ($matches) {
        return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
    }, $str);
}

/**
 * Architecture Purpose
 * - Strip ASCII control-range characters from a string for XML-safe output shaping.
 *
 * Design Intent
 * - Keep low-level cleanup of problematic control bytes in one shared text helper.
 *
 * Strict Constraints
 * - This helper performs text sanitation only; content policy remains elsewhere.
 *
 * Porting Guidelines
 * - Port as a regex-based control-character filter.
 */
function removeASCII($string)
{
    // https://stackoverflow.com/a/1176923
    $ascii = '/[\x00-\x1F\x7F\xA0]/u';

    return preg_replace($ascii, '', $string);
}

/**
 * Architecture Purpose
 * - Strip non-ASCII multibyte bytes from legacy text using the project's historical regex semantics.
 *
 * Design Intent
 * - Preserve this narrow cleanup helper for legacy integrations that need to remove CJK/high-byte content.
 *
 * Strict Constraints
 * - This helper performs raw text filtering only; language/business interpretation stays elsewhere.
 *
 * Porting Guidelines
 * - Port with care, preserving the current byte-oriented regex behavior rather than rewriting semantics silently.
 */
function removeZH($string)
{
    $ascii = '/[\x00-\x1F\x80-\xFF]/';

    return preg_replace($ascii, '', $string);
}

/**
 * Architecture Purpose
 * - Detect whether a string contains CJK characters.
 *
 * Design Intent
 * - Keep one shared Unicode-range detector for integrations that branch on CJK content.
 *
 * Strict Constraints
 * - This helper reports character presence only.
 *
 * Porting Guidelines
 * - Port as a Unicode-range regex check.
 */
function containsCJK($string)
{
    // 定義包含中、日、韓文字符的Unicode範圍的正則表達式
    $cjkPattern = '/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u';

    // 使用preg_match來檢測字串中是否含有中、日、韓文字元
    return preg_match($cjkPattern, $string);
}

/**
 * Architecture Purpose
 * - Validate a registration-id string against the legacy accepted formats.
 *
 * Design Intent
 * - Keep this project-specific pattern check in one shared helper rather than duplicating regexes.
 *
 * Strict Constraints
 * - This helper validates syntax only; registration ownership and business policy stay elsewhere.
 *
 * Porting Guidelines
 * - Port as a regex-based validator with the same strict/loose modes.
 */
function chkRegisterID($str, $mode = 'strict')
{
    if ('strict' == $mode) {
        return preg_match('/^[A]-\d{4}-[a-z]-\d{3}$/i', $str, $output);
    } else {
        return preg_match('/^[A]-\d{4}-[a-z]-\d{3}\D{0,4}$/i', $str, $output);
    }
}

/**
 * Architecture Purpose
 * - Ask the shared role layer whether the current staff context has one authority.
 *
 * Design Intent
 * - Keep this small legacy auth predicate centralized for callers that still rely on the global helper surface.
 *
 * Strict Constraints
 * - This helper delegates to the role layer only; privilege ownership stays in the auth modules.
 *
 * Porting Guidelines
 * - Port as a thin auth predicate that delegates to the owning role service.
 */
function canDo($authority = '')
{
    return F3CMS\fRole::hasAuth(F3CMS\fStaff::_current('priv'), $authority);
}

/**
 * Architecture Purpose
 * - Enforce login and authority presence through the shared auth/runtime layers.
 *
 * Design Intent
 * - Keep this legacy guard helper thin by delegating login enforcement and failure response to the owning auth/reaction classes.
 *
 * Strict Constraints
 * - This helper must not turn into a standalone auth policy engine.
 *
 * Porting Guidelines
 * - Port as a thin guard wrapper around the target platform's auth and response layers.
 */
function chkAuth($authority = '')
{
    F3CMS\kStaff::_chkLogin();

    if ('' != $authority && !canDo($authority)) {
        F3CMS\Reaction::_return(8009);
    }
}

/**
 * Architecture Purpose
 * - Produce HTML-safe JSON text for presentation contexts.
 *
 * Design Intent
 * - Keep the common `jsonEncode + htmlentities` combination in one helper.
 *
 * Strict Constraints
 * - This helper is output-encoding only.
 *
 * Porting Guidelines
 * - Port as a presentation-safe JSON encoder using the target platform's escaping utilities.
 */
function hJsonEncode($obj)
{
    return htmlentities(jsonEncode($obj));
}

/**
 * Architecture Purpose
 * - Expose the current request's CSRF token from the configured session backend.
 *
 * Design Intent
 * - Hide backend differences between database, Redis, and default framework CSRF storage behind one helper.
 *
 * Strict Constraints
 * - This helper returns token state only; broader CSRF lifecycle policy stays in the session/auth layers.
 *
 * Porting Guidelines
 * - Port as a compatibility accessor that delegates to the active session/CSRF provider.
 */
function getCSRF()
{
    if ('database' == f3()->get('sessionBase')) {
        return f3()->get('sess')->csrf();
    } elseif ('redis' == f3()->get('sessionBase')) {
        return f3()->get('sess')->csrf();
    } else {
        return f3()->CSRF;
    }
}

/**
 * Architecture Purpose
 * - Detect whether the current request should be treated as an AJAX/JSON request.
 *
 * Design Intent
 * - Keep legacy transport heuristics centralized for callers that branch on request style.
 *
 * Strict Constraints
 * - This helper checks headers only; response/auth policy remains elsewhere.
 *
 * Porting Guidelines
 * - Port as a request-header heuristic helper.
 */
function isAjax()
{
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 'xmlhttprequest' == strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])) || (!empty($_SERVER['HTTP_ACCEPT']) && false !== strpos(strtolower($_SERVER['HTTP_ACCEPT']), 'json'));
}

/**
 * Architecture Purpose
 * - Encode a value to JSON with the project's default Unicode/slash handling.
 *
 * Design Intent
 * - Keep one shared JSON encoding policy for legacy callers.
 *
 * Strict Constraints
 * - This helper shapes encoding flags only; schema/business semantics stay outside.
 *
 * Porting Guidelines
 * - Port as a shared JSON encoder preserving Unicode and slash behavior.
 */
function jsonEncode($obj, $pretty = false)
{
    if ($pretty) {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
    } else {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    }

    return json_encode($obj, $flags);
}

/**
 * Architecture Purpose
 * - Decode JSON into an array with legacy error-string fallback behavior.
 *
 * Design Intent
 * - Preserve the project's historical decode-and-report contract for callers that expect string error messages instead of exceptions.
 *
 * Strict Constraints
 * - This helper performs decoding only; callers still own response/error handling policy.
 *
 * Porting Guidelines
 * - Port carefully, preserving the current return contract if dependent callers still expect non-exception error strings.
 */
function jsonDecode($str)
{
    $rtn = json_decode($str, true);

    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            break;
        case JSON_ERROR_DEPTH:
            $rtn = 'Maximum stack depth exceeded';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            $rtn = 'Underflow or the modes mismatch';
            break;
        case JSON_ERROR_CTRL_CHAR:
            $rtn = 'Unexpected control character found';
            break;
        case JSON_ERROR_SYNTAX:
            $rtn = 'Syntax error, malformed JSON';
            break;
        case JSON_ERROR_UTF8:
            $rtn = 'Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
        default:
            $rtn = 'Unknown error';
            break;
    }

    return $rtn;
}

/**
 * Architecture Purpose
 * - Generate a cryptographically secure random string from an allowed character set.
 *
 * Design Intent
 * - Keep shared secure token/string generation in one low-level helper.
 *
 * Strict Constraints
 * - This helper provides randomness only; token ownership and lifecycle stay elsewhere.
 *
 * Porting Guidelines
 * - Port using the target platform's cryptographically secure RNG.
 */
function secure_random_string($length, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
{
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; ++$i) {
        $randomIndex = random_int(0, $charactersLength - 1);
        $randomString .= $characters[$randomIndex];
    }

    return $randomString;
}

/**
 * Architecture Purpose
 * - Generate the project's legacy UUID-like identifier format from a secure random source.
 *
 * Design Intent
 * - Keep this compatibility identifier shape in one helper for callers that still depend on it.
 *
 * Strict Constraints
 * - This helper generates identifiers only; it does not assign business meaning.
 *
 * Porting Guidelines
 * - Port as a compatibility ID generator, preserving the current dashed formatting if external contracts depend on it.
 */
function uuid()
{
    $chars = secure_random_string(40, '0123456789abcdefghijklmnopqrstuvwxyz');

    return preg_replace('/(.{8})(.{4})(.{4})(.{4})(.{12})(.+)/', '$1-$2-$3-$4-$5', $chars);
}
