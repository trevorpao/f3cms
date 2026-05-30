<?php

namespace F3CMS;

/**
 * Architecture Purpose
 * - Module is the shared entry-layer utility base in the FORKS libs layer. It provides
 *   low-level request normalization, escaping, language/device lookup, slug shaping,
 *   and class-path shifting without becoming the owner of entity truth or business
 *   coordination.
 *
 * Design Intent
 * - This class exists to hold thin cross-module helpers that are genuinely runtime or
 *   transport oriented. It should not absorb Feed persistence ownership, Reaction
 *   orchestration, or module-owned validation/business judgment just because many
 *   modules call through it.
 *
 * Strict Constraints
 * - Module is not a generic dumping ground. Do not move entity-specific payload rules,
 *   writeback decisions, or owner-side duty judgment into this class.
 * - If convenience conflicts with owner boundary, keep the boundary and leave the
 *   module-specific logic with the owning module, Kit, Feed, or Outfit layer.
 *
 * Porting Guidelines
 * - In .NET or Go, map this class to a narrow shared utility/runtime surface only.
 *   Preserve request/input normalization and environment lookups here, but keep owner
 *   modules responsible for business coordination and persistence truth.
 */

class Module
{
    /**
     * Architecture Role
     * - Recursively normalizes request or payload values through Module's shared escaping
     *   and sanitization rules before lower layers consume them.
     *
     * Strict Constraints
     * - This helper provides transport/input sanitization only; it must not become the
     *   place where entity-specific payload policy is decided.
     *
     * Porting Note
     * - Preserve this as the shared recursive input escaping helper for transport-facing
     *   values.
     */
    protected static function _escape($array, $quote = true)
    {
        if (is_array($array)) {
            foreach ($array as $k => $v) {
                if (is_string($v)) {
                    if ($quote) {
                        $array[$k] = mh()->quote(self::protectedXss2($v));
                    } else {
                        $array[$k] = ('content' != $k) ? self::_mres(self::protectedXss2($v)) : self::_mres(PFHelper::getInstance()->purify(html_entity_decode($v)), false);
                    }
                } elseif (is_array($v)) {
                    $array[$k] = self::_escape($v, $quote);
                }
            }
        } else {
            if ($quote) {
                $array = mh()->quote(self::protectedXss2($array));
            } else {
                $array = self::_mres(self::protectedXss2($array));
            }
        }

        return $array;
    }

    /**
     * Architecture Role
     * - Applies the low-level string escaping variant used by Module when transport data
     *   needs DB-oriented escaping semantics.
     *
     * Strict Constraints
     * - This remains a thin escaping primitive; it should not expand into a substitute
     *   for owner-side persistence logic or query ownership.
     *
     * Porting Note
     * - Keep this only as a compatibility escape helper if target runtimes still need the
     *   same legacy string handling semantics.
     */
    static protected function _mres($param, $quote = true, $only_for_mssql = false)
    {
        // if(is_array($param))
        //     return array_map(__METHOD__, $param);

        if ($only_for_mssql && !empty($param) && is_string($param)) {
            if ($quote) {
                return str_replace(['\\', "\0", "'", '"', "\x1a"], ['\\\\', '\\0', "\\'", '\\"', '\\Z'], $param);
            } else {
                return str_replace(['\\', "\0", "\x1a"], ['\\\\', '\\0', '\\Z'], $param);
            }
        }

        return $param;
    }

    /**
     * Architecture Role
     * - Provides the stricter HTML-encoding variant of Module's shared XSS protection.
     *
     * Strict Constraints
     * - This is a generic output/input sanitation helper only; caller-specific rendering
     *   or content policy must stay outside Module.
     *
     * Porting Note
     * - Preserve this as a simple shared encoding helper when equivalent escaping is
     *   still needed in target runtimes.
     */
    final static function protectedXss($str)
    {
        return htmlentities($str, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Architecture Role
     * - Provides Module's transport-oriented XSS sanitation path for incoming raw request
     *   strings.
     *
     * Strict Constraints
     * - Keep this as a shared sanitation primitive only; do not push feature-specific
     *   content filtering policy into Module.
     *
     * Porting Note
     * - Preserve this as the default raw-request sanitation helper paired with `_escape()`.
     */
    final static function protectedXss2($str)
    {
        $str = rawurldecode($str);

        return filter_var($str, FILTER_SANITIZE_SPECIAL_CHARS);
    }

    /**
     * Architecture Role
     * - Resolves the shifted FORKS class name for a target layer/module prefix.
     *
     * Strict Constraints
     * - Class-path shifting belongs to shared runtime/autoload support; it must not grow
     *   into an owner-side coordination registry.
     *
     * Porting Note
     * - Preserve this as a narrow naming/path translation helper if future ports keep the
     *   same layer-shift convention.
     */
    public static function _shift($name, $target)
    {
        $name = str_replace(['F3CMS\\', '\\'], ['', ''], $name);

        [$type, $className] = preg_split('/(?<=[fork])(?=[A-Z])/', $name);

        return '\\F3CMS\\' . \F3CMS_Autoloader::getPrefix()[$target] . $className;
    }

    /**
     * Architecture Role
     * - Normalizes inbound request payloads from CORS/body/post/get/request sources into
     *   one escaped transport payload.
     *
     * Strict Constraints
     * - Request normalization is shared entry-layer logic only; owner modules still own
     *   request interpretation, validation, and business branching.
     *
     * Porting Note
     * - Keep this as the canonical request payload collector for legacy entry flows.
     */
    public static function _getReq()
    {
        $rtn = [];

        if (1 == f3()->get('isCORS')) {
            $str = f3()->get('BODY');
            if (empty($str)) {
                $str = file_get_contents('php://input');
            }

            $rtn = json_decode($str, true);
            if (!(JSON_ERROR_NONE == json_last_error())) {
                parse_str($str, $rtn);
            }
        }

        if (empty($rtn)) {
            $rtn = f3()->get('POST');
        }

        if (empty($rtn)) {
            $rtn = f3()->get('GET');
        }

        if (empty($rtn)) {
            $rtn = f3()->get('REQUEST');
        }

        return self::_escape($rtn, false);
    }

    /**
     * Architecture Role
     * - Resolves the active language from runtime environment, request headers, cookies,
     *   and explicit overrides.
     *
     * Strict Constraints
     * - Language resolution is a shared environment/context concern; it should not absorb
     *   module-specific locale policy beyond selecting among accepted languages.
     *
     * Porting Note
     * - Preserve this as the shared runtime language resolver for entry-layer code.
     */
    public static function _lang($args = [])
    {
        $acceptLang = f3()->get('acceptLang');
        if (1 == count($acceptLang)) {
            return f3()->get('defaultLang');
        } elseif (isset($_SERVER['HTTP_X_BACKEND_LANG'])) {
            $lang = $_SERVER['HTTP_X_BACKEND_LANG'];
            if (!in_array($lang, $acceptLang)) {
                $lang = f3()->get('defaultLang');
            }

            return $lang;
        } else {
            if (!f3()->exists('lang') || !empty($args)) {
                $lang = f3()->get('defaultLang');

                if (f3()->exists('COOKIE.user_lang')) {
                    $lang = f3()->get('COOKIE.user_lang');
                }

                if (isset($_SERVER['HTTP_CUSTOMER_LANG'])) {
                    $lang = $_SERVER['HTTP_CUSTOMER_LANG'];
                }

                if (!empty($args) && !empty($args['lang'])) {
                    $lang = $args['lang'];
                }

                if (f3()->exists('REQUEST.lang') && !empty(f3()->get('REQUEST.lang'))) {
                    $lang = f3()->get('REQUEST.lang');
                }

                if (!in_array($lang, $acceptLang)) {
                    $lang = f3()->get('defaultLang');
                }

                f3()->set('lang', $lang);
            }
        }

        return f3()->get('lang');
    }

    /**
     * Architecture Role
     * - Detects and caches a coarse mobile-device classification from the current user
     *   agent string.
     *
     * Strict Constraints
     * - Device detection is shared request-context logic only; it must not become a
     *   proxy for module-owned presentation or business policy.
     *
     * Porting Note
     * - Preserve this as a thin request-context device helper if equivalent behavior is
     *   still required in target runtimes.
     */
    public static function _mobile_user_agent()
    {
        if (!f3()->exists('device')) {
            $device = 'unknown';

            if (stristr($_SERVER['HTTP_USER_AGENT'], 'ipad')) {
                $device = 'ipad';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'iphone') || strstr($_SERVER['HTTP_USER_AGENT'], 'iphone')) {
                $device = 'iphone';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'blackberry')) {
                $device = 'blackberry';
            } elseif (stristr($_SERVER['HTTP_USER_AGENT'], 'android')) {
                $device = 'android';
            }

            f3()->set('device', $device);
        } else {
            $device = f3()->get('device');
        }

        return $device;
    }

    /**
     * Architecture Role
     * - Converts free-form text into Module's canonical slug shape for shared URL/path
     *   usage.
     *
     * Strict Constraints
     * - Slug shaping here is a generic formatting helper; owner modules still decide when
     *   a slug is valid or unique for entity persistence.
     *
     * Porting Note
     * - Preserve this as the shared slug-formatting helper for paths and identifiers.
     */
    public static function _slugify($text)
    {
        $text = str_replace('//', '/', $text);
        $text = str_replace(' ', '-', $text);

        // Replace non-letter or digit characters with '-'.
        $text = preg_replace('~[^\\pL\d%/-]+~u', '-', $text);
        $text = preg_replace('~[^\\pL\\d%/-]+~u', '-', $text);

        // Trim and convert to lowercase.
        $text = trim($text, '-');
        $text = strtolower($text);

        // URL encode the slug.
        $text = urlencode($text);

        return $text;
    }

    /**
     * Architecture Role
     * - Resolves whether a FORKS class/module path exists on disk using the project's
     *   autoload naming convention.
     *
     * Strict Constraints
     * - Existence checks here stay at the runtime/autoload layer; they must not become a
     *   generalized module discovery or ownership-routing system.
     *
     * Porting Note
     * - Preserve this as a compatibility helper only if future ports still depend on the
     *   same class-to-file naming convention.
     */
    public static function _exists($pClassName)
    {
        $className = ltrim($pClassName, '\\');
        $fileName  = '';
        $namespace = '';

        if ($lastNsPos = strrpos($className, '\\')) {
            $namespace = substr($className, 0, $lastNsPos);
            $className = substr($className, $lastNsPos + 1);
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
        }

        [$type, $moduleName] = preg_split('/(?<=[fork])(?=[A-Z])/', $className);

        if (null !== $moduleName) {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $moduleName) . DIRECTORY_SEPARATOR . \F3CMS_Autoloader::getType()[$type] . '.php';

            $fileName = str_replace('libs', 'modules', __DIR__) . str_replace('F3CMS', '', $fileName);

            if (false === file_exists($fileName)) {
                $fileName = str_replace('/f3cms', '', $fileName);
            }
        } else {
            $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $type) . '.php';
            $fileName = __DIR__ . str_replace('F3CMS', '', $fileName);
        }

        if ((false === file_exists($fileName)) || (false === is_readable($fileName))) {
            return false;
        }

        return true;
    }
}
